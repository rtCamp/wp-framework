# CLAUDE.md — `wp-php-toolkit`

Read this file at the start of every session. Topic-specific details live in [.claude/issues/](.claude/issues/) (per-task state) and [.claude/commands/](.claude/commands/) (skills). Read those only when the task requires.

---

## What this repo is

A Composer package of shared PHP utilities for rtCamp WordPress projects. Consumed by every rtCamp plugin skeleton (including `features-plugin-skeleton` and Aryan's frontend skeleton). Classes here are general-purpose — project-specific code belongs in the consuming skeleton, not here.

---

## Non-negotiables

- No runtime dependencies in `composer.json` `require` — dev tools are fine.
- `declare(strict_types=1);` at the top of every PHP file.
- Every public method has full type declarations on parameters and return.
- Every class and trait docblock has `@package` and `@since`.
- `ai_context()` methods never return SQL text, error messages, user data, or paths.
- Late static binding (`static::`) — never `self::` — for singleton access.

---

## Principle — prefer official WordPress tooling

When WordPress or `@wordpress/*` ships something that covers our need, we use it. Build custom only when there's a real gap — no official option, or the official option blocks a hard constraint (zero-runtime-deps, dev-mode guards, etc.).

Before adding any new tool, package, or convention: check if an official WordPress option already exists. If it does, use that and layer rtCamp-specific overrides on top.

---

## Language & versions

| | |
|---|---|
| PHP | 8.3+ (EOL Dec 2027) |
| PHPUnit | `^12.0` max — `yoast/phpunit-polyfills ^4.0` caps at v12 |
| PHPStan | `^2.0`, level 5 |
| PHPCS | WordPress-Core, WordPress-Extra, WordPress-Docs, WordPressVIPMinimum (VIPCS), PHPCompatibilityWP |
| Composer | 2.x |

**Banned in `require`:** anything. This package is zero-runtime-deps.

---

## Directory layout

```
src/
  Traits/         Singleton trait
  Utilities/      Logger, Cache, Transients, Feature_Flags, Performance, Security
  Dev/            Dev Monitor, collectors, interfaces, views
tests/            PHPUnit suite, same namespace layout as src/
composer.json
phpcs.xml         shared baseline — consumed by skeleton repos
phpstan.neon      shared baseline — consumed by skeleton repos
CHANGELOG.md
```

PSR-4 autoload: `RtCamp\WPToolkit\{Path}\{Class}` → `src/{Path}/{Class}.php`. File names match class names exactly.

---

## Architecture patterns

Three first-class instantiation patterns. Pick the right one — don't default to Singleton.

| Pattern | Use when | Examples |
|---|---|---|
| **Singleton** (`use Singleton;`) | Shared state across the request, hook wiring once | `Logger`, `Cache`, `Dev_Monitor`, every collector |
| **Multi-instance** (`__construct` with config) | Per-call config, isolated state between instances | `Transients`, base classes prefixed `Abstract_` |
| **Stateless helper** (`final class`, `private __construct()`) | Pure functions, no state at all | `AI_Data_Sanitizer` |

Rule of thumb: default to multi-instance unless there's a specific reason to share state.

Singleton classes must have an empty `public function setup(): void {}` stub — the boot sequence requires it.

---

## Coding standards

- PHPCS WordPress standard — zero errors before PR.
- PHPStan level 5 — zero errors before PR.
- No `mixed` return type unless genuinely unavoidable — flag with a code comment when used.
- Method names: `snake_case`. Class and trait names: `PascalCase`.
- No static calls to WordPress functions in class properties — only inside methods.

---

## Testing

- Every new class gets at least one PHPUnit test.
- Tests live in `tests/<Subfolder>/<Name>Test.php`, mirroring `src/`.
- Test namespace: `RtCamp\WPToolkit\Tests\<Subfolder>`.
- Run full suite: `composer test` (alias for `vendor/bin/phpunit`).
- WordPress test lib setup: `bin/install-wp-tests.sh wordpress_test root '' localhost latest`.

---

## Git workflow

- Every milestone has a long-lived release branch (`release/v1.0.0`). Never merge directly into `main`.
- Task branches: `v1.0.0/task/<kebab-slug>`, based on `release/v1.0.0`.
- Commit style: [Conventional Commits](https://www.conventionalcommits.org/) — `feat(traits): add Singleton trait`.
- PR title: `[v1.0.0] <commit subject>`. PR target: `release/v1.0.0`.
- Squash merge. Never `--no-verify`.

---

## Working on an issue

When the user references a GitHub issue (`#13`, "issue 13", "the Singleton task"):

1. Check `.claude/issues/<N>-<slug>.md`.
2. **File exists** → the issue is in progress or complete. Read it for the current state: decisions made, files changed, verification output, open questions. Continue from there.
3. **File missing** → the issue has not been started. Copy `.claude/issues/_TEMPLATE.md` to `.claude/issues/<N>-<slug>.md`, fill the `Summary` from the GitHub issue, set `status: in-progress`. Commit this file as part of the first commit on the task branch.

The issue file is the single source of truth for what's been decided and done on this task. Update it as work progresses — decisions, files changed, verification output, open questions. It replaces any handoff Slack message when an engineer rotates out.

### Rotation protocol (seniors at 4h/day may rotate mid-issue)

- **Leaving an issue:** run `/handoff out` — Claude generates the log entry + a GitHub issue comment. Push WIP (`wip:` commit is fine), apply `Status: Blocked`, post the comment.
- **Picking up an issue:** pull the branch, run `composer check` to confirm reproducibility, then `/handoff in`. Remove `Status: Blocked`, post the comment.
- The outgoing entry must be detailed enough that the incoming engineer needs **zero questions**. That's the quality bar.

---

## Available skills

Invoked with `/<command>` in chat. Each loads its own instructions only when used.

- `/add-utility <name>` — scaffold a new utility class with Singleton, `setup()`, tests
- `/add-collector <name>` — scaffold a Dev Monitor collector with the right interfaces
- `/review-collector <path>` — audit a collector for interface + privacy compliance
- `/check-privacy` — scan every `ai_context()` method for banned data references
- `/add-interface <name>` — scaffold a new collector interface
- `/review-module <path>` — general PHP class review against this repo's conventions
- `/handoff [out|in]` — generate a rotation handoff log entry + GitHub comment

---

## PR authoring

Use the template at `.github/pull_request_template.md`. The PR description mirrors the issue structure so reviewers read both side-by-side.

Draft the PR body from the issue file:

- PR "What this does" ← issue file `Summary`
- PR "Changes" ← issue file `Files changed so far`
- PR "How I verified" ← issue file `Verification run`
- PR "Reviewer notes" ← issue file `Notes for the reviewer`

Always include `Closes #<N>` in the PR body so the GitHub Project card auto-moves to Done on merge.

---

## User preferences

- No auto-commits — the developer runs all `git` commands themselves.
- Brief, direct replies in chat.
- Don't create unsolicited documentation files.
- British English in prose; American English in code identifiers.
- Don't add emojis to files unless asked.
