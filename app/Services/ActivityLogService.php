<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ActivityLogService
{
    private const HIDDEN_FIELDS = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
        'avatar_data', 'signature_data', 'public_gantt_token', 'token', 'api_token', 'created_at', 'updated_at',
    ];

    private const VOLATILE_USER_FIELDS = ['last_seen_at', 'dashboard_tasks_seen_id'];

    public function recordModel(Model $model, string $action): void
    {
        if ($model instanceof ActivityLog || ! app()->bound('request') || ! request()->route()) {
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

    private function modelChanges(Model $model, string $action): array
    {
        if ($action === 'deleted' || $action === 'restored') {
            return [];
        }

        $newValues = $action === 'created' ? $model->getAttributes() : $model->getChanges();
        $excluded = self::HIDDEN_FIELDS;
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
                'old' => $this->safeValue($oldValue),
                'new' => $this->safeValue($newValue),
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
            'url' => $request->fullUrl(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];
    }
}
