<?php

namespace Kolydart\Laravel\App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;

/**
 * Class MakeOrderedPivotMigration
 *
 * Artisan command to generate migrations for adding order columns to pivot tables.
 *
 * @package Kolydart\Laravel\App\Console\Commands
 */
class MakeOrderedPivotMigration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:ordered-pivot-migration
                            {table : The name of the pivot table}
                            {--order-column=order : The name of the order column}
                            {--after= : The column to place the order column after}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a migration to add an order column to a pivot table';

    /**
     * The filesystem instance.
     *
     * @var Filesystem
     */
    protected $files;

    /**
     * Create a new command instance.
     *
     * @param Filesystem $files
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
        $table = $this->argument('table');
        $orderColumn = $this->option('order-column');
        $afterColumn = $this->option('after');

        $migrationName = 'add_' . $orderColumn . '_to_' . $table . '_table';
        $className = Str::studly($migrationName);

        $migrationPath = $this->getMigrationPath($migrationName);

        if ($this->files->exists($migrationPath)) {
            $this->error('Migration already exists!');
            return 1;
        }

        $stub = $this->getStub();
        $stub = $this->replacePlaceholders($stub, [
            'ClassName' => $className,
            'table' => $table,
            'orderColumn' => $orderColumn,
            'afterColumn' => $afterColumn,
        ]);

        $this->files->put($migrationPath, $stub);

        $this->info("Migration created successfully: {$migrationPath}");

        return 0;
    }

    /**
     * Get the migration path.
     *
     * @param string $name
     * @return string
     */
    protected function getMigrationPath(string $name): string
    {
        return database_path('migrations/' . date('Y_m_d_His') . '_' . $name . '.php');
    }

    /**
     * Get the migration stub.
     *
     * @return string
     */
    public function getStub(): string
    {
        return <<<'STUB'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('{{table}}', function (Blueprint $table) {
            {{columnDefinition}}
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('{{table}}', function (Blueprint $table) {
            $table->dropColumn('{{orderColumn}}');
        });
    }
};
STUB;
    }

    /**
     * Replace placeholders in the stub.
     *
     * @param string $stub
     * @param array $replacements
     * @return string
     */
    protected function replacePlaceholders(string $stub, array $replacements): string
    {
        $columnDefinition = '$table->integer(\'' . $replacements['orderColumn'] . '\')->default(0)';

        if (!empty($replacements['afterColumn'])) {
            $columnDefinition .= '->after(\'' . $replacements['afterColumn'] . '\')';
        }

        $columnDefinition .= ';';

        $replacements['columnDefinition'] = $columnDefinition;

        foreach ($replacements as $key => $value) {
            $stub = str_replace('{{' . $key . '}}', $value, $stub);
        }

        return $stub;
    }
}
