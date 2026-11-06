<section {{ $attributes->class(['content-text']) }}><h2>{{ $props['heading'] ?? '' }}</h2><div>{!! $props['body'] ?? '' !!}</div>
    @if ($props['items'])<ul>@foreach ($props['items'] as $item)<li>@if ($item->url)<a href="{{ $item->url }}">{{ $item->label }}</a>@else{{ $item->label }}@endif</li>@endforeach</ul>@endif
</section>
