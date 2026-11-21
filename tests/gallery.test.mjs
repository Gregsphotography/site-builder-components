import test from 'node:test';
import assert from 'node:assert/strict';
import { adjacentIndex, arrowDelta, updateNavigation } from '../resources/js/gallery.js';

test('gallery movement stops at both bounds and visual arrows reverse in RTL', () => {
    let current = 0;
    for (const key of ['ArrowLeft', 'ArrowRight', 'ArrowRight', 'ArrowRight']) current = adjacentIndex(current, arrowDelta(key, false), 3);
    assert.equal(current, 2);
    for (const key of ['ArrowRight', 'ArrowRight', 'ArrowRight']) current = adjacentIndex(current, arrowDelta(key, true), 3);
    assert.equal(current, 0);
    assert.equal(arrowDelta('Tab', true), 0);
    assert.equal(adjacentIndex(0, 1, 1), 0);
});

test('missing document/native browser support leaves the import usable for server-side tooling', async () => {
    const { initializeGalleries } = await import('../resources/js/gallery.js');
    const staticRoot = { querySelector: () => null };
    assert.doesNotThrow(() => initializeGalleries({ querySelectorAll: () => [staticRoot] }));
    assert.doesNotThrow(() => initializeGalleries({ querySelectorAll: () => [{ querySelector: () => ({}) }] }));
});

test('focus stays inside the lightbox when the active navigation button becomes disabled', () => {
    const ownerDocument = { activeElement: null };
    const button = () => ({
        ownerDocument,
        enabled: true,
        get disabled() { return !this.enabled; },
        set disabled(value) {
            this.enabled = !value;
            if (value && ownerDocument.activeElement === this) ownerDocument.activeElement = null;
        },
        focus() { ownerDocument.activeElement = this; },
    });
    const previous = button(), next = button(), close = button();
    next.focus();
    updateNavigation(previous, next, close, 1, 2);
    assert.equal(ownerDocument.activeElement, previous);
    updateNavigation(previous, next, close, 0, 2);
    assert.equal(ownerDocument.activeElement, next);
    updateNavigation(previous, next, close, 0, 1);
    assert.equal(ownerDocument.activeElement, close);
});
