<?php

namespace App\Console\Commands;

use App\Models\Tim;
use App\Services\TimAccountGenerator;
use Illuminate\Console\Command;

class GenerateMissingTimAccounts extends Command
{
    protected $signature = 'tim:generate-accounts';
    protected $description = 'Generate user accounts for all tim that do not have one yet';

    public function handle(TimAccountGenerator $generator): int
    {
        $timsWithoutAccount = Tim::whereDoesntHave('user')->get();

        if ($timsWithoutAccount->isEmpty()) {
            $this->info('All tim already have accounts.');

            return self::SUCCESS;
        }

        $this->info("Found {$timsWithoutAccount->count()} tim without accounts. Generating...");

        $bar = $this->output->createProgressBar($timsWithoutAccount->count());
        $bar->start();

        foreach ($timsWithoutAccount as $tim) {
            $user = $generator->createAccount($tim);
            $this->newLine();
            $this->line("✓ Generated account for: {$tim->nama_tim} → {$user->username}");
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('All accounts generated successfully!');

        return self::SUCCESS;
    }
}
