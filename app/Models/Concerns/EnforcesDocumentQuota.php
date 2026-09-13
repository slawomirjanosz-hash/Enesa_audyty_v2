<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Services\DocumentQuotaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

trait EnforcesDocumentQuota
{
    public function save(array $options = [])
    {
        $new = ! $this->exists;
        if (! $this->getAttribute('storage_owner_id')) {
            $this->setAttribute('storage_owner_id', $this->uploaded_by ?? auth()->id());
        }
        try {
            return DB::transaction(function () use ($options) {
                if ($this->getAttribute('storage_owner_id')) {
                    $owner = User::withTrashed()->whereKey($this->getAttribute('storage_owner_id'))->lockForUpdate()->firstOrFail();
                    $used = app(DocumentQuotaService::class)->used($owner->id);
                    $previous = $this->exists && $this->getOriginal('storage_owner_id') == $owner->id ? (int) $this->getOriginal('size') : 0;
                    if ((int) $this->size > $previous && $used - $previous + (int) $this->size > $owner->document_limit_bytes) {
                        throw ValidationException::withMessages(['file' => 'Przekroczono limit miejsca na dokumenty. Administrator może zwiększyć limit.']);
                    }
                }

                return parent::save($options);
            });
        } catch (ValidationException $exception) {
            // Clean up only a rejected new upload, never a file referenced by another record.
            if (($new || $this->isDirty('stored_path')) && $this->stored_path && ! DB::table('documents')->where('stored_path', $this->stored_path)->exists()
                && ! DB::table('iso_section_documents')->where('stored_path', $this->stored_path)->exists()) {
                Storage::disk('local')->delete($this->stored_path);
            }
            throw $exception;
        }
    }
}
