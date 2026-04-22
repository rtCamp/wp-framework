# wp-php-toolkit

Shared PHP utilities for rtCamp WordPress projects. Consumed as a Composer package (`rtcamp/wp-php-toolkit`) by every rtCamp plugin skeleton.

## What's inside

- `Singleton` trait — standard `get_instance()` pattern
- Utility classes — `Logger`, `Cache`, `Transients`, `Performance` (timers)
- Collector interfaces consumed by `rtcamp/wp-dev-monitor` — `Collector_Interface`, `Stoppable`, `Profilable`, `Renderable`, `Issue_Provider`, `AI_Context_Provider`, `Timeline_Event_Provider`
- Shared PHPCS and PHPStan baselines

## What's NOT here (intentional)

The Dev Monitor panel itself — collectors, views, assets, AI chatbox — lives in [`rtcamp/wp-dev-monitor`](https://github.com/rtCamp/wp-dev-monitor). This package only ships the interfaces those collectors implement, so both packages can evolve independently.

## Development

See [`CLAUDE.md`](./CLAUDE.md) for architecture rules, conventions, testing, and git workflow.

Per-issue progress lives in [`.claude/issues/`](./.claude/issues/). Claude skills live in [`.claude/commands/`](./.claude/commands/).

## Install (consumer side)

```bash
composer require rtcamp/wp-php-toolkit
```

## License

GPL-2.0-or-later
