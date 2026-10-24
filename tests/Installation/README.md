# New-installation browser test

This test installs Panopticon the way a new user does: it unzips the **release ZIP** into a bare Apache 2.4 +
PHP-FPM + database lab and walks the whole setup wizard in a real browser, once per interface language. It stops at the
first successful login. It is the slow release gate, not part of `composer test`.

## Why it exists

A fresh installation is the first thing a new user sees of Panopticon, and nothing else tests it. The unit and
integration suites run against a developer checkout, and `tests/WebServer/` deliberately bypasses the setup wizard.

Translations have broken this first impression before: missing keys or values made pages show AWF's
`BAD TRANSLATION. LANGUAGE KEY “…” HAS THE WRONG NUMBER OR KIND OF VALUE ARGUMENTS.` text.
`tests/Unit/Language/LanguageFilesTest.php` checks the language files statically, but it cannot see a key that is only
resolved at runtime, a plural variant the code asks for, or a string whose placeholders match en-GB but not what the
code passes. Only rendering the real pages catches those.

On every page, in every language, these are failures:

- any `BAD TRANSLATION` text, and any raw `PANOPTICON_*` language key in the visible text, page title or
  `alt`/`title`/`placeholder` attributes;
- PHP errors, warnings, notices or deprecations, in the page or in the PHP / Apache error log of the container (the lab
  sets `error_reporting = E_ALL` and logs to a file, because `assets/php.ini` would otherwise hide everything);
- JavaScript exceptions, `console.error` output, and failed (HTTP 400 and up) requests for local assets, which would
  mean something is missing from the ZIP;
- a wizard step that does not lead to the next one, or an install that does not end on the dashboard after login.

Text that is still English in a non-English run is **not** a failure. The static unit test enforces translation
completeness, and many strings are legitimately identical.

It has already caught three real setup bugs, all fixed: (1) with a read-only `config.php`, the skip-CRON and finish
steps crashed; (2) after creating the administrator account, a new installation went to the login page, because the
session was lost when `config.php` was first written; (3) with a read-only `config.php`, the CRON page crashed because
the web CRON key was generated and saved there.

## Requirements

- Docker with Compose v2.
- Node.js on `PATH`.
- The sibling `../buildfiles` checkout (next to this repository) with `npm install` done. The test uses its shared
  Playwright toolchain (`playwright/run.mjs`) and cached Chromium; `run.sh` installs the browser if it's missing. This
  project has no Playwright setup of its own.
- PHP and `curl` on the host (to read `composer.json` and talk to the lab), and `phing` if there is no ZIP yet.

## Quick start

```bash
# Build the release ZIP first (recompiles the language files), then test it:
phing git-app -Dversion=git
composer dump-autoload --dev       # building the ZIP rewrote vendor/ without dev dependencies
composer test:install
```

The full matrix runs 27 tests and takes roughly 15 to 30 minutes. MySQL 5.7 has no arm64 image, so on Apple Silicon it
runs emulated and is the slowest part.

`composer test:install` is `tests/Installation/run.sh`. Pass options after `--`:
`composer test:install -- --php=8.3 --lang=de-DE`.

**Building a ZIP rewrites `vendor/` without the dev dependencies.** This applies to `phing git-app` and to `run.sh`'s
own fallback when there is no ZIP. Run `composer dump-autoload --dev` (or `composer install`) before `composer test`
afterwards.

### Options

