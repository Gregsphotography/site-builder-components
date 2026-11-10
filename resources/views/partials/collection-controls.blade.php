@php(app(\SiteBuilder\Core\Rendering\PublicAssets::class)->request('components.collections'))
<div class="sb-collection-controls" data-sb-collection-controls hidden>
    @if($props['searchable'] ?? false)<label>{{ $props['search_label'] ?: __('Search this collection') }}<input type="search" data-sb-collection-search maxlength="200" autocomplete="off"></label>@endif
    @if($props['filterable'] ?? false)<label>{{ $props['filter_label'] ?: __('Filter by category') }}<select data-sb-collection-tag><option value="">{{ $props['all_label'] ?: __('All') }}</option></select></label>@endif
    <button type="button" data-sb-collection-reset>{{ $props['reset_label'] ?: __('Clear filters') }}</button>
    <p role="status" aria-live="polite" data-sb-collection-count data-format="{{ $props['result_label'] ?: __(':count of :total items') }}"></p>
</div>
<p data-sb-collection-empty hidden>{{ $props['empty_message'] ?: __('No matching items. Try another search or clear the filters.') }}</p>
