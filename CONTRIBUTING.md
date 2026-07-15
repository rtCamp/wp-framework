# Contributing to wp-framework

Thanks for your interest in improving `rtcamp/wp-framework`. This is a
**library** — a shared PHP base consumed as a Composer package by rtCamp
WordPress plugins and themes — so its public contract (`inc/Contracts/`) is
treated as stable and changes to it are considered breaking.

## Ground rules

- **PHP 8.2+.** The package ships **zero runtime dependencies**.
- **The contract surface is the API.** Any change to an interface, abstract, or
  public signature under `inc/Contracts/` is a breaking change — call it out
  explicitly in your PR.
- **Conventions live in [AGENTS.md](AGENTS.md)** (shared across all
  contributors and AI tools). Read it before your first PR.

## Development setup

```bash
# 1. Install PHP dev dependencies
composer install

# 2. Bring up WordPress for the integration tests (Docker required)
npm install
npm run wp-env start    # starts @wordpress/env
```

## Before you open a PR

Run the full check suite locally — all of it must exit `0`:

```bash
composer check     # PHPCS (lint) + PHPStan (analyse) + PHPUnit (test)
```

Individual steps:

```bash
composer lint      # PHPCS against WordPress Coding Standards
composer lint:fix  # auto-fix fixable violations
composer analyse   # PHPStan static analysis
composer test      # PHPUnit
```

Tests run against real WordPress via `@wordpress/env`. Follow TDD: add a failing
test under `tests/` (which mirrors `inc/`) first, then the implementation.

## Pull request checklist

- [ ] `composer check` passes (lint + analyse + test, all green).
- [ ] New/changed behavior is covered by tests.
- [ ] Any change to `inc/Contracts/` is flagged as breaking in the PR description.
- [ ] A `CHANGELOG.md` entry is added under `## [Unreleased]`.
- [ ] Commits follow [Conventional Commits](https://www.conventionalcommits.org/).

## Reporting security issues

Do **not** open a public issue for security vulnerabilities. Report them
privately via GitHub's [security advisory](https://github.com/rtCamp/wp-framework/security/advisories/new)
form, or email `security@rtcamp.com`.

## License

By contributing, you agree that your contributions are licensed under the
project's [GPL-2.0-or-later](LICENSE.md) license.
