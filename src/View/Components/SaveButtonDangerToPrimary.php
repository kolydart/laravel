<?php

namespace Kolydart\Laravel\View\Components;

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
                document.addEventListener('DOMContentLoaded', function() {
                    var buttons = document.querySelectorAll('button.btn.btn-danger');
                    Array.prototype.forEach.call(buttons, function(btn) {
                        var text = btn.innerText || btn.textContent;
                        text = text.trim();
                        if (text === 'Save' || text === 'Αποθήκευση') {
                            btn.classList.remove('btn-danger');
                            btn.classList.add('btn-primary');
                        }
                    });
                });
            </script>
        HTML;
    }
}
