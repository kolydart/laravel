<?php

namespace Kolydart\Laravel\Tests\App\Traits;

use Illuminate\Database\Eloquent\Model;
use Kolydart\Laravel\App\Support\OrderedPivotSync;
use Kolydart\Laravel\App\Traits\HandlesOrderedPivot;
use Kolydart\Laravel\App\Traits\HasOrderedPivot;
use Kolydart\Laravel\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;

/**
 * Structural tests for the ordered-pivot toolkit: wiring, delegation and asset
 * publishing. These are cheap guards against the traits drifting back into
 * carrying their own copy of the diff.
 *
 * The behaviour of the diff itself — ordering, and the phantom-event-free write
 * pattern — is covered against a real sqlite database in
 * `tests/App/Support/OrderedPivotSyncTest.php`.
 */
class OrderedPivotTest extends TestCase
{
    private function makeModel(): object
    {
        return new class extends Model {
            use HasOrderedPivot;
        };
    }

    private function makeController(): object
    {
        return new class {
            use HandlesOrderedPivot;

            public function callSyncWithOrder(Model $model, string $relation, array $ids): void
            {
                $this->syncWithOrder($model, $relation, $ids);
            }
        };
    }

    #[Test]
    public function both_traits_delegate_the_diff_to_the_shared_support_class(): void
    {
        foreach ([$this->makeModel(), $this->makeController()] as $host) {
            $ref = new ReflectionMethod($host, 'syncWithOrder');

            $source = implode('', array_slice(
                file($ref->getFileName()),
                $ref->getStartLine() - 1,
                $ref->getEndLine() - $ref->getStartLine() + 1
            ));

            $this->assertStringContainsString('OrderedPivotSync::apply', $source);
            $this->assertStringNotContainsString('->attach(', $source, 'Diff logic must not be duplicated in the trait.');
        }
    }

    #[Test]
    public function support_class_exposes_apply_and_ordered_ids(): void
    {
        $this->assertTrue(method_exists(OrderedPivotSync::class, 'apply'));
        $this->assertTrue(method_exists(OrderedPivotSync::class, 'orderedIds'));

        $ref = new ReflectionMethod(OrderedPivotSync::class, 'apply');
        $this->assertTrue($ref->isStatic());
        $this->assertSame('order', $ref->getParameters()[2]->getDefaultValue());
    }

    #[Test]
    public function ordered_belongs_to_many_defaults_to_the_order_column(): void
    {
        $ref = new ReflectionMethod($this->makeModel(), 'orderedBelongsToMany');
        $params = $ref->getParameters();

        $this->assertSame('orderColumn', end($params)->getName());
        $this->assertSame('order', end($params)->getDefaultValue());
    }

    #[Test]
    public function javascript_asset_exposes_drag_reorder_gated_behind_sortable(): void
    {
        $js = file_get_contents(__DIR__ . '/../../../src/Resources/js/ordered-select.js');

        $this->assertStringContainsString('static enableDragReorder', $js);
        $this->assertStringContainsString("typeof Sortable === 'undefined'", $js, 'Drag reorder must degrade when SortableJS is absent.');
        $this->assertStringContainsString('[data-drag-reorder]', $js, 'autoInit must pick up the opt-in attribute.');
    }

    #[Test]
    public function sortable_js_is_bundled_and_published(): void
    {
        $bundled = __DIR__ . '/../../../src/Resources/js/vendor/Sortable.min.js';

        $this->assertFileExists($bundled);
        $this->assertStringContainsString('Sortable 1.15.6', file_get_contents($bundled));

        $provider = file_get_contents(__DIR__ . '/../../../src/Providers/OrderedPivotServiceProvider.php');
        $this->assertStringContainsString("public_path('vendor/kolydart/js/Sortable.min.js')", $provider);
    }

    #[Test]
    public function blade_component_opts_into_drag_reorder(): void
    {
        $blade = file_get_contents(__DIR__ . '/../../../src/Resources/views/components/ordered-select.blade.php');

        $this->assertStringContainsString("'dragReorder' => true", $blade);
        $this->assertStringContainsString('data-drag-reorder', $blade);
    }
}
