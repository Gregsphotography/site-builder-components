@php($custom = app(\SiteBuilder\Components\Rendering\ContentPresentations::class)->render($options['variant'] ?? 'cards', $item, $options))
@if($custom){!! $custom !!}@else
<article class="sb-content-item">
    @if(($options['show_image'] ?? true) && $item->image)<img src="{{ $item->image }}" alt="{{ $item->alt }}" loading="lazy" decoding="async">@endif
    <div class="sb-content-item-copy">
        @if(($options['show_date'] ?? false) && $item->date)<time datetime="{{ $item->date }}">{{ $item->date }}</time>@endif
        <h3><a href="{{ $item->url }}">{{ $item->title }}</a></h3>
        @if($item->subtitle !== '')<p>{{ $item->subtitle }}</p>@endif
        @if(($options['show_excerpt'] ?? true) && $item->excerpt !== '')<p>{{ $item->excerpt }}</p>@endif
        @if($item->tags !== [])<ul class="sb-content-tags" aria-label="{{ __('Topics') }}">@foreach($item->tags as $tag)<li>{{ $tag }}</li>@endforeach</ul>@endif
    </div>
</article>

@endif
