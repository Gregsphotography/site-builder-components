<div {{ $attributes->class(['sb-block', 'sb-table']) }} tabindex="0" role="region" aria-label="{{ $props['caption'] }}">
<table><caption>{{ $props['caption'] }}</caption><thead><tr>@foreach($props['columns'] ?? [] as $column)<th scope="col">{{ $column->label }}</th>@endforeach</tr></thead>
<tbody>
    @foreach($props['rows'] ?? [] as $row)
    <tr>
        @foreach($row->cells ?? [] as $cell)
            @if($loop->first && ($props['row_headers'] ?? false))
                <th scope="row"><div class="sb-prose">{!! $cell->body !!}</div></th>
            @else
                <td><div class="sb-prose">{!! $cell->body !!}</div></td>
            @endif
        @endforeach
    </tr>
    @endforeach
</tbody></table>
</div>
