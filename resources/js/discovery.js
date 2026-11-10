const mounted = new Map();

export function boundedInteger(value, minimum, maximum, fallback) {
    const number = Number(value);
    return Number.isInteger(number) ? Math.min(maximum, Math.max(minimum, number)) : fallback;
}

export function carouselWindow(index, count, visible) {
    const size = Math.max(1, Math.min(count || 1, visible));
    const start = Math.max(0, Math.min(Math.max(0, count - size), index));
    return { start, end: Math.min(count, start + size), maximum: Math.max(0, count - size) };
}

function search(form) {
    const input = form.querySelector('input[name="q"]');
    const list = form.querySelector('[data-suggestions]');
    const status = form.querySelector('[data-search-status]');
    if (!input || !list || !status) return () => {};
    let timer, controller, sequence = 0, active = -1, items = [];
    const close = () => {
        active = -1;
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
    };
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-controls', list.id);
    input.setAttribute('aria-expanded', 'false');
    list.setAttribute('role', 'listbox');
    const choose = (index) => {
        const item = items[index];
        if (item) window.location.assign(item.url);
    };
    const highlight = (index) => {
        active = index;
        [...list.children].forEach((option, i) => option.setAttribute('aria-selected', String(i === active)));
        if (list.children[active]) {
            input.setAttribute('aria-activedescendant', list.children[active].id);
            list.children[active].scrollIntoView({ block: 'nearest' });
        }
    };
    const update = async () => {
        controller?.abort();
        const current = ++sequence;
        const text = input.value.trim();
        close();
        if ([...text].length < 2) { status.textContent = ''; return; }
        controller = new AbortController();
        const url = new URL(form.dataset.endpoint, window.location.href);
        url.searchParams.set('q', [...text].slice(0, 200).join(''));
        url.searchParams.set('locale', form.dataset.locale);
        url.searchParams.set('source', form.dataset.source || '*');
        if (form.dataset.contentType) url.searchParams.set('type', form.dataset.contentType);
        if (form.dataset.contentTag) url.searchParams.set('tag', form.dataset.contentTag);
        try {
            const response = await fetch(url, { signal: controller.signal, headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
            if (!response.ok) throw new Error('suggestions-unavailable');
            const payload = await response.json();
            if (current !== sequence || document.activeElement !== input || input.value.trim() !== text) return;
            items = (Array.isArray(payload.items) ? payload.items : []).slice(0, 5).filter((item) => {
                if (typeof item.title !== 'string' || typeof item.url !== 'string' || /[\x00-\x20\\]/.test(item.url)) return false;
                try { return ['http:', 'https:'].includes(new URL(item.url, window.location.href).protocol); } catch { return false; }
            });
            list.replaceChildren();
            items.forEach((item, index) => {
                const option = document.createElement('li');
                option.id = `${list.id}-${index}`;
                option.setAttribute('role', 'option');
                option.setAttribute('aria-selected', 'false');
                option.textContent = item.title;
                option.addEventListener('pointerdown', (event) => event.preventDefault());
                option.addEventListener('click', () => choose(index));
                list.append(option);
            });
            list.hidden = items.length === 0;
            input.setAttribute('aria-expanded', String(items.length > 0));
            status.textContent = items.length === 1 ? (form.dataset.singularLabel || '1 suggestion') : (form.dataset.countLabel || ':count suggestions').replace(':count', String(items.length));
        } catch (error) {
            if (error.name !== 'AbortError' && current === sequence) {
                close();
                status.textContent = form.dataset.unavailableLabel || 'Suggestions unavailable. Submit to search.';
            }
        }
    };
    const changed = () => {
        clearTimeout(timer);
        controller?.abort();
        sequence++;
        close();
        timer = setTimeout(update, 300);
    };
    const keydown = (event) => {
        if (event.key === 'Escape') {
            if (!list.hidden) { event.preventDefault(); event.stopPropagation(); }
            close(); controller?.abort(); sequence++; return;
        }
        if (list.hidden) return;
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            highlight((active + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length);
        } else if (event.key === 'Enter' && active >= 0) {
            event.preventDefault(); choose(active);
        } else if (event.key === 'Tab') close();
    };
    input.addEventListener('input', changed);
    input.addEventListener('keydown', keydown);
    input.addEventListener('blur', close);
    return () => {
        clearTimeout(timer); controller?.abort();
        input.removeEventListener('input', changed);
        input.removeEventListener('keydown', keydown);
        input.removeEventListener('blur', close);
    };
}

function carousel(root) {
    const track = root.querySelector('[data-carousel-track]');
    const slides = [...root.querySelectorAll('[data-carousel-slide]')];
    const controls = root.querySelector('[data-carousel-controls]');
    const previous = root.querySelector('[data-carousel-prev]');
    const next = root.querySelector('[data-carousel-next]');
    const pause = root.querySelector('[data-carousel-pause]');
    const position = root.querySelector('[data-carousel-position]');
    if (!track || !controls || !previous || !next || !position || slides.length < 2) return () => {};
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    const configured = boundedInteger(root.dataset.perView, 1, 4, 3);
    const interval = boundedInteger(root.dataset.interval, 3, 30, 5) * 1000;
    let index = 0, perView = 1, timer, scrollTimer, userPaused = reduced.matches, hovering = false, focused = false, onScreen = !('IntersectionObserver' in window);
    const autoplay = root.dataset.autoplay === 'true';
    root.classList.add('is-enhanced');
    root.setAttribute('aria-roledescription', root.dataset.carouselLabel || 'carousel');
    track.tabIndex = 0;
    controls.hidden = false;
    const schedule = () => {
        clearTimeout(timer);
        const state = carouselWindow(index, slides.length, perView);
        if (!autoplay || userPaused || reduced.matches || hovering || focused || !onScreen || document.hidden || !root.isConnected || !state.maximum) return;
        timer = setTimeout(() => move(index >= state.maximum ? 0 : index + perView, false), interval);
    };
    const update = () => {
        const state = carouselWindow(index, slides.length, perView);
        index = state.start;
        previous.disabled = index === 0;
        next.disabled = index === state.maximum;
        position.textContent = (root.dataset.positionLabel || ':start–:end of :count').replace(':start', String(index + 1)).replace(':end', String(state.end)).replace(':count', String(slides.length));
        position.setAttribute('aria-live', autoplay && !userPaused && !focused ? 'off' : 'polite');
        slides.forEach((slide, i) => {
            slide.inert = (i < state.start || i >= state.end) && !slide.contains(document.activeElement);
            slide.setAttribute('aria-label', `${i + 1} / ${slides.length}`);
        });
        if (pause) {
            pause.textContent = userPaused ? pause.dataset.playLabel : pause.dataset.pauseLabel;
            pause.setAttribute('aria-pressed', String(userPaused));
            pause.disabled = reduced.matches;
        }
        const motionNote = root.querySelector('[data-carousel-motion-note]');
        if (motionNote) motionNote.hidden = !reduced.matches;
        schedule();
    };
    const move = (target, interaction = true, animate = true) => {
        if (interaction) userPaused = true;
        const state = carouselWindow(target, slides.length, perView);
        index = state.start;
        const slide = slides[index].getBoundingClientRect();
        const origin = slides[0].getBoundingClientRect();
        const rtl = getComputedStyle(track).direction === 'rtl';
        const left = rtl ? slide.right - origin.right : slide.left - origin.left;
        track.scrollTo({ left, behavior: animate && !reduced.matches ? 'smooth' : 'instant' });
        update();
    };
    const resized = () => {
        perView = Math.min(configured, root.clientWidth < 600 ? 1 : (root.clientWidth < 900 ? 2 : configured));
        root.style.setProperty('--sb-carousel-per-view', String(perView));
        move(index, false, false);
    };
    const prevClick = () => move(index - perView);
    const nextClick = () => move(index + perView);
    const pauseClick = () => { userPaused = !userPaused; update(); };
    const keydown = (event) => {
        if (event.target !== track || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        event.preventDefault();
        const rtl = getComputedStyle(track).direction === 'rtl';
        move(event.key === 'Home' ? 0 : event.key === 'End' ? slides.length : index + ((event.key === 'ArrowRight') !== rtl ? perView : -perView));
    };
    const scroll = () => {
        const frame = track.getBoundingClientRect();
        const rtl = getComputedStyle(track).direction === 'rtl';
        const distances = slides.map((slide) => Math.abs(rtl ? slide.getBoundingClientRect().right - frame.right : slide.getBoundingClientRect().left - frame.left));
        index = distances.indexOf(Math.min(...distances)); update();
    };
    const enter = () => { hovering = true; schedule(); };
    const scrolling = () => { clearTimeout(scrollTimer); scrollTimer = setTimeout(scroll, 150); };
    const leave = () => { hovering = false; schedule(); };
    const focusIn = () => { focused = true; schedule(); };
    const focusOut = (event) => { focused = root.contains(event.relatedTarget); schedule(); };
    const interaction = () => { userPaused = true; update(); };
    const motion = () => { if (reduced.matches) userPaused = true; update(); };
    previous.addEventListener('click', prevClick); next.addEventListener('click', nextClick); pause?.addEventListener('click', pauseClick);
    track.addEventListener('keydown', keydown); track.addEventListener('scrollend', scroll);
    track.addEventListener('scroll', scrolling, { passive: true });
    track.addEventListener('pointerdown', interaction); track.addEventListener('wheel', interaction, { passive: true });
    root.addEventListener('mouseenter', enter); root.addEventListener('mouseleave', leave);
    root.addEventListener('focusin', focusIn); root.addEventListener('focusout', focusOut);
    document.addEventListener('visibilitychange', schedule); reduced.addEventListener('change', motion);
    const resize = 'ResizeObserver' in window ? new ResizeObserver(resized) : null;
    resize?.observe(root); window.addEventListener('resize', resized);
    const intersection = 'IntersectionObserver' in window ? new IntersectionObserver((entries) => { onScreen = entries[0]?.isIntersecting === true; schedule(); }) : null;
    intersection?.observe(root); resized();
    return () => {
        clearTimeout(timer); clearTimeout(scrollTimer); resize?.disconnect(); intersection?.disconnect();
        document.removeEventListener('visibilitychange', schedule); window.removeEventListener('resize', resized); reduced.removeEventListener('change', motion);
        previous.removeEventListener('click', prevClick); next.removeEventListener('click', nextClick); pause?.removeEventListener('click', pauseClick);
        track.removeEventListener('keydown', keydown); track.removeEventListener('scrollend', scroll); track.removeEventListener('scroll', scrolling); track.removeEventListener('pointerdown', interaction); track.removeEventListener('wheel', interaction);
        root.removeEventListener('mouseenter', enter); root.removeEventListener('mouseleave', leave); root.removeEventListener('focusin', focusIn); root.removeEventListener('focusout', focusOut);
        slides.forEach((slide) => { slide.inert = false; });
    };
}

function comparison(root) {
    const range = root.querySelector('[data-comparison-range]');
    const control = root.querySelector('[data-comparison-control]');
    const frame = root.querySelector('.sb-comparison-images');
    if (!range || !control || !frame) return () => {};
    const update = () => {
        const value = boundedInteger(range.value, 0, 100, 50);
        frame.style.setProperty('--sb-compare-position', `${value}%`);
        range.setAttribute('aria-valuetext', `${value}% ${root.dataset.afterLabel || 'after'}`);
    };
    root.classList.add('is-enhanced'); control.hidden = false; update();
    range.addEventListener('input', update);
    return () => range.removeEventListener('input', update);
}

function hotspots(root) {
    const triggers = [...root.querySelectorAll('[data-hotspot-trigger]')];
    const panels = [...root.querySelectorAll('[data-hotspot-panel]')];
    const details = [...root.querySelectorAll('[data-hotspot-detail]')];
    let active = -1;
    const close = (restore = false) => {
        if (active < 0) return;
        panels[active].hidden = true; triggers[active].setAttribute('aria-expanded', 'false');
        const previous = active; active = -1;
        if (restore) triggers[previous].focus();
    };
    const open = (index) => {
        if (active === index) { close(true); return; }
        close(); active = index;
        const panel = panels[index];
        panel.hidden = false; triggers[index].setAttribute('aria-expanded', 'true');
        const anchor = triggers[index].getBoundingClientRect();
        const size = panel.getBoundingClientRect();
        panel.style.left = `${Math.max(8, Math.min(window.innerWidth - size.width - 8, anchor.left))}px`;
        panel.style.top = `${Math.max(8, Math.min(window.innerHeight - size.height - 8, anchor.bottom + 8))}px`;
        panel.querySelector('[data-hotspot-close]').focus();
    };
    const cleanups = [];
    triggers.forEach((trigger, index) => {
        if (!panels[index] || !details[index]) return;
        trigger.style.left = `clamp(1.375rem, ${boundedInteger(trigger.dataset.x, 0, 10000, 5000) / 100}%, calc(100% - 1.375rem))`;
        trigger.style.top = `clamp(1.375rem, ${boundedInteger(trigger.dataset.y, 0, 10000, 5000) / 100}%, calc(100% - 1.375rem))`;
        const copy = details[index].cloneNode(true); copy.removeAttribute('data-hotspot-detail');
        const content = document.createElement('div'); content.append(...copy.childNodes);
        panels[index].querySelector('[data-hotspot-copy]').replaceChildren(content);
        trigger.hidden = false;
        const click = () => open(index), dismiss = () => close(true);
        trigger.addEventListener('click', click);
        panels[index].querySelector('[data-hotspot-close]').addEventListener('click', dismiss);
        cleanups.push(() => { trigger.removeEventListener('click', click); panels[index].querySelector('[data-hotspot-close]').removeEventListener('click', dismiss); });
    });
    const outside = (event) => { if (active >= 0 && !panels[active].contains(event.target) && !triggers[active].contains(event.target)) close(); };
    const escape = (event) => { if (event.key === 'Escape' && active >= 0) { event.preventDefault(); event.stopPropagation(); close(true); } };
    const blur = (event) => { if (active >= 0 && !panels[active].contains(event.relatedTarget) && !triggers[active].contains(event.relatedTarget)) close(); };
    document.addEventListener('pointerdown', outside); root.addEventListener('keydown', escape); root.addEventListener('focusout', blur);
    return () => { close(); cleanups.forEach((cleanup) => cleanup()); document.removeEventListener('pointerdown', outside); root.removeEventListener('keydown', escape); root.removeEventListener('focusout', blur); };
}

export function initializeDiscovery(scope = document) {
    for (const [element, cleanup] of mounted) {
        if (!element.isConnected) { cleanup(); mounted.delete(element); }
    }
    for (const [selector, mount] of [['[data-sb-search]', search], ['[data-sb-carousel]', carousel], ['[data-sb-comparison]', comparison], ['[data-sb-hotspots]', hotspots]]) {
        for (const element of scope.querySelectorAll(selector)) {
            if (!mounted.has(element)) mounted.set(element, mount(element));
        }
    }
}

if (typeof document !== 'undefined') {
    const boot = () => {
        initializeDiscovery();
        new MutationObserver(() => initializeDiscovery()).observe(document.body, { childList: true, subtree: true });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true }); else boot();
}
