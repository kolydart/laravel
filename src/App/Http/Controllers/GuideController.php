<?php

namespace Kolydart\Laravel\App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\View\View;
use Kolydart\Laravel\App\Support\Guide\GuidePage;
use Kolydart\Laravel\App\Support\Guide\GuideRenderer;
use Kolydart\Laravel\App\Support\Guide\GuideRepository;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The in-app guide: an index with search, one page per `<slug>.md`, and the
 * images those pages reference.
 *
 * Every action asks the same question first (`kolydart.guide.gate`), so an
 * image is never looser than the page that shows it. See src/docs/guide.md.
 */
class GuideController extends Controller
{
    public function __construct(
        private readonly GuideRepository $guide,
        private readonly GuideRenderer $renderer,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorizeGuide();

        // `?q[]=x` arrives as an array; it is not a query.
        $query = $request->query('q', '');
        $query = is_string($query) ? trim($query) : '';

        return view('kolydart::guide.index', [
            'sections' => $this->visibleSections(),
            'sectionLabels' => $this->sectionLabels(),
            'query' => $query,
            'results' => $query === '' ? null : $this->searchResults($query),
        ]);
    }

    public function show(string $slug): View
    {
        $this->authorizeGuide();

        $page = $this->guide->find($slug);

        // A hidden page answers exactly as a missing one does.
        abort_if($page === null || $this->isHidden($page), Response::HTTP_NOT_FOUND);

        $html = $this->renderer->render(
            $page->body,
            fn (string $path): string => route($this->routeName('asset'), ['path' => $path]),
            fn (string $target): string => route($this->routeName('show'), ['slug' => $target]),
        );

        return view('kolydart::guide.show', [
            'page' => $page,
            'html' => new HtmlString($html),
            'sections' => $this->visibleSections(),
            'sectionLabels' => $this->sectionLabels(),
        ]);
    }

    public function asset(string $path): BinaryFileResponse
    {
        $this->authorizeGuide();

        // realpath() throws on a null byte rather than returning false.
        abort_if(str_contains($path, "\0"), Response::HTTP_NOT_FOUND);

        $root = realpath($this->guide->imagesPath());
        $file = $root === false ? false : realpath($root.DIRECTORY_SEPARATOR.$path);

        // realpath() resolves `..` and symlinks, so the prefix check is what
        // keeps the route inside the images folder.
        abort_if(
            $file === false || ! str_starts_with($file, $root.DIRECTORY_SEPARATOR) || ! is_file($file),
            Response::HTTP_NOT_FOUND
        );

        // Images live in images/<slug>/: those of a page the user may not see
        // answer exactly as the page does. The owner is read from the resolved
        // path, never the requested one, so `visible/../hidden/x.png` and
        // `./hidden/x.png` name the folder they actually reach. Lower-cased,
        // because slugs are, and a case-insensitive filesystem would otherwise
        // let `HIDDEN/x.png` through.
        $folder = strtolower(strtok(substr($file, strlen($root) + 1), DIRECTORY_SEPARATOR) ?: '');
        $owner = $this->guide->find($folder);
        abort_if($owner !== null && $this->isHidden($owner), Response::HTTP_NOT_FOUND);

        // Anything in images/ is served, an `.svg` or `.html` included, on the
        // application's origin. Shown through <img> the policy does not apply;
        // opened directly, it keeps a script inside the file from running.
        return response()->file($file, [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox",
        ]);
    }

    /**
     * The route name of an action, under the configured name prefix.
     */
    public static function routeName(string $action): string
    {
        return (config('kolydart.guide.routes.name') ?? 'admin.').'guide.'.$action;
    }

    private function authorizeGuide(): void
    {
        $gate = config('kolydart.guide.gate');

        abort_if($gate !== null && Gate::denies($gate), Response::HTTP_FORBIDDEN);
    }

    /**
     * `visibility: admin` pages need `kolydart.guide.admin_gate`. With no gate
     * configured nobody sees them: an unset option must not publish a page that
     * was marked as restricted.
     */
    private function isHidden(GuidePage $page): bool
    {
        if (! $page->isAdminOnly()) {
            return false;
        }

        $gate = config('kolydart.guide.admin_gate');

        return $gate === null || Gate::denies($gate);
    }

    /**
     * @return Collection<string, Collection<int, GuidePage>>
     */
    private function visibleSections(): Collection
    {
        return $this->guide->bySection()
            ->map(fn (Collection $pages): Collection => $pages->reject(fn (GuidePage $page): bool => $this->isHidden($page))->values())
            ->reject(fn (Collection $pages): bool => $pages->isEmpty());
    }

    /**
     * @return array<string, string>
     */
    private function sectionLabels(): array
    {
        return (array) config('kolydart.guide.sections', []);
    }

    /**
     * @return Collection<int, array{page: GuidePage, title: HtmlString, snippet: HtmlString}>
     */
    private function searchResults(string $query): Collection
    {
        return $this->guide->search($query)
            ->reject(fn (GuidePage $page): bool => $this->isHidden($page))
            ->map(fn (GuidePage $page): array => [
                'page' => $page,
                'title' => $this->highlight($page->title, $query),
                'snippet' => $this->highlight($this->guide->snippet($page, $query) ?? (string) $page->excerpt, $query),
            ])
            ->values();
    }

    /**
     * Escape the text, then mark the first match of the query. Matching is
     * accent-insensitive, as in the search itself (GuideRepository::locate()).
     */
    private function highlight(string $text, string $query): HtmlString
    {
        $text = \Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text;
        $match = GuideRepository::locate($text, $query);

        if ($match === null) {
            return new HtmlString(e($text));
        }

        [$pos, $length] = $match;

        return new HtmlString(
            e(mb_substr($text, 0, $pos))
            .'<mark>'.e(mb_substr($text, $pos, $length)).'</mark>'
            .e(mb_substr($text, $pos + $length))
        );
    }
}
