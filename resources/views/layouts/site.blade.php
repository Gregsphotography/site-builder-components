@php
    $theme = app(\SiteBuilder\Components\Themes\ThemeCatalog::class);
    $preset = $theme->preset($preset ?? null);
    $scaffold = app(\SiteBuilder\Components\Rendering\SiteScaffold::class)->data($locale);
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ config('site-builder.locales.'.$locale.'.dir', 'ltr') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('site-builder::meta')
    @if (config('site-builder-components.assets', true) && $preset !== 'host')<link rel="stylesheet" href="{{ $theme->assetUrl() }}">@endif
</head>
<body>
<div data-site-builder-theme="{{ $preset }}" data-site-builder-theme-version="1">
    <a class="sb-skip-link" href="#site-builder-content">{{ __('Skip to content') }}</a>
    @if (isset($headerSlot))
    <header class="sb-region sb-region--header">{{ $headerSlot }}</header>
    @else
    <header class="sb-header">
        <a class="sb-brand" href="{{ url('/') }}">@if ($scaffold['logo'])<img src="{{ $scaffold['logo'] }}" alt="" width="48" height="48">@endif<span>{{ $scaffold['name'] }}</span></a>
        @if ($scaffold['header'])<nav aria-label="{{ __('Main navigation') }}">@include('site-builder-components::partials.navigation', ['items' => $scaffold['header']])</nav>@endif
    </header>
    @endif
    <main id="site-builder-content" class="sb-main" data-site-builder-canvas tabindex="-1">{{ $slot }}</main>
    @if (isset($footerSlot))
    <footer class="sb-region sb-region--footer">{{ $footerSlot }}</footer>
    @else
    <footer class="sb-footer"><p>{{ $scaffold['footer'] }}</p>@if ($scaffold['footer_links'])<nav aria-label="{{ __('Footer navigation') }}">@include('site-builder-components::partials.navigation', ['items' => $scaffold['footer_links']])</nav>@endif</footer>
    @endif
</div>
@siteBuilderAssets
</body>
</html>
