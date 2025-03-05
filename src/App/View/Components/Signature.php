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
     * ```
     */
    public function __construct(bool $copyright = false)
    {
        $this->developedBy = 'developed by <a href="https://www.kolydart.gr">kolydart</a>';
        $this->copyright = $copyright;
    }

    public function render()
    {
        return <<<'blade'
            <div class="text-center text-muted">
                {!! $developedBy !!}
                @if($copyright)
                    <br>
                    &copy; {{ date('Y') }} - All rights reserved
                @endif
            </div>
        blade;
    }
} 