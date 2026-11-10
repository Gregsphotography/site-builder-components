<{{ $props['level'] ?? 'h2' }} {{ $attributes->class(['sb-block', 'sb-heading']) }}>
    {{ $props['text'] ?? '' }}
    @if(filled($props['highlight']))
        <mark>{{ $props['highlight'] }}</mark>
    @endif
    @if(filled($props['after']))
        {{ $props['after'] }}
    @endif
</{{ $props['level'] ?? 'h2' }}>
