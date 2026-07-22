# Vendored, locally modified third-party classes

This directory contains **verbatim copies of third-party classes with small, deliberate local
modifications**. Each copy declares the *same namespace and class name as the upstream original* and is loaded, in
place of the upstream version, early during bootstrap — before Composer's autoloader can define the upstream class.

## Why these files exist

The modifications used to be applied **at runtime**: read the upstream source with `file_get_contents()`, rewrite it
with `str_replace()`, write the result to a stream-wrapper path, `require` it, then `unlink` it.

That "read → rewrite → write → execute → delete" sequence is textbook file-**dropper** behaviour. Heuristic malware
scanners (e.g. Imunify360 / ImunifyAV, signature family `SMW-INJ-CLOUDAV-php.dropper.file-*`) flag it as malicious even
though it is perfectly legitimate. Because such scanners inspect files **at rest**, no amount of code obfuscation helps —
the only reliable fix is to stop generating executable PHP at runtime.

Shipping the modified classes as ordinary, auditable files removes the dropper pattern entirely while preserving the
exact behaviour.

## Contents

| File                               | Upstream package / version        | Modifications |
|------------------------------------|-----------------------------------|---------------|
| `symfony-error-handler/FlattenException.php`  | `symfony/error-handler` v6.4.36 | Surface the real exception message as the status text (instead of the generic "Whoops…"). |
| `symfony-error-handler/HtmlErrorRenderer.php` | `symfony/error-handler` v6.4.36 | (1) Give the non-debug "simple" error page the same rich template context as the debug page — our `templates/system/fatal.php` needs `$exception`. (2) Allow the active theme to override error templates/assets. |

Each modification is wrapped in `AKEEBA PANOPTICON CUSTOMISATION` / `END AKEEBA PANOPTICON CUSTOMISATION` comment
markers. Everything outside those markers is a verbatim copy of the upstream file.

Loaded by: `Akeeba\Panopticon\Application\BootstrapUtilities::overrideHtmlErrorRenderer()`.

## Keeping them in sync when the dependency updates

`tests/Unit/Application/ErrorHandlerPatchesTest.php` pins the SHA-256 of each upstream file. When `composer update`
pulls a new `symfony/error-handler`, that test **fails** if the upstream class changed. To re-sync:

1. Diff the new upstream file against the version these copies were based on.
2. Copy the new upstream file here, verbatim.
3. Re-apply the change(s) inside the `AKEEBA PANOPTICON CUSTOMISATION` markers.
4. Update the expected hash (and the version noted above and in the file headers) in the test.
5. Run `composer test:unit` to confirm.

Do **not** silence the test without re-syncing — a stale copy means Panopticon runs an out-of-date version of the class.
