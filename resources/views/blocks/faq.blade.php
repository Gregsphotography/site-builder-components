<section {{ $attributes->class(['sb-block', 'sb-faq']) }}>
    @if (filled($props['heading']))<h2>{{ $props['heading'] }}</h2>@endif
    @foreach ($props['items'] ?? [] as $item)
        @if (filled($item->question))<details><summary>{{ $item->question }}</summary><div class="sb-prose">{!! $item->answer ?? '' !!}</div></details>@endif
    @endforeach
</section>
