import test from 'node:test';
import assert from 'node:assert/strict';
import { adjacentIndex, arrowDelta } from '../resources/js/gallery.js';

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
