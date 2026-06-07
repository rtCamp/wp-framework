# AI review & instructions system

How GitHub Copilot code-review instructions and AI-agent guidance are authored, distributed, and kept in sync across the framework, the plugin/theme skeletons, and assembled `wp-content` projects.

## The problem it solves

1. **Copilot code review only reads instruction files from the *repo root* `.github/`.** It ignores nested `.github/` directories.
2. Our code lives in **three kinds of repo**, with **two different roots**:
   - a **standalone plugin/theme** repo → root *is* the package;
   - an **assembled `wp-content`** repo → root is `wp-content`, packages are nested under `plugins/<slug>/` and `themes/<slug>/`.
3. The framework is a **Composer dependency** of the packages (lands in each package's gitignored `vendor/`), so it is **invisible at review**: its contract rules must be embedded in the consumers.
4. The same rules must reach **two audiences**: Copilot **code review** (`.github/`) and **AI coding agents** (Claude Code, Copilot coding agent, Codex → `AGENTS.md` / `CLAUDE.md`).

## The pieces

| File | Repo | Role |
|---|---|---|
| `ai/framework-php.instructions.md` | framework | **Canonical** framework + WordPress review rules. Single source of truth. |
| `bin/install-ai-instructions.php` | framework | Copies the canonical rules into a consumer's `.github/instructions/`. Runs from the consumer's Composer hooks. |
| `bin/sync-ai-instructions.js` | framework | Projects every package's `.github/instructions/` up to the `wp-content` root, re-globbing `applyTo`. Runs from `npm run sync-ai`. |
| `.github/copilot-instructions.md` | each package | Repo-wide overview + review conduct (skeleton-authored). |
| `.github/instructions/structure.instructions.md` | each package | Package layout + wiring (skeleton-authored, carries the package's names). |
| `.github/instructions/framework-php.instructions.md` | each package | **Generated** by the installer from the framework. Banner-marked; do not hand-edit. |
| `AGENTS.md` | each package | Tool-agnostic brief for coding agents. Inlines key principles + points to `.github/`. |
| `CLAUDE.md` | each package | Thin; defers to `AGENTS.md`, holds any Claude-only overrides. |

## Data flow

```
framework  ai/framework-php.instructions.md   (edit rules here)
   │
   │  composer install/update  →  bin/install-ai-instructions.php   (per package)
   ▼
plugins/<slug>/.github/instructions/framework-php.instructions.md   (generated, committed)
   + structure.instructions.md, copilot-instructions.md             (skeleton-authored)
   │
   │  npm run init / npm run sync-ai  →  bin/sync-ai-instructions.js  (walks up to wp-content root)
   ▼
wp-content/.github/instructions/
   framework-php.instructions.md        applyTo: plugins/a/**/*.php,plugins/b/**/*.php,themes/c/**/*.php   (MERGED)
   <slug>-structure.instructions.md     applyTo: plugins/<slug>/inc/**                                      (per package)
```

- **Identical** files across packages (the framework-fed `php-wordpress`) merge into **one** file with a combined `applyTo`.
- **Unique** files (each package's `structure`, which carries its own names) are emitted **per package** as `<slug>-<name>`.

## Names vs globs — two transforms, two tools

| Transform | Done by | When |
|---|---|---|
| Placeholder names (`Project_Name`→real namespace, `project-name`→slug) | `bin/init.js` in the skeleton (now also processes `.github/`) | `npm run init` |
| Re-glob `applyTo` to the wp-content root + merge | `bin/sync-ai-instructions.js` (framework) | `npm run sync-ai` (chained after init) |

## Two review contexts, one source

| Context | Repo root | Copilot reads | Globs |
|---|---|---|---|
| Standalone plugin/theme | the package | the package's own `.github/` | root-relative (`**/*.php`, `inc/**`) |
| Assembled `wp-content` | `wp-content` | `wp-content/.github/` (projected) | prefixed (`plugins/<slug>/**`) |

The framework repo itself has its **own** `.github/` with framework-*development* rules; reviewed when you PR the framework, and does **not** flow into consumers (it's a vendored dependency, invisible at consumer review).

## Using it

- **Assemble a project**: drop packages into `wp-content/{plugins,themes}/` → `composer install` (each package's hook installs `php-wordpress`) → `npm install && npm run init` (sets names, then syncs the wp-content root).
- **Add another plugin later**: same `npm run init`; the sync discovers it and merges it into the shared `php-wordpress` `applyTo` + its own `structure` file.
- **Change a shared rule**: edit `ai/framework-php.instructions.md` in the framework → consumers pick it up on `composer update` → `npm run sync-ai`.
- **Change a package-specific rule**: edit that package's `.github/instructions/structure.instructions.md` → `npm run sync-ai`.
- **CI gate**: run `php vendor/rtcamp/wp-framework/bin/install-ai-instructions.php --check` (per package) and `npm run sync-ai -- --check` (at the wp-content root) to fail a PR if the committed instructions are stale. Copilot reads instructions from the **base branch**, so keeping `main` current is what matters.

## Constraints & guards

- **4k characters per instruction file**: Copilot only reads the first ~4000. Both tools warn (STDERR) when a generated file exceeds it; keep `ai/framework-php.instructions.md` lean.
- **`applyTo` accepts comma-separated globs**: relied on by the merge.
- Generated files carry a `GENERATED by …` banner; never hand-edit them; edit the source in the framework or skeleton.
- Third-party plugins in `wp-content` are **not** governed by these rules; exclude them from review via Copilot content-exclusion settings if needed.
