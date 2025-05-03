<?php

namespace Kolydart\Laravel\App\View\Components;

use Illuminate\View\Component;

/**
 * SaveButtonDangerToPrimary Component
 *
 * This component modifies the styling of Save buttons by replacing the 'btn-danger' class
 * with 'btn-primary' for buttons containing the text 'Save' or 'Αποθήκευση'.
 *
 * Usage:
 * <x-kolydart::save-button-danger-to-primary />
 *
 * Note: This component injects JavaScript that runs on document ready to modify button classes.
 */
class SaveButtonDangerToPrimary extends Component
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
        return <<<'HTML'
            <script>
                jQuery(document).ready(function($) {
                    $("button.btn.btn-danger:contains('Save')").removeClass("btn-danger").addClass("btn-primary");
                    $("button.btn.btn-danger:contains('Αποθήκευση')").removeClass("btn-danger").addClass("btn-primary");
                });
            </script>
        HTML;
    }
}
