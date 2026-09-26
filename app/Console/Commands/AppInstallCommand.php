<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\PermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'app:install',
    description: 'Run pending migrations and sync permissions. Safe to run after every deploy.',
)]
final class AppInstallCommand extends Command
{
    public function handle(): int
    {
        $this->info('Running migrations...');
        $migrate = Artisan::call('migrate', ['--force' => true], $this->output);

        if ($migrate !== self::SUCCESS) {
            $this->error('Migrations failed.');

            return self::FAILURE;
        }

        $this->info('Syncing permissions...');
        Artisan::call('db:seed', [
            '--class' => PermissionSeeder::class,
            '--force' => true,
        ], $this->output);

        // Link public/storage for the local disk (Laravel's signed route 403s /storage/* otherwise); idempotent, harmless on S3.
        if (! file_exists(public_path('storage'))) {
            Artisan::call('storage:link', [], $this->output);
        }

        $this->info('Install finished.');

        return self::SUCCESS;
    }
}
