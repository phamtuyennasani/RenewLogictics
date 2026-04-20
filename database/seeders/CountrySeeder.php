<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $jsonPath = base_path('json/countries.json');

        if (!file_exists($jsonPath)) {
            $this->command->error("File not found: {$jsonPath}");
            return;
        }

        $countries = json_decode(file_get_contents($jsonPath), true);

        if (!is_array($countries)) {
            $this->command->error('Invalid JSON format');
            return;
        }

        $data = collect($countries)->map(fn($c) => [
            'id'         => $c['id'],
            'name'       => $c['name'],
            'iso2'       => $c['iso2'] ?? null,
            'iso3'       => $c['iso3'] ?? null,
            'phonecode'  => $c['phonecode'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        DB::table('countries')->insertOrIgnore($data);

        $this->command->info('Đã import ' . count($data) . ' countries thành công.');
    }
}
