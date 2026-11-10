<?php

namespace Kolydart\Laravel\App\Support\Guide;

use Closure;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\MarkdownConverter;
use RuntimeException;

/**
 * Markdown of a guide page to HTML.
 *
 * The pages are maintainer-authored files in git, never user input, so raw
 * HTML is let through (a `<kbd>`, a `<details>` block). Unsafe link schemes
 * (`javascript:`) are still refused.
 */
class GuideRenderer
{
    private readonly MarkdownConverter $converter;

    public function __construct()
    {
        $environment = new Environment([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
        ]);

        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new AutolinkExtension());
        $environment->addExtension(new StrikethroughExtension());
        $environment->addExtension(new TaskListExtension());

        $this->converter = new MarkdownConverter($environment);
    }

    /**
     * Render a page body.
     *
     * Two kinds of relative address are rewritten, so that a page reads the same
     * in the editor's markdown preview and in the application:
     *
     * - `images/<slug>/<file>` → `$assetUrl('<slug>/<file>')`, the route that
     *   serves the image behind the same authorization as the page;
     * - `<slug>.md` or a bare `<slug>`, with an optional `#fragment`
     *   → `$pageUrl('<slug>')` plus the fragment.
     *
     * Every other address (`https://…`, `/admin/…`, `#…`) is left as written,
     * except a bare `<name>.md` that is not a slug, which throws.
     *
     * @throws RuntimeException
     *
     * @param  Closure(string): string  $assetUrl
     * @param  Closure(string): string  $pageUrl
     */
    public function render(string $markdown, Closure $assetUrl, Closure $pageUrl): string
    {
        $html = $this->converter->convert($markdown)->getContent();

        // Maintainer notes to each other; not for the reader, not even in the source.
        $html = preg_replace('/<!--.*?-->/s', '', $html) ?? $html;

        $html = $this->addHeadingIds($html);

        $html = preg_replace_callback(
            // Not `data-src`: the look-behind keeps the match to the attribute itself.
            '/(?<![\w-])(src|href)="images\/([^"]+)"/i',
            // CommonMark has already percent-encoded the path (`μενού.png`,
            // `a b.png`) and route() encodes it again; decoded first, the file
            // name reaches the route once. route() leaves `%`, `#` and `?`
            // unencoded, though, and those would cut the name short or change
            // it, so they go back in encoded.
            fn (array $m): string => sprintf('%s="%s"', $m[1], e($assetUrl(strtr(
                rawurldecode(html_entity_decode($m[2])),
                ['%' => '%25', '#' => '%23', '?' => '%3F'],
            )))),
            $html
        ) ?? $html;

        // A bare `<name>.md` that cannot be a slug (`README.md`, `Roles.md`)
        // would otherwise stay a relative link to nothing, unnoticed. It throws,
        // as a malformed page does, in the test that renders every page.
        if (preg_match('/(?<![\w-])href="(?![a-z0-9]+(?:-[a-z0-9]+)*\.md(?:#|"))([^"\/:#?]+\.md)(?:#[^"]*)?"/', $html, $bad)) {
            throw new RuntimeException("Guide link [{$bad[1]}] is not a guide page: a page link is '<slug>.md', in lower-case latin letters, digits and dashes.");
        }

        return preg_replace_callback(
            '/(?<![\w-])href="([a-z0-9]+(?:-[a-z0-9]+)*)(?:\.md)?(#[^"]*)?"/',
            fn (array $m): string => sprintf('href="%s%s"', e($pageUrl($m[1])), $m[2] ?? ''),
            $html
        ) ?? $html;
    }

    /**
     * The fragment a heading answers to, built the way GitHub and the common
     * editor previews build it, so a `[…](#ενότητα)` link written against the
     * preview also works in the application: lower case, punctuation dropped,
     * each space a dash, letters of any script (and their accents) kept.
     */
    public static function headingId(string $headingHtml): string
    {
        $text = html_entity_decode(strip_tags($headingHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = preg_replace('/[^\p{L}\p{M}\p{N}\s_-]/u', '', $text) ?? $text;

        return preg_replace('/\s/u', '-', $text) ?? $text;
    }

    /**
     * An `id` on every heading; a repeated heading gets `-1`, `-2`, as on GitHub.
     */
    private function addHeadingIds(string $html): string
    {
        $used = [];

        return preg_replace_callback(
            '/<h([1-6])>(.*?)<\/h\1>/s',
            function (array $m) use (&$used): string {
                $base = self::headingId($m[2]);

                if ($base === '') {
                    return $m[0];
                }

                $id = $base;

                for ($n = 1; isset($used[$id]); $n++) {
                    $id = $base.'-'.$n;
                }

                $used[$id] = true;

                return sprintf('<h%1$s id="%2$s">%3$s</h%1$s>', $m[1], e($id), $m[2]);
            },
            $html
        ) ?? $html;
    }
}
