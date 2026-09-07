import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import sourceLinks from '../source-links.mjs';

test('repository links resolve without changing doc links, code examples or source files', () => {
  const source = mkdtempSync(path.join(tmpdir(), 'docs-links-'));
  try {
    const docs = path.join(source, 'docs');
    mkdirSync(docs);
    mkdirSync(path.join(source, 'inc'));
    writeFileSync(path.join(source, 'inc', 'Example.php'), '<?php');
    const tree = { children: [
      { type: 'link', url: '../inc/Example.php?plain=1#L1' },
      { type: 'link', url: '../inc/' },
      { type: 'link', url: 'architecture.md#overview' },
      { type: 'link', url: 'https://example.com' },
      { type: 'code', value: '[example](../inc/Example.php)' },
    ] };
    sourceLinks({ source, docs, repository: 'example/project', sourceRef: 'main' })(tree, { path: path.join(docs, 'index.md') });
    assert.equal(tree.children[0].url, 'https://github.com/example/project/blob/main/inc/Example.php?plain=1#L1');
    assert.equal(tree.children[1].url, 'https://github.com/example/project/tree/main/inc');
    assert.equal(tree.children[2].url, 'architecture.md#overview');
    assert.equal(tree.children[3].url, 'https://example.com');
    assert.equal(tree.children[4].value, '[example](../inc/Example.php)');
    assert.throws(() => sourceLinks({ source, docs, repository: 'example/project', sourceRef: 'main' })({ type: 'link', url: '../missing.php' }, { path: path.join(docs, 'index.md') }), /Unresolved repository link/);
  } finally { rmSync(source, { recursive: true, force: true }); }
});
