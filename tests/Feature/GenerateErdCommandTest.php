<?php

namespace Kolydart\Laravel\Tests\Feature;

use Kolydart\Laravel\App\Console\Commands\GenerateErdCommand;
use PHPUnit\Framework\TestCase;

class GenerateErdCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_it_simplifies_junction_tables_by_default()
    {
        $command = new TestableGenerateErdCommand();

        // Let's test the simplifySchema method logic directly
        $schema = $command->getTestSchema();
        $simplified = $command->testSimplifySchema($schema);

        // Assert role_user (junction) is gone
        $this->assertArrayNotHasKey('role_user', $simplified['tables']);

        // Assert users and roles remain
        $this->assertArrayHasKey('users', $simplified['tables']);
        $this->assertArrayHasKey('roles', $simplified['tables']);

        // Assert relationship added
        $this->assertCount(1, $simplified['many_to_many']);
        $this->assertEquals('roles', $simplified['many_to_many'][0]['table_a']);
        $this->assertEquals('users', $simplified['many_to_many'][0]['table_b']);
        $this->assertEquals('role_user', $simplified['many_to_many'][0]['via']);
    }

    public function test_it_keeps_junction_tables_complex_columns()
    {
        $command = new TestableGenerateErdCommand();
        $schema = $command->getTestSchema();

        // Add a "complex" column to role_user
        $schema['tables']['role_user']['columns'][] = [
            'name' => 'extra_data',
            'type' => 'string',
        ];

        $simplified = $command->testSimplifySchema($schema);

        // Assert role_user STAYS because it has extra data
        $this->assertArrayHasKey('role_user', $simplified['tables']);
        $this->assertEmpty($simplified['many_to_many']);
    }

    public function test_it_keeps_tables_with_one_fk()
    {
        $command = new TestableGenerateErdCommand();
        $schema = $command->getTestSchema();

        // Remove one FK
        array_pop($schema['tables']['role_user']['foreign_keys']);

        $simplified = $command->testSimplifySchema($schema);

        // Assert role_user STAYS because it doesn't have 2 FKs
        $this->assertArrayHasKey('role_user', $simplified['tables']);
    }
    public function test_it_generates_correct_relationship_syntax()
    {
        $command = new TestableGenerateErdCommand();
        $schema = $command->getTestSchema();
        $simplified = $command->testSimplifySchema($schema);

        $mermaid = $command->testGenerateMermaidERD($simplified);

        // Check for simplified syntax
        $this->assertStringContainsString('roles }o--o{ users : "role_user"', $mermaid);

        // Ensure role_user table definition is NOT present
        $this->assertStringNotContainsString('role_user {', $mermaid);
    }

    public function test_it_has_correct_default_ignored_tables()
    {
        $command = new TestableGenerateErdCommand();
        $defaults = $command->testGetDefaultIgnoredTables();

        $expected = [
            'migrations',
            'password_resets',
            'permissions',
            'personal_access_tokens',
            'roles',
            'users',
        ];

        foreach ($expected as $table) {
            $this->assertContains($table, $defaults);
        }
    }
    public function test_it_has_correct_default_ignored_columns()
    {
        $command = new TestableGenerateErdCommand();
        $defaults = $command->testGetDefaultIgnoredColumns();

        $expected = [
            'created_at',
            'updated_at',
            // 'deleted_at',
        ];

        foreach ($expected as $table) {
            $this->assertContains($table, $defaults);
        }
    }

    public function test_it_includes_font_size_directive()
    {
        $command = new TestableGenerateErdCommand();

        // Simulate option
        $command->mockOptions(['font-size' => '24px']);

        $schema = $command->getTestSchema();
        $mermaid = $command->testGenerateMermaidERD($schema);

        $this->assertStringContainsString("%%{init: {'theme': 'base', 'themeVariables': { 'fontSize': '24px'}}}%%", $mermaid);
    }

    public function test_it_bolds_table_names()
    {
        $this->markTestIncomplete('something changed in the mermaid syntax');
        $command = new TestableGenerateErdCommand();
        $schema = $command->getTestSchema();
        $mermaid = $command->testGenerateMermaidERD($schema);

        $this->assertStringContainsString('users["**users**"] {', $mermaid);
        $this->assertStringContainsString('roles["**roles**"] {', $mermaid);
    }

    public function test_it_uses_config_values()
    {
        $command = new TestableGenerateErdCommand();

        // Mock config values
        $command->mockConfig(['erd-generator.font_size' => '30px']);

        $schema = $command->getTestSchema();
        $mermaid = $command->testGenerateMermaidERD($schema);

        // Check font size from config
        $this->assertStringContainsString("'fontSize': '30px'", $mermaid);
        $this->assertStringContainsString("'theme': 'base'", $mermaid);
    }

    public function test_it_uses_default_font_size()
    {
        $command = new TestableGenerateErdCommand();
        // No config mocked, no options mocked

        $schema = $command->getTestSchema();
        $mermaid = $command->testGenerateMermaidERD($schema);

        // Check default font size
        $this->assertStringContainsString("'fontSize': '24px'", $mermaid);
    }
}

