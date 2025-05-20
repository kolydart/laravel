<?php

namespace Kolydart\Laravel\App\View\Components;

use Illuminate\View\Component;

class Signature extends Component
{
    public $developedBy;
    public $copyright;

    /**
     * Create a new component instance.
     *
     * @param bool $copyright Whether to show copyright notice
     * 
     * @example usage in blade views:
     * ```blade
     * <x-kolydart::signature />
     * <x-kolydart::signature :copyright="true" />
     * ```
     */
    public function __construct(bool $copyright = false)
    {
        $this->developedBy = 'developed by <a href="https://www.kolydart.gr">kolydart</a>';
        $this->copyright = $copyright;
    }

    public function render()
    {
        return <<<'HTML'
            <div class="row d-flex d-print-none {{ !$copyright ? 'justify-content-end' : 'justify-content-between' }}">
                <span class="{{ !$copyright ? 'mr-1 opacity-50' : 'ml-1' }}">
                    @if ($copyright)
                        <strong>&copy;&nbsp;</strong>{{ date('Y') }}
                    @endif
                </span>
                <span class="mr-1 opacity-50">{!! $developedBy !!}</span>
            </div>
        HTML;
    }
} 