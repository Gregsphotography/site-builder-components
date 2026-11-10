@php
    $items = array_map(fn ($item) => (array) $item, $props['items'] ?? []);
    $links = app(\SiteBuilder\Components\Rendering\ContentLinks::class);
    $images = app(\SiteBuilder\Components\Rendering\Images::class);
    $identity = 'sb-gallery-'.substr(hash('sha256', $context->mode.':'.($context->pageId ?? 'fragment').':'.$attributes->get('data-site-builder-render-address', $node->node->id).':'.\Illuminate\Support\Str::uuid()), 0, 32);
    $usable = array_filter($items, fn ($item) => !empty($item['image']) && trim((($item['decorative'] ?? false) ? '' : ($item['alt'] ?? '')).($item['caption'] ?? '')) !== '');
    $enhanced = ($props['lightbox'] ?? false) && $context->mode === 'public' && $usable !== [];
    if ($enhanced) { app(\SiteBuilder\Core\Rendering\PublicAssets::class)->request('components.gallery'); }
    $filtering = ($props['searchable'] ?? false) || ($props['filterable'] ?? false);
@endphp
<section {{ $attributes->class(['sb-block', 'sb-gallery']) }} id="{{ $identity }}" data-sb-gallery @if($filtering) data-sb-collection @endif tabindex="-1" @if($items !== []) aria-label="{{ $props['label'] ?? __('Image gallery') }}" @endif>
    @if(!empty($props['heading']))<h2>{{ $props['heading'] }}</h2>@endif
    @if($filtering) @include('site-builder-components::partials.collection-controls') @endif
    <div class="sb-gallery-grid sb-gallery-grid--{{ $props['columns'] ?? 'three' }}">
    @foreach($items as $item)
        @php
            $image = $item['image'] ?? null;
            $full = $item['full_image'] ?? $image;
            $description = trim(($item['decorative'] ?? false) ? '' : ($item['alt'] ?? '')) ?: trim($item['caption'] ?? '');
            $trigger = $enhanced && $image && $full && $description !== '';
            $destination = $links->url($item['page'] ?? null, $item['url'] ?? null, $context->locale);
        @endphp
        <figure class="sb-gallery-item" @if($filtering) data-sb-collection-item data-search="{{ ($item['caption'] ?? '').' '.($item['alt'] ?? '') }}" data-tags="{{ json_encode(array_map(fn($tag) => $tag->label, $item['tags'] ?? []), JSON_UNESCAPED_UNICODE) }}" @endif>
            @if($image)
                @if($description !== '' && $full)<a href="{{ $full }}" @if($trigger) data-sb-gallery-image aria-haspopup="dialog" aria-controls="{{ $identity }}-dialog" @endif aria-label="{{ __('Enlarge image: :description', ['description' => $description]) }}">@endif
                <img src="{{ $image }}" @if($srcset = $images->srcset($item['sources'] ?? [])) srcset="{{ $srcset }}" sizes="(max-width: 48rem) 100vw, 50vw" @endif alt="{{ ($item['decorative'] ?? false) ? '' : ($item['alt'] ?? '') }}" @if(!empty($item['width'])) width="{{ $item['width'] }}" @endif @if(!empty($item['height'])) height="{{ $item['height'] }}" @endif loading="lazy" @if($trigger) data-sb-gallery-alt="{{ ($item['decorative'] ?? false) ? '' : ($item['alt'] ?? '') }}" @endif>
                @if($description !== '' && $full)</a>@endif
            @else
                <p class="sb-media-unavailable">{{ __('Image unavailable') }}</p>
            @endif
            @if(!empty($item['caption']))<figcaption data-sb-gallery-caption>{{ $item['caption'] }}</figcaption>@endif
            @if($destination && !empty($item['link_label']))<a class="sb-gallery-destination" href="{{ $destination }}">{{ $item['link_label'] }}</a>@endif
        </figure>
    @endforeach
    </div>
    @if($enhanced)
    <dialog id="{{ $identity }}-dialog" class="sb-gallery-dialog" aria-modal="true" aria-label="{{ $props['label'] ?? __('Image gallery') }}" data-sb-gallery-dialog>
        <div class="sb-gallery-toolbar"><button type="button" data-sb-gallery-close>{{ __('Close gallery') }}</button><span role="status" aria-live="polite" data-sb-gallery-status data-format="{{ __('Image :current of :total') }}"></span></div>
        <figure><img data-sb-gallery-full alt=""><figcaption data-sb-gallery-dialog-caption></figcaption></figure>
        <p role="status" data-sb-gallery-error hidden>{{ __('This image could not be loaded. You can choose another image or close the gallery.') }}</p>
        <div class="sb-gallery-controls"><button type="button" data-sb-gallery-previous>{{ __('Previous image') }}</button><button type="button" data-sb-gallery-next>{{ __('Next image') }}</button></div>
    </dialog>
    @endif
</section>
