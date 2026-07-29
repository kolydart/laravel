<?php

namespace Kolydart\Laravel\Tests\App\Testing;

use Illuminate\Support\Collection;
use Kolydart\Laravel\App\Testing\InteractsWithSmokeCrawler;
use Kolydart\Laravel\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Contract coverage for the console-log filter behind the smoke crawler.
 *
 * `significantConsoleErrors()` decides whether a crawled route is reported as a
 * failure, so a mistake here either hides real breakage from every consuming
 * project or fails builds on third-party noise. The filter is pure string work,
 * which is why it is extracted out of `visitUri()` and asserted directly here
 * rather than through a browser.
 */
class InteractsWithSmokeCrawlerTest extends TestCase
{
    /** Exposes the protected filter, with no project-specific patterns. */
    private function crawler(array $projectPatterns = []): object
    {
        return new class($projectPatterns)
        {
            use InteractsWithSmokeCrawler;

            public function __construct(protected array $ignoredConsolePatterns) {}

            public function filter(array $entries): Collection
            {
                return $this->significantConsoleErrors($entries);
            }
        };
    }

    /** @return array{level: string, message: string} */
    private function severe(string $message): array
    {
        return ['level' => 'SEVERE', 'message' => $message];
    }

    #[Test]
    public function a_genuine_severe_error_is_reported(): void
    {
        $logs = $this->crawler()->filter([
            $this->severe('http://app.test/js/app.js 42 Uncaught TypeError: x is not a function'),
        ]);

        $this->assertCount(1, $logs);
    }

    #[Test]
    public function entries_below_severe_are_dropped(): void
    {
        $logs = $this->crawler()->filter([
            ['level' => 'WARNING', 'message' => 'Deprecated API usage'],
            ['level' => 'INFO', 'message' => 'Livewire hydrated'],
        ]);

        $this->assertCount(0, $logs);
    }

    /**
     * The regression this filter exists for: a transient Google Fonts outage
     * must not fail whichever route happened to be loading at the time.
     */
    #[Test]
    public function failed_requests_to_third_party_asset_hosts_are_ignored(): void
    {
        $logs = $this->crawler()->filter([
            $this->severe('https://fonts.gstatic.com/s/sourcesanspro/v23/6xK1.woff2 - Failed to load resource: net::ERR_CONNECTION_CLOSED'),
            $this->severe('https://fonts.googleapis.com/css?family=Source+Sans+Pro - Failed to load resource'),
            $this->severe('https://www.googletagmanager.com/gtag/js - Failed to load resource'),
        ]);

        $this->assertCount(0, $logs);
    }

    /**
     * The counterpart the host list must NOT swallow: an app whose own CSP blocks
     * its own font stylesheet mentions the third-party host, but this is an
     * application misconfiguration, not internet weather. Suppressing it would
     * leave fonts broken site-wide with a green suite.
     */
    #[Test]
    public function a_csp_refusal_naming_a_third_party_host_is_still_reported(): void
    {
        $logs = $this->crawler()->filter([
            $this->severe("Refused to load the stylesheet 'https://fonts.googleapis.com/css2?family=Inter' because it violates the following Content Security Policy directive: \"style-src 'self'\"."),
        ]);

        $this->assertCount(1, $logs);
    }

    /** Same rule from the other side: an app error whose stack quotes the host. */
    #[Test]
    public function an_app_error_quoting_a_third_party_host_is_still_reported(): void
    {
        $logs = $this->crawler()->filter([
            $this->severe('Uncaught TypeError: dataLayer.push is not a function at https://www.googletagmanager.com/gtag/js:1:120'),
        ]);

        $this->assertCount(1, $logs);
    }

    /** The application's own assets are never covered by the host list. */
    #[Test]
    public function a_failed_request_to_the_app_itself_is_reported(): void
    {
        $logs = $this->crawler()->filter([
            $this->severe('http://app.test/js/app.js - Failed to load resource: net::ERR_CONNECTION_CLOSED'),
        ]);

        $this->assertCount(1, $logs);
    }

    #[Test]
    public function browser_chrome_artefacts_are_ignored_by_default(): void
    {
        $logs = $this->crawler()->filter([
            $this->severe('http://app.test/favicon.ico - Failed to load resource: 404'),
            $this->severe('chrome-extension://abc/inject.js - denied'),
            $this->severe('DevTools failed to load source map'),
        ]);

        $this->assertCount(0, $logs);
    }

    /** Unlike the host list, these drop the entry wherever they match. */
    #[Test]
    public function browser_chrome_artefacts_are_ignored_without_a_load_failure(): void
    {
        $logs = $this->crawler()->filter([
            $this->severe('chrome-extension://abc/inject.js 1 Uncaught TypeError'),
        ]);

        $this->assertCount(0, $logs);
    }

    #[Test]
    public function project_patterns_are_applied_on_top_of_the_defaults(): void
    {
        $logs = $this->crawler(['/conversions/'])->filter([
            $this->severe('http://app.test/storage/1/conversions/thumb.jpg - 404'),
            $this->severe('http://app.test/favicon.ico - 404'),
            $this->severe('http://app.test/js/app.js 12 Uncaught ReferenceError'),
        ]);

        $this->assertCount(1, $logs);
        $this->assertStringContainsString('ReferenceError', $logs->first()['message']);
    }

    /**
     * A project re-listing a shared default must not produce a duplicated
     * pattern list, and must still filter correctly.
     */
    #[Test]
    public function re_listing_a_default_pattern_is_harmless(): void
    {
        $logs = $this->crawler(['favicon.ico'])->filter([
            $this->severe('http://app.test/favicon.ico - 404'),
        ]);

        $this->assertCount(0, $logs);
    }

    /** WebDriver has been seen to return entries without a message key. */
    #[Test]
    public function an_entry_without_a_message_is_tolerated_and_reported(): void
    {
        $logs = $this->crawler()->filter([
            ['level' => 'SEVERE'],
        ]);

        $this->assertCount(1, $logs);
    }

    /** Pins the silent-drop direction: no level means no evidence of severity. */
    #[Test]
    public function an_entry_without_a_level_is_dropped(): void
    {
        $logs = $this->crawler()->filter([
            ['message' => 'Uncaught TypeError: x is not a function'],
        ]);

        $this->assertCount(0, $logs);
    }

    #[Test]
    public function the_returned_collection_is_reindexed(): void
    {
        $logs = $this->crawler()->filter([
            $this->severe('http://app.test/favicon.ico - 404'),
            $this->severe('http://app.test/js/app.js 12 Uncaught ReferenceError'),
        ]);

        $this->assertSame([0], $logs->keys()->all());
    }
}
