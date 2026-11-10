const initialized = new WeakSet();

export function initializeEmbeds(scope = document) {
    const roots = [...(scope.matches?.('[data-sb-embed], [data-sb-video]') ? [scope] : []), ...scope.querySelectorAll('[data-sb-embed], [data-sb-video]')];
    for (const root of roots) {
        if (initialized.has(root)) continue;
        initialized.add(root);
        const video = root.querySelector('video');
        if (video) {
            const error = root.querySelector('[data-sb-video-error]');
            const showError = () => { if (error) error.hidden = false; };
            video.addEventListener('error', showError);
            video.querySelectorAll('source').forEach(source => source.addEventListener('error', showError));
            video.addEventListener('loadedmetadata', () => { if (error) error.hidden = true; });
        }
        if (!root.matches('[data-sb-embed]')) continue;
        root.querySelector('[data-sb-load-embed]')?.addEventListener('click', event => {
            event.preventDefault();
            if (root.querySelector('iframe')) return;
            const iframe = document.createElement('iframe');
            iframe.src = root.dataset.src;
            iframe.title = root.dataset.title || (root.dataset.kind === 'map' ? 'Map' : 'Video');
            iframe.allow = 'fullscreen; picture-in-picture; encrypted-media';
            iframe.allowFullscreen = true;
            iframe.referrerPolicy = 'strict-origin-when-cross-origin';
            iframe.tabIndex = 0;
            root.replaceChildren(iframe);
            iframe.focus();
        }, { once: true });
    }
}

if (typeof document !== 'undefined') {
    initializeEmbeds();
    new MutationObserver(records => records.forEach(record => record.addedNodes.forEach(node => { if (node.nodeType === 1) initializeEmbeds(node); }))).observe(document.documentElement, { childList: true, subtree: true });
}
