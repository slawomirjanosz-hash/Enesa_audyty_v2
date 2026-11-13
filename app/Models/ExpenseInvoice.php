<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseInvoice extends Model
{
    use SoftDeletes;

    public const WORKFLOW = ['new' => 'Nowa', 'read' => 'Odczytana', 'returned' => 'Do uzupełnienia', 'settled' => 'Rozliczona', 'disputed' => 'Rozbieżność', 'closed' => 'Zakończono'];

    public const SETTLEMENT_METHODS = ['refund' => 'Zwrot pracownikowi', 'advance' => 'Potrącenie z zaliczki', 'none' => 'Bez zwrotu'];

    public static function pendingWorkflowCount(User $user, bool $accounting): int
    {
        return static::query()->whereIn('workflow_status', $accounting ? ['new', 'disputed'] : ['settled', 'returned'])
            ->when(! $accounting, fn ($query) => $query->where('owner_id', $user->id))->count();
    }

    public function recordWorkflow(string $status, User $actor, ?string $note = null): void
    {
        $this->forceFill([
            'workflow_status' => $status,
            'workflow_history' => [...($this->workflow_history ?? []), [
                'status' => $status, 'actor' => $actor->name, 'actor_id' => $actor->id,
                'at' => now('Europe/Warsaw')->format('d.m.Y H:i'), 'note' => $note,
                'method' => $status === 'settled' ? $this->settlement_method : null,
                'amount' => $status === 'settled' ? $this->settlement_amount : null,
            ]],
        ]);
    }

    public const METHODS = ['unknown' => 'Nieustalona', 'card' => 'Karta', 'cash' => 'Gotówka', 'transfer' => 'Przelew'];

    public const SOURCES = ['unknown' => 'Nieustalone', 'company' => 'Firmowe', 'personal' => 'Prywatne'];

    public const PAYMENTS = ['unknown' => 'Nieustalone', 'unpaid' => 'Nieopłacona', 'paid' => 'Opłacona', 'partial' => 'Częściowo opłacona', 'settled' => 'Rozliczona — zamknięta'];

    protected $guarded = ['id'];

    protected $casts = ['purchase_date' => 'date', 'net_amount' => 'decimal:2', 'gross_amount' => 'decimal:2', 'is_unassigned' => 'boolean', 'settled_at' => 'datetime', 'revision' => 'integer', 'workflow_history' => 'array', 'settlement_amount' => 'decimal:2'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id')->withTrashed();
    }

    public function statusLabel(): string
    {
        return $this->settled_at ? 'Rozliczona' : ($this->is_unassigned ? 'Do przypisania' : 'Do rozliczenia');
    }
}
