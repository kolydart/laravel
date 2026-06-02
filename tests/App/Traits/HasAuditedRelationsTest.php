<?php

namespace Kolydart\Laravel\Tests\App\Traits;

use Illuminate\Database\Eloquent\Model;
use Kolydart\Laravel\App\Traits\HasAuditedRelations;
use Kolydart\Laravel\Tests\TestCase;
use ReflectionMethod;

/**
 * Structural tests για το HasAuditedRelations trait. Δεν τρέχουν DB
 * operations — η ολοκληρωμένη integration καλύπτεται στο l_helmarc
 * (tests/Feature/app/Livewire/pgAuditLogsTest.php).
 */
class HasAuditedRelationsTest extends TestCase
{
    private function makeHost(): object
    {
        return new class extends Model {
            use HasAuditedRelations;
        };
    }

    /** @test */
    public function trait_exposes_expected_public_methods(): void
    {
        $host = $this->makeHost();
        $methods = [
            'auditedAttach', 'auditedDetach', 'auditedSync',
            'auditedSyncWithoutDetaching', 'auditedToggle',
            'auditedSyncWithOrder', 'auditedSyncRoledPivot',
        ];
        foreach ($methods as $method) {
            $this->assertTrue(method_exists($host, $method), "Missing method: {$method}");
        }
    }

    /** @test */
    public function audited_sync_with_order_has_correct_signature(): void
    {
        $ref = new ReflectionMethod($this->makeHost(), 'auditedSyncWithOrder');
        $params = $ref->getParameters();

        $this->assertCount(3, $params);
        $this->assertSame('relation', $params[0]->getName());
        $this->assertSame('ids', $params[1]->getName());
        $this->assertSame('orderColumn', $params[2]->getName());
        $this->assertSame('order', $params[2]->getDefaultValue());
    }

    /** @test */
    public function audited_sync_roled_pivot_has_correct_defaults(): void
    {
        $ref = new ReflectionMethod($this->makeHost(), 'auditedSyncRoledPivot');
        $params = $ref->getParameters();

        $this->assertCount(4, $params);
        $this->assertSame('role', $params[2]->getDefaultValue());
        $this->assertSame('creator', $params[3]->getDefaultValue());
    }

    /** @test */
    public function silent_pivot_update_is_protected(): void
    {
        $ref = new ReflectionMethod($this->makeHost(), 'silentPivotUpdate');
        $this->assertTrue($ref->isProtected(), 'silentPivotUpdate must be protected');

        $params = $ref->getParameters();
        $this->assertCount(4, $params);
        // extraWhere has a default of []
        $this->assertSame([], $params[3]->getDefaultValue());
    }

    /** @test */
    public function normalize_audited_ids_accepts_int_array_collection_and_model(): void
    {
        $host = $this->makeHost();
        $ref = new ReflectionMethod($host, 'normalizeAuditedIds');
        $ref->setAccessible(true);

        // Integer
        $this->assertSame([42 => []], $ref->invoke($host, 42, []));

        // Numeric array
        $this->assertSame([1 => [], 2 => []], $ref->invoke($host, [1, 2], []));

        // Assoc array με pivot attrs
        $this->assertSame(
            [1 => ['role' => 'creator']],
            $ref->invoke($host, [1 => ['role' => 'creator']], [])
        );

        // Με defaults
        $this->assertSame(
            [5 => ['extra' => 'x']],
            $ref->invoke($host, 5, ['extra' => 'x'])
        );
    }

    /** @test */
    public function extract_role_from_pivot_returns_role_when_set(): void
    {
        $host = $this->makeHost();
        $ref = new ReflectionMethod($host, 'extractRoleFromPivot');
        $ref->setAccessible(true);

        $this->assertSame('creator', $ref->invoke($host, ['role' => 'creator']));
        $this->assertNull($ref->invoke($host, []));
        $this->assertNull($ref->invoke($host, ['role' => null]));
    }

    /** @test */
    public function resolve_audited_relation_throws_for_undefined_relation(): void
    {
        $host = $this->makeHost();
        $this->expectException(\LogicException::class);

        $ref = new ReflectionMethod($host, 'resolveAuditedRelation');
        $ref->setAccessible(true);
        $ref->invoke($host, 'doesNotExist');
    }
}