| Option                                | Meaning                                                                                     |
|---------------------------------------|---------------------------------------------------------------------------------------------|
| *(none)*                              | The full matrix below                                                                       |
| `--zip=<path>`                        | Test this ZIP. Default: the newest `release/panopticon-*.zip`, or build one with `phing git-app` |
| `--php=X.Y`                           | Run only with this PHP version (must be inside `composer.json`'s `require.php`)             |
| `--db=mysql:X.Y` or `--db=mariadb:X.Y`| Run only with this database server and version                                              |
| `--lang=de-DE,el-GR`                  | Run only these languages. The others are reported loudly as not tested                      |
| `--cron`                              | Run only the CRON benchmark path (en-GB unless `--lang` is given)                           |
| `--saveconfig`                        | Run only the manual `config.php` path (en-GB unless `--lang` is given)                      |
| `--keep`                              | Leave the stack up afterwards, for debugging                                                |
| `--down`                              | Tear down any leftover stack and exit                                                       |
| `--headed`                            | Show the browser                                                                            |
| `--help`                              | Print the usage text                                                                        |

With any of `--php`, `--db`, `--lang`, `--cron` or `--saveconfig`, the matrix is replaced by exactly **one**
combination. Whatever you don't give defaults to the newest PHP, the newest MySQL, all languages and the normal path.

On entry `run.sh` always tears down any leftover stack, and at the end it tears the stack down unless `--keep` is
given. Its port is 4291 (`INSTALLTEST_PORT` overrides it) and the Compose project is `panopticon_installtest`, so it
doesn't collide with `tests/WebServer/`.

## The matrix

| Run                | PHP | Database      | Languages  | Path                                             | Tests |
|--------------------|-----|---------------|------------|--------------------------------------------------|-------|
| Oldest edge        | 8.3 | MySQL 5.7     | all 12     | normal, `skipcron`                               | 12    |
| Newest edge        | 8.5 | MySQL 26.10   | 11 (not en-GB) | normal, `skipcron`                           | 11    |
| CRON benchmark     | 8.5 | MySQL 26.10   | en-GB      | normal, real CRON benchmark                      | 1     |
| Manual `config.php`| 8.5 | MySQL 26.10   | en-GB      | read-only web root, `saveconfig` page, manual upload | 1 |
| MariaDB oldest     | 8.3 | MariaDB 10.3  | en-GB      | normal, `skipcron`                               | 1     |
| MariaDB newest     | 8.5 | MariaDB 13.0  | en-GB      | normal, `skipcron`                               | 1     |

That is 27 tests. The CRON benchmark and the manual `config.php` run *are* the en-GB installs of the newest edge, so
en-GB is not installed a third time there.

The language list is **discovered**, not hard-coded: it is every language in the installer's language selector (today
the 12 `.ini` files in `languages/`). Adding a language adds it to the test. If the selector doesn't offer a language
you asked for, the run fails.

### Where each bound comes from

- **Oldest PHP**: the lowest version `composer.json` `require.php` allows (`>=8.3 <8.7` gives 8.3). `run.sh` reads it.
- **Newest PHP**: the newest *stable* PHP that constraint allows. Today that is 8.5 (8.6 is not released yet). It is
  declared in `run.sh` (`PHP_NEWEST`) and must be bumped by hand.
