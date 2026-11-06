@php
    $variant = in_array($props['variant'], ['equal', 'sidebar-start', 'sidebar-end'], true) ? $props['variant'] : 'equal';
    $count = count(array_filter($node->node->children, fn ($child) => $child->visibility !== 'hidden'));
    $count = max(1, min(4, $count));
@endphp
<div {{ $attributes->class(['sb-block', 'sb-columns', 'sb-columns--'.$variant, 'sb-columns--'.$count]) }}>{{ $children }}</div>
