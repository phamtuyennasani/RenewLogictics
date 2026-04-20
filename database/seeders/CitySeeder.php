<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        $jsonPath = base_path('json/cities.json');

        if (!file_exists($jsonPath)) {
            $this->command->error("File not found: {$jsonPath}");
            return;
        }

        // Đếm tổng số dòng trước để hiển thị tiến trình
        $total = (int) shell_exec("find /c/v \"{$jsonPath}\" 2>nul || grep -c \"\" \"{$jsonPath}\"");
        if ($total < 2) {
            $total = 0; // fallback
        } else {
            $total = $total - 2; // trừ 2 dòng [] của json
        }

        $handle = fopen($jsonPath, 'r');
        if (!$handle) {
            $this->command->error("Cannot open file: {$jsonPath}");
            return;
        }

        $buffer = '';
        $depth = 0;
        $inObject = false;
        $objectStart = -1;
        $inserted = 0;
        $chunkSize = 1000;
        $chunk = [];

        while (($char = fgetc($handle)) !== false) {
            $buffer .= $char;

            if ($char === '{') {
                if ($depth === 0) {
                    $inObject = true;
                    $objectStart = strlen($buffer) - 1;
                }
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0 && $inObject) {
                    $inObject = false;
                    $jsonStr = substr($buffer, $objectStart);
                    $c = json_decode($jsonStr, true);

                    if (is_array($c)) {
                        $chunk[] = [
                            'id'           => $c['id'] ?? null,
                            'name'         => $c['name'] ?? null,
                            'state_code'   => $c['state_code'] ?? null,
                            'state_id'     => $c['state_id'] ?? null,
                            'country_id'   => $c['country_id'] ?? null,
                            'country_code' => $c['country_code'] ?? null,
                            'created_at'   => now(),
                            'updated_at'   => now(),
                        ];
                    }

                    $buffer = '';

                    if (count($chunk) >= $chunkSize) {
                        DB::table('cities')->insertOrIgnore($chunk);
                        $inserted += count($chunk);
                        $chunk = [];
                        $this->command->info("Đã import {$inserted}/{$total}...");
                    }
                }
            }
        }

        // Insert phần còn lại
        if (!empty($chunk)) {
            DB::table('cities')->insertOrIgnore($chunk);
            $inserted += count($chunk);
        }

        fclose($handle);

        $this->command->info("Đã import {$inserted} cities thành công.");
    }
}
