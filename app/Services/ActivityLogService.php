<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\CompanyReliabilityReport;
use App\Models\CylinderImport;
use App\Models\CylinderInspection;
use App\Models\CylinderProtocol;
use App\Models\ExpenseInvoice;
use App\Models\FinancialPlan;
use App\Models\FinancialPlanChange;
use App\Models\FinancialPlanLine;
use App\Models\HrLeave;
use App\Models\HrLeaveApprovalTask;
use App\Models\HrVehicle;
use App\Models\HrVehicleColumnResponsibility;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ActivityLogService
{
    public const HIDDEN_FIELDS = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'external_url',
        'avatar_data', 'signature_data', 'public_gantt_token', 'token', 'api_token', 'created_at', 'updated_at',
    ];

    public const VOLATILE_USER_FIELDS = ['last_seen_at', 'dashboard_tasks_seen_id', 'security_activity_at', 'last_login_at', 'failed_login_count', 'failed_login_attempts', 'failed_login_at', 'login_locked_until'];

    public function recordModel(Model $model, string $action): void
    {
        if ($model instanceof ActivityLog || $model instanceof ExpenseInvoice || $model instanceof HrLeave || $model instanceof HrLeaveApprovalTask || ! app()->bound('request') || ! request()->route()) {
            return;
        }

        $changes = $this->modelChanges($model, $action);
        if ($action === 'updated' && $changes === []) {
            return;
        }

        ActivityLog::create($this->requestMetadata() + [
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'subject_label' => $this->subjectLabel($model),
            'changes' => $changes ?: null,
        ]);
    }

    public function recordAuthentication(User $user, string $action): void
    {
        ActivityLog::create($this->requestMetadata() + [
            'user_id' => $user->id,
            'action' => $action,
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'subject_label' => $user->name.' ('.$user->email.')',
            'changes' => null,
        ]);
    }

    public function recordUserPermissions(User $user, array $before, array $after): void
    {
        sort($before);
        sort($after);
        if ($before === $after || ! app()->bound('request') || ! request()->route()) {
            return;
        }
        ActivityLog::create($this->requestMetadata() + [
            'user_id' => auth()->id(),
            'action' => 'permissions_updated',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'subject_label' => $user->name.' ('.$user->email.')',
            'changes' => ['permissions' => ['old' => $before, 'new' => $after]],
        ]);
    }

    private function modelChanges(Model $model, string $action): array
    {
        if ($model instanceof FinancialPlan || $model instanceof FinancialPlanLine || $model instanceof FinancialPlanChange) {
            // Detailed financial history is encrypted and available only inside the assigned plan.
            return [];
        }
        if ($model instanceof CompanyReliabilityReport) {
            // The general change log must not disclose the confidential report or its rating.
            return [];
        }
        if ($action === 'deleted' || $action === 'restored') {
            return [];
        }

        $newValues = $action === 'created' ? $model->getAttributes() : $model->getChanges();
        $excluded = [...self::HIDDEN_FIELDS, 'signature_data', 'issuer_snapshot'];
        if ($model instanceof HrVehicleColumnResponsibility) {
            $excluded = [...$excluded, 'revision', 'updated_by'];
        }
        if ($model instanceof CylinderProtocol) {
            $excluded = [...$excluded, 'snapshot'];
        }
        if ($model instanceof CylinderImport) {
            $excluded = [...$excluded, 'rows'];
        }
        if ($model instanceof User) {
            $excluded = [...$excluded, ...self::VOLATILE_USER_FIELDS];
        }
        $newValues = collect($newValues)->reject(fn (mixed $value, string $field): bool => in_array($field, $excluded, true)
            || Str::contains(Str::lower($field), ['password', 'token', 'secret', 'avatar_data']))->all();
        $result = [];
        foreach ($newValues as $field => $newValue) {
            $oldValue = $action === 'created' ? null : $model->getOriginal($field);
            if (str_ends_with($field, '_date')) {
                $oldValue = $oldValue instanceof DateTimeInterface ? $oldValue->format('Y-m-d') : (is_string($oldValue) ? substr($oldValue, 0, 10) : $oldValue);
                $newValue = $newValue instanceof DateTimeInterface ? $newValue->format('Y-m-d') : (is_string($newValue) ? substr($newValue, 0, 10) : $newValue);
            }
            $result[$field] = [
                'old' => $model instanceof CylinderInspection && $field === 'observations' ? $oldValue : $this->safeValue($oldValue),
                'new' => $model instanceof CylinderInspection && $field === 'observations' ? $newValue : $this->safeValue($newValue),
            ];
        }

        return $result;
    }

    private function safeValue(mixed $value): mixed
    {
        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }
        if (is_array($value)) {
            return array_map(fn ($item) => $this->safeValue($item), $value);
        }
        $value = (string) $value;

        return mb_strlen($value) > 500 ? '[długi tekst: '.mb_strlen($value).' znaków]' : $value;
    }

    private function subjectLabel(Model $model): string
    {
        if ($model instanceof HrVehicleColumnResponsibility) {
            return 'Kolumna: '.(HrVehicle::COLUMNS[$model->column_key] ?? $model->column_key);
        }
        if ($model instanceof HrVehicle) {
            return Str::limit($model->name, 200).' · '.$model->registration_number;
        }
        foreach (['name', 'title', 'number', 'original_filename', 'email'] as $field) {
            if (filled($model->getAttribute($field))) {
                return (string) $model->getAttribute($field);
            }
        }

        return class_basename($model).' #'.$model->getKey();
    }

    private function requestMetadata(): array
    {
        $request = request();

        return [
            'route_name' => $request->route()?->getName(),
            'url' => $request->root().'/'.($request->route()?->uri() ?? ''),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];
    }
}
