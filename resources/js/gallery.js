const initialized = new WeakSet();

export function adjacentIndex(index, delta, count) {
    return Math.max(0, Math.min(count - 1, index + delta));
}

export function arrowDelta(key, rtl) {
    return key === 'ArrowRight' ? (rtl ? -1 : 1) : key === 'ArrowLeft' ? (rtl ? 1 : -1) : 0;
}

export function initializeGalleries(scope = document) {
    const roots = [...(scope.matches?.('[data-sb-gallery]') ? [scope] : []), ...scope.querySelectorAll('[data-sb-gallery]')];
    for (const root of roots) {
        if (initialized.has(root)) continue;
        const dialog = root.querySelector('[data-sb-gallery-dialog]');
        if (!dialog || typeof dialog.showModal !== 'function') continue;
        initialized.add(root);
        const triggers = [...root.querySelectorAll('[data-sb-gallery-image]')];
        const close = dialog.querySelector('[data-sb-gallery-close]');
        const previous = dialog.querySelector('[data-sb-gallery-previous]');
        const next = dialog.querySelector('[data-sb-gallery-next]');
        const image = dialog.querySelector('[data-sb-gallery-full]');
        const caption = dialog.querySelector('[data-sb-gallery-dialog-caption]');
        const status = dialog.querySelector('[data-sb-gallery-status]');
        const error = dialog.querySelector('[data-sb-gallery-error]');
        let index = 0, opener = null, opened = false, removal = null;

        const show = value => {
            index = adjacentIndex(value, 0, triggers.length);
            const trigger = triggers[index];
            const thumbnail = trigger.querySelector('img');
            caption.textContent = trigger.closest('figure').querySelector('[data-sb-gallery-caption]')?.textContent || '';
            status.textContent = status.dataset.format.replace(':current', String(index + 1)).replace(':total', String(triggers.length));
            error.hidden = true;
            image.hidden = false;
            image.alt = thumbnail?.getAttribute('alt') || '';
            image.onload = () => { error.hidden = true; image.hidden = false; };
            image.onerror = () => { image.hidden = true; error.hidden = false; };
            image.src = trigger.href;
            previous.disabled = index === 0;
            next.disabled = index === triggers.length - 1;
            if (document.activeElement === previous && previous.disabled) next.disabled ? close.focus() : next.focus();
            if (document.activeElement === next && next.disabled) previous.disabled ? close.focus() : previous.focus();
        };
        const finish = () => {
            if (!opened) return;
            opened = false;
            removal?.disconnect();
            image.removeAttribute('src');
            // Native modality owns background inertness. Never rewrite a host's scroll or inert state.
            if (document.querySelector('dialog[open]')) { opener = null; return; }
            let target = opener?.isConnected ? opener : root.isConnected ? root : document.querySelector('main');
            if (target) {
                const temporary = !target.hasAttribute('tabindex') && target.tagName !== 'A';
                if (temporary) target.setAttribute('tabindex', '-1');
                target.focus({ preventScroll: true });
                if (temporary) target.removeAttribute('tabindex');
            }
            opener = null;
        };
        triggers.forEach((trigger, selected) => trigger.addEventListener('click', event => {
            if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || dialog.open || document.querySelector('dialog[open]')) return;
            try { dialog.showModal(); } catch { return; }
            event.preventDefault();
            opener = trigger;
            opened = true;
            show(selected);
            close.focus();
            removal = new MutationObserver(() => {
                if (!root.isConnected || !dialog.isConnected) { dialog.close(); finish(); }
            });
            removal.observe(document.documentElement, { childList: true, subtree: true });
        }));
        close.addEventListener('click', () => dialog.close());
        dialog.addEventListener('close', finish);
        previous.addEventListener('click', () => show(adjacentIndex(index, -1, triggers.length)));
        next.addEventListener('click', () => show(adjacentIndex(index, 1, triggers.length)));
        dialog.addEventListener('keydown', event => {
            const delta = arrowDelta(event.key, getComputedStyle(root).direction === 'rtl');
            if (delta) { event.preventDefault(); show(adjacentIndex(index, delta, triggers.length)); }
            if (event.key === 'Tab') {
                const controls = [...dialog.querySelectorAll('button')].filter(button => !button.disabled && !button.hidden);
                const first = controls[0], last = controls.at(-1);
                if (event.shiftKey && (document.activeElement === first || !dialog.contains(document.activeElement))) { event.preventDefault(); last.focus(); }
                else if (!event.shiftKey && (document.activeElement === last || !dialog.contains(document.activeElement))) { event.preventDefault(); first.focus(); }
            }
        });
    }
}

if (typeof document !== 'undefined') {
    initializeGalleries();
    new MutationObserver(records => records.forEach(record => record.addedNodes.forEach(node => {
        if (node.nodeType === 1) initializeGalleries(node);
    }))).observe(document.documentElement, { childList: true, subtree: true });
}
