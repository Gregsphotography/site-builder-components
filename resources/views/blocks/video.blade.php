@php
    $provider = $props['provider'] ?? 'uploaded';
    $source = $provider === 'uploaded' ? $props['media'] : $props['url'];
    $embedded = app(\SiteBuilder\Components\Rendering\EmbedUrls::class)->video($provider, $props['url']);
    $safeSource = is_string($source) && app(\SiteBuilder\Core\Rendering\SafeUrl::class)->valid($source, true) ? $source : null;
    if ($context->mode === 'public') { app(\SiteBuilder\Core\Rendering\PublicAssets::class)->request('components.embeds'); }
@endphp
<section {{ $attributes->class(['sb-block', 'sb-video']) }}>
    @if(filled($props['title']))<h2>{{ $props['title'] }}</h2>@endif
    <div class="sb-video-frame sb-video-frame--{{ $props['aspect'] ?? 'landscape' }}" data-sb-video>
    @if($context->mode === 'preview')
        @if($props['poster'])<img src="{{ $props['poster'] }}" alt="">@endif
        <p>{{ __('Video preview — playback is available on the published page.') }}</p>
    @elseif($embedded)
        <div data-sb-embed data-src="{{ $embedded }}" data-title="{{ $props['title'] }}" data-kind="video">
            @if($props['poster'])<img src="{{ $props['poster'] }}" alt="" loading="lazy">@endif
            <button type="button" data-sb-load-embed>{{ __('Load video') }}</button>
        </div>
    @elseif($safeSource)
        <video controls preload="metadata" playsinline @if($props['poster']) poster="{{ $props['poster'] }}" @endif aria-label="{{ $props['title'] }}">
            <source src="{{ $safeSource }}">
            @foreach($props['tracks'] ?? [] as $track)
                @php($trackUrl = $track->track ?: $track->url)
                @if($trackUrl && app(\SiteBuilder\Core\Rendering\SafeUrl::class)->valid($trackUrl, true))<track kind="captions" src="{{ $trackUrl }}" srclang="{{ $track->language }}" label="{{ $track->label }}" @if($track->default) default @endif>@endif
            @endforeach
            <a href="{{ $safeSource }}">{{ __('Open video') }}</a>
        </video>
        <p data-sb-video-error role="status" hidden>{{ __('This video could not be played. Try opening the video directly.') }}</p>
    @else
        <p>{{ __('Choose a video in the editor.') }}</p>
    @endif
    </div>
    @if($context->mode === 'public' && $safeSource)<p><a href="{{ $safeSource }}">{{ __('Open video directly') }}</a></p>@elseif($context->mode === 'public' && $embedded && $props['url'])<p><a href="{{ $props['url'] }}">{{ __('Open video on its website') }}</a></p>@endif
    @if(filled($props['transcript']))<details><summary>{{ __('Read transcript') }}</summary><div class="sb-prose">{!! $props['transcript'] !!}</div></details>@endif
</section>
