<?php

namespace Kolydart\Laravel\App\View\Components;

use Illuminate\View\Component;

class FormFieldsSize extends Component
{
    public $class;

    /**
     *
     * @param string $class The CSS class to apply to the form fields.
     *
     *
     * <x-kolydart::form-fields-size />
     *
     * @example usage in blade views:
     * @section('scripts')
     *
     *   <x-kolydart::form-fields-size />
     *
     * @endsection
     *
     * This will render as:
     * ```html
     * <form class="row mx-3">
     *     <div class="form-group col-md-6">
     *         <input type="text" name="name">
     *     </div>
     *     <div class="form-group col-md-6">
     *         <input type="email" name="email">
     *     </div>
     * </form>
     * ```
     */
    public function __construct(string $class = 'col-md-6')
    {
        $this->class = $class;
    }

    public function render()
    {
        return <<<'HTML'
            <script>
                jQuery(document).ready(function($) {
                    $("form").addClass("row mx-3");
                    $("form > div.form-group").addClass("{{ $class }}");
                });
            </script>
        HTML;
    }
}
