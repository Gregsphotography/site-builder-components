import test from 'node:test';
import assert from 'node:assert/strict';
import { normalizeSearch, matchesItem } from '../resources/js/collections.js';

test('collection search folds accents and case while requiring every search term', () => {
    assert.equal(normalizeSearch('  CAFÉ Zürich  ', 'de'), 'cafe zurich');
    assert.equal(matchesItem('Café in Zürich', ['Places'], 'ZURICH café', '', 'de'), true);
    assert.equal(matchesItem('Café in Zürich', ['Places'], 'café bern', '', 'de'), false);
});

test('selected tags intersect text search and are matched exactly', () => {
    assert.equal(matchesItem('A studio', ['Créative work'], 'studio', 'creative work', 'en'), true);
    assert.equal(matchesItem('A studio', ['Creative work'], 'studio', 'work', 'en'), false);
    assert.equal(matchesItem('A studio', ['Workspaces'], '', '', 'en'), true);
});

test('query text is literal and cannot become a regular expression or HTML', () => {
    assert.equal(matchesItem('A [quiet] studio', [], '[quiet]', ''), true);
    assert.equal(matchesItem('A studio', [], '.*', ''), false);
    assert.equal(matchesItem('A studio', [], '<script>', ''), false);
});
