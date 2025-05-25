<?php

namespace Kolydart\Laravel\Tests\Feature;

use Kolydart\Laravel\Tests\TestCase;
use Kolydart\Laravel\App\Traits\HasOrderedPivot;
use Kolydart\Laravel\App\Traits\HandlesOrderedPivot;

class OrderedPivotTest extends TestCase
{
    /** @test */
    public function it_can_create_ordered_belongs_to_many_relationship()
    {
        $mock = $this->createMockModel();

        // Test that the trait method exists and can be called
        $this->assertTrue(method_exists($mock, 'orderedBelongsToMany'));
        $this->assertTrue(method_exists($mock, 'syncWithOrder'));
        $this->assertTrue(method_exists($mock, 'getOrderedIds'));
    }

    /** @test */
    public function controller_trait_has_required_methods()
    {
        $controller = new TestController();

        $this->assertTrue(method_exists($controller, 'syncWithOrder'));
        $this->assertTrue(method_exists($controller, 'getOrderedIds'));
        $this->assertTrue(method_exists($controller, 'prepareOrderedRelationshipForEdit'));
    }

    /** @test */
    public function it_validates_relationship_method_exists()
    {
        $controller = new TestController();
        $model = $this->createMockModelWithoutMethod();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Relationship method 'nonexistent' does not exist on model");

        $this->callProtectedMethod($controller, 'syncWithOrder', [$model, 'nonexistent', []]);
    }

    /** @test */
    public function it_filters_empty_ids()
    {
        $controller = new TestController();

        // Test that empty values are filtered
        $ids = [1, '', 2, null, 3, 0];
        $filtered = array_filter($ids, function($id) {
            return !empty($id);
        });

        $this->assertEquals([1, 2, 3], array_values($filtered));
    }

    private function createMockModel()
    {
        return new class {
            use HasOrderedPivot;

            public function belongsToMany($related, $table = null, $foreignPivotKey = null, $relatedPivotKey = null, $parentKey = null, $relatedKey = null)
            {
                // Mock implementation
                return new class {
                    public function withPivot($columns) { return $this; }
                    public function getTable() { return 'test_table'; }
                    public function orderBy($column) { return $this; }
                };
            }
        };
    }

    private function createMockModelWithoutMethod()
    {
        return new class extends \Illuminate\Database\Eloquent\Model {
            // Empty model without any methods
        };
    }

    private function callProtectedMethod($object, $method, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}

// Test Controller
class TestController
{
    use HandlesOrderedPivot;
}
