import test from 'node:test';
import assert from 'node:assert/strict';
import { boundedInteger, carouselWindow, initializeDiscovery } from '../resources/js/discovery.js';
import { openDialog } from '#site-builder-core-modals';

class Element extends EventTarget {
    constructor() { super(); this.attributes = new Map(); this.dataset = {}; this.children = []; this.hidden = false; this.value = ''; this.textContent = ''; this.id = ''; this.isConnected = true; }
    setAttribute(name, value) { this.attributes.set(name, value); }
    getAttribute(name) { return this.attributes.get(name) ?? null; }
    removeAttribute(name) { this.attributes.delete(name); }
    replaceChildren(...items) { this.children = items; }
    append(...items) { this.children.push(...items); }
    scrollIntoView() {}
}

const wait = (milliseconds) => new Promise(resolve => setTimeout(resolve, milliseconds));

test('live suggestions ignore out-of-order responses, escape their titles and preserve the query when Escape dismisses them', async () => {
    const input = new Element(), list = new Element(), status = new Element(), form = new Element();
    list.id = 'suggestions';
    form.dataset = { endpoint: '/suggest', locale: 'de' };
    form.querySelector = selector => ({ 'input[name="q"]': input, '[data-suggestions]': list, '[data-search-status]': status })[selector];
    const pending = [];
    const originalFetch = globalThis.fetch;
    globalThis.document = { activeElement: input, createElement: () => new Element() };
    globalThis.window = { location: { href: 'https://example.test/', assign: () => {} } };
    globalThis.fetch = (url, options) => new Promise(resolve => pending.push({ url, options, resolve }));
    try {
        initializeDiscovery({ querySelectorAll: selector => selector === '[data-sb-search]' ? [form] : [] });
        input.value = 'older'; input.dispatchEvent(new Event('input')); await wait(330);
        input.value = 'newer'; input.dispatchEvent(new Event('input')); await wait(330);
        assert.equal(pending.length, 2);
        assert.equal(pending[0].options.signal.aborted, true);
        assert.equal(pending[1].url.searchParams.get('locale'), 'de');
        pending[1].resolve({ ok: true, json: async () => ({ items: [{ title: '<img onerror="bad()">', url: '/newer' }, { title: 'Unsafe destination', url: 'javascript:bad()' }] }) });
        await wait(0);
        pending[0].resolve({ ok: true, json: async () => ({ items: [{ title: 'Stale result', url: '/older' }] }) });
        await wait(0);
        assert.equal(list.children.length, 1);
        assert.equal(list.children[0].textContent, '<img onerror="bad()">');
        assert.equal(input.getAttribute('aria-expanded'), 'true');
        const escape = new Event('keydown', { cancelable: true }); Object.defineProperty(escape, 'key', { value: 'Escape' });
        input.dispatchEvent(escape);
        assert.equal(escape.defaultPrevented, true, 'Prevent native search-input clearing while closing the suggestion popup.');
        assert.equal(input.value, 'newer');
        assert.equal(input.getAttribute('aria-expanded'), 'false');
        const closedEscape = new Event('keydown', { cancelable: true }); Object.defineProperty(closedEscape, 'key', { value: 'Escape' });
        input.dispatchEvent(closedEscape);
        assert.equal(closedEscape.defaultPrevented, false, 'Do not trap Escape when a surrounding native dialog should handle it.');
    } finally {
        form.isConnected = false; initializeDiscovery({ querySelectorAll: () => [] });
        globalThis.fetch = originalFetch; delete globalThis.document; delete globalThis.window;
    }
});

test('carousel windows end on complete visible groups without inventing or duplicating slides', () => {
    assert.deepEqual(carouselWindow(12, 7, 3), { start: 4, end: 7, maximum: 4 });
    assert.deepEqual(carouselWindow(-1, 3, 4), { start: 0, end: 3, maximum: 0 });
    assert.deepEqual(carouselWindow(2, 3, 1), { start: 2, end: 3, maximum: 2 });
    assert.equal(boundedInteger('10001', 0, 10000, 5000), 10000);
    assert.equal(boundedInteger('42.5', 0, 10000, 5000), 5000);
});

