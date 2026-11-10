<?php

namespace Kolydart\Laravel\App\Support\Guide;

use Illuminate\Support\Collection;
use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads the guide pages off the filesystem, on every request.
 *
 * There is no build step and no database table: a page is a `<slug>.md` file
 * in `kolydart.guide.path`, versioned in git next to the code it documents.
 * `README.md` in the same folder is the authoring contract and is skipped.
 *
 * A malformed page throws rather than disappearing. A page that silently drops
 * out of the menu is a gap nobody notices; an exception is caught by the test
 * that renders every page, before the page is deployed.
 */
class GuideRepository
{
    /** A slug is the URL segment and the file name, so it stays in this alphabet. */
    public const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public const VISIBILITIES = ['private', 'admin'];

    private readonly string $basePath;

    /** @var array<string, int> section key => position */
    private readonly array $sectionOrder;

    /** @var Collection<int, GuidePage>|null */
    private ?Collection $memo = null;

    /**
     * @param  list<string>  $sections  Section keys in menu order. A page in a
     *                                   section that is not listed sorts last.
     */
    public function __construct(string $basePath, array $sections = [])
    {
        $this->basePath = rtrim($basePath, '/');
        $this->sectionOrder = array_flip(array_values($sections));
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function imagesPath(): string
    {
        return $this->basePath.'/images';
    }

    /**
     * All pages, ordered by section, then `order`, then title.
     *
     * @return Collection<int, GuidePage>
     */
    public function all(): Collection
    {
        return $this->memo ??= $this->scan()
            ->sort(fn (GuidePage $a, GuidePage $b): int => (
                [$this->sectionPosition($a->section), $a->order] <=> [$this->sectionPosition($b->section), $b->order]
            ) ?: strcmp($a->title, $b->title))
            ->values();
    }

    /**
     * Pages grouped by section, sections in configured order.
     *
     * @return Collection<string, Collection<int, GuidePage>>
     */
    public function bySection(): Collection
    {
        return $this->all()
            ->groupBy(fn (GuidePage $page): string => $page->section)
            ->sortBy(fn (Collection $pages, string $section): int => $this->sectionPosition($section));
    }

    /**
     * One page by slug. Until all() has run, only that page's file is read:
     * an image request asks for the page that owns it, and must neither parse
     * every other page nor fail because some other page is malformed.
     */
    public function find(string $slug): ?GuidePage
    {
        if ($this->memo !== null) {
            return $this->memo->firstWhere('slug', $slug);
        }

        if (! preg_match(self::SLUG_PATTERN, $slug) || ! is_dir($this->basePath)) {
            return null;
        }

        // The exact name from the listing, not is_file() alone: on a
        // case-insensitive filesystem `readme` would otherwise reach README.md.
        $name = $slug.'.md';

        return in_array($name, scandir($this->basePath) ?: [], true)
            ? $this->parse($this->basePath.'/'.$name)
            : null;
    }

    /**
     * Full-text search over title, excerpt and body, case- and accent-insensitive
     * (so «εγγραφη» finds «εγγραφή»). Title matches rank highest, then excerpt,
     * then the number of matches in the body.
     *
     * Visibility is not applied here; the caller removes what the user may not see.
     *
     * @return Collection<int, GuidePage>
     */
    public function search(string $query): Collection
    {
        $needle = self::fold(trim($query));

        if ($needle === '') {
            return collect();
        }

        return $this->all()
            ->map(fn (GuidePage $page): array => ['page' => $page, 'score' => $this->relevance($page, $needle)])
            ->filter(fn (array $hit): bool => $hit['score'] > 0)
            ->sortByDesc('score')
            ->pluck('page')
            ->values();
    }

    /**
     * Plain text around the first match in the body, or null when the body does
     * not contain the query (it matched only the title or the excerpt).
     */
    public function snippet(GuidePage $page, string $query, int $radius = 90): ?string
    {
        $plain = $this->plainText($page->body);
        $match = self::locate($plain, $query);

        if ($match === null) {
            return null;
        }

        [$pos, $length] = $match;
        $start = max(0, $pos - $radius);
        $slice = trim(mb_substr($plain, $start, $length + $pos - $start + $radius));

        return ($start > 0 ? '… ' : '').$slice.' …';
    }

    /**
     * Lower-case, strip accents and fold final sigma.
     *
     * Not one character to one in general (a Hangul syllable decomposes into
     * several letters, a combining mark that does not compose disappears), so
     * a position in the folded text is not a position in the original: use
     * locate() for that. \Normalizer is always there: without the intl
     * extension, symfony/polyfill-intl-normalizer comes with Laravel.
     */
    public static function fold(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $decomposed = \Normalizer::normalize($text, \Normalizer::FORM_D);

        if ($decomposed !== false) {
            $text = preg_replace('/\p{Mn}+/u', '', $decomposed) ?? $text;
        }

        return str_replace('ς', 'σ', $text);
    }

    /**
     * The first match of the query in $text, folded as the search folds, as
     * [start, length] in characters of $text; null when there is none.
     *
     * Each character is folded on its own, so every folded character knows the
     * original one it came from, whatever folding does to the length. A match
     * that ends just before characters folding to nothing (a stray combining
     * mark) takes them along, so a highlight never splits a letter from its
     * accent.
     *
     * @return array{0: int, 1: int}|null
     */
    public static function locate(string $text, string $query): ?array
    {
        $needle = self::fold(trim($query));

        if ($needle === '') {
            return null;
        }

        $chars = mb_str_split($text);
        $folded = '';
        $origin = [];
        $empty = [];

        foreach ($chars as $i => $char) {
            $piece = self::fold($char);
            $folded .= $piece;
            $empty[$i] = $piece === '';
            array_push($origin, ...array_fill(0, mb_strlen($piece), $i));
        }

        $pos = mb_strpos($folded, $needle);

        if ($pos === false) {
            return null;
        }

        $start = $origin[$pos];
        $end = $origin[$pos + mb_strlen($needle) - 1] + 1;

        while ($end < count($chars) && $empty[$end]) {
            $end++;
        }

        return [$start, $end - $start];
    }

    private function sectionPosition(string $section): int
    {
        return $this->sectionOrder[$section] ?? 999;
    }

    private function relevance(GuidePage $page, string $needle): int
    {
        $score = 0;

        if (str_contains(self::fold($page->title), $needle)) {
            $score += 10;
        }

        if ($page->excerpt !== null && str_contains(self::fold($page->excerpt), $needle)) {
            $score += 4;
        }

        return $score + mb_substr_count(self::fold($this->plainText($page->body)), $needle);
    }

    /**
     * Markdown reduced to readable text, for matching and snippets. Composed
     * (NFC), so that an accent written as a separate mark stays with its
     * letter in the snippet.
     */
    private function plainText(string $body): string
    {
        $text = preg_replace('/```.*?```/s', ' ', $body) ?? $body;
        $text = preg_replace('/<!--.*?-->/s', ' ', $text) ?? $text;
        $text = strip_tags($text);
        $text = preg_replace('/!\[[^\]]*\]\([^)]*\)/u', ' ', $text) ?? $text;
        $text = preg_replace('/\]\([^)]*\)/u', '', $text) ?? $text;
        $text = preg_replace('/[#>*_`\[\]|]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = \Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text;

        return trim($text);
    }

    /**
     * @return Collection<int, GuidePage>
     */
    private function scan(): Collection
    {
        if (! is_dir($this->basePath)) {
            return collect();
        }

        return collect(glob($this->basePath.'/*.md') ?: [])
            ->reject(fn (string $file): bool => basename($file) === 'README.md')
            ->map(fn (string $file): GuidePage => $this->parse($file))
            ->values();
    }

    private function parse(string $file): GuidePage
    {
        // A byte-order mark, as some Windows editors write, would hide the
        // opening `---` from the pattern below.
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', (string) file_get_contents($file)) ?? '';

        if (! preg_match('/^---\R(.*?)\R---\R?(.*)$/s', $raw, $matches)) {
            throw new RuntimeException("Guide page [{$file}] is missing YAML front matter.");
        }

        try {
            $meta = Yaml::parse($matches[1]);
        } catch (ParseException $e) {
            throw new RuntimeException("Guide page [{$file}] has invalid front matter: {$e->getMessage()}", 0, $e);
        }

        $meta = is_array($meta) ? $meta : [];
        $slug = $meta['slug'] ?? null;

        if (! is_string($slug) || ! preg_match(self::SLUG_PATTERN, $slug)) {
            throw new RuntimeException("Guide page [{$file}] needs a 'slug' of lower-case latin letters, digits and dashes.");
        }

        // The file name is the slug: no two files can then claim one address,
        // and the images folder (images/<slug>/) is found from either.
        if ($slug !== basename($file, '.md')) {
            throw new RuntimeException("Guide page [{$file}] declares slug '{$slug}'; the file must be named '{$slug}.md'.");
        }

        $visibility = $this->scalar($meta, 'visibility', $file) ?? 'private';

        if (! in_array($visibility, self::VISIBILITIES, true)) {
            throw new RuntimeException("Guide page [{$file}] has visibility '{$visibility}'; expected one of: ".implode(', ', self::VISIBILITIES).'.');
        }

        $order = $meta['order'] ?? 100;

        if (! is_int($order)) {
            throw new RuntimeException("Guide page [{$file}] needs a whole number as 'order'.");
        }

        $source = $meta['source'] ?? [];
        $source = is_scalar($source) ? [$source] : $source;

        if (! is_array($source) || array_filter($source, fn (mixed $item): bool => ! is_scalar($item)) !== []) {
            throw new RuntimeException("Guide page [{$file}] needs 'source' as a path or a list of paths.");
        }

        return new GuidePage(
            slug: $slug,
            title: $this->scalar($meta, 'title', $file) ?? $slug,
            section: $this->scalar($meta, 'section', $file) ?? 'general',
            order: $order,
            excerpt: $this->scalar($meta, 'excerpt', $file),
            visibility: $visibility,
            source: array_values(array_map('strval', $source)),
            body: $matches[2],
        );
    }

    /**
     * A front matter value that must be a single value, as a string; null when
     * absent. A list or a map throws with the file and the key, rather than
     * reaching a string cast as an "Array to string conversion" warning.
     *
     * @param  array<mixed>  $meta
     */
    private function scalar(array $meta, string $key, string $file): ?string
    {
        $value = $meta[$key] ?? null;

        if ($value !== null && ! is_scalar($value)) {
            throw new RuntimeException("Guide page [{$file}] needs a single value as '{$key}', not a list or a map.");
        }

        return $value === null ? null : (string) $value;
    }
}
