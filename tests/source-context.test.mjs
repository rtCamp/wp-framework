import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, realpathSync, symlinkSync, rmSync } from 'node:fs';
import path from 'node:path';
import { tmpdir } from 'node:os';
import { sourceContext } from '../source-context.mjs';

test('requires an explicit external source checkout', () => {
  assert.throws(() => sourceContext({}), /Set DOCS_SOURCE/);
});

test('resolves defaults and nested docs, including paths with spaces', () => {
  const temp = mkdtempSync(path.join(tmpdir(), 'docs context '));
  try {
    mkdirSync(path.join(temp, 'docs'));
    mkdirSync(path.join(temp, 'manual', 'guide'), { recursive: true });
    const source = realpathSync(temp);
    assert.deepEqual(sourceContext({ DOCS_SOURCE: temp }), {
      source, docs: path.join(source, 'docs'), docsRelative: 'docs',
    });
    assert.equal(sourceContext({ DOCS_SOURCE: temp, DOCS_DIRECTORY: 'manual/guide' }).docs, path.join(source, 'manual/guide'));
    assert.throws(() => sourceContext({ DOCS_SOURCE: temp, DOCS_DIRECTORY: 'missing' }), /ENOENT/);
  } finally { rmSync(temp, { recursive: true, force: true }); }
});

test('rejects absolute paths, traversal and symlink escapes', () => {
  const temp = mkdtempSync(path.join(tmpdir(), 'docs-context-'));
  try {
    mkdirSync(path.join(temp, 'source')); mkdirSync(path.join(temp, 'outside'));
    const env = { DOCS_SOURCE: path.join(temp, 'source') };
    symlinkSync(path.join(temp, 'outside'), path.join(temp, 'source', 'linked'));
    assert.throws(() => sourceContext({ ...env, DOCS_DIRECTORY: path.join(temp, 'outside') }), /relative/);
    assert.throws(() => sourceContext({ ...env, DOCS_DIRECTORY: '../outside' }), /inside/);
    assert.throws(() => sourceContext({ ...env, DOCS_DIRECTORY: 'linked' }), /inside/);
  } finally { rmSync(temp, { recursive: true, force: true }); }
});
