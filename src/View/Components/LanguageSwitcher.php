<?php

namespace Kolydart\Laravel\View\Components;

use Illuminate\View\Component;

/**
 * Language switcher component for switching between available languages
 *
 * This component automatically detects the current locale and displays a link
 * to switch to the first available alternative language. It requires the
 * 'panel.available_languages' configuration to be set.
 *
 * @example Basic usage in blade views:
 * <x-kolydart::language-switcher />
 *
 * @example With custom CSS class:
 * <x-kolydart::language-switcher class="nav-link" />
 *
 * @example In navigation dropdown:
 * <div class="dropdown-menu">
 *     <x-kolydart::language-switcher />
 * </div>
 *
 * @example In navbar:
 * <ul class="navbar-nav">
 *     <li class="nav-item">
 *         <x-kolydart::language-switcher class="nav-link" />
 *     </li>
 * </ul>
 *
 * Configuration required in config/panel.php:
 * 'available_languages' => [
 *     'en' => 'English',
 *     'el' => 'Greek',
 *     'fr' => 'French',
 * ]
 *
 * Features:
 * - Automatically detects current locale using app()->getLocale()
 * - Shows only languages different from current locale
 * - Generates URL with 'change_language' parameter
 * - Includes Font Awesome language icon (fas fa-language)
 * - Supports customizable CSS classes
 * - Returns empty string when no alternative languages available
 * - Only shows first available alternative language (not all)
 *
 * @param string $class CSS class for the language switcher link (default: 'dropdown-item')
 * @return string HTML for language switcher link or empty string if no alternatives
 */
class LanguageSwitcher extends Component
{
    public $class;
    public $mode;
    public $langLocale;
    public $langName;
    public $show;

    /**
     * Create a new component instance.
     *
     * @param string $class CSS class for the language switcher link
     * @param string $mode Mode to render ('link' or 'dropdown')
     */
    public function __construct($class = 'dropdown-item', $mode = 'link')
    {
        $this->class = $class;
        $this->mode = $mode;
        $this->show = false;
        $this->langLocale = null;
        $this->langName = null;

        // Check if panel configuration exists and has available languages
        if (!config('panel.available_languages')) {
            return;
        }

        // Only show if there are multiple languages
        if (count(config('panel.available_languages')) > 1) {
            $this->show = true;
        }

        // For link mode, we need exactly one alternative language
        if ($this->mode === 'link') {
            foreach(config('panel.available_languages') as $langLocale => $langName) {
                if (app()->getLocale() != $langLocale) {
                    $this->langLocale = $langLocale;
                    $this->langName = $langName;
                    break; // Only show the first available language option
                }
            }
        }
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render()
    {
        if (!$this->show) {
            return '';
        }

        if ($this->mode === 'dropdown') {
            return <<<'BLADE'
            <li class="nav-item dropdown">
                <a class="nav-link" data-toggle="dropdown" href="#">
                    {{ strtoupper(app()->getLocale()) }}
                </a>
                <div class="dropdown-menu dropdown-menu-right">
                    @foreach(config('panel.available_languages') as $locale => $name)
                        <a class="{{ $class }}" href="{{ request()->fullUrlWithQuery(['change_language' => $locale]) }}">{{ strtoupper($locale) }} ({{ $name }})</a>
                    @endforeach
                </div>
            </li>
BLADE;
        }

        return <<<'BLADE'
            <a class="{{ $class }}" href="{{ request()->fullUrlWithQuery(['change_language' => $langLocale]) }}">
                <i class="fas fa-language"></i> {{ $langName }}
            </a>
BLADE;
    }
}