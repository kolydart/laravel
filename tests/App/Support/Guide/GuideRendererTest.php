<?php

namespace Kolydart\Laravel\Tests\App\Support\Guide;

use Kolydart\Laravel\App\Support\Guide\GuideRenderer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class GuideRendererTest extends TestCase
{
    private function render(string $markdown): string
    {
        return (new GuideRenderer())->render(
            $markdown,
            fn (string $path): string => '/asset/'.$path,
            fn (string $slug): string => '/page/'.$slug,
        );
    }

    #[Test]
    public function it_renders_markdown_and_tables(): void
    {
        $html = $this->render("## Τίτλος\n\n| Α | Β |\n|---|---|\n| 1 | 2 |\n");

        $this->assertStringContainsString('<h2 id="τίτλος">Τίτλος</h2>', $html);
        $this->assertStringContainsString('<table>', $html);
    }

    #[Test]
    public function relative_images_point_at_the_asset_route(): void
    {
        $html = $this->render('![Μενού](images/ploigisi/menu.png)');

        $this->assertStringContainsString('src="/asset/ploigisi/menu.png"', $html);
    }

    #[Test]
    public function an_image_name_with_greek_letters_or_spaces_reaches_the_asset_route_decoded(): void
    {
        $html = $this->render("![Μενού](images/ploigisi/μενού.png)\n\n![Β](<images/ploigisi/a b.png>)");

        $this->assertStringContainsString('src="/asset/ploigisi/μενού.png"', $html);
        $this->assertStringContainsString('src="/asset/ploigisi/a b.png"', $html);
    }

    #[Test]
    public function characters_the_route_leaves_unencoded_reach_it_encoded(): void
    {
        // On disk: `a#b.png`, `a?b.png`, `a%b.png`.
        $html = $this->render("![](images/x/a%23b.png)\n\n![](images/x/a%3Fb.png)\n\n![](images/x/a%25b.png)");

        $this->assertStringContainsString('src="/asset/x/a%23b.png"', $html);
        $this->assertStringContainsString('src="/asset/x/a%3Fb.png"', $html);
        $this->assertStringContainsString('src="/asset/x/a%25b.png"', $html);
    }

    #[Test]
    public function only_src_and_href_attributes_are_rewritten(): void
    {
        $html = $this->render('<img data-src="images/x/y.png" src="images/x/y.png"> <a data-href="roloi" href="roloi">x</a>');

        $this->assertStringContainsString('data-src="images/x/y.png"', $html);
        $this->assertStringContainsString(' src="/asset/x/y.png"', $html);
        $this->assertStringContainsString('data-href="roloi"', $html);
        $this->assertStringContainsString(' href="/page/roloi"', $html);
    }

    #[Test]
    public function a_markdown_link_that_cannot_be_a_page_throws(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Guide link [README.md] is not a guide page');

        $this->render('[Συμβόλαιο](README.md)');
    }

    #[Test]
    public function links_to_other_pages_point_at_the_page_route_with_or_without_md(): void
    {
        $html = $this->render('[Ρόλοι](roloi.md#export) και [Έννοιες](vasikes-ennoies).');

        $this->assertStringContainsString('href="/page/roloi#export"', $html);
        $this->assertStringContainsString('href="/page/vasikes-ennoies"', $html);
    }

    #[Test]
    public function other_addresses_are_left_as_written(): void
    {
        $html = $this->render('[Α](https://example.org/a) [Β](/admin/people) [Γ](#top) [Δ](../features/x.md)');

        $this->assertStringContainsString('href="https://example.org/a"', $html);
        $this->assertStringContainsString('href="/admin/people"', $html);
        $this->assertStringContainsString('href="#top"', $html);
        $this->assertStringContainsString('href="../features/x.md"', $html);
    }

    #[Test]
    public function headings_get_ids_that_match_editor_preview_fragments(): void
    {
        $html = $this->render("## Εγγραφή και τμήμα\n\n## Ρόλοι: ποιος κάνει τι;\n\n## Εγγραφή και τμήμα\n");

        $this->assertStringContainsString('<h2 id="εγγραφή-και-τμήμα">', $html);
        $this->assertStringContainsString('<h2 id="ρόλοι-ποιος-κάνει-τι">', $html);
        $this->assertStringContainsString('<h2 id="εγγραφή-και-τμήμα-1">', $html);
    }

    #[Test]
    public function html_comments_and_unsafe_links_do_not_reach_the_page(): void
    {
        $html = $this->render("<!-- σημείωση συντηρητή -->\n\n[x](javascript:alert(1))");

        $this->assertStringNotContainsString('σημείωση συντηρητή', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }
}
