<?php

namespace Kolydart\Laravel\Tests\App\Support\Guide;

use Kolydart\Laravel\App\Support\Guide\GuidePage;
use Kolydart\Laravel\App\Support\Guide\GuideRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class GuideRepositoryTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/kolydart-guide-'.bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        rmdir($this->dir);

        parent::tearDown();
    }

    private function page(string $file, string $frontMatter, string $body = "Κείμενο.\n"): void
    {
        file_put_contents($this->dir.'/'.$file, "---\n{$frontMatter}\n---\n{$body}");
    }

    private function repository(array $sections = ['intro', 'flows']): GuideRepository
    {
        return new GuideRepository($this->dir, $sections);
    }

    #[Test]
    public function it_reads_the_front_matter_of_each_page(): void
    {
        $this->page('roles.md', "slug: roles\ntitle: Ρόλοι\nsection: intro\norder: 20\nexcerpt: Ποιος κάνει τι.\nsource: [features/auth.md]", "# Σώμα\n");

        $page = $this->repository()->find('roles');

        $this->assertInstanceOf(GuidePage::class, $page);
        $this->assertSame('Ρόλοι', $page->title);
        $this->assertSame('intro', $page->section);
        $this->assertSame(20, $page->order);
        $this->assertSame('Ποιος κάνει τι.', $page->excerpt);
        $this->assertSame('private', $page->visibility);
        $this->assertSame(['features/auth.md'], $page->source);
        $this->assertSame("# Σώμα\n", $page->body);
    }

    #[Test]
    public function it_skips_the_readme_and_returns_null_for_an_unknown_slug(): void
    {
        file_put_contents($this->dir.'/README.md', "# Συμβόλαιο\n");
        $this->page('roles.md', 'slug: roles');

        $repository = $this->repository();

        $this->assertSame(['roles'], $repository->all()->pluck('slug')->all());
        $this->assertNull($repository->find('README'));
        $this->assertNull($repository->find('missing'));
    }

    #[Test]
    public function it_orders_by_configured_section_then_order_then_title(): void
    {
        $this->page('b.md', "slug: b\ntitle: Β\nsection: flows\norder: 1");
        $this->page('a.md', "slug: a\ntitle: Α\nsection: intro\norder: 20");
        $this->page('c.md', "slug: c\ntitle: Γ\nsection: intro\norder: 10");
        $this->page('d.md', "slug: d\ntitle: Δ\nsection: unlisted\norder: 1");

        $repository = $this->repository();

        $this->assertSame(['c', 'a', 'b', 'd'], $repository->all()->pluck('slug')->all());
        $this->assertSame(['intro', 'flows', 'unlisted'], $repository->bySection()->keys()->all());
    }

    #[Test]
    public function it_returns_no_pages_when_the_folder_is_missing(): void
    {
        $this->assertTrue((new GuideRepository($this->dir.'/missing'))->all()->isEmpty());
    }

    #[Test]
    public function a_page_without_front_matter_throws(): void
    {
        file_put_contents($this->dir.'/roles.md', "# Ρόλοι\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing YAML front matter');

        $this->repository()->all();
    }

    #[Test]
    public function a_slug_that_differs_from_the_file_name_throws(): void
    {
        $this->page('roles.md', 'slug: rolos');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("must be named 'rolos.md'");

        $this->repository()->all();
    }

    #[Test]
    public function a_slug_outside_the_url_alphabet_throws(): void
    {
        $this->page('Roles.md', 'slug: Roles');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('lower-case latin letters');

        $this->repository()->all();
    }

    #[Test]
    public function an_unknown_visibility_throws(): void
    {
        $this->page('roles.md', "slug: roles\nvisibility: public");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("visibility 'public'");

        $this->repository()->all();
    }

    #[Test]
    public function invalid_yaml_throws_with_the_file_name(): void
    {
        $this->page('roles.md', "slug: roles\nexcerpt: [unclosed");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('roles.md] has invalid front matter');

        $this->repository()->all();
    }

    #[Test]
    public function search_ignores_case_and_accents_and_ranks_title_matches_first(): void
    {
        $this->page('body.md', "slug: body\ntitle: Πλοήγηση", "Η ΕΓΓΡΑΦΗ ανοίγει από το μενού.\n");
        $this->page('title.md', "slug: title\ntitle: Νέα εγγραφή", "Χωρίς άλλη αναφορά.\n");
        $this->page('none.md', "slug: none\ntitle: Αίθουσες", "Τίποτα σχετικό.\n");

        $hits = $this->repository()->search('εγγραφη');

        $this->assertSame(['title', 'body'], $hits->pluck('slug')->all());
    }

    #[Test]
    public function the_snippet_keeps_the_original_accents_around_the_match(): void
    {
        $this->page('body.md', 'slug: body', "Πρώτα η **καρτέλα** του μαθητή, μετά η εγγραφή.\n");

        $repository = $this->repository();
        $snippet = $repository->snippet($repository->find('body'), 'ΕΓΓΡΑΦΗ');

        $this->assertStringContainsString('μετά η εγγραφή.', $snippet);
        $this->assertStringNotContainsString('**', $snippet);
        $this->assertNull($repository->snippet($repository->find('body'), 'αίθουσα'));
    }

    #[Test]
    public function link_targets_are_neither_searched_nor_shown_in_the_snippet(): void
    {
        $this->page('body.md', 'slug: body', "Βλ. [Ρόλοι](roloi-kai-prosvasi.md), και μετά τα υπόλοιπα.\n");

        $repository = $this->repository();

        $this->assertTrue($repository->search('roloi')->isEmpty());
        $this->assertStringContainsString('Ρόλοι, και μετά', $repository->snippet($repository->find('body'), 'ρολοι'));
    }

    #[Test]
    public function a_decomposed_accent_in_the_source_does_not_shift_the_snippet(): void
    {
        // «εγγραφή» with the tonos as a separate combining mark (NFD).
        $decomposed = "Πρώτα η καρτέλα, μετά η εγγραφ\u{03B7}\u{0301} και τέλος το τμήμα.\n";
        $this->page('body.md', 'slug: body', $decomposed);

        $repository = $this->repository();
        // Radius 0: the slice is exactly the match, so a one-character shift shows.
        $this->assertSame('… τμήμα …', $repository->snippet($repository->find('body'), 'τμημα', 0));
    }

    #[Test]
    public function fold_drops_case_accents_and_final_sigma(): void
    {
        $this->assertSame('αρχη, ι, υ, εγγραφεσ', GuideRepository::fold('Ἀρχή, ΐ, ΰ, Εγγραφές'));
    }

    #[Test]
    public function locate_maps_the_match_back_when_folding_changes_the_length(): void
    {
        // A Hangul syllable folds to two or three letters: 3 characters become 8.
        $this->assertSame([4, 5], GuideRepository::locate('한국어 τμήμα', 'τμημα'));
        // A combining mark with no precomposed form folds to nothing: 2 become 1.
        $this->assertSame([3, 5], GuideRepository::locate("x\u{0301} τμήμα", 'ΤΜΗΜΑ'));
        // A match ending before such a mark takes it along.
        $this->assertSame([1, 2], GuideRepository::locate("ax\u{0301}b", 'x'));
        $this->assertNull(GuideRepository::locate('τμήμα', 'αίθουσα'));
        $this->assertNull(GuideRepository::locate('τμήμα', '  '));
    }

    #[Test]
    public function a_negative_order_sorts_before_a_positive_one(): void
    {
        $this->page('a.md', "slug: a\nsection: intro\norder: -5");
        $this->page('b.md', "slug: b\nsection: intro\norder: -10");
        $this->page('c.md', "slug: c\nsection: intro\norder: 3");
        $this->page('d.md', "slug: d\nsection: intro\norder: 1000000000");

        $this->assertSame(['b', 'a', 'c', 'd'], $this->repository()->all()->pluck('slug')->all());
    }

    #[Test]
    public function a_list_where_a_single_value_belongs_throws_with_the_key(): void
    {
        $this->page('roles.md', "slug: roles\ntitle: [a, b]");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("roles.md] needs a single value as 'title'");

        $this->repository()->all();
    }

    #[Test]
    public function a_nested_source_throws(): void
    {
        $this->page('roles.md', "slug: roles\nsource: [[a, b]]");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("needs 'source' as a path or a list of paths");

        $this->repository()->all();
    }

    #[Test]
    public function an_order_that_is_not_a_whole_number_throws(): void
    {
        $this->page('roles.md', "slug: roles\norder: first");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("needs a whole number as 'order'");

        $this->repository()->all();
    }

    #[Test]
    public function a_single_source_is_a_list_of_one(): void
    {
        $this->page('roles.md', "slug: roles\nsource: features/auth.md");

        $this->assertSame(['features/auth.md'], $this->repository()->find('roles')->source);
    }

    #[Test]
    public function a_byte_order_mark_does_not_hide_the_front_matter(): void
    {
        file_put_contents($this->dir.'/roles.md', "\xEF\xBB\xBF---\nslug: roles\ntitle: Ρόλοι\n---\nΚείμενο.\n");

        $this->assertSame('Ρόλοι', $this->repository()->find('roles')->title);
    }

    #[Test]
    public function find_reads_only_the_page_it_is_asked_for(): void
    {
        $this->page('roles.md', 'slug: roles');
        file_put_contents($this->dir.'/broken.md', "no front matter\n");
        file_put_contents($this->dir.'/README.md', "# Συμβόλαιο\n");

        $repository = $this->repository();

        $this->assertSame('roles', $repository->find('roles')->slug);
        // Only the exact file name counts, even on a case-insensitive filesystem.
        $this->assertNull($repository->find('readme'));
        $this->assertNull($repository->find('../roles'));
        $this->assertNull($repository->find('missing'));
    }
}
