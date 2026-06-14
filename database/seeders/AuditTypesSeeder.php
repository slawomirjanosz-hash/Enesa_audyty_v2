<?php

namespace Database\Seeders;

use App\Models\AuditType;
use Illuminate\Database\Seeder;

class AuditTypesSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Audyt Energetyczny Przedsiębiorstwa', 'slug' => 'aep'],
            ['name' => 'ISO 50001',                           'slug' => 'iso50001'],
            ['name' => 'Białe Certyfikaty',                   'slug' => 'biale-certyfikaty'],
        ];

        foreach ($types as $type) {
            AuditType::firstOrCreate(['slug' => $type['slug']], $type);
        }
    }
}
