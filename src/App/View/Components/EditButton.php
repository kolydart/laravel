<?php

namespace Kolydart\Laravel\App\View\Components;

use Illuminate\View\Component;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Request;
use Kolydart\Laravel\App\Helpers\RouterHelper;

class EditButton extends Component
{
    public $text;
    public $url;
    public $show;

    /**
     * Create a new component instance.
     *
     * @example usage in blade views:
     * ```blade
     * <x-kolydart::edit-button />
     * ```
     */
    public function __construct()
    {
        $this->show = false;

        // Check if the user has permission to edit the resource
        if( Gate::denies(RouterHelper::getPermissionTitle()) ){
            return null;
        }

        // Get the text for the edit button
        if (Lang::has('gw.edit')) {
            $this->text = trans('gw.edit');
        } elseif (Lang::has('global.edit')) {
            $this->text = trans('global.edit');
        } else {
            $this->text = 'Edit';
        }

        // Get the URL for the edit button
        if (Route::getCurrentRoute()->getActionMethod() == 'show') {

            // Check if the controller exists and has an edit method
            if (!is_object(request()->route()->controller) || 
                !(method_exists(get_class(request()->route()->controller), 'edit'))) {
                return;
            }
            // Replace the 'show' method with 'edit' in the router name
            $this->url = route(
                RouterHelper::replaceMethodInRouterName(),
                request()->segment(count(\request()->segments()))
            );

            // Show the edit button
            $this->show = true;
        }
    }

    public function render()
    {
        if (!$this->show) {
            return '';
        }

        // Render the edit button
        return <<<'BLADE'
            <a href="{{ $url }}" class="btn btn-warning">
                <i class="fa fa-edit"></i> {{ $text }}
            </a>
        BLADE;
    }
} 