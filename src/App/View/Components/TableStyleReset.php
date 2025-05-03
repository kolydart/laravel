<?php

namespace Kolydart\Laravel\App\View\Components;

use Illuminate\View\Component;

/**
 * Bootstrap Table Style Reset Component
 *
 * This component removes the default Bootstrap table-striped and table-bordered classes
 * from all tables on the page. It can be included in layouts to standardize table styling.
 *
 * Usage:
 * <x-kolydart::table-style-reset />
 *
 * Note: This component injects JavaScript that runs on document ready to modify table classes.
 */
class TableStyleReset extends Component
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
        return <<<'BLADE'
<script>
    jQuery(document).ready(function($) {
        $("table.table-striped")
            .removeClass('table-striped')
            .removeClass('table-bordered')
            // .addClass('table-responsive') /** breaks scroll right */
            // .addClass('table-sm')
            // .addClass('table-borderless')
            // .addClass('table-hover')
            ;

        });
</script>
BLADE;
    }
}
