# AGENTS.md — wp-framework

Tool-agnostic brief for AI coding agents (Claude Code, Copilot coding agent, Codex). `rtcamp/wp-framework`: shared base contracts (interfaces, abstracts, traits) and small utilities consumed via Composer by every rtCamp plugin/theme skeleton. **Zero runtime dependencies.** PHP 8.2+.

## Authoritative rules

- `.github/instructions/php.instructions.md`: framework-development rules and review flags.
- `.github/copilot-instructions.md`: overview + review conduct.

## Key principles (full detail in the files above)

- **`inc/Contracts/` is public API.** Interfaces, abstracts, and their method signatures are consumed by every plugin/theme: a signature change breaks all of them. Treat such changes as breaking; follow `docs/backward-compatibility.md`.
- **Zero runtime deps**: `composer.json` `require` holds only `php`; everything else is `require-dev`.
- **TDD**: failing PHPUnit test first (`tests/` mirrors `inc/`), then code.
- `declare( strict_types = 1 );`, full types, `@package`/`@since`, `static::` not `self::`, PSR-4 (`rtCamp\WPFramework\` → `inc/`).
- **When you change a contract, update `ai/framework-php.instructions.md`**: the rules file shipped to consumers and installed into their `.github/` by `bin/install-ai-instructions.php`.

## Structure

`inc/Contracts/{Interfaces,Abstracts,Traits}/` (the consumed contract surface), `inc/` root (`Container`, `AssetLoader`, `ComponentLoader`), `inc/Utils/`. `ai/` holds the canonical consumer instruction doc; `bin/` holds the installer (framework → package) and sync (package → wp-content root) tools.

## This repo also ships tooling for consumers

- `ai/framework-php.instructions.md`: the canonical framework+WordPress review rules.
- `bin/install-ai-instructions.php`: run from a consumer's Composer `post-install`/`post-update`; copies the rules into the consumer's `.github/instructions/`.
- `bin/sync-ai-instructions.js`: run from a consumer (`npm run sync-ai`); projects every package's instructions up to the `wp-content` repo root for Copilot review.
