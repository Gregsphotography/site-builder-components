<section {{ $attributes->class(['content-image-text']) }}>
    @if ($props['image'])<img src="{{ $props['image'] }}" alt="{{ $props['alt'] ?? '' }}" width="720" height="480" loading="lazy">@endif
    <div><h2>{{ $props['heading'] ?? '' }}</h2><div>{!! $props['body'] ?? '' !!}</div></div>
</section>
