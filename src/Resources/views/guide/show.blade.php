@extends(config('kolydart.guide.layout', 'layouts.admin'))

@section('title'){{ $page->title }} | @endsection

@section('styles')
@parent
<style>
    .guide-content { max-width: 50rem; }
    .guide-content img { max-width: 100%; height: auto; }

    /* Bootstrap's heading scale dwarfs 1rem body text in long-form pages. */
    .guide-content h1 { font-size: 1.6em; }
    .guide-content h2 { font-size: 1.4em; }
    .guide-content h3 { font-size: 1.2em; }
    .guide-content h4, .guide-content h5, .guide-content h6 { font-size: 1.05em; }
    .guide-content h1, .guide-content h2, .guide-content h3,
    .guide-content h4, .guide-content h5, .guide-content h6 { margin-top: 1.5em; margin-bottom: .5em; }
    .guide-content > :first-child { margin-top: 0; }

    /* Markdown tables carry no Bootstrap class; wide ones scroll instead of squeezing. */
    .guide-content table { display: block; max-width: 100%; overflow-x: auto; border-collapse: collapse; margin-bottom: 1rem; }
    .guide-content th, .guide-content td { border: 1px solid #dee2e6; padding: .35rem .6rem; vertical-align: top; }
    .guide-content thead th { background-color: #f8f9fa; }

    #guide-toc .nav-link.active { font-weight: 600; color: #212529; background-color: #e9ecef; border-radius: .25rem; }

    .guide-content blockquote { border-left: 4px solid #dee2e6; padding-left: 1rem; color: #495057; }
</style>
@endsection

@section('content')

<div class="row">
    <div class="col-lg-3">
        <div class="card">
            <div class="card-header">
                <a href="{{ route(\Kolydart\Laravel\App\Http\Controllers\GuideController::routeName('index')) }}">{{ trans('kolydart::guide.title') }}</a>
            </div>
            <div class="card-body p-2">
                <form method="GET" action="{{ route(\Kolydart\Laravel\App\Http\Controllers\GuideController::routeName('index')) }}" class="mb-2" role="search">
                    <input type="search" name="q" class="form-control form-control-sm"
                           placeholder="{{ trans('kolydart::guide.search_placeholder') }}" aria-label="{{ trans('kolydart::guide.search_placeholder') }}" autocomplete="off">
                </form>

                <nav id="guide-toc" aria-label="{{ trans('kolydart::guide.contents') }}">
                    @foreach($sections as $section => $pages)
                        <div class="text-muted text-bold small mt-2 mb-1">{{ $sectionLabels[$section] ?? $section }}</div>
                        <div class="nav flex-column">
                            @foreach($pages as $item)
                                <a href="{{ route(\Kolydart\Laravel\App\Http\Controllers\GuideController::routeName('show'), $item->slug) }}"
                                   class="nav-link px-2 py-1 {{ $item->slug === $page->slug ? 'active' : '' }}"
                                   @if($item->slug === $page->slug) aria-current="page" @endif>
                                    {{ $item->title }}
                                </a>
                            @endforeach
                        </div>
                    @endforeach
                </nav>
            </div>
        </div>
    </div>

    <div class="col-lg-9">
        <div class="card">
            <div class="card-header">
                <h1 class="card-title h5 mb-0">{{ $page->title }}</h1>
            </div>
            <div class="card-body">
                <article class="guide-content">
                    {{ $html }}
                </article>
            </div>
        </div>
    </div>
</div>

@endsection
