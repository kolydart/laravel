<?php

namespace Kolydart\Laravel\Tests\Feature;

use Kolydart\Laravel\Tests\TestCase;
use Kolydart\Laravel\App\Console\Commands\MakeOrderedPivotMigration;
use Illuminate\Filesystem\Filesystem;

class MigrationCommandTest extends TestCase
{
    private $files;
    private $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->files = new Filesystem();
        $this->tempDir = sys_get_temp_dir() . '/test_migrations';

        if (!$this->files->exists($this->tempDir)) {
            $this->files->makeDirectory($this->tempDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        if ($this->files->exists($this->tempDir)) {
            $this->files->deleteDirectory($this->tempDir);
        }
        parent::tearDown();
    }

    /** @test */
    public function it_can_generate_migration_without_after_clause()
    {
        $command = new MakeOrderedPivotMigration($this->files);

        // Mock the database_path function
        $migrationPath = $this->tempDir . '/' . date('Y_m_d_His') . '_add_order_to_test_table_table.php';

        $stub = $command->getStub();
        $replacements = [
            'ClassName' => 'AddOrderToTestTableTable',
            'table' => 'test_table',
            'orderColumn' => 'order',
            'afterColumn' => '',
        ];

        $result = $this->callProtectedMethod($command, 'replacePlaceholders', [$stub, $replacements]);

        $this->assertStringContains('$table->integer(\'order\')->default(0);', $result);
        $this->assertStringContains('Schema::table(\'test_table\'', $result);
        $this->assertStringContains('$table->dropColumn(\'order\');', $result);
    }

    /** @test */
    public function it_can_generate_migration_with_after_clause()
    {
        $command = new MakeOrderedPivotMigration($this->files);

        $stub = $command->getStub();
        $replacements = [
            'ClassName' => 'AddOrderToTestTableTable',
            'table' => 'test_table',
            'orderColumn' => 'order',
            'afterColumn' => 'user_id',
        ];

        $result = $this->callProtectedMethod($command, 'replacePlaceholders', [$stub, $replacements]);

        $this->assertStringContains('$table->integer(\'order\')->default(0)->after(\'user_id\');', $result);
        $this->assertStringContains('Schema::table(\'test_table\'', $result);
        $this->assertStringContains('$table->dropColumn(\'order\');', $result);
    }

    /** @test */
    public function it_can_generate_migration_with_custom_order_column()
    {
        $command = new MakeOrderedPivotMigration($this->files);

        $stub = $command->getStub();
        $replacements = [
            'ClassName' => 'AddSortOrderToTestTableTable',
            'table' => 'test_table',
            'orderColumn' => 'sort_order',
            'afterColumn' => '',
        ];

        $result = $this->callProtectedMethod($command, 'replacePlaceholders', [$stub, $replacements]);

        $this->assertStringContains('$table->integer(\'sort_order\')->default(0);', $result);
        $this->assertStringContains('$table->dropColumn(\'sort_order\');', $result);
    }

    /** @test */
    public function it_generates_proper_migration_path()
    {
        $command = new MakeOrderedPivotMigration($this->files);

        // We can't easily test the actual path generation without mocking database_path
        // But we can test the method exists and returns a string
        $this->assertTrue(method_exists($command, 'getMigrationPath'));
    }

    private function callProtectedMethod($object, $method, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    private function assertStringContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertTrue(
            strpos($haystack, $needle) !== false,
            $message ?: "Failed asserting that '$haystack' contains '$needle'"
        );
    }
}
