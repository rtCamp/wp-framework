import path from 'node:path';
import { sourceContext } from './source-context.mjs';
import sourceLinks from './source-links.mjs';

const { source, docs, docsRelative } = sourceContext();
const repository = process.env.GITHUB_REPOSITORY || 'rtCamp/wp-framework';
if (!/^[\w.-]+\/[\w.-]+$/.test(repository)) throw new Error('Expected GITHUB_REPOSITORY as owner/name');
const [owner, title] = repository.split('/');
const pagesUrl = process.env.PAGES_BASE_URL ? new URL(process.env.PAGES_BASE_URL) : null;
const sourceRef = process.env.DOCS_SOURCE_REF || 'main';
const encodePath = value => value.split(path.sep).map(encodeURIComponent).join('/');
export default {
  title,
  url: pagesUrl?.origin || `https://${owner.toLowerCase()}.github.io`,
  baseUrl: pagesUrl
    ? `${pagesUrl.pathname.replace(/\/$/, '')}/`
    : (title.toLowerCase() === `${owner.toLowerCase()}.github.io` ? '/' : `/${title}/`),
  trailingSlash: true,
  onBrokenLinks: 'throw',
  onBrokenAnchors: 'throw',
  markdown: { format: 'detect', hooks: { onBrokenMarkdownLinks: 'throw' } },
  presets: [['classic', {
    docs: {
      path: docs,
      routeBasePath: '/',
      sidebarPath: './sidebars.js',
      editUrl: ({ docPath }) => `https://github.com/${repository}/edit/${sourceRef}/${encodePath(docsRelative)}/${encodePath(docPath)}`,
      beforeDefaultRemarkPlugins: [[sourceLinks, { source, docs, repository, sourceRef }]],
    },
    blog: false,
    pages: false,
    theme: { customCss: './style.css' },
  }]],
  themeConfig: {
    navbar: { title, items: [
      { type: 'docSidebar', sidebarId: 'docs', label: 'Documentation', position: 'left' },
      { href: `https://github.com/${repository}`, label: 'GitHub', position: 'right' },
    ] },
    colorMode: { respectPrefersColorScheme: true },
    prism: { additionalLanguages: ['php', 'bash', 'json'] },
    footer: { style: 'dark', copyright: `${title} · ${owner}` },
  },
};
