<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'app:demo',
    description: 'Reset and seed the database with demo data.',
)]
final class AppDemoCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'app:demo {--force : Allow wiping the database in production}';

    public function handle(): int
    {
        // Wipes the whole database. In production it needs an interactive "yes" or --force,
        // so it can never run by accident as a deploy command (that would erase real data).
        if (! $this->confirmToProceed('This will ERASE ALL DATA and reseed demo content.')) {
            return self::FAILURE;
        }

        $this->info('Dropping all tables and re-migrating...');
        Artisan::call('migrate:fresh', ['--force' => true], $this->output);

        $this->info('Seeding database with demo data...');
        Artisan::call('db:seed', [
            '--class' => DatabaseSeeder::class,
            '--force' => true,
        ], $this->output);

        $this->info('');
        $this->info('Demo data seeded successfully.');
        $this->info('Admin login: admin@example.com / password');

        return self::SUCCESS;
    }
}
