import path from 'node:path';
import { realpathSync } from 'node:fs';

/** Resolve external source/docs directories without allowing a checkout escape. */
export function sourceContext(env = process.env) {
  if (!env.DOCS_SOURCE) throw new Error('Set DOCS_SOURCE to the documentation source checkout');
  const source = realpathSync(path.resolve(env.DOCS_SOURCE));
  const directory = env.DOCS_DIRECTORY || 'docs';
  if (path.isAbsolute(directory)) throw new Error('DOCS_DIRECTORY must be relative to DOCS_SOURCE');
  const docs = realpathSync(path.resolve(source, directory));
  const docsRelative = path.relative(source, docs);
  if (docsRelative === '..' || docsRelative.startsWith(`..${path.sep}`) || path.isAbsolute(docsRelative)) {
    throw new Error('Documentation directory must be inside the source checkout');
  }
  return { source, docs, docsRelative };
}
