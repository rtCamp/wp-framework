# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- `Singleton` trait in `src/Traits/` providing the standard `get_instance()` pattern.
- `Logger` utility — PSR-3-style singleton logger (`debug`, `info`, `warning`, `error`) that writes to `error_log()` when `WP_DEBUG` is true; silent in production.
- `Transients` utility — multi-instance wrapper around WordPress transients with per-instance key prefixing to prevent collisions between modules.
- `Timer` utility — singleton with named start/stop timers (float seconds), lap/split support, and `get_all()` for bulk consumption by Dev Monitor's Timing collector.
- `bin/install-wp-tests.sh` — canonical WP-CLI scaffold script for provisioning the WordPress core test suite (`wp-tests-lib`) and a clean MySQL test database. PHPUnit boots real WordPress so utilities are tested end-to-end.
