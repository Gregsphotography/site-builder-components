<article>
    <header class="sb-block">
        <h1>{{ $snapshot['title'] }}</h1>
        @if ($cover)
            <img class="sb-hero__image" src="{{ $cover }}" alt="">
        @endif
    </header>
    @include('site-builder-components::album-gallery', ['showHeading' => false])
</article>
