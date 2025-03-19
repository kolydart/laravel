<?php

namespace Kolydart\Laravel\App\View\Components;

use Illuminate\View\Component;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\View;

class MessageDisplay extends Component
{
    public $messages = [];

    /**
     * Create a new component instance.
     *
     * @example usage in blade views:
     * ```blade
     * <x-kolydart::message-display />
     * ```
     */

    public function __construct()
    {
        if (Session::has('warning')) {
            $this->messages[] = [
                'type' => 'warning',
                'text' => Session::get('warning')
            ];
        }

        if (Session::has('error')) {
            $this->messages[] = [
                'type' => 'error',
                'text' => Session::get('error')
            ];
        }

        if (Session::exists('gw_message_text')) {
            $this->messages[] = [
                'type' => Session::get('gw_message_type', 'warning'),
                'text' => Session::get('gw_message_text', 'Could not retrieve message. Error 8LeDJ87SYpiVBcxn.')
            ];
        }
    }

    public function render()
    {
        if (empty($this->messages)) {
            return '';
        }

        return View::make('components.message-display');
    }
}