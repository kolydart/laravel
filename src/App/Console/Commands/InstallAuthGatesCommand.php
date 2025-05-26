<?php

namespace Kolydart\Laravel\App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class InstallAuthGatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kolydart:install-auth-gates {--force : Overwrite existing files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install the AuthGates middleware';

    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Create a new command instance.
     *
     * @param  \Illuminate\Filesystem\Filesystem  $files
     * @return void
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Install the middleware
        $this->installAuthGatesMiddleware();

        $this->info('AuthGates middleware installed successfully.');
        $this->comment('PLEASE ADD THE FOLLOWING MIDDLEWARE to your app/Http/Kernel.php file:');
        $this->comment('\App\Http\Middleware\AuthGates::class,');
        $this->comment('... to both "web" and "api" groups in $middlewareGroups');
        //    Example for web group:
        //    protected $middlewareGroups = [
        //        'web' => [
        //            // ... other middleware ...
        //            \App\Http\Middleware\AuthGates::class,
        //        ],
        //        // ...
        //    ];

        $this->comment("Auto creating \App\Http\Middleware\AuthGates::class,");

        return 0;
    }

    /**
     * Install the AuthGates middleware.
     *
     * @return void
     */
    protected function installAuthGatesMiddleware()
    {
        $middlewarePath = app_path('Http/Middleware/AuthGates.php');

        if ($this->files->exists($middlewarePath) && !$this->option('force')) {
            $this->error('AuthGates middleware already exists!');
            $this->comment('Use --force to overwrite.');
            return;
        }

        $this->ensureDirectoryExists(dirname($middlewarePath));

        $stub = <<<'EOT'
<?php

namespace App\Http\Middleware;

use Kolydart\Laravel\App\Http\Middleware\AccessControl;

class AuthGates extends AccessControl
{
    // This class extends the AuthGates from the Kolydart\Laravel package
    // Any project-specific customizations can be added here
}
EOT;

        $this->files->put($middlewarePath, $stub);
        $this->info('AuthGates middleware created: ' . $middlewarePath);
    }

    /**
     * Ensure the directory exists, create it if necessary.
     *
     * @param  string  $directory
     * @return void
     */
    protected function ensureDirectoryExists($directory)
    {
        if (!$this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0755, true, true);
        }
    }
}
