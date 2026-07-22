<?php

namespace Kolydart\Laravel\Tests\App\Support;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Kolydart\Laravel\App\Support\OrderedPivotSync;
use Kolydart\Laravel\App\Traits\HasOrderedPivot;
use Kolydart\Laravel\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Behavioural coverage for the ordered-pivot smart diff, against a real
 * in-memory sqlite database.
 *
 * `OrderedPivotSync` is the single implementation behind
 * `HasOrderedPivot::syncWithOrder()`, `HandlesOrderedPivot::syncWithOrder()` and
 * `HasAuditedRelations::auditedSyncWithOrder()`, so its phantom-event-free
 * guarantees are asserted here in terms of the SQL actually issued.
 */
class OrderedPivotSyncTest extends TestCase
{
    private Capsule $capsule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->capsule = new Capsule($this->app);
        $this->capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();

        $schema = $this->capsule->schema();

        $schema->create('sync_types', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
        });

        $schema->create('sync_pages', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
        });

        $schema->create('sync_page_sync_type', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('sync_type_id');
            $table->unsignedInteger('sync_page_id');
            $table->unsignedInteger('order')->default(0);
        });

        $this->capsule->table('sync_types')->insert([['id' => 1, 'name' => 'type']]);
        $this->capsule->table('sync_pages')->insert([
            ['id' => 10, 'name' => 'a'],
            ['id' => 20, 'name' => 'b'],
            ['id' => 30, 'name' => 'c'],
            ['id' => 40, 'name' => 'd'],
        ]);
    }

    protected function tearDown(): void
    {
        Model::unsetConnectionResolver();

        parent::tearDown();
    }

    /** Fresh relationship instance — `apply()` mutates the one it is given. */
    private function relation(): BelongsToMany
    {
        return SyncType::query()->findOrFail(1)->pages();
    }

    /** The pivot rows as `related_id => order`, ordered by the order column. */
    private function pivotState(): array
    {
        return $this->capsule->table('sync_page_sync_type')
            ->orderBy('order')
            ->pluck('order', 'sync_page_id')
            ->map(fn ($order) => (int) $order)
            ->all();
    }

    /** Run a callback with the query log on, returning the statements issued. */
    private function captureQueries(callable $callback): array
    {
        $connection = $this->capsule->connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        $callback();

        $connection->disableQueryLog();

        return array_column($connection->getQueryLog(), 'query');
    }

    /** Statements touching the pivot table, keyed by verb. */
    private function pivotWrites(array $queries): array
    {
        $writes = ['insert' => 0, 'update' => 0, 'delete' => 0];

        foreach ($queries as $query) {
            if (!str_contains($query, 'sync_page_sync_type')) {
                continue;
            }

            $verb = strtolower(strtok(ltrim($query), ' '));

            if (isset($writes[$verb])) {
                $writes[$verb]++;
            }
        }

        return $writes;
    }

    #[Test]
    public function it_attaches_ids_in_submitted_order_starting_at_one(): void
    {
        OrderedPivotSync::apply($this->relation(), [30, 10, 20]);

        $this->assertSame([30 => 1, 10 => 2, 20 => 3], $this->pivotState());
    }

    #[Test]
    public function it_ignores_empty_ids(): void
    {
        OrderedPivotSync::apply($this->relation(), [30, '', null, 10, 0]);

        $this->assertSame([30 => 1, 10 => 2], $this->pivotState());
    }

    #[Test]
    public function an_unchanged_sync_writes_nothing_to_the_pivot_table(): void
    {
        OrderedPivotSync::apply($this->relation(), [10, 20, 30]);

        $writes = $this->pivotWrites($this->captureQueries(
            fn () => OrderedPivotSync::apply($this->relation(), [10, 20, 30])
        ));

        $this->assertSame(
            ['insert' => 0, 'update' => 0, 'delete' => 0],
            $writes,
            'A sync with identical input must produce zero pivot writes.'
        );
        $this->assertSame([10 => 1, 20 => 2, 30 => 3], $this->pivotState());
    }

    #[Test]
    public function a_reorder_only_change_issues_updates_and_never_detaches(): void
    {
        OrderedPivotSync::apply($this->relation(), [10, 20, 30]);

        $writes = $this->pivotWrites($this->captureQueries(
            fn () => OrderedPivotSync::apply($this->relation(), [30, 20, 10])
        ));

        $this->assertSame(0, $writes['delete'], 'Reordering must not detach surviving rows.');
        $this->assertSame(0, $writes['insert'], 'Reordering must not re-attach surviving rows.');
        $this->assertSame(2, $writes['update'], 'Only the two rows whose order changed should be updated.');

        $this->assertSame([30 => 1, 20 => 2, 10 => 3], $this->pivotState());
    }

    #[Test]
    public function it_detaches_removed_ids_and_attaches_new_ones_only(): void
    {
        OrderedPivotSync::apply($this->relation(), [10, 20, 30]);

        $writes = $this->pivotWrites($this->captureQueries(
            fn () => OrderedPivotSync::apply($this->relation(), [10, 20, 40])
        ));

        $this->assertSame(1, $writes['delete'], 'Only the removed id should be detached.');
        $this->assertSame(1, $writes['insert'], 'Only the new id should be attached.');
        $this->assertSame(0, $writes['update'], 'Ids keeping their position need no update.');

        $this->assertSame([10 => 1, 20 => 2, 40 => 3], $this->pivotState());
    }

    #[Test]
    public function syncing_an_empty_array_detaches_everything(): void
    {
        OrderedPivotSync::apply($this->relation(), [10, 20]);
        OrderedPivotSync::apply($this->relation(), []);

        $this->assertSame([], $this->pivotState());
    }

    #[Test]
    public function ordered_ids_returns_ids_in_stored_order(): void
    {
        OrderedPivotSync::apply($this->relation(), [30, 10, 20]);

        $this->assertSame(
            [30, 10, 20],
            array_map('intval', OrderedPivotSync::orderedIds($this->relation()))
        );
    }

    #[Test]
    public function diff_reports_the_three_buckets_without_touching_the_database(): void
    {
        OrderedPivotSync::apply($this->relation(), [10, 20, 30]);

        $diff = OrderedPivotSync::diff($this->relation(), [30, 20, 40]);

        $this->assertSame([10], $diff['detach']);
        $this->assertSame([40 => 3], $diff['attach']);
        $this->assertSame([30 => 1], $diff['reorder'], '20 keeps position 2 and must not be reordered.');

        $this->assertSame([10 => 1, 20 => 2, 30 => 3], $this->pivotState(), 'diff() must not mutate the pivot.');
    }

    #[Test]
    public function the_model_trait_helper_produces_the_same_result_as_the_support_class(): void
    {
        $type = SyncType::query()->findOrFail(1);

        $type->syncWithOrder($type->pages(), [30, 10]);

        $this->assertSame([30 => 1, 10 => 2], $this->pivotState());
        $this->assertSame([30, 10], array_map('intval', $type->getOrderedIds($type->pages())));
    }
}

class SyncType extends Model
{
    use HasOrderedPivot;

    public $timestamps = false;

    protected $table = 'sync_types';

    protected $guarded = [];

    public function pages(): BelongsToMany
    {
        return $this->orderedBelongsToMany(
            SyncPage::class,
            'sync_page_sync_type',
            'sync_type_id',
            'sync_page_id'
        );
    }
}

class SyncPage extends Model
{
    public $timestamps = false;

    protected $table = 'sync_pages';

    protected $guarded = [];
}
