<?php

namespace Kolydart\Laravel\Tests\App\Http\Controllers;

use Illuminate\Auth\Access\Gate;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Contracts\Routing\ResponseFactory as ResponseFactoryContract;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\ResponseFactory;
use Illuminate\Support\Facades\Facade;
use Illuminate\View\View;
use Kolydart\Laravel\App\Http\Controllers\GuideController;
use Kolydart\Laravel\App\Support\Guide\GuideRenderer;
use Kolydart\Laravel\App\Support\Guide\GuideRepository;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * The controller called directly, with a real Gate and real files: what the
 * routes add on top (middleware, the `{slug}` pattern) is the application's.
 */
class GuideControllerTest extends TestCase
{
    private string $dir;

    private Application $app;

    /** @var list<string> abilities the current user holds */
    private array $abilities = ['guide_access'];

    /** @var array<string, mixed> data of the last view rendered */
    private array $viewData = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/kolydart-guide-ctl-'.bin2hex(random_bytes(4));
        mkdir($this->dir.'/images/visible', 0777, true);
        mkdir($this->dir.'/images/hidden', 0777, true);

        file_put_contents($this->dir.'/visible.md', "---\nslug: visible\ntitle: Τμήματα\n---\nΤα τμήματα της σχολής.\n");
        file_put_contents($this->dir.'/hidden.md', "---\nslug: hidden\ntitle: Ρυθμίσεις τμημάτων\nvisibility: admin\n---\nΜόνο για διαχειριστές.\n");
        file_put_contents($this->dir.'/images/visible/a.png', 'png-a');
        file_put_contents($this->dir.'/images/hidden/b.png', 'png-b');
        file_put_contents($this->dir.'/secret.txt', 'outside the images folder');

        $this->app = new Application($this->dir);
        Container::setInstance($this->app);
        Facade::setFacadeApplication($this->app);

        $this->app->instance('config', new Repository([
            'kolydart' => ['guide' => ['gate' => 'guide_access', 'admin_gate' => 'guide_admin']],
        ]));

        $gate = new Gate($this->app, fn () => new \stdClass);

        foreach (['guide_access', 'guide_admin'] as $ability) {
            $gate->define($ability, fn ($user): bool => in_array($ability, $this->abilities, true));
        }

        $this->app->instance(GateContract::class, $gate);

        $views = Mockery::mock(ViewFactory::class);
        $views->shouldReceive('make')->andReturnUsing(function (string $name, array $data): View {
            $this->viewData = $data;

            return Mockery::mock(View::class);
        });
        $this->app->instance(ViewFactory::class, $views);

        $this->app->instance(
            ResponseFactoryContract::class,
            new ResponseFactory($views, Mockery::mock(Redirector::class))
        );
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
        Mockery::close();

