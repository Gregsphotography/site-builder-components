<ul>@foreach($items as $item)
    @php
        $links = app(\SiteBuilder\Components\Rendering\ContentLinks::class);
        $url = $links->url($item->page ?? null, $item->url ?? null, $context->locale);
        $children = $depth < 3 ? ($item->children ?? []) : [];
        $label = $item->label ?? '';
    @endphp
    <li>
        @if($url)<a href="{{ $url }}" @if($links->current($item->page ?? null, $url, $context)) aria-current="page" @endif>{{ $label }}</a>@elseif(empty($children))<span>{{ $label }}</span>@endif
        @if(!empty($children))
            <details><summary>{{ $url ? __('More in :section', ['section' => $label], $context->locale) : $label }}</summary>
                @include('site-builder-components::partials.navigation-items', ['items' => $children, 'depth' => $depth + 1])
            </details>
        @endif
    </li>
@endforeach</ul>
