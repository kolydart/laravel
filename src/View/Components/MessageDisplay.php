<?php

namespace Kolydart\Laravel\View\Components;

use Illuminate\View\Component;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\View;

class MessageDisplay extends Component
{
    public $messages = [];

    /**
     * display of various types of session messages
     * retrieves warnings, errors and custom gw.message from Presenter::message()
     *
     * @example usage in blade views:
     *
     * <x-kolydart::message-display />
     *
     * put in the admin/frontend layout file, next to @errors->all()
     *
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

    public function render(): string|null
    {
        if (empty($this->messages)) {
            return null;
        }

        return <<<'BLADE'
            @foreach($messages as $message)
                <div class="row mb-2">
                    <div class="col-lg-12">
                        <div class="alert alert-{{ $message['type'] }} {{ $message['type'] === 'error' ? 'text-white' : '' }}" role="alert">
                            @if($message['type'] === 'warning' || $message['type'] === 'error')
                                {{ $message['text'] }}
                            @else
                                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                                {{ $message['text'] }}
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        BLADE;
    }
}