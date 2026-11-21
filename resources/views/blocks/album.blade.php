@use('SiteBuilder\Components\Albums\AlbumLibrary')
@php
    $snapshot = isset($props['album']) ? app(AlbumLibrary::class)->publishedById($props['album']) : null;
@endphp
@if ($snapshot !== null)
    @include('site-builder-components::album-gallery', ['snapshot' => $snapshot])
@elseif ($context->mode === 'preview')
    <div {{ $attributes->class(['sb-block']) }}>{{ __('Choose a published album to display its photos.') }}</div>
@else
    <div {{ $attributes }} hidden></div>
@endif
