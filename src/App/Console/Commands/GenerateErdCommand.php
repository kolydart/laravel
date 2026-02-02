<?php

namespace Kolydart\Laravel\App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class GenerateErdCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'erd:generate {--output= : Output file path} {--compare} {--database=} {--raw-relationships : Output the full schema without simplifying relationships} {--ignore=* : Tables to ignore (can be used multiple times)} {--ignore-column=* : Columns to ignore (can be used multiple times)} {--font-size= : Font size for the diagram (e.g. 20px)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Entity Relationship Diagram in Mermaid format from database schema';

    /**
     * Default output path for the ERD file.
     *
     * @var string
     */
    protected const DEFAULT_OUTPUT_PATH = 'docs/database-erd.md';

    /**
     * Default font size for the diagram.
     *
     * @var string
     */
    protected const DEFAULT_FONT_SIZE = '24px';

    /**
     * Default tables to ignore.
     *
     * @var array
     */
    protected $defaultIgnoredTables = [
        'audit_logs',
        'media',
        'migrations',
        'password_resets',
        'permissions',
        'personal_access_tokens',
        'roles',
        'users',
    ];

    /**
     * Default columns to ignore.
     *
     * @var array
     */
    protected $defaultIgnoredColumns = [
        'created_at',
        'updated_at',
        // 'deleted_at',
    ];

    /**
     * The console command help text.
     *
     * @var string
     */
    protected $help = <<<HELP
Entity Relationship Diagram (ERD) Generator

Usage:
  php artisan erd:generate [options]

Options:
  --output=path             Specify output file path (default: docs/database-erd.md)
  --compare                 Compare with existing ERD file and show if changes detected
  --database=connection     Use specific database connection
  --raw-relationships       Disable simplification (show junction tables explicitly)
  --ignore=table            Ignore specific tables (can be used multiple times)
  --ignore-column=col       Ignore specific columns (can be used multiple times)
  --font-size=size          Set custom font size (e.g. 20px)

Description:
  Generates a Mermaid ERD file from your database schema.

  By default:
  - Many-to-Many relationships are simplified (junction tables hidden).
  - System tables (migrations, password_resets, etc.) are ignored.
  - Timestamps (created_at, updated_at) are ignored.

  Configuration:
  To publish the config file:
  php artisan vendor:publish --tag=erd-generator-config

  You can configure defaults in config/erd-generator.php.
  Precedence: Command options > Config file > Class defaults.

Examples:
  php artisan erd:generate
  php artisan erd:generate --raw-relationships --ignore=jobs
  php artisan erd:generate --output=docs/schema.md --font-size=20px
