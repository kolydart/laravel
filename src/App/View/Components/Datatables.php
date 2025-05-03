<?php

namespace Kolydart\Laravel\App\View\Components;

use Illuminate\View\Component;

/**
 * Datatables Component
 *
 * This component auto-focuses the DataTables search input and hides the bulk delete button on index pages.
 *
 * Usage:
 * <x-kolydart::datatables />
 *
 * Note: This component injects JavaScript that runs on document ready to modify DataTables UI.
 * Do not register globally unless needed.
 */
class Datatables extends Component
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
            @if (str(Route::currentRouteAction())->afterLast('@')->toString() == 'index')
                <script>
                    // Extend DataTables defaults to auto-focus search input and hide bulk delete button
                    $.extend(true, $.fn.dataTable.defaults, {
                        initComplete: function() {
                            $('#DataTables_Table_0_filter > label > input').eq(0).focus();
                            $('.actions > .dt-buttons > .btn-danger').hide();
                        },
                    });
                </script>
            @endif
        BLADE;
    }
}
