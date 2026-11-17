<?php

namespace Kolydart\Laravel\View\Components;

use Illuminate\View\Component;

class FormFieldsSize extends Component
{
    public $class;

    /**
     * Lays out every form of the page in two columns, with JS on DOMContentLoaded:
     * each `<form>` gets `row mx-3` and each direct child `div.form-group` gets `$class`.
     *
     * Opt-out: a form with `data-fields-size="off"` is skipped entirely
     * (no `row mx-3` on the form, no `$class` on its form groups), e.g. for
     * forms that define their own layout. All other forms are unaffected.
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
     *
     * A form opted out with `<form data-fields-size="off">` is left as written.
     */
    public function __construct(string $class = 'col-md-6')
    {
        $this->class = $class;
    }

    public function render()
    {
        return <<<'HTML'
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var forms = document.querySelectorAll('form:not([data-fields-size="off"])');
                    Array.prototype.forEach.call(forms, function(form) {
                        form.classList.add('row', 'mx-3');
                        var groups = form.querySelectorAll(':scope > div.form-group');
                        Array.prototype.forEach.call(groups, function(group) {
                            group.classList.add("{{ $class }}");
                        });
                    });
                });
            </script>
        HTML;
    }
}
