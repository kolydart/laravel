<?php

namespace Kolydart\Laravel\App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeControllerTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kolydart:make-test 
                            {name : The name of the controller test (e.g., UserController or Admin/UserController)}
                            {--model= : The model class name (e.g., \App\User)}
                            {--table= : The database table name}
                            {--role=Admin : The role required for authentication}
                            {--route-path= : The route path prefix (e.g., admin.users)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new controller test using the kolydart stub';

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
        $name = $this->argument('name');
        $controllerName = Str::studly($name);
        
        // Parse the controller path and name
        $parts = explode('/', $controllerName);
        $controllerBaseName = array_pop($parts);
        $className = $controllerBaseName . 'Test';
        $namespace = 'Tests\\Feature\\app\\Http\\Controllers';
        
        if (!empty($parts)) {
            $namespace .= '\\' . implode('\\', $parts);
        }

        // Determine model and table from controller name if not provided
        $model = $this->option('model');
        $table = $this->option('table');
        $routePath = $this->option('route-path');

        if (!$model) {
            // Try to guess the model from the controller name
            $modelName = str_replace('Controller', '', $controllerBaseName);
            $model = '\\App\\' . Str::singular($modelName);
        }

        if (!$table) {
            // Try to guess the table from the model name
            $modelBaseName = class_basename($model);
            $table = Str::snake(Str::plural($modelBaseName));
        }

        if (!$routePath) {
            // Try to guess the route path
            $prefix = !empty($parts) ? Str::lower(implode('.', $parts)) . '.' : '';
            $resource = Str::kebab(Str::plural(str_replace('Controller', '', $controllerBaseName)));
            $routePath = $prefix . $resource;
        }

        // Create the test file path
        $path = base_path('tests/Feature/app/Http/Controllers');
        if (!empty($parts)) {
            $path .= '/' . implode('/', $parts);
        }
        $path .= '/' . $className . '.php';

        // Check if file already exists
        if ($this->files->exists($path) && !$this->confirm("The test [{$path}] already exists. Do you want to overwrite it?")) {
            return 0;
        }

        // Create directory if it doesn't exist
        $this->ensureDirectoryExists(dirname($path));

        // Get the stub content
        $stub = $this->files->get(__DIR__ . '/../../../stubs/controller-test.stub');

        // Replace placeholders
        $stub = str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ model }}', '{{ table }}', '{{ role }}', '{{ route_path }}'],
            [$namespace, $className, $model, $table, $this->option('role'), $routePath],
            $stub
        );

        // Write the file
        $this->files->put($path, $stub);

        $this->info("Controller test created successfully: {$path}");
        $this->line("Model: {$model}");
        $this->line("Table: {$table}");
        $this->line("Role: {$this->option('role')}");
        $this->line("Route Path: {$routePath}");

        return 0;
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