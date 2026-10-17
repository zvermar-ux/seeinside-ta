<?php

namespace App\Console\Commands;

use App\Models\Agency;
use App\Models\ImportRun;
use App\Services\Aries\AriesClient;
use App\Services\Aries\AriesImporter;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Throwable;

class AriesImport extends Command
{
    protected $signature = 'aries:import
        {--agency= : ID of the agency that receives the data (created if omitted)}
        {--agency-name=Aries Travel : Name for a newly created agency}
        {--username= : Aries username (or ARIES_USERNAME in .env)}
        {--only= : Comma list: operators,groups,objects,charters,subagents,users,slider}
        {--skip-images : Do not download images}
        {--skip-details : Do not load descriptions, maps and galleries (fast)}';

    protected $description = 'Migrate arrangements, hotels, groups, destinations, charters and subagents from the Aries administration';

    public function handle(): int
    {
        $this->callSilently('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);

        $agency = $this->resolveAgency();
        if (! $agency) {
            return self::FAILURE;
        }

        $username = $this->option('username') ?: config('aries.username') ?: $this->ask('Aries username');
        $password = config('aries.password') ?: $this->secret('Aries password (not shown)');

        $client = new AriesClient(config('aries.url'), config('aries.timeout'));

        $this->info('Logging in to '.config('aries.url').' …');
        try {
            $client->login($username, (string) $password);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $run = ImportRun::create([
            'agency_id' => $agency->id,
            'source' => AriesImporter::SOURCE,
            'status' => 'running',
            'started_at' => now(),
        ]);

        $only = array_filter(array_map('trim', explode(',', (string) $this->option('only'))));

        $importer = (new AriesImporter($client, $agency, ! $this->option('skip-images'), ! $this->option('skip-details')))
            ->onLog(fn (string $m) => $this->line($m))
            ->onProgress(function (string $label, int $total) {
                $bar = $this->output->createProgressBar(max($total, 1));
                $bar->setFormat("  {$label}: %current%/%max% [%bar%] %percent:3s%%");
                $bar->start();
                $count = 0;

                return function () use ($bar, $total, &$count) {
                    $bar->advance();
                    if (++$count >= $total) {
                        $bar->finish();
                        $this->newLine();
                    }
                };
            });

        try {
            $stats = $importer->run($only);
            $run->update(['status' => empty($stats['errors']) ? 'finished' : 'failed', 'stats' => $stats, 'finished_at' => now()]);
        } catch (Throwable $e) {
            $run->update(['status' => 'failed', 'stats' => $importer->stats, 'message' => $e->getMessage(), 'finished_at' => now()]);
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(['', ''], collect($stats)->map(fn ($v, $k) => [$k, $v])->values()->all());
        $this->info("Done. Agency: {$agency->name} (#{$agency->id})");

        return empty($stats['errors']) ? self::SUCCESS : self::FAILURE;
    }

    protected function resolveAgency(): ?Agency
    {
        if ($id = $this->option('agency')) {
            $agency = Agency::find($id);
            if (! $agency) {
                $this->error("Agency #{$id} not found.");
            }

            return $agency;
        }

        $agency = Agency::firstOrCreate(
            ['name' => $this->option('agency-name')],
            ['email' => null, 'is_active' => true],
        );

        if ($agency->wasRecentlyCreated) {
            $this->info("Created agency \"{$agency->name}\" (#{$agency->id}).");
        }

        return $agency;
    }
}
