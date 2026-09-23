<?php

namespace App\Console\Commands;

use App\Models\Lga;
use App\Models\PollingUnit;
use App\Models\State;
use App\Models\Ward;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use JsonException;

/**
 * Expects a JSON array of states, each nesting lgas → wards → polling_units, where every
 * level has a "code" (INEC delimitation code for that level) and a "name". Re-running is
 * safe: records are matched on code within their parent and names are updated.
 */
#[Signature('locations:import {path : Path to the locations JSON file, optionally gzipped (.gz)}')]
#[Description('Import states, LGAs, wards and polling units from a JSON file')]
class ImportLocations extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        // The full INEC register decodes to ~120MB of arrays, above PHP's default CLI limit.
        ini_set('memory_limit', '512M');

        try {
            $contents = (string) file_get_contents($path);

            if (str_ends_with($path, '.gz')) {
                $contents = (string) gzdecode($contents);
            }

            /** @var list<array{code: string, name: string, lgas?: list<array{code: string, name: string, wards?: list<array{code: string, name: string, polling_units?: list<array{code: string, name: string}>}>}>}> $states */
            $states = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->error("Invalid JSON: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $pollingUnitCount = 0;

        foreach ($states as $stateData) {
            DB::transaction(function () use ($stateData, &$pollingUnitCount): void {
                $state = State::query()->updateOrCreate(['code' => $stateData['code']], ['name' => $stateData['name']]);

                foreach ($stateData['lgas'] ?? [] as $lgaData) {
                    $lga = Lga::query()->updateOrCreate(
                        ['state_id' => $state->id, 'code' => $lgaData['code']],
                        ['name' => $lgaData['name']],
                    );

                    foreach ($lgaData['wards'] ?? [] as $wardData) {
                        $ward = Ward::query()->updateOrCreate(
                            ['lga_id' => $lga->id, 'code' => $wardData['code']],
                            ['name' => $wardData['name']],
                        );

                        $now = now();
                        $rows = array_map(fn (array $unit): array => [
                            'ward_id' => $ward->id,
                            'code' => $unit['code'],
                            'name' => $unit['name'],
                            'created_at' => $now,
                            'updated_at' => $now,
                        ], $wardData['polling_units'] ?? []);

                        foreach (array_chunk($rows, 500) as $chunk) {
                            PollingUnit::query()->upsert($chunk, ['ward_id', 'code'], ['name', 'updated_at']);
                        }

                        $pollingUnitCount += count($rows);
                    }
                }
            });

            $this->line("Imported {$stateData['name']}");
        }

        $this->info('Imported '.count($states)." states and {$pollingUnitCount} polling units.");

        return self::SUCCESS;
    }
}
