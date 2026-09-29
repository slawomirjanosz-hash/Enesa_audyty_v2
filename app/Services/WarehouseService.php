<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use App\Models\WarehouseDocument;
use App\Models\WarehouseItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WarehouseService
{
    /** Quantities use thousandths and PLN prices use grosz; no floating stock accumulation. */
    public static function scaled(string|int|float $value, int $places): int
    {
        return (int) round((float) $value * (10 ** $places));
    }

    public function post(array $data, User $user, ?Project $project, ?Company $supplier): WarehouseDocument
    {
        return DB::transaction(function () use ($data, $user, $project, $supplier) {
            // Serializes double submissions for this operator; item locks serialize all operators.
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
            $existing = WarehouseDocument::where('submission_token', $data['submission_token'])->first();
            if ($existing) {
                abort_unless($existing->created_by === $user->id, 403);
                if ($existing->payload_hash !== $hash) {
                    throw ValidationException::withMessages(['submission_token' => 'Ten formularz został już zapisany. Otwórz nowy dokument.']);
                }

                return $existing;
            }
            $items = WarehouseItem::whereIn('id', array_column($data['lines'], 'item_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $document = WarehouseDocument::create([
                'type' => $data['type'], 'document_date' => $data['document_date'],
                'submission_token' => $data['submission_token'], 'payload_hash' => $hash,
                'created_by' => $user->id, 'author_name' => $user->name,
                'project_id' => $project?->id, 'project_label' => $project ? $project->number.' — '.$project->name : null,
                'supplier_id' => $supplier?->id, 'supplier_name' => $supplier?->name,
                'reference' => $data['reference'] ?? null, 'notes' => $data['notes'],
            ]);
            $prefix = ['receipt' => 'PZ', 'issue' => 'WZ', 'adjustment' => 'KOR'][$data['type']];
            $document->update(['number' => $prefix.'/'.$document->document_date->format('Y').'/'.str_pad((string) $document->id, 6, '0', STR_PAD_LEFT)]);
            foreach ($data['lines'] as $index => $line) {
                $item = $items->get($line['item_id']);
                if (! $item || ! $item->is_active) {
                    throw ValidationException::withMessages(["lines.$index.item_id" => 'Pozycja nie istnieje lub jest zarchiwizowana.']);
                }
                $before = self::scaled($item->quantity, 3);
                $amount = self::scaled($line['quantity'], 3);
                $cost = self::scaled($item->unit_cost, 2);
                $change = match ($data['type']) {
                    'receipt' => $amount,
                    'issue' => -$amount,
                    'adjustment' => $amount - $before,
                };
                if ($data['type'] === 'adjustment' && (int) $line['revision'] !== $item->revision) {
                    throw ValidationException::withMessages(["lines.$index.quantity" => 'Stan zmienił się od otwarcia formularza. Odśwież go i ponownie sprawdź ilość.']);
                }
                $after = $before + $change;
                if ($after < 0 || $after > 1000000000 || $change === 0) {
                    throw ValidationException::withMessages(["lines.$index.quantity" => 'Niewystarczający stan, przekroczony limit 1 000 000 jednostek lub brak zmiany. Dostępne: '.$item->quantity.' '.$item->unit]);
                }
                if ($data['type'] === 'receipt') {
                    $cost = self::scaled($line['unit_cost'], 2);
                    $item->unit_cost = round(($before * self::scaled($item->unit_cost, 2) + $amount * $cost) / $after) / 100;
                }
                $document->lines()->create([
                    'warehouse_item_id' => $item->id, 'sku' => $item->sku, 'name' => $item->name, 'unit' => $item->unit,
                    'quantity_before' => $before / 1000, 'quantity_change' => $change / 1000, 'quantity_after' => $after / 1000, 'unit_cost' => $cost / 100,
                ]);
                $item->quantity = $after / 1000;
                $item->revision++;
                $item->save();
            }

            return $document;
        }, 3);
    }
}