class TestableGenerateErdCommand extends GenerateErdCommand
{
    private $mockOptions = [];

    public function __construct()
    {
        parent::__construct();
        // Since we are taking a 'unit test' approach to protected methods,
        // we don't necessarily need the full Laravel app structure for the command instance
        // unless we call methods that depend on it (like option()).
    }

    public function mockOptions(array $options)
    {
        $this->mockOptions = $options;
    }

    public function option($key = null)
    {
        if ($key && isset($this->mockOptions[$key])) {
            return $this->mockOptions[$key];
        }

        // Return null if we are running in a test context without Input bound
        if (! $this->input) {
            return null;
        }

        return parent::option($key);
    }

    protected $mockConfig = [];

    public function mockConfig(array $config)
    {
        $this->mockConfig = array_merge($this->mockConfig, $config);
    }

    protected function getConfig(string $key, $default = null)
    {
        return $this->mockConfig[$key] ?? $default;
    }

    // Expose protected method for testing
    public function testSimplifySchema(array $schema): array
    {
        return $this->simplifySchema($schema);
    }

    public function testGenerateMermaidERD(array $schema): string
    {
        return $this->generateMermaidERD($schema);
    }

    public function testGetDefaultIgnoredTables(): array
    {
        return $this->getDefaultIgnoredTables();
    }

    public function testGetDefaultIgnoredColumns(): array
    {
        return $this->getDefaultIgnoredColumns();
    }

    public function getTestSchema(): array
    {
        return [
            'tables' => [
                'users' => [
                    'columns' => [
                        ['name' => 'id', 'type' => 'int', 'autoIncrement' => true, 'nullable' => false],
                        ['name' => 'name', 'type' => 'string', 'autoIncrement' => false, 'nullable' => false],
                    ],
                    'foreign_keys' => [],
                    'primary_key' => ['id'],
                    'indexes' => [],
                ],
                'roles' => [
                    'columns' => [
                        ['name' => 'id', 'type' => 'int', 'autoIncrement' => true, 'nullable' => false],
                        ['name' => 'name', 'type' => 'string', 'autoIncrement' => false, 'nullable' => false],
                    ],
                    'foreign_keys' => [],
                    'primary_key' => ['id'],
                    'indexes' => [],
                ],
                'role_user' => [
                    'columns' => [
                        ['name' => 'user_id', 'type' => 'int', 'autoIncrement' => false, 'nullable' => false],
                        ['name' => 'role_id', 'type' => 'int', 'autoIncrement' => false, 'nullable' => false],
                        // System columns should be ignored
                        ['name' => 'created_at', 'type' => 'datetime', 'autoIncrement' => false, 'nullable' => true],
                    ],
                    'foreign_keys' => [
                        [
                            'columns' => ['user_id'],
                            'referenced_table' => 'users',
                            'referenced_columns' => ['id'],
                        ],
                        [
                            'columns' => ['role_id'],
                            'referenced_table' => 'roles',
                            'referenced_columns' => ['id'],
                        ],
                    ],
                    'primary_key' => [],
                    'indexes' => [],
                ]
            ]
        ];
    }
}
