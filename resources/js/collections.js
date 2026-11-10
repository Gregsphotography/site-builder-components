const initialized = new WeakSet();

export function normalizeSearch(value, locale) {
    const text = String(value).normalize('NFD').replace(/\p{M}/gu, '');
    try { return text.toLocaleLowerCase(locale || undefined).trim(); } catch { return text.toLowerCase().trim(); }
}

export function matchesItem(text, tags, query, tag, locale) {
    const haystack = normalizeSearch(`${text} ${tags.join(' ')}`, locale);
    return normalizeSearch(query, locale).split(/\s+/u).filter(Boolean).every(word => haystack.includes(word))
        && (!tag || tags.some(value => normalizeSearch(value, locale) === normalizeSearch(tag, locale)));
}

export function initializeCollections(scope = document) {
    const roots = [...(scope.matches?.('[data-sb-collection]') ? [scope] : []), ...scope.querySelectorAll('[data-sb-collection]')];
    for (const root of roots) {
        if (initialized.has(root)) continue;
        initialized.add(root);
        const own = selector => [...root.querySelectorAll(selector)].filter(node => node.closest('[data-sb-collection]') === root);
        const items = own('[data-sb-collection-item]').map(element => {
            let tags = [];
            try { tags = JSON.parse(element.dataset.tags || '[]').filter(value => typeof value === 'string' && value.trim()); } catch {}
            return { element, tags, text: element.dataset.search || '' };
        });
        const search = own('[data-sb-collection-search]')[0];
        const select = own('[data-sb-collection-tag]')[0];
        const count = own('[data-sb-collection-count]')[0];
        const empty = own('[data-sb-collection-empty]')[0];
        const locale = root.closest('[lang]')?.lang || document.documentElement.lang || undefined;
        if (select) {
            const tags = new Map();
            items.flatMap(item => item.tags).forEach(tag => { const key = normalizeSearch(tag, locale); if (!tags.has(key)) tags.set(key, tag); });
            for (const [value, label] of tags) { const option = document.createElement('option'); option.value = value; option.textContent = label; select.append(option); }
        }
        const update = () => {
            let visible = 0;
            items.forEach(item => {
                item.element.hidden = !matchesItem(item.text, item.tags, search?.value || '', select?.value || '', locale);
                if (!item.element.hidden) visible++;
            });
            if (count) count.textContent = count.dataset.format.replaceAll(':count', String(visible)).replaceAll(':total', String(items.length));
            if (empty) empty.hidden = visible !== 0;
            root.dispatchEvent(new CustomEvent('sb:collection-change'));
        };
        let timer;
        search?.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(update, 120); });
        select?.addEventListener('change', update);
        own('[data-sb-collection-reset]')[0]?.addEventListener('click', () => { clearTimeout(timer); if (search) search.value = ''; if (select) select.value = ''; update(); search?.focus(); });
        own('[data-sb-collection-controls]').forEach(control => { control.hidden = false; });
        update();
    }
}

if (typeof document !== 'undefined') {
    initializeCollections();
    new MutationObserver(records => records.forEach(record => record.addedNodes.forEach(node => { if (node.nodeType === 1) initializeCollections(node); }))).observe(document.documentElement, { childList: true, subtree: true });
}