- **Oldest databases**: the documented minimums, MySQL 5.7 and MariaDB 10.3 (the wiki's Install-Panopticon page),
  declared in `run.sh`.
- **Newest databases**: the newest **GA** release found on Docker Hub, pinned as major.minor (`run.sh`:
  `MYSQL_NEWEST`, `MARIADB_NEWEST`). This is deliberately *not* the LTS. MySQL now numbers releases YY.M, so 26.10 is
  the newest while 9.7 is the LTS; for MariaDB 13.0 is the newest and 12.3 the LTS. Breakage shows up at the newest
  edge first. Re-check these two whenever you review the matrix.

### Why the edges, and why they are paired

Breaks happen at the edges of the supported range. A new PHP version's backwards-compatibility breaks will break the
newest version if they are not addressed; addressing them without regard for older versions breaks the oldest. The same
applies to the database server. Testing both edges catches both.

PHP and database edges are deliberately **paired** (oldest with oldest, newest with newest) to save time. That makes a
failure harder to pin on PHP or on the database, so use `--php` and `--db` to separate them.

## Bisecting a failure

Say `PHP 8.3 · MySQL 5.7 · el-GR` fails. Is it the language, PHP or the database?

```bash
# The same language on the newest edge:
composer test:install -- --php=8.5 --db=mysql:26.10 --lang=el-GR

# Old PHP with a newer database, then new PHP with the old database:
composer test:install -- --php=8.3 --db=mysql:8.4 --lang=el-GR
composer test:install -- --php=8.5 --db=mysql:5.7 --lang=el-GR

# Watch it happen, and leave the lab up to poke at:
composer test:install -- --php=8.3 --db=mysql:5.7 --lang=el-GR --headed --keep
tests/Installation/run.sh --down      # when you are done
```

A `--lang` run prints a `PARTIAL RUN` warning for the languages it left out, so a green partial run is never mistaken
for a full one.

## The two special en-GB runs

Both run only in en-GB on the newest edge. They test the setup *path*, which doesn't depend on the language, and the
language pages are covered by the 11 other newest-edge installs.

- **CRON benchmark** (`--cron`). Instead of `skipcron`, a loop in the PHP container (standing in for crontab) runs
  `php cli/panopticon.php task:run` while the page polls `cronHeartbeat` and moves to `finish` by itself. The benchmark
  (`src/Task/MaxExec.php`) burns CPU for up to about 185 seconds, so this one run takes about 3 minutes. It checks
  whether a time-burning CRON job is killed by an external timeout. That does not depend on the language or the PHP
  version, so one run is enough. Every other install takes `skipcron`, but every language still renders and checks the
  CRON page first.
- **Manual `config.php`** (`--saveconfig`). The shared-host case the `saveconfig` page exists for. The web root is made
  read-only to PHP-FPM, while `cache/`, `log/` and `tmp/` stay writable. Saving the configuration fails and the wizard
  shows the `saveconfig` page. The test takes the configuration text from that page, writes it as `config.php` through
  `docker exec` (as a user uploading by FTP would), leaves it read-only, continues, takes `skipcron`, and finishes with
  the log-out and log-in.

The `session` page (with its FTP form) only appears when neither PHP's session save path nor `tmp/` is writable. No
normal install reaches it, so every language loads it directly and checks its strings (render check only).

## What the lab is

- The PHP image is `php:<version>-fpm` with Apache 2.4 (`proxy_fcgi`, `mod_rewrite`, `.htaccess` allowed) and
  `assets/php.ini`. It has **no** `COPY .` of the repository, no Composer and no `config.php` or `PANOPTICON_*`
  variables. The web root holds only the ZIP's contents as unzipped. `htaccess.txt` is *not* renamed to `.htaccess`,
  because the install docs don't tell users to.
- PHP extensions are those a typical host has and Panopticon declares (`composer.json`'s `ext-*`, plus
  `pdo_mysql`/`mysqli` and OPcache). It also installs **bcmath**: the bundled web-push library logs a PHP Notice on
  every `task:run` without GMP or BCMath, and most hosts have one of them.
- Databases run on tmpfs with no volumes. Every install starts from a fresh unzip and an empty database, so no state
  carries over between languages.

## Where the results land

Everything is under `tests/Installation/results/`, which is wiped at the start of each run. There is one directory
per combination, named like `php8.5-mysql26.10-normal`:

- `report.json` and `report/index.html`: Playwright's results and HTML report.
- `container-output.log`: the stack's stdout and stderr.
- `artifacts/<test name>/`: only for **failing** tests. It holds the Playwright `trace.zip`, the screenshot, and the
  `php-error.log` and `apache-error.log` of that install (the lab empties the logs before every install, so they are
  attached per failing test).

At the end `summary.mjs` prints a per-combination pass / fail count and lists each failing combination with the wizard
step it failed at, plus any partial-run warnings and infrastructure failures. The exit status is non-zero if anything
failed. Open a trace with `node ../buildfiles/playwright/run.mjs show-trace <trace.zip>` or from the HTML report.

## Release checklist

This is step 2 of three in the `panopticon-release` skill (`.claude/skills/panopticon-release/SKILL.md`):

1. `phing git-app -Dversion=X.Y.Z`: rebuilds the `.pot`, recompiles every `.ini` from its `.po`, and builds the ZIP.
   Commit any `.ini`/`.pot` changes it produces.
2. `composer test:install`: tests that ZIP. **Stop the release if it fails.**
3. `phing all release update -Dversion=X.Y.Z`.

Plain `phing git` skips the language compilation and ships whatever `.ini` files are committed, so a broken `.po`
could pass this test and still ship. That is why step 1 is `git-app`.

## Not covered

Anything after the first successful login; CI (this runs locally only); upgrades from an older version; the Docker
image's environment-variable install and the CLI `config:create` install; NginX, IIS and `mod_php`; and translation
*quality* or completeness (the static unit test covers that).