test('the actual carousel engine pauses automatic movement for hover, hidden pages, user choice and reduced motion', (context) => {
    context.mock.timers.enable({ apis: ['setTimeout'] });
    const root = new Element(), track = new Element(), controls = new Element(), previous = new Element(), next = new Element(), pause = new Element(), position = new Element(), note = new Element(), media = new Element();
    let offset = 0;
    const slides = [0, 1, 2].map(index => { const slide = new Element(); slide.contains = () => false; slide.getBoundingClientRect = () => ({ left: 16 + index * 420 - offset, right: 16 + index * 420 - offset + 400 }); return slide; });
    root.dataset = { perView: '1', autoplay: 'true', interval: '3' }; root.clientWidth = 400;
    root.classList = { add() {} }; root.style = { setProperty() {} }; root.contains = () => false;
    root.querySelectorAll = () => slides;
    root.querySelector = selector => ({ '[data-carousel-track]': track, '[data-carousel-controls]': controls, '[data-carousel-prev]': previous, '[data-carousel-next]': next, '[data-carousel-pause]': pause, '[data-carousel-position]': position, '[data-carousel-motion-note]': note })[selector];
    track.getBoundingClientRect = () => ({ left: 0, right: 400 }); track.scrollBy = ({ left }) => { offset += left; };
    track.scrollTo = ({ left }) => { offset = left; };
    pause.dataset = { playLabel: 'Play', pauseLabel: 'Pause' }; media.matches = false;
    globalThis.document = new Element(); document.hidden = false; document.activeElement = null;
    globalThis.window = new Element(); window.matchMedia = () => media;
    globalThis.getComputedStyle = () => ({ direction: 'ltr' });
    try {
        initializeDiscovery({ querySelectorAll: selector => selector === '[data-sb-carousel]' ? [root] : [] });
        assert.equal(offset, 0, 'A padded track must remain at its first slide during initialization.');
        assert.equal(position.textContent, '1–1 of 3');
        context.mock.timers.tick(3000);
        assert.equal(position.textContent, '2–2 of 3');
        root.dispatchEvent(new Event('mouseenter')); context.mock.timers.tick(9000);
        assert.equal(position.textContent, '2–2 of 3');
        root.dispatchEvent(new Event('mouseleave')); context.mock.timers.tick(3000);
        assert.equal(position.textContent, '3–3 of 3');
        document.hidden = true; document.dispatchEvent(new Event('visibilitychange')); context.mock.timers.tick(9000);
        assert.equal(position.textContent, '3–3 of 3');
        document.hidden = false; document.dispatchEvent(new Event('visibilitychange'));
        pause.dispatchEvent(new Event('click')); context.mock.timers.tick(9000);
        assert.equal(pause.textContent, 'Play'); assert.equal(position.textContent, '3–3 of 3');
        pause.dispatchEvent(new Event('click')); context.mock.timers.tick(3000);
        assert.equal(position.textContent, '1–1 of 3');
        media.matches = true; media.dispatchEvent(new Event('change')); context.mock.timers.tick(9000);
        assert.equal(position.textContent, '1–1 of 3'); assert.equal(pause.disabled, true); assert.equal(note.hidden, false);
        media.matches = false; media.dispatchEvent(new Event('change')); context.mock.timers.tick(9000);
        assert.equal(position.textContent, '1–1 of 3', 'A motion preference change must not silently restart playback.');
    } finally {
        root.isConnected = false; initializeDiscovery({ querySelectorAll: () => [] });
        context.mock.timers.reset(); delete globalThis.document; delete globalThis.window; delete globalThis.getComputedStyle;
    }
});