        $this->removeDirectory($this->dir);

        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            is_dir($dir.'/'.$entry) ? $this->removeDirectory($dir.'/'.$entry) : unlink($dir.'/'.$entry);
        }

        rmdir($dir);
    }

    private function controller(): GuideController
    {
        return new GuideController(new GuideRepository($this->dir), new GuideRenderer());
    }

    /**
     * The HTTP status the call answers: 200 when it returns, the code of the
     * HttpException abort() throws otherwise.
     */
    private function statusOf(callable $call): int
    {
        try {
            $call();
        } catch (HttpExceptionInterface $e) {
            return $e->getStatusCode();
        }

        return 200;
    }

    #[Test]
    public function an_image_of_a_visible_page_is_served_with_a_restrictive_policy(): void
    {
        $response = $this->controller()->asset('visible/a.png');

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertSame(realpath($this->dir.'/images/visible/a.png'), $response->getFile()->getRealPath());
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('sandbox', (string) $response->headers->get('Content-Security-Policy'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function pathsOutsideTheImages(): array
    {
        return [
            'parent folder' => ['../secret.txt'],
            'through a page folder' => ['visible/../../secret.txt'],
            'null byte' => ["visible/a.png\0.txt"],
            'missing file' => ['visible/missing.png'],
            'a folder, not a file' => ['visible'],
            'absolute path' => ['/etc/passwd'],
        ];
    }

    #[Test]
    #[DataProvider('pathsOutsideTheImages')]
    public function a_path_that_is_not_an_image_file_answers_404(string $path): void
    {
        $this->assertSame(404, $this->statusOf(fn () => $this->controller()->asset($path)));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function pathsToHiddenImages(): array
    {
        return [
            'direct' => ['hidden/b.png'],
            'through a visible folder' => ['visible/../hidden/b.png'],
            'with a dot segment' => ['./hidden/b.png'],
            // Reaches the file on a case-insensitive filesystem (macOS, Windows).
            'upper case' => ['HIDDEN/b.png'],
        ];
    }

    #[Test]
    #[DataProvider('pathsToHiddenImages')]
    public function an_image_of_a_hidden_page_answers_404(string $path): void
    {
        $this->assertSame(404, $this->statusOf(fn () => $this->controller()->asset($path)));
    }

    #[Test]
    public function the_admin_gate_reveals_admin_pages_and_their_images(): void
    {
        $this->abilities[] = 'guide_admin';

        $this->assertSame(200, $this->statusOf(fn () => $this->controller()->asset('hidden/b.png')));
        $this->assertSame(200, $this->statusOf(fn () => $this->controller()->show('hidden')));
    }

    #[Test]
    public function with_no_admin_gate_configured_admin_pages_are_shown_to_nobody(): void
    {
        $this->abilities[] = 'guide_admin';
        config(['kolydart.guide.admin_gate' => null]);

        $this->assertSame(404, $this->statusOf(fn () => $this->controller()->show('hidden')));
        $this->assertSame(404, $this->statusOf(fn () => $this->controller()->asset('hidden/b.png')));
    }

    #[Test]
    public function a_hidden_page_answers_as_a_missing_one(): void
    {
        $this->assertSame(404, $this->statusOf(fn () => $this->controller()->show('hidden')));
        $this->assertSame(404, $this->statusOf(fn () => $this->controller()->show('missing')));
        $this->assertSame(200, $this->statusOf(fn () => $this->controller()->show('visible')));
    }

    #[Test]
    public function without_the_gate_every_action_answers_403(): void
    {
        $this->abilities = [];

        $this->assertSame(403, $this->statusOf(fn () => $this->controller()->index(Request::create('/guide'))));
        $this->assertSame(403, $this->statusOf(fn () => $this->controller()->show('visible')));
        $this->assertSame(403, $this->statusOf(fn () => $this->controller()->asset('visible/a.png')));
        // The gate comes first: a missing page does not answer 404 to someone who may not read the guide.
        $this->assertSame(403, $this->statusOf(fn () => $this->controller()->show('missing')));
    }

    #[Test]
    public function with_no_gate_configured_any_user_past_the_middleware_reads_the_guide(): void
    {
        $this->abilities = [];
        config(['kolydart.guide.gate' => null]);

        $this->assertSame(200, $this->statusOf(fn () => $this->controller()->show('visible')));
    }

    #[Test]
    public function an_image_is_served_even_when_another_page_is_malformed(): void
    {
        file_put_contents($this->dir.'/broken.md', "no front matter\n");

        $this->assertSame(200, $this->statusOf(fn () => $this->controller()->asset('visible/a.png')));
    }

    #[Test]
    public function an_array_query_is_not_a_search(): void
    {
        $this->controller()->index(Request::create('/guide', 'GET', ['q' => ['x']]));

        $this->assertSame('', $this->viewData['query']);
        $this->assertNull($this->viewData['results']);
    }

    #[Test]
    public function search_marks_the_match_and_leaves_out_hidden_pages(): void
    {
        // Both titles match («Τμήματα», «Ρυθμίσεις τμημάτων»); only one may be seen.
        $this->controller()->index(Request::create('/guide', 'GET', ['q' => 'τμημα']));

        $results = $this->viewData['results'];

        $this->assertSame(['visible'], $results->pluck('page.slug')->all());
        $this->assertSame('<mark>Τμήμα</mark>τα', (string) $results->first()['title']);
        $this->assertSame(['visible'], $this->viewData['sections']->flatten()->pluck('slug')->all());
    }
}
