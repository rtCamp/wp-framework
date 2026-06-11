# Telemetry: Query Monitor → MCP for AI coding agents

A local-dev-only module that makes the data [Query Monitor](https://wordpress.org/plugins/query-monitor/) already collects — slow/duplicate DB queries, outbound HTTP calls, PHP errors, each with the file and line that caused it — queryable by the AI coding agent sitting next to your source (Claude Code, Opencode, Cursor).

The agent gets two things side by side: **what happened at runtime** (telemetry with host-resolved file paths) and **the code that caused it** (the repo it's already open in). That pairing lets it fix problems, not just describe them.

## How it works

```
Dev browses local site (QM + Telemetry module active)
        │  every request captured at shutdown → MySQL tables
        ▼
wp-framework abilities ──► MCP Adapter ──MCP (STDIO or HTTP)──► Claude Code / Opencode ──opens──► source files (host paths)
```

- Every request is captured at `shutdown` (after QM's own dispatch), normalized once, and persisted to two capped tables (`{$prefix}rt_framework_telemetry_requests` / `_request_data`, ring buffer of 200 by default). QM itself only ever holds the current request — persistence is what lets an ability read it back from a *different* request (the MCP call).
- Four [Abilities](https://make.wordpress.org/core/2025/07/17/abilities-api/) (WordPress 6.9+) are registered under the `wp-framework` category and flagged `meta.mcp.public`, so the official [MCP Adapter](https://github.com/WordPress/mcp-adapter) auto-exposes them as MCP tools with **zero glue code**:

| Ability | Purpose |
|---|---|
| `wp-framework/list-requests` | Recent captures with headline metrics — pick a request id here first |
| `wp-framework/get-telemetry` | Full normalized QM data (queries + stacks, HTTP, errors, assets, timing) with **host file paths** |
| `wp-framework/compare-requests` | Before/after metric deltas — proof a fix worked |
| `wp-framework/profile-url` | Capture fresh telemetry via HTTP loopback, multi-sample medians |

- The agent's "connection with code" needs nothing from MCP: it already has filesystem access to your repo. The bridge is the `host_file` + `line` on every stack frame in `get-telemetry` output.

The typical loop: `profile-url` → `get-telemetry` → agent opens the offending file → writes the fix → re-profiles → `compare-requests` confirms the improvement.

## Setup

### 1. Load the module (skeleton)

Add the module to the class list your plugin loads through the framework's `Loader`:

```php
protected function get_classes(): array {
	return [
		// ...your modules...
		\rtCamp\WPFramework\Telemetry\TelemetryModule::class, // no-ops outside gated local dev
	];
}
```

Safe to ship permanently: the module registers nothing unless **both** gates pass (see below).

### 2. wp-config (local only)

```php
define( 'WP_ENVIRONMENT_TYPE', 'local' );
define( 'RT_FRAMEWORK_DEV_MODE', true );

// Docker/wp-env only — map container paths to host paths the agent can open:
define( 'RT_FRAMEWORK_TELEMETRY_CONTAINER_ROOT', '/var/www/html/wp-content/plugins/my-plugin' );
define( 'RT_FRAMEWORK_TELEMETRY_HOST_ROOT', '/Users/me/projects/my-plugin' );

// Docker/wp-env only — a base URL reachable from inside the container:
define( 'RT_FRAMEWORK_TELEMETRY_LOOPBACK_BASE', 'http://localhost' );
```

The raw telemetry (real SQL, paths, errors) must never be reachable in production: the module is hard-gated to `wp_get_environment_type() === 'local'` **and** `RT_FRAMEWORK_DEV_MODE`, and every ability additionally requires `manage_options`. The `rt_framework_telemetry_enabled` filter can disable further but can never force-enable.

### 3. Install the two plugins (not Composer dependencies)

```sh
wp plugin install query-monitor --activate   # the telemetry source
# MCP Adapter: https://github.com/WordPress/mcp-adapter/releases (install + activate)
```

### 4. Connect your agent

WordPress + the MCP Adapter is the MCP **server**; Claude Code / Opencode are **clients**. Two transports:

**STDIO (default for local dev).** The client spawns a WP-CLI process; no network, no password — auth is "you can run WP-CLI as that user". `.mcp.json` (Claude Code):

```json
{
	"mcpServers": {
		"wp-framework": {
			"command": "wp",
			"args": ["mcp-adapter", "serve", "--server=mcp-adapter-default-server", "--user=admin"]
		}
	}
}
```

With wp-env, replace the command with `"command": "npm", "args": ["run", "--silent", "env:cli", "--", "wp", "mcp-adapter", "serve", ...]`.

Opencode (`opencode.json`):

```json
{
	"mcp": {
		"wp-framework": {
			"type": "local",
			"command": ["wp", "mcp-adapter", "serve", "--server=mcp-adapter-default-server", "--user=admin"]
		}
	}
}
```

**HTTP (alternative — one persistent server, multiple clients).** The Adapter registers a REST route; auth via an application password:

```sh
wp user application-password create admin telemetry-mcp --porcelain
```

```json
{
	"mcpServers": {
		"wp-framework": {
			"type": "http",
			"url": "http://localhost:8888/wp-json/mcp/mcp-adapter-default-server",
			"headers": { "Authorization": "Basic <base64 of user:app-password>" }
		}
	}
}
```

Opencode uses `"type": "remote"` with the same `url`/`headers`. Known caveat: Claude Code may ignore static `Authorization` headers when the server advertises OAuth ([anthropics/claude-code#59467](https://github.com/anthropics/claude-code/issues/59467)) — verify with `curl -H "Authorization: Basic ..." <url>` and fall back to STDIO if it bites.

## Configuration reference

| Constant | Default | Purpose |
|---|---|---|
| `RT_FRAMEWORK_DEV_MODE` | undefined (off) | Master switch for the dev layer |
| `RT_FRAMEWORK_TELEMETRY_RETENTION` | 200 (floor 10) | Captured requests kept (ring buffer) |
| `RT_FRAMEWORK_TELEMETRY_CONTAINER_ROOT` | — | Container path prefix of your project |
| `RT_FRAMEWORK_TELEMETRY_HOST_ROOT` | — | Host path prefix of your project |
| `RT_FRAMEWORK_TELEMETRY_LOOPBACK_BASE` | `home_url()` | Base URL for profile-url loopbacks |

| Filter | Purpose |
|---|---|
| `rt_framework_telemetry_enabled` | Disable the module even on gated local (cannot enable elsewhere) |
| `rt_framework_telemetry_retention` | Adjust the ring-buffer size |
| `rt_framework_telemetry_path_map` | Add container→host prefix mappings (e.g. a mounted theme) |

Headers: loopback profile requests carry `X-WP-Framework-Profile: <batch>`; send `X-WP-Framework-Ignore: 1` on internal sub-requests that should not be captured. Unmappable backtrace paths (e.g. WP core inside wp-env's hidden install) yield `host_file: null` — never a guessed path.

## Notes & gotchas

- **QM collects nothing for non-viewers.** The module grants `view_query_monitor` to all users via `user_has_cap` — required for anonymous/loopback captures, and safe only because the module exists exclusively on gated local environments.
- **WP-CLI requests are never captured** (QM doesn't run its pipeline there). The STDIO MCP server *is* a WP-CLI process, which is exactly why `profile-url` issues real HTTP loopbacks instead of profiling in-process.
- **Tables are created lazily** via `dbDelta` keyed on the `rt_framework_telemetry_schema` option (a Composer library has no activation hook).
- **A capture failure never breaks the request** — errors are logged to the debug log with the `[wp-framework telemetry]` prefix and swallowed.

## Manual verification checklist

Things unit tests can't cover (they need a real WP + QM install):

1. Browse a few pages → rows appear in both telemetry tables; `wp option get rt_framework_telemetry_schema` returns the schema version.
2. From the connected agent: `list-requests` → `get-telemetry` (host paths resolve to openable files) → `profile-url` on the home page → `compare-requests` across two captures.
3. Negative: with `WP_ENVIRONMENT_TYPE` ≠ local, no hooks register and no tables are created; with QM deactivated, capture no-ops silently; without the MCP Adapter, abilities simply aren't exposed (no errors).