test('padded carousels retain their position across resizing and move one visible group in either direction', context => {
    context.mock.timers.enable({ apis: ['setTimeout'] });
    for (const direction of ['ltr', 'rtl']) {
        const root = new Element(), track = new Element(), controls = new Element(), previous = new Element(), next = new Element(), position = new Element(), media = new Element();
        let offset = 0, behavior;
        const sign = direction === 'rtl' ? -1 : 1;
        const slides = [0, 1, 2].map(index => {
            const slide = new Element(); slide.contains = () => false;
            slide.getBoundingClientRect = () => direction === 'rtl'
                ? { left: -16 - index * 420 - offset, right: 384 - index * 420 - offset }
                : { left: 16 + index * 420 - offset, right: 416 + index * 420 - offset };
            return slide;
        });
        root.dataset = { perView: '2', autoplay: 'false', interval: '3' }; root.clientWidth = 1000;
        root.classList = { add() {} }; root.style = { setProperty() {} }; root.contains = () => false;
        root.querySelectorAll = () => slides;
        root.querySelector = selector => ({ '[data-carousel-track]': track, '[data-carousel-controls]': controls, '[data-carousel-prev]': previous, '[data-carousel-next]': next, '[data-carousel-position]': position })[selector];
        track.getBoundingClientRect = () => ({ left: 0, right: 400 });
        track.scrollBy = ({ left }) => { offset += left; };
        track.scrollTo = options => { offset = options.left; behavior = options.behavior; };
        media.matches = false;
        globalThis.document = new Element(); document.hidden = false; document.activeElement = null;
        globalThis.window = new Element(); window.matchMedia = () => media;
        globalThis.getComputedStyle = () => ({ direction });
        try {
            initializeDiscovery({ querySelectorAll: selector => selector === '[data-sb-carousel]' ? [root] : [] });
            assert.equal(offset, 0); assert.equal(behavior, 'instant');
            window.dispatchEvent(new Event('resize')); context.mock.timers.tick(9000);
            assert.equal(offset, 0, 'Initialization and repeated resize must not advance a non-autoplay carousel.');
            assert.equal(position.textContent, '1–2 of 3');
            next.dispatchEvent(new Event('click'));
            assert.equal(offset, sign * 420); assert.equal(position.textContent, '2–3 of 3');
            assert.equal(behavior, 'smooth');
            root.clientWidth = 390; window.dispatchEvent(new Event('resize'));
            assert.equal(offset, sign * 420); assert.equal(position.textContent, '2–2 of 3');
            assert.equal(behavior, 'instant');
            next.dispatchEvent(new Event('click'));
            assert.equal(offset, sign * 840); assert.equal(position.textContent, '3–3 of 3');
            previous.dispatchEvent(new Event('click'));
            assert.equal(offset, sign * 420); assert.equal(position.textContent, '2–2 of 3');
        } finally {
            root.isConnected = false; initializeDiscovery({ querySelectorAll: () => [] });
            delete globalThis.document; delete globalThis.window; delete globalThis.getComputedStyle;
        }
    }
    context.mock.timers.reset();
});

test('nested native dialogs restore focus to their own trigger and keep scrolling locked until the last owned modal closes', () => {
    class Dialog extends EventTarget {
        open = false;
        showModal() { this.open = true; }
        close() { this.open = false; this.dispatchEvent(new Event('close')); }
    }
    const parent = new Dialog(), child = new Dialog(), focused = [];
    const trigger = name => ({ isConnected: true, closest: selector => selector === 'dialog' && name === 'gallery' ? parent : null, focus: () => focused.push(name) });
    globalThis.document = { documentElement: { style: { overflow: 'clip' } } };
    try {
        assert.equal(openDialog(parent, trigger('popup')), true);
        assert.equal(openDialog(child, trigger('gallery')), true);
        child.close();
        assert.deepEqual(focused, ['gallery']);
        assert.equal(document.documentElement.style.overflow, 'hidden');
        parent.close();
        assert.deepEqual(focused, ['gallery', 'popup']);
        assert.equal(document.documentElement.style.overflow, 'clip');
    } finally {
        delete globalThis.document;
        delete globalThis[Symbol.for('site-builder.native-modals.v1')];
    }
});
