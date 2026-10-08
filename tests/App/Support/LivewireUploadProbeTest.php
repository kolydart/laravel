<?php

namespace Kolydart\Laravel\Tests\App\Support;

use Exception;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Routing\ResponseFactory as ResponseFactoryContract;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\ResponseFactory;
use Kolydart\Laravel\App\Support\LivewireUploadProbe;
use Kolydart\Laravel\Providers\KolydartServiceProvider;
use Kolydart\Laravel\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Throwable;

/**
 * Livewire is not a dependency of the package, so the exception is played by a fixture class
 * passed in place of the real names. What the real names are is pinned by the first test; that
 * they still match the installed Livewire is the consuming application's probe test's job.
 */
class LivewireUploadProbeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The config() and response() helpers read Container::getInstance(), not $this->app.
        Container::setInstance($this->app);

        $this->app->instance('config', new Repository(['app' => ['debug' => false], 'kolydart' => []]));

        // Only json() is reached, which needs neither collaborator.
        $this->app->instance(ResponseFactoryContract::class, new ResponseFactory(
            Mockery::mock(ViewFactory::class),
            Mockery::mock(Redirector::class)
        ));
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
        Mockery::close();

        parent::tearDown();
    }

    /**
     * Render as JSON, the way a Livewire update request is answered, through the handler's
     * public API. The renderable() spy sees the exception after mapping and returns null, so
     * the default rendering goes on.
     *
     * @return array{0: Response, 1: Throwable|null} the response and the exception it was built from
     */
    private function render(Handler $handler, Throwable $e): array
    {
        $rendered = null;
        $handler->renderable(function (Throwable $e) use (&$rendered) {
            $rendered = $e;
        });
        $handler->shouldRenderJsonWhen(fn () => true);

        return [$handler->render(Request::create('/livewire/update', 'POST'), $e), $rendered];
    }

    private function probe(): FakeMissingFileUploadsTraitException
    {
        return new FakeMissingFileUploadsTraitException(
            'Cannot handle file upload without [Livewire\WithFileUploads] trait on the [secret-form] component class.'
        );
    }

    /** A pin, not a behaviour test: adding, dropping or moving a location has to be deliberate. */
    #[Test]
    public function it_knows_the_exception_in_every_livewire_version(): void
    {
        $this->assertSame([
            'Livewire\Features\SupportFileUploads\MissingFileUploadsTraitException', // 3, 4
            'Livewire\Exceptions\MissingFileUploadsTraitException',                  // 2
        ], LivewireUploadProbe::EXCEPTIONS);
    }

    #[Test]
    public function it_renders_the_exception_as_a_bad_request(): void
    {
        $handler = new Handler($this->app);

        $mapped = LivewireUploadProbe::map($handler, [FakeMissingFileUploadsTraitException::class]);

        $this->assertSame([FakeMissingFileUploadsTraitException::class], $mapped);

        [$response, $rendered] = $this->render($handler, $original = $this->probe());

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(['message' => 'Bad request.'], json_decode($response->getContent(), true));
        $this->assertInstanceOf(BadRequestHttpException::class, $rendered);
        $this->assertSame($original, $rendered->getPrevious());
    }

    /** Livewire's message names the component; an HTTP exception's message reaches the client. */
    #[Test]
    public function the_response_does_not_carry_livewires_message(): void
    {
        $handler = new Handler($this->app);
        LivewireUploadProbe::map($handler, [FakeMissingFileUploadsTraitException::class]);

        [$response] = $this->render($handler, $this->probe());

        $this->assertStringNotContainsString('secret-form', $response->getContent());
    }

    /** A forgotten trait on a real file input throws the same exception; in development it must show. */
    #[Test]
    public function it_stands_aside_while_debugging(): void
    {
        $this->app['config']->set('app.debug', true);

        $reported = [];
        $handler = $this->reportingHandler($reported);
        LivewireUploadProbe::map($handler, [FakeMissingFileUploadsTraitException::class]);

        $handler->report($probe = $this->probe());
        [$response, $rendered] = $this->render($handler, $probe);

        $this->assertSame([$probe], $reported);
        $this->assertSame($probe, $rendered);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString('secret-form', $response->getContent());
    }

    /**
     * report() maps before it asks shouldntReport(), so the mapped exception is what is judged.
     * The callback stands in for Sentry and the log: it sees what would be reported, and its
     * `false` stops the chain before the handler reaches for a logger.
     *
     * @param  list<Throwable>  $reported
     */
    private function reportingHandler(array &$reported): Handler
    {
        $handler = new Handler($this->app);
        $handler->reportable(function (Throwable $e) use (&$reported): bool {
            $reported[] = $e;

            return false;
        });

        return $handler;
    }

    #[Test]
    public function a_mapped_probe_is_not_reported(): void
    {
        $reported = [];
        $handler = $this->reportingHandler($reported);
        LivewireUploadProbe::map($handler, [FakeMissingFileUploadsTraitException::class]);

        $handler->report(new FakeMissingFileUploadsTraitException);

        $this->assertSame([], $reported);
    }

    /** The control for the test above: unmapped, the same exception is reported. */
    #[Test]
    public function an_unmapped_probe_is_reported(): void
    {
        $reported = [];
        $handler = $this->reportingHandler($reported);

        $handler->report($probe = new FakeMissingFileUploadsTraitException);

        $this->assertSame([$probe], $reported);
    }

    #[Test]
    public function it_skips_a_location_absent_from_the_installed_livewire(): void
    {
        $handler = new Handler($this->app);

        $mapped = LivewireUploadProbe::map($handler, [
            'Livewire\Nowhere\MissingFileUploadsTraitException',
            FakeMissingFileUploadsTraitException::class,
        ]);

        $this->assertSame([FakeMissingFileUploadsTraitException::class], $mapped);
    }

    #[Test]
    public function it_leaves_other_exceptions_alone(): void
    {
        $reported = [];
        $handler = $this->reportingHandler($reported);
        LivewireUploadProbe::map($handler, [FakeMissingFileUploadsTraitException::class]);

        $other = new RuntimeException('boom');
        $handler->report($other);
        [$response, $rendered] = $this->render($handler, $other);

        $this->assertSame([$other], $reported);
        $this->assertSame($other, $rendered);
        $this->assertSame(500, $response->getStatusCode());
    }

    #[Test]
    public function it_leaves_a_handler_without_map_alone(): void
    {
        $handler = Mockery::mock(ExceptionHandler::class);

        $this->assertSame([], LivewireUploadProbe::map($handler, [FakeMissingFileUploadsTraitException::class]));
    }

    #[Test]
    public function the_provider_registers_on_the_application_handler(): void
    {
        $this->app->singleton(ExceptionHandler::class, fn ($app) => new Handler($app));

        $calls = [];
        $this->bootProbe(function (ExceptionHandler $handler) use (&$calls) {
            $calls[] = $handler;
        });

        $handler = $this->app->make(ExceptionHandler::class);

        $this->assertSame([$handler], $calls);
    }

    #[Test]
    public function the_provider_can_be_opted_out(): void
    {
        $this->app['config']->set('kolydart.livewire.upload_probe_as_bad_request', false);
        $this->app->singleton(ExceptionHandler::class, fn ($app) => new Handler($app));

        $calls = [];
        $this->bootProbe(function (ExceptionHandler $handler) use (&$calls) {
            $calls[] = $handler;
        });

        $this->app->make(ExceptionHandler::class);

        $this->assertSame([], $calls);
    }

    /**
     * Run the provider's registration with a spy behind it. The callback the provider hands
     * the container is the only thing under test here; what it calls is covered above.
     */
    private function bootProbe(callable $spy): void
    {
        $provider = new class($this->app, $spy) extends KolydartServiceProvider
        {
            public function __construct($app, private $spy)
            {
                parent::__construct($app);
            }

            public function callAfterResolving($name, $callback)
            {
                parent::callAfterResolving($name, function ($handler) use ($callback) {
                    ($this->spy)($handler);

                    return $callback($handler);
                });
            }
        };

        (new ReflectionMethod($provider, 'registerLivewireUploadProbe'))->invoke($provider);
    }
}

class FakeMissingFileUploadsTraitException extends Exception
{
}
