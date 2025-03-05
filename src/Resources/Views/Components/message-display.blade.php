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