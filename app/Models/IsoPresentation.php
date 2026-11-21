<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IsoPresentation extends Model
{
    protected $fillable = ['section_id', 'title', 'description', 'original_filename', 'source_size', 'slide_count', 'created_by'];

    public function displayDescription(): string
    {
        return trim(str_replace([
            ' Materiał na podstawie książki Bronisława Pytla „Energia pod kontrolą”.',
            ' Na podstawie książki Bronisława Pytla „Energia pod kontrolą”, tom 1 i 2.',
            ' na podstawie książki Bronisława Pytla „Energia pod kontrolą”, tom 1 i 2.',
        ], ['', '', '.'], $this->description ?? ''));
    }
}
