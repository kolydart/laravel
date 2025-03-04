<?php

namespace Kolydart\Laravel\App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\App;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Prevents database seeding in production environment.
 *
 * This service provider adds a safety check to prevent accidental database seeding
 * in production environments. When enabled, it will terminate the execution of
 * the `db:seed` command IF RUN IN PRODUCTION.
 *
 * Usage:
 * The provider is automatically registered through Laravel's package discovery.
 * Simply install the package:
 *     composer require kolydart/laravel
 */
class DbSeedProtectionServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if (App::environment('production')) {
            Event::listen(CommandStarting::class, function (CommandStarting $event) {
                $this->handleDbSeedCommand($event->command);
            });
        }
    }

    /**
     * Handle the db:seed command.
     *
     * @param string $command
     * @throws RuntimeException
     */
    public function handleDbSeedCommand(string $command): void
    {
        if ($command === 'db:seed' && App::environment('production')) {
            $message = "Database seeding IS NOT ALLOWED in production environment.";
            fwrite(STDOUT, $message);
            throw new RuntimeException($message);
        }
    }

    public function register()
    {
        //
    }
}
