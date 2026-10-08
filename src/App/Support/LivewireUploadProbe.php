<?php

namespace Kolydart\Laravel\App\Support;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Throwable;

/**
 * Answers a file upload request to a component that cannot receive one with 400, not 500.
 *
 * Livewire refuses an upload call on a component without `WithFileUploads` by throwing
 * `MissingFileUploadsTraitException` before anything runs. In production it is almost always
 * a crafted request: a scanner taking any component snapshot from a public page (a search
 * box, the login form) and posting an upload call against it to the Livewire update
 * endpoint. The refusal itself is correct; left unmapped it surfaces as a 500 and is
 * reported as an application error.
 *
 * Mapped to `BadRequestHttpException`, it answers 400 and is not reported: `report()` maps
 * an exception before it decides whether to report it, and HTTP exceptions are on the
 * framework's internal do-not-report list. So the probe stays out of Sentry and the log,
 * provided Sentry is wired through `reportable()` (`Integration::handles()`); an overridden
 * `report()` that captures before calling the parent still sees the unmapped exception.
 *
 * The same exception is also what a developer gets for a real file input on a component
 * that lacks the trait, and Livewire's message names it. So the mapping stands aside while
 * `app.debug` is on, and the original exception, its message and trace are shown and logged
 * as before. The response carries the framework's own fixed `Bad request.` rather than
 * Livewire's message, which names the component class; the original stays reachable
 * through `getPrevious()`.
 *
 * The class has moved between Livewire versions, so every known location is listed. A
 * Livewire version that moves it again makes the mapping a silent no-op; the consuming
 * application's probe test (see README, Security) is what catches that.
 */
class LivewireUploadProbe
{
    /**
     * Every location of the exception, newest first.
     *
     * Livewire 3 and 4: `Features\SupportFileUploads`, thrown for `_startUpload`.
     * Livewire 2: `Exceptions`, thrown for `startUpload`.
     */
    public const EXCEPTIONS = [
        'Livewire\Features\SupportFileUploads\MissingFileUploadsTraitException',
        'Livewire\Exceptions\MissingFileUploadsTraitException',
    ];

    /**
     * Map the installed Livewire's exception, if there is one.
     *
     * `map()` belongs to the framework's concrete handler, not to the contract, so a custom
     * handler implementing only the contract is left alone. Without Livewire no class
     * exists and nothing is registered.
     *
     * @param  list<string>  $classes  the candidates; only tests pass anything but the default
     * @return list<class-string> the classes that were mapped
     */
    public static function map(ExceptionHandler $handler, array $classes = self::EXCEPTIONS): array
    {
        if (! method_exists($handler, 'map')) {
            return [];
        }

        $mapped = [];

        foreach ($classes as $class) {
            if (! class_exists($class)) {
                continue;
            }

            // app.debug is read per exception, not at registration; returning $e
            // leaves the exception unmapped.
            $handler->map($class, fn (Throwable $e) => config('app.debug')
                ? $e
                : new BadRequestHttpException('Bad request.', $e));

            $mapped[] = $class;
        }

        return $mapped;
    }
}
