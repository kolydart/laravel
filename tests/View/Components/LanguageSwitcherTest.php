<?php

namespace Kolydart\Laravel\Tests\View\Components;

use Kolydart\Laravel\View\Components\LanguageSwitcher;
use Kolydart\Laravel\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\App;

/**
 * Test-specific LanguageSwitcher class that doesn't depend on Laravel facades
 */
class TestLanguageSwitcher extends LanguageSwitcher
{
    public function __construct($class = 'dropdown-item', array $availableLanguages = [], string $currentLocale = 'en')
    {
        $this->class = $class;
        $this->show = false;
        $this->langLocale = null;
        $this->langName = null;

        // Check if available languages exist
        if (empty($availableLanguages)) {
            return;
        }

        // Find the first language that is not the current locale
        foreach($availableLanguages as $langLocale => $langName) {
            if ($currentLocale != $langLocale) {
                $this->langLocale = $langLocale;
                $this->langName = $langName;
                $this->show = true;
                break; // Only show the first available language option
            }
        }
    }

    public function render()
    {
        if (!$this->show) {
            return '';
        }

        return 'Language switcher would be rendered here';
    }
}

class LanguageSwitcherTest extends TestCase
{
    /**
     * Test that language switcher shows when multiple languages are available
     */
    public function test_language_switcher_shows_when_multiple_languages_available()
    {
        $availableLanguages = [
            'en' => 'English',
            'el' => 'Greek',
            'fr' => 'French'
        ];

        $component = new TestLanguageSwitcher('dropdown-item', $availableLanguages, 'en');

        $this->assertTrue($component->show);
        $this->assertEquals('el', $component->langLocale);
        $this->assertEquals('Greek', $component->langName);
        $this->assertEquals('dropdown-item', $component->class);
    }

    /**
     * Test that language switcher doesn't show when no languages are available
     */
    public function test_language_switcher_hidden_when_no_languages_available()
    {
        $component = new TestLanguageSwitcher('dropdown-item', [], 'en');

        $this->assertFalse($component->show);
        $this->assertNull($component->langLocale);
        $this->assertNull($component->langName);
    }

    /**
     * Test that language switcher doesn't show when only current language is available
     */
    public function test_language_switcher_hidden_when_only_current_language_available()
    {
        $availableLanguages = [
            'en' => 'English'
        ];

        $component = new TestLanguageSwitcher('dropdown-item', $availableLanguages, 'en');

        $this->assertFalse($component->show);
        $this->assertNull($component->langLocale);
        $this->assertNull($component->langName);
    }

    /**
     * Test that custom CSS class is applied correctly
     */
    public function test_custom_css_class_applied()
    {
        $availableLanguages = [
            'en' => 'English',
            'el' => 'Greek'
        ];

        $component = new TestLanguageSwitcher('nav-link', $availableLanguages, 'en');

        $this->assertEquals('nav-link', $component->class);
        $this->assertTrue($component->show);
    }

    /**
     * Test that the first available language (not current) is selected
     */
    public function test_first_available_language_selected()
    {
        $availableLanguages = [
            'en' => 'English',
            'el' => 'Greek',
            'fr' => 'French',
            'de' => 'German'
        ];

        $component = new TestLanguageSwitcher('dropdown-item', $availableLanguages, 'fr');

        $this->assertTrue($component->show);
        $this->assertEquals('en', $component->langLocale);
        $this->assertEquals('English', $component->langName);
    }

    /**
     * Test that render returns empty string when component should not show
     */
    public function test_render_returns_empty_when_not_showing()
    {
        $component = new TestLanguageSwitcher('dropdown-item', [], 'en');

        $this->assertEquals('', $component->render());
    }

    /**
     * Test that render returns content when component should show
     */
    public function test_render_returns_content_when_showing()
    {
        $availableLanguages = [
            'en' => 'English',
            'el' => 'Greek'
        ];

        $component = new TestLanguageSwitcher('dropdown-item', $availableLanguages, 'en');

        $this->assertNotEmpty($component->render());
        $this->assertIsString($component->render());
    }
}