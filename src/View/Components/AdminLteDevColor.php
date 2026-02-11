<?php

namespace Kolydart\Laravel\View\Components;

use Illuminate\View\Component;

/**
 * AdminLteDevColor Component
 *
 * This component injects JavaScript to modify the AdminLTE sidebar color in the local environment.
 *
 * Usage:
 * <x-kolydart::admin-lte-dev-color />
 *
 * Note: This component only changes the sidebar color if the application environment is 'local'.
 */
class AdminLteDevColor extends Component
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
        $isLocal = app()->environment() === 'local';

        if ($isLocal) {
            return <<<'HTML'
                <script>
                    $(document).ready(function() {
                        $('.sidebar-dark-primary').css('background-color','#001FA1');
                    });
                </script>
            HTML;

        }
    }
}