HELP;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!class_exists(\Doctrine\DBAL\Schema\Schema::class) && !class_exists(\Doctrine\DBAL\Schema\AbstractSchemaManager::class)) {
            $this->error('The doctrine/dbal package is required to generate ERDs.');
            $this->line('Please install it in your project: composer require doctrine/dbal');
            return self::FAILURE;
        }

        $this->info('Generating ERD from database schema...');

        $database = $this->option('database') ?: $this->getConfig('database.default');

        // Output precedence: Option > Config > Default
        $outputPath = $this->option('output')
            ?? $this->getConfig('erd-generator.output')
            ?? self::DEFAULT_OUTPUT_PATH;

        // Simplification precedence: Option flag (forces raw) > Config > Default (true)
        // If --raw-relationships is passed, we DO NOT simplify.
        // If not passed, we check config.
        $shouldSimplify = $this->option('raw-relationships')
            ? false
            : $this->getConfig('erd-generator.simplify_relationships', true);

        // Ignore Tables precedence: Config (overrides class default) + Option (appends)
        // If config exists, use it. If not, use class defaults. Then merge options.
        $configIgnoredTables = $this->getConfig('erd-generator.ignored_tables');
        $baseIgnoredTables = $configIgnoredTables !== null ? $configIgnoredTables : $this->getDefaultIgnoredTables();

        $ignoredTables = array_merge(
            $baseIgnoredTables,
            $this->option('ignore')
        );

        // Ignore Columns precedence
        $configIgnoredColumns = $this->getConfig('erd-generator.ignored_columns');
        $baseIgnoredColumns = $configIgnoredColumns !== null ? $configIgnoredColumns : $this->getDefaultIgnoredColumns();

        $ignoredColumns = array_merge(
            $baseIgnoredColumns,
            $this->option('ignore-column')
        );

        try {
            $schema = $this->extractDatabaseSchema($database, $ignoredTables, $ignoredColumns);

            if (empty($schema['tables'])) {
                $this->warn('No tables found in the database (or all were ignored).');
                return self::FAILURE;
            }

            if ($shouldSimplify) {
                $schema = $this->simplifySchema($schema);
            }

            $mermaidERD = $this->generateMermaidERD($schema);

            $this->ensureOutputDirectory($outputPath);

            if ($this->option('compare') && File::exists($outputPath)) {
                $this->compareWithExisting($outputPath, $mermaidERD);
            }

            File::put($outputPath, $mermaidERD);

            $this->info("ERD generated successfully: {$outputPath}");
            $this->info("Tables processed: " . count($schema['tables']));

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error generating ERD: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    protected function getDefaultIgnoredTables(): array
    {
        return $this->defaultIgnoredTables;
    }

    protected function getDefaultIgnoredColumns(): array
    {
        return $this->defaultIgnoredColumns;
    }

    protected function extractDatabaseSchema(string $database, array $ignoredTables = [], array $ignoredColumns = []): array
    {
        $connection = DB::connection($database);
        $schema = [];

        $schemaManager = $this->getDoctrineSchemaManager($connection);
        $tables = $schemaManager->listTableNames();

        foreach ($tables as $tableName) {
            if (in_array($tableName, $ignoredTables)) {
                continue;
            }

            $table = $schemaManager->introspectTable($tableName);
            $columns = [];
            $foreignKeys = [];

            foreach ($table->getColumns() as $column) {
                if (in_array($column->getName(), $ignoredColumns)) {
                    continue;
                }

                $columns[] = [
                    'name' => $column->getName(),
                    'type' => $column->getType()::class,
                    'nullable' => !$column->getNotnull(),
                    'default' => $column->getDefault(),
                    'autoIncrement' => $column->getAutoincrement(),
                ];
            }

            foreach ($table->getForeignKeys() as $foreignKey) {
                $foreignKeys[] = [
                    'columns' => $foreignKey->getLocalColumns(),
                    'referenced_table' => $foreignKey->getForeignTableName(),
                    'referenced_columns' => $foreignKey->getForeignColumns(),
                ];
            }

            $schema['tables'][$tableName] = [
                'columns' => $columns,
                'primary_key' => $table->getPrimaryKey() ? $table->getPrimaryKey()->getColumns() : [],
                'indexes' => array_map(fn($index) => [
                    'name' => $index->getName(),
                    'columns' => $index->getColumns(),
                    'unique' => $index->isUnique(),
                ], $table->getIndexes()),
                'foreign_keys' => $foreignKeys,
            ];
        }

        return $schema;
    }

    /**
     * Get Doctrine Schema Manager compatible with both Laravel 10 and 11.
     */
    protected function getDoctrineSchemaManager($connection)
    {
        // Laravel < 11
        if (version_compare(app()->version(), '11.0.0', '<')) {
            return $connection->getDoctrineSchemaManager();
        }

        // Laravel 11+
        $driverMap = [
            'mysql' => 'pdo_mysql',
            'pgsql' => 'pdo_pgsql',
            'sqlite' => 'pdo_sqlite',
            'sqlsrv' => 'pdo_sqlsrv',
        ];

        $driverName = $connection->getDriverName();
        $doctrineDriver = $driverMap[$driverName] ?? 'pdo_mysql';

        $config = $connection->getConfig();

        $params = [
            'pdo' => $connection->getPdo(),
            'dbname' => $config['database'] ?? null,
            'driver' => $doctrineDriver,
            'user' => $config['username'] ?? null,
            'password' => $config['password'] ?? null,
            'host' => $config['host'] ?? null,
            'port' => $config['port'] ?? null,
        ];

        // Ensure we don't pass nulls if keys are missing, as it might confuse Doctrine
        $params = array_filter($params, fn($value) => !is_null($value));

        $doctrineConnection = \Doctrine\DBAL\DriverManager::getConnection($params);

        return $doctrineConnection->createSchemaManager();
    }

    protected function simplifySchema(array $schema): array
    {
        $systemColumns = ['id', 'created_at', 'updated_at', 'deleted_at'];
        $simplifiedSchema = $schema;
        $simplifiedSchema['many_to_many'] = [];

        foreach ($schema['tables'] as $tableName => $tableData) {
            $columns = array_column($tableData['columns'], 'name');
            $foreignKeys = $tableData['foreign_keys'];

            // Check if it's a junction table candidates
            // 1. Must have exactly 2 foreign keys
            if (count($foreignKeys) !== 2) {
                continue;
            }

            // 2. Identify FK columns
            $fkColumns = [];
            foreach ($foreignKeys as $fk) {
                $fkColumns = array_merge($fkColumns, $fk['columns']);
            }

            // 3. Check if all other columns are system columns
            $nonFkColumns = array_diff($columns, $fkColumns);
            $isJunctionTable = true;
            foreach ($nonFkColumns as $col) {
                if (!in_array($col, $systemColumns)) {
                    $isJunctionTable = false;
                    break;
                }
            }

            if ($isJunctionTable) {
                // Remove table from standard list
                unset($simplifiedSchema['tables'][$tableName]);

                // Register Many-to-Many relationship
                $tableA = $foreignKeys[0]['referenced_table'];
                $tableB = $foreignKeys[1]['referenced_table'];

                // Sort relationships to ensure consistency
                $tables = [$tableA, $tableB];
                sort($tables);

                $simplifiedSchema['many_to_many'][] = [
                    'table_a' => $tables[0],
                    'table_b' => $tables[1],
                    'via' => $tableName
                ];
            }
        }

        return $simplifiedSchema;
    }

    protected function generateMermaidERD(array $schema): string
    {
        $mermaid = "```mermaid\n";

        // Font size precedence: Option > Config > Default (24px)
        $fontSize = $this->option('font-size') ?? $this->getConfig('erd-generator.font_size') ?? self::DEFAULT_FONT_SIZE;

        if ($fontSize) {
            // Must use theme: base for themeVariables to work on non-typical elements like relationships
            $mermaid .= "%%{init: {'theme': 'base', 'themeVariables': { 'fontSize': '{$fontSize}'}}}%%\n";
        }

        $mermaid .= "erDiagram\n";

        foreach ($schema['tables'] as $tableName => $tableData) {
            $mermaid .= "    {$tableName}[\"**{$tableName}**\"] {\n";

            foreach ($tableData['columns'] as $column) {
                $type = $this->mapColumnType($column['type']);
                $constraints = $this->getColumnConstraints($column, $tableData);

                $mermaid .= "        {$type} {$column['name']}{$constraints}\n";
            }

            $mermaid .= "    }\n\n";
        }

        // Standard One-to-Many Relationships
        foreach ($schema['tables'] as $tableName => $tableData) {
            foreach ($tableData['foreign_keys'] as $foreignKey) {
                $referencedTable = $foreignKey['referenced_table'];
                // Only draw if referenced table still exists (wasn't simplified away)
                if (isset($schema['tables'][$referencedTable])) {
                    $mermaid .= "    {$referencedTable} ||--o{ {$tableName} : \"has\"\n";
                }
            }
        }

        // Simplified Many-to-Many Relationships
        if (isset($schema['many_to_many'])) {
            foreach ($schema['many_to_many'] as $rel) {
                // Use }o--o{ notation
                if (isset($schema['tables'][$rel['table_a']]) && isset($schema['tables'][$rel['table_b']])) {
                     $mermaid .= "    {$rel['table_a']} }o--o{ {$rel['table_b']} : \"{$rel['via']}\"\n";
                }
            }
        }

        $mermaid .= "```\n";

        return $mermaid;
    }

    protected function mapColumnType(string $type): string
    {
        $shortType = strtolower(class_basename($type));

        return match (true) {
            str_contains($shortType, 'integer') || str_contains($shortType, 'bigint') || str_contains($shortType, 'smallint') => 'int',
            str_contains($shortType, 'string') || str_contains($shortType, 'text') => 'string',
            str_contains($shortType, 'decimal') || str_contains($shortType, 'float') || str_contains($shortType, 'double') => 'decimal',
            str_contains($shortType, 'boolean') => 'boolean',
            str_contains($shortType, 'datetime') || str_contains($shortType, 'timestamp') => 'datetime',
            str_contains($shortType, 'date') => 'date',
            str_contains($shortType, 'time') => 'time',
            default => 'string',
        };
    }

    protected function getColumnConstraints(array $column, array $tableData): string
    {
        $constraints = [];

        if (in_array($column['name'], $tableData['primary_key'])) {
            $constraints[] = 'PK';
        }

        if ($column['autoIncrement']) {
            $constraints[] = 'AUTO_INCREMENT';
        }

        if (!$column['nullable']) {
            $constraints[] = 'NOT_NULL';
        }

        foreach ($tableData['foreign_keys'] as $foreignKey) {
            if (in_array($column['name'], $foreignKey['columns'])) {
                $constraints[] = 'FK';
                break;
            }
        }

        foreach ($tableData['indexes'] as $index) {
            if ($index['unique'] && in_array($column['name'], $index['columns'])) {
                $constraints[] = 'UK';
                break;
            }
        }

        return $constraints ? ' "' . implode(', ', $constraints) . '"' : '';
    }

    protected function ensureOutputDirectory(string $outputPath): void
    {
        $directory = dirname($outputPath);

        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
            $this->info("Created directory: {$directory}");
        }
    }

    protected function compareWithExisting(string $outputPath, string $newContent): void
    {
        $existingContent = File::get($outputPath);

        if ($existingContent === $newContent) {
            $this->info('No changes detected in database schema.');
        } else {
            $this->warn('Database schema changes detected!');
            $this->line('Review the changes in the generated file.');
        }
    }

    /**
     * Get value from configuration.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getConfig(string $key, $default = null)
    {
        return config($key, $default);
    }
}
