# Web-server MCP integration tests

This test tier boots a **real Apache 2.4 + PHP container** (PHP-FPM by default), enables the MCP
server, and makes **real HTTP requests** to its endpoint. It exists to cover the transport layer —
the seam between the web server and PHP — which the in-process tests in `tests/Integration/Mcp/`
cannot reach because they set `$_SERVER['HTTP_AUTHORIZATION']` directly and mock `php://input`.

That gap is exactly what let [gh-1010](https://github.com/akeeba/panopticon/issues/1010) ship past
a green suite: a web server that strips the `Authorization` header, and a missing `.htaccess`
rewrite for the short `/mcp` URL.

## Running

```bash
composer test:webserver
```

This **runs the main test suite first** (`composer test`) and only proceeds if it passes — the
web-server tier is meant to run *after* the main integration tests succeed. It then builds the
container, mints an API token inside it, and runs the PHPUnit `webserver` suite against it.

Requirements: Docker (with Compose v2) and `curl` on the host.

### Options

`run.sh` accepts:

| Option              | Default | Meaning                                             |
|---------------------|---------|-----------------------------------------------------|
| `--sapi=fpm\|modphp`| `fpm`   | PHP SAPI to test                                     |
| `--php-version=X.Y` | `8.4`   | PHP version tag to build (Apache 2.4 from the base) |
| `--port=NNNN`       | `4290`  | Host port mapped to Apache `:80`                     |
| `--no-pretest`      | —       | Skip the `composer test` gate (e.g. in CI)          |
| `--keep`            | —       | Leave the container up afterwards (for debugging)   |

```bash
# Also cover the mod_php half of the gh-1010 fix:
tests/WebServer/run.sh --sapi=modphp

# A different PHP version:
tests/WebServer/run.sh --php-version=8.3
```

## What it proves — the transport matrix

The image ships **no root `.htaccess`** and does not enable `mod_rewrite`; `htaccess.txt` *is*
copied in, so the tests toggle behaviour by copying it to `.htaccess` (`enableHtaccess()`) or
removing it (`disableHtaccess()`). The FPM vhost deliberately omits `CGIPassAuth`, so the
`.htaccess` `SetEnvIf Authorization ...` line is the sole `Authorization`-passing mechanism —
the thing under test.

| `.htaccess` | endpoint          | token transport         | expected (FPM)         |
|-------------|-------------------|-------------------------|------------------------|
| absent      | `/index.php/mcp`  | `Authorization: Bearer` | **401** ¹              |
| absent      | `/index.php/mcp`  | `X-Panopticon-Token`    | **200**, tools listed  |
| absent      | `/index.php/mcp`  | `?_panopticon_token=`   | **200**, tools listed  |
| absent      | `/mcp`            | any                     | **404**                |
| present     | `/index.php/mcp`  | `Authorization: Bearer` | **200**, tools listed  |
| present     | `/mcp`            | `Authorization: Bearer` | **200**, tools listed  |
| present     | `/index.php/mcp`  | none                    | **401**                |

¹ Under `--sapi=modphp` this cell flips to **200**: mod_php exposes `Authorization` to the
`getallheaders()` fallback even without the `SetEnvIf` trick. That is the one cell covering the
mod_php half of commit `9c7291d`.

## How it works

- `docker/Dockerfile.fpm` — Apache 2.4 + PHP-FPM in one image; vhost proxies PHP over FastCGI
  (`mod_proxy_fcgi`) with `AcceptPathInfo On` so `/index.php/mcp` resolves to `index.php` with
  `PATH_INFO=/mcp`.
- `docker/Dockerfile.modphp` — Apache 2.4 + mod_php variant for the secondary SAPI.
- `docker-compose.webserver.yml` — the app container + an ephemeral MySQL 8 (tmpfs, no
  persistence). Env-var config mode (`PANOPTICON_USING_ENV=1`) with a **pinned**
  `PANOPTICON_SECRET`, so tokens are deterministic and validation never hits `no_secret`.
- Token minting uses the `token:create` CLI command (`src/CliCommand/TokenCreate.php`), run
  inside the container so the token matches the running secret.
- The PHPUnit suite (`AbstractWebServerTestCase`, `McpWebServerTest`) drives HTTP with Guzzle and
  toggles `.htaccess` via `docker exec`. It **skips cleanly** when the
  `PANOPTICON_TEST_BASE_URL` / `_TOKEN` / `_CONTAINER` env vars are absent, so a bare
  `vendor/bin/phpunit` (default `phpunit.xml`, which does not include `tests/WebServer`) is
  unaffected.

## CI

This tier is **not** wired into CI yet (it is local-only). A future gated job in
`.github/workflows/php.yml` would declare `needs: build` and call
`composer test:webserver -- --no-pretest`, so it runs only after unit + integration pass.
