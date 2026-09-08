<?php

namespace Kolydart\Laravel\Tests\App\Support;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Kolydart\Laravel\App\Support\MediaAccess;
use Kolydart\Laravel\App\Support\MediaAccessContract;
use Kolydart\Laravel\App\Support\MediaUrl;
use Kolydart\Laravel\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaAccessTest extends TestCase
{
    protected $gate;

    protected $auth;

    protected $log;

    protected function setUp(): void
    {
        parent::setUp();

        // The config() helper reads Container::getInstance(), not $this->app.
        Container::setInstance($this->app);

        $this->app->instance('config', new Repository([
            'kolydart' => [
                'media' => [
                    'public_collections' => ['ck-media'],
                    'gates' => [
                        'App\Customer@attachment' => 'customer_document_show',
                        'App\Customer'            => 'customer_show',
                        'App\Expense'             => 'expense_show',
                        'App\ContentPage'         => null,
                    ],
                ],
            ],
            // isPublic() asks the disk, not only the collection list, so the
            // fixtures need both kinds: one web-served, one not.
            'filesystems' => [
                'disks' => [
                    'public' => ['driver' => 'local', 'url' => 'http://localhost/storage'],
                    'local'  => ['driver' => 'local'],
                    's3'     => ['driver' => 's3'],
                ],
            ],
        ]));

        $this->gate = Mockery::mock(GateContract::class);
        $this->auth = Mockery::mock(AuthFactory::class);
        $this->log = Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing();

        $this->app->instance(GateContract::class, $this->gate);
        $this->app->instance('auth', $this->auth);
        $this->app->instance('log', $this->log);
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);

        parent::tearDown();
    }

    protected function media(string $modelType, string $collection, string $disk = 'public'): Media
    {
        $media = new Media;
        $media->model_type = $modelType;
        $media->collection_name = $collection;
        $media->disk = $disk;

        return $media;
    }

    #[Test]
    public function it_prefers_the_collection_specific_mapping_over_the_model_wide_one()
    {
        $this->gate->shouldReceive('allows')->once()->with('customer_document_show')->andReturn(true);

        $this->assertTrue(
            (new MediaAccess)->allows($this->media('App\Customer', 'attachment'))
        );
    }

    #[Test]
    public function it_falls_back_to_the_model_wide_mapping()
    {
        $this->gate->shouldReceive('allows')->once()->with('customer_show')->andReturn(true);

        $this->assertTrue(
            (new MediaAccess)->allows($this->media('App\Customer', 'avatar'))
        );
    }

    #[Test]
    public function it_denies_when_the_mapped_gate_denies()
    {
        $this->gate->shouldReceive('allows')->once()->with('expense_show')->andReturn(false);

        $this->assertFalse(
            (new MediaAccess)->allows($this->media('App\Expense', 'receipt'))
        );
    }

    #[Test]
    public function a_null_ability_admits_any_authenticated_user()
    {
        $this->auth->shouldReceive('check')->once()->andReturn(true);

        $this->assertTrue(
            (new MediaAccess)->allows($this->media('App\ContentPage', 'ck-media'))
        );
    }

    #[Test]
    public function a_null_ability_still_refuses_a_guest()
    {
        $this->auth->shouldReceive('check')->once()->andReturn(false);

        $this->assertFalse(
            (new MediaAccess)->allows($this->media('App\ContentPage', 'ck-media'))
        );
    }

    /**
     * The failure direction that does not leak: a model nobody mapped is denied,
     * so a collection added after this config was written is private until
     * someone says otherwise.
     */
    #[Test]
    public function an_unmapped_model_is_denied_when_there_is_no_wildcard()
    {
        $this->gate->shouldNotReceive('allows');

        $this->assertFalse(
            (new MediaAccess)->allows($this->media('App\SomethingNew', 'file'))
        );
    }

    #[Test]
    public function a_wildcard_entry_covers_models_that_are_not_named()
    {
        $this->app['config']->set('kolydart.media.gates.*', 'fallback_show');

        $this->gate->shouldReceive('allows')->once()->with('fallback_show')->andReturn(true);

        $this->assertTrue(
            (new MediaAccess)->allows($this->media('App\SomethingNew', 'file'))
        );
    }

    #[Test]
    public function only_the_listed_collections_count_as_public()
    {
        $this->assertTrue(MediaUrl::isPublic($this->media('App\Customer', 'ck-media')));
        $this->assertFalse(MediaUrl::isPublic($this->media('App\Customer', 'attachment')));
    }

    /**
     * The qualified form is what a mixed decision needs: one model's `image` may
     * be display material while the same collection name elsewhere is not.
     */
    #[Test]
    public function a_qualified_public_entry_covers_only_its_own_model()
    {
        $this->app['config']->set('kolydart.media.public_collections', [
            'ck-media',
            'App\Listing@image',
        ]);

        $this->assertTrue(MediaUrl::isPublic($this->media('App\Listing', 'image')));
        $this->assertFalse(MediaUrl::isPublic($this->media('App\Customer', 'image')));
    }

    /**
     * A stale `public_collections` entry is the one thing this class cannot
     * afford to trust: `getUrl()` on a disk with no `url` key answers
     * /storage/{path}, which corresponds to nothing. The disk has the last word,
     * and the media falls through to the protected route, which still resolves
     * the file from the media's own `disk` column.
     */
    #[Test]
    public function a_listed_collection_is_not_public_once_its_disk_stops_being_web_served()
    {
        $this->log->shouldReceive('warning')->once();

        $this->assertFalse(
            MediaUrl::isPublic($this->media('App\ContentPage', 'ck-media', 'local'))
        );
    }

    #[Test]
    public function a_disk_the_application_does_not_declare_is_not_trusted()
    {
        $this->log->shouldReceive('warning')->once();

        $this->assertFalse(
            MediaUrl::isPublic($this->media('App\ContentPage', 'ck-media', 'disk-that-was-removed'))
        );
    }

    /**
     * Only the local driver silently answers a dead URL; a cloud disk builds its
     * URL from its own endpoint, so a missing `url` key there is a default.
     */
    #[Test]
    public function a_cloud_disk_counts_as_web_served_without_a_url_key()
    {
        $this->assertTrue(
            MediaUrl::isPublic($this->media('App\ContentPage', 'ck-media', 's3'))
        );
    }

    /**
     * Conversions may live on a disk of their own, and that is the disk that
     * decides for a conversion URL.
     */
    #[Test]
    public function a_conversion_is_judged_by_the_conversions_disk()
    {
        $media = $this->media('App\ContentPage', 'ck-media');
        $media->conversions_disk = 'local';

        $this->log->shouldReceive('warning')->once();

        $this->assertFalse(MediaUrl::isPublic($media, 'thumb'));
        $this->assertTrue(MediaUrl::isPublic($media));
    }

    /**
     * The controller type-hints the contract, so a replacement resolver needs
     * only the one method — the config and the docs promise exactly that.
     */
    #[Test]
    public function the_default_resolver_satisfies_the_published_contract()
    {
        $this->assertInstanceOf(MediaAccessContract::class, new MediaAccess);
    }
}
