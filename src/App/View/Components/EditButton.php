<?php

namespace Kolydart\Laravel\App\View\Components;

use Illuminate\View\Component;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Request;

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
        
        // Get the current route name and replace 'show' with 'edit'
        $currentRoute = Route::currentRouteName();
        $permission = str_replace('show', 'edit', $currentRoute);
        
        if (Gate::denies($permission)) {
            return;
        }

        if (Lang::has('gw.edit')) {
            $this->text = trans('gw.edit');
        } elseif (Lang::has('global.edit')) {
            $this->text = trans('global.edit');
        } else {
            $this->text = 'Edit';
        }

        if (Route::getCurrentRoute()->getActionMethod() == 'show') {
            if (!is_object(request()->route()->controller) || 
                !(method_exists(get_class(request()->route()->controller), 'edit'))) {
                return;
            }

            // Get the current route parameters
            $parameters = Request::route()->parameters();
            $lastParameter = end($parameters);

            $this->url = route(
                str_replace('show', 'edit', $currentRoute),
                $lastParameter
            );
            $this->show = true;
        }
    }

    public function render()
    {
        if (!$this->show) {
            return '';
        }

        return <<<'blade'
            <a href="{{ $url }}" class="btn btn-warning">
                <i class="fa fa-edit"></i> {{ $text }}
            </a>
        blade;
    }
} 