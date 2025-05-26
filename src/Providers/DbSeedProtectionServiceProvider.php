<?php

namespace Kolydart\Laravel\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\App;
use Illuminate\Console\Command;
use RuntimeException;
use Illuminate\Support\Facades\Request;

/**
 * Prevents database seeding in production environment.
 *
 * This service provider adds a safety check to prevent accidental database seeding
 * in production environments. When enabled, it will terminate the execution of
 * the `db:seed` command IF RUN IN PRODUCTION.
 *
 * Exceptions:
 * - Allows db:seed when called from the 'seed:static' command
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
        // Allow db:seed when called from seed:static command
        if ($command === 'db:seed' && App::environment('production')) {
            // Get current command from argument input
            $callingCommand = $this->getCallingCommand();

            // Allow db:seed when called from seed:static
            if ($callingCommand === 'seed:static') {
                return;
            }

            $message = "Database seeding IS NOT ALLOWED in production environment.";
            fwrite(STDOUT, $message);
            throw new RuntimeException($message);
        }
    }

    /**
     * Get the command that called db:seed
     *
     * @return string|null
     */
    protected function getCallingCommand(): ?string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        // Try to find the original calling command
        foreach ($trace as $frame) {
            if (isset($frame['object']) && $frame['object'] instanceof Command) {
                $command = $frame['object'];
                // Get the actual command name
                return $command->getName();
            }
        }

        // Check if this is being called from Artisan
        if (isset($_SERVER['argv']) && count($_SERVER['argv']) > 1) {
            return $_SERVER['argv'][1];
        }

        return null;
    }

    public function register()
    {
        //
    }
}