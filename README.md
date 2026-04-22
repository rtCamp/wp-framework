# wp-php-toolkit

Shared PHP utilities for rtCamp WordPress projects. Consumed as a Composer package (`rtcamp/wp-php-toolkit`) by every rtCamp plugin skeleton.

## What's inside

- `Singleton` trait — standard `get_instance()` pattern
- Utility classes — `Logger`, `Cache`, `Transients`, `Feature_Flags`, `Performance`, `Security`
- Dev Monitor — unified dev panel with 14 PHP collectors + 7 interfaces
- Shared PHPCS and PHPStan baselines

## Development

See [`CLAUDE.md`](./CLAUDE.md) for architecture rules, conventions, testing, and git workflow.

Per-issue progress lives in [`.claude/issues/`](./.claude/issues/). Claude skills live in [`.claude/commands/`](./.claude/commands/).

## Install (consumer side)

```bash
composer require rtcamp/wp-php-toolkit
```

## License

GPL-2.0-or-later
