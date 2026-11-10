const initialized = new WeakSet();
let sequence = 0;

export function revealTarget(target) {
    if (!target) return;
    const ancestors = [];
    for (let node = target; node; node = node.parentElement) ancestors.push(node);
    for (const node of ancestors.reverse()) {
        if (node.matches?.('details')) node.open = true;
        if (node.matches?.('[data-sb-tab-panel]')) node.dispatchEvent(new Event('sb:reveal'));
    }
}

export function initializeInteractions(scope = document) {
    const roots = [...(scope.matches?.('[data-sb-tabs], [data-sb-accordion]') ? [scope] : []), ...scope.querySelectorAll('[data-sb-tabs], [data-sb-accordion]')];
    for (const root of roots) {
        if (initialized.has(root)) continue;
        initialized.add(root);
        if (root.matches('[data-sb-accordion]')) {
            const items = [...root.children].filter(node => node.tagName === 'DETAILS');
            if (root.dataset.multiple === 'false') {
                const name = `sb-accordion-${++sequence}`;
                items.forEach(item => {
                    item.name = name;
                    item.addEventListener('toggle', () => { if (item.open) items.forEach(other => { if (other !== item) other.open = false; }); });
                });
            }
            continue;
        }
        const panels = [...root.children].filter(node => node.matches('[data-sb-tab-panel]'));
        if (!panels.length) continue;
        const id = `sb-tabs-${++sequence}`;
        const list = document.createElement('div');
        list.className = 'sb-tab-list';
        list.setAttribute('role', 'tablist');
        list.setAttribute('aria-label', root.getAttribute('aria-label') || 'Information');
        const buttons = [];
        const activate = (index, focus = false) => {
            panels.forEach((panel, current) => {
                panel.hidden = current !== index;
                buttons[current].tabIndex = current === index ? 0 : -1;
                buttons[current].setAttribute('aria-selected', String(current === index));
            });
            if (focus) buttons[index].focus();
        };
        panels.forEach((panel, index) => {
            const title = [...panel.children].find(node => node.matches('[data-sb-panel-title]'));
            const button = document.createElement('button');
            button.type = 'button';
            button.id = `${id}-tab-${index}`;
            button.textContent = title?.textContent || String(index + 1);
            button.setAttribute('role', 'tab');
            panel.id ||= `${id}-panel-${index}`;
            button.setAttribute('aria-controls', panel.id);
            panel.setAttribute('role', 'tabpanel');
            panel.setAttribute('aria-labelledby', button.id);
            panel.tabIndex = 0;
            if (title) title.hidden = true;
            button.addEventListener('click', () => activate(index));
            button.addEventListener('keydown', event => {
                const rtl = getComputedStyle(root).direction === 'rtl';
                const delta = event.key === 'ArrowRight' ? (rtl ? -1 : 1) : event.key === 'ArrowLeft' ? (rtl ? 1 : -1) : 0;
                let next = delta ? (index + delta + panels.length) % panels.length : event.key === 'Home' ? 0 : event.key === 'End' ? panels.length - 1 : null;
                if (next !== null) { event.preventDefault(); activate(next, true); }
            });
            panel.addEventListener('sb:reveal', () => activate(index));
            buttons.push(button);
            list.append(button);
        });
        root.prepend(list);
        activate(0);
    }
}

function revealHash() {
    try { revealTarget(document.getElementById(decodeURIComponent(location.hash.slice(1)))); } catch { /* Invalid fragments cannot name a target. */ }
}

if (typeof document !== 'undefined') {
    initializeInteractions();
    revealHash();
    window.addEventListener('hashchange', revealHash);
    document.addEventListener('click', event => {
        const link = event.target.closest?.('a[href^="#"]');
        if (link) { try { revealTarget(document.getElementById(decodeURIComponent(link.hash.slice(1)))); } catch {} }
    });
    new MutationObserver(records => records.forEach(record => record.addedNodes.forEach(node => { if (node.nodeType === 1) initializeInteractions(node); }))).observe(document.documentElement, { childList: true, subtree: true });
}
