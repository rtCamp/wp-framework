import path from 'node:path';
import { existsSync, statSync } from 'node:fs';

// Transform only links to repository files outside the documentation tree.
// Markdown links, images and their anchors inside docs remain Docusaurus-managed.
export default function sourceLinks({ source, docs, repository, sourceRef }) {
  return (tree, file) => {
    function visit(node) {
      if (['link', 'definition'].includes(node.type) && node.url && !/^(?:[a-z][a-z\d+.-]*:|\/|#)/i.test(node.url)) {
        const suffixAt = node.url.search(/[?#]/);
        const pathname = suffixAt === -1 ? node.url : node.url.slice(0, suffixAt);
        const suffix = suffixAt === -1 ? '' : node.url.slice(suffixAt);
        const target = path.resolve(path.dirname(file.path), decodeURIComponent(pathname));
        const fromDocs = path.relative(docs, target);
        const fromSource = path.relative(source, target);
        if (fromDocs === '..' || fromDocs.startsWith(`..${path.sep}`)) {
          if (fromSource === '..' || fromSource.startsWith(`..${path.sep}`) || !existsSync(target)) {
            throw new Error(`Unresolved repository link in ${file.path}: ${node.url}`);
          }
          const type = statSync(target).isDirectory() ? 'tree' : 'blob';
          node.url = `https://github.com/${repository}/${type}/${sourceRef}/${fromSource.split(path.sep).map(encodeURIComponent).join('/')}${suffix}`;
        }
      }
      node.children?.forEach(visit);
    }
    visit(tree);
  };
}
