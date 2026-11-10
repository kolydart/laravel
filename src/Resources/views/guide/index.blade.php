@extends(config('kolydart.guide.layout', 'layouts.admin'))

@section('content')

<div class="card">
    <div class="card-header">
        {{ trans('kolydart::guide.title') }}
    </div>

    <div class="card-body">
        <form method="GET" action="{{ route(\Kolydart\Laravel\App\Http\Controllers\GuideController::routeName('index')) }}" class="mb-4" role="search" id="guide-search-form">
            <div class="input-group">
                <input type="search" name="q" value="{{ $query }}" class="form-control" id="guide-search-input"
                       placeholder="{{ trans('kolydart::guide.search_placeholder') }}" aria-label="{{ trans('kolydart::guide.search_placeholder') }}" autocomplete="off">
                <div class="input-group-append">
                    <button type="submit" class="btn btn-primary">{{ trans('kolydart::guide.search') }}</button>
                    @if($query !== '')
                        <a href="{{ route(\Kolydart\Laravel\App\Http\Controllers\GuideController::routeName('index')) }}" class="btn btn-default">{{ trans('kolydart::guide.clear') }}</a>
                    @endif
                </div>
            </div>
        </form>

        @if($results !== null)
            <p class="text-muted small" id="guide-results-count">
                {{ trans_choice('kolydart::guide.results', $results->count(), ['count' => $results->count(), 'query' => $query]) }}
            </p>

            <div class="list-group">
                @foreach($results as $result)
                    <a href="{{ route(\Kolydart\Laravel\App\Http\Controllers\GuideController::routeName('show'), $result['page']->slug) }}"
                       class="list-group-item list-group-item-action">
                        <strong>{{ $result['title'] }}</strong>
                        @if((string) $result['snippet'] !== '')
                            <div class="text-muted small">{{ $result['snippet'] }}</div>
                        @endif
                    </a>
                @endforeach
            </div>
        @else
            @forelse($sections as $section => $pages)
                <h4 class="mt-3">{{ $sectionLabels[$section] ?? $section }}</h4>
                <div class="list-group mb-4">
                    @foreach($pages as $item)
                        <a href="{{ route(\Kolydart\Laravel\App\Http\Controllers\GuideController::routeName('show'), $item->slug) }}" class="list-group-item list-group-item-action">
                            <strong>{{ $item->title }}</strong>
                            @if($item->excerpt)
                                <div class="text-muted small">{{ $item->excerpt }}</div>
                            @endif
                        </a>
                    @endforeach
                </div>
            @empty
                <p class="text-muted">{{ trans('kolydart::guide.empty') }}</p>
            @endforelse
        @endif
    </div>
</div>

@endsection
