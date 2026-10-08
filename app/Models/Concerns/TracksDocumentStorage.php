<?php

namespace App\Models\Concerns;

trait TracksDocumentStorage
{
    public function save(array $options = [])
    {
        // Keep attribution for usage reporting, without imposing a per-user quota.
        if (! $this->getAttribute('storage_owner_id')) {
            $this->setAttribute('storage_owner_id', $this->uploaded_by ?? auth()->id());
        }

        return parent::save($options);
    }
}
