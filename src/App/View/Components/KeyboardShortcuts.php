<?php

namespace Kolydart\Laravel\App\View\Components;

use Illuminate\View\Component;
use Illuminate\Support\HtmlString;

/**
 * KeyboardShortcuts Component
 *
 * This component provides keyboard shortcut functionality for form submissions.
 * It captures the Cmd+S (Mac) or Ctrl+S (Windows/Linux) keyboard shortcut and
 * automatically clicks the submit button on any form present on the page.
 *
 * Usage:
 * - Add <x-keyboard-shortcuts /> to your layout files where you want
 *   keyboard shortcuts to be available.
 * - Primarily used for saving forms with Cmd+S or Ctrl+S instead of
 *   clicking the submit button.
 *
 * @package Kolydart\Laravel\App\View\Components
 */
class KeyboardShortcuts extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        $script = <<<'HTML'
            <script>
            document.addEventListener('keydown', function(event) {
                // Check for Cmd+S (Mac) or Ctrl+S (Windows/Linux)
                if ((event.metaKey || event.ctrlKey) && event.key === 's') {
                    event.preventDefault(); // Prevent the default save dialog
                    document.querySelector('form button.btn[type="submit"]').click();
                }

                // Check for Cmd+E (Mac) or Ctrl+E (Windows/Linux)
                if ((event.metaKey || event.ctrlKey) && event.key === 'e') {
                    event.preventDefault(); // Prevent the default browser behavior
                    document.querySelector('#gw-edit-button').click(); // Click the specified button
                }
            });
            </script>
            HTML;

        return new HtmlString($script);
    }
}
