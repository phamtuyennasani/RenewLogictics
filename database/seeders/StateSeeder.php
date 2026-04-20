<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StateSeeder extends Seeder
{
    public function run(): void
    {
        $jsonPath = base_path('json/states.json');

        if (!file_exists($jsonPath)) {
            $this->command->error("File not found: {$jsonPath}");
            return;
        }

        $states = json_decode(file_get_contents($jsonPath), true);

        if (!is_array($states)) {
            $this->command->error('Invalid JSON format');
            return;
        }

        $data = collect($states)->map(fn($s) => [
            'id'           => $s['id'],
            'name'         => $s['name'],
            'country_id'   => $s['country_id'],
            'country_code' => $s['country_code'] ?? null,
            'iso2'         => $s['iso2'] ?? null,
            'created_at'   => now(),
            'updated_at'   => now(),
        ])->toArray();

        DB::table('states')->insertOrIgnore($data);

        $this->command->info('Đã import ' . count($data) . ' states thành công.');
    }
}
