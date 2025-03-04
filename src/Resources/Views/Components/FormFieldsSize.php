<?php

namespace Kolydart\Laravel\Resources\Views\Components;

use Illuminate\View\Component;

class FormFieldsSize extends Component
{
    public $class;

    /**
     * Create a new component instance.
     *
     * @param string $class The CSS class to apply to the form fields.
     * 
     * use 
     * <x-kolydart::form-fields-size />
     * 
     * @example usage in blade views:
     * ```blade
     * <form>
     *     <x-kolydart::form-fields-size class="col-md-6" />
     *     <div class="form-group">
     *         <input type="text" name="name">
     *     </div>
     *     <div class="form-group">
     *         <input type="email" name="email">
     *     </div>
     * </form>
     * ```
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
        return <<<'blade'
            <script>
                jQuery(document).ready(function($) {
                    $("form").addClass("row mx-3");
                    $("form > div.form-group").addClass("{{ $class }}");
                });
            </script>
        blade;
    }
}
