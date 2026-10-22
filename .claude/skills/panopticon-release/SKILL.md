---
name: panopticon-release
description: Use when releasing Panopticon — running phing, tagging, or publishing a version. Panopticon deviates from the generic Akeeba release recipe; read this before running any phing release target.
---

# Panopticon Release Process

Panopticon deviates from the generic Akeeba release-workflow recipe, in two ways: the final "full release" step, and a new-installation test gate before it. Run these three steps, in order:

```bash
# 1. Rebuild the .pot, recompile every .ini from its .po, build the ZIP
phing git-app -Dversion=X.Y.Z
#    Commit any .ini / .pot changes this produced, before step 2.

# 2. Install the ZIP in Docker and walk the setup wizard in all languages (STOP the release if this fails)
composer test:install

# 3. The full release
phing all release update -Dversion=X.Y.Z
```

Use `git-app`, not plain `phing git`, in step 1: `git` skips the language compilation and would ship whatever `.ini` files are committed, so a broken `.po` could pass step 2 and still ship. Step 2 tests the newest `release/panopticon-*.zip`; see `tests/Installation/README.md`. Building the ZIP rewrites `vendor/` without dev dependencies: run `composer dump-autoload --dev` before `composer test`. `all` in step 3 rebuilds the ZIP and is kept because it may grow other release-only work; run step 3 from the same commit as the ZIP tested in step 2, with no edits in between.

**Not** `phing all release docsdeploy` (the generic recipe) and **not** `phing all -Dversion=X.Y.Z` with `release`/`update` folded into `all`'s dependency chain — both are broken here.

Why: `build.xml`'s `all` target used to `depend` on `release,update`. `release` internally does `<phingcall target="github-release">` (`release.method=github` in `build/build.properties`). Phing 3.1.2 has a regression where this phingcall throws `"... task calling a target that depends on its parent target 'release'."` whenever *any* target in the project depends on the target the phingcall is nested in — regardless of which target you actually invoke from the command line (Phing parses the whole buildfile up front, so the mere existence of the dependency triggers it). `all`'s `depends` list was changed to just `git,documentation`; `release` and `update` must be invoked as separate top-level targets in the same `phing` command so their own dependency chains (`new-release`, `setup-properties`, etc.) still get satisfied once, in order, without re-triggering `new-release` (which wipes `release/`) after the package/zip has already been built.

Also, `docsdeploy` is a no-op in this repo (`common.xml`'s default "not set up" stub) — `update` is what actually matters, since it generates `release/update.json` and uploads it to getpanopticon.com via `updatejson`.

This is a **panopticon-specific** fix, not a shared-`buildfiles`/`common.xml` issue: an audit of every `build.xml` under `~/Projects/akeeba`, `~/Projects/j4-akeeba`, and `~/Projects/dionysopoulos` found panopticon was the only repo whose `all` target depended on `release`/`update`, and the only repo with a local `update` target override. Don't port this fix to `common.xml` or other repos.

## Docker

Docker image is built and pushed to GHCR automatically on `git push --tags` (the normal release path).
