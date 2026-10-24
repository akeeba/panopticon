# Language files

## Which file to edit

`languages/en-GB.ini` is the only hand-edited INI file. Every other language is edited in its `languages/LANG.po`
`msgstr` and compiled to `LANG.ini` with `phing language` (`php assets/po_to_ini.php --all`). After changing en-GB,
regenerate `languages/panopticon.pot` with `phing create-pot` (`php assets/ini_to_po.php --pot`), and translate the new
or changed keys in every `.po` file in the same change. Per-language glossaries are `languages/LANG.glossary.md`.

**Why:** the `.ini` translations are build output; a hand edit is overwritten by the next `phing language`.

**How to apply:** edit `msgstr` lines in place. Don't regenerate a translation's `.po` from its `.ini` with
`ini_to_po.php LANG` unless you have to: it rewrites the whole file and drops the wrapping and comments translation
tools leave there.

## Write each escape exactly once

A string goes through two escaping layers, and each must be applied once, by the right tool:

- In a PO file, `"` inside `msgid`/`msgstr` is written `\"`. That is PO syntax; `po_to_ini` turns it into the INI `\"`.
  So the PO file has `msgstr "<a href=\"%s\">…</a>"`, never `\\"` or `\\\"`.
- In `en-GB.ini`, a `"` inside a value is always written `\"`. A bare `"` loads in AWF, which reads the file in raw
  mode, but breaks PHP's standard INI parser and translation tools; `LanguageFilesTest` rejects it.
- No other backslash is ever needed. AWF only interprets `\n`, `\t` and `\\`; anything else is displayed as-is: `\'`
  shows a backslash, `%1\$d` (a PHP double-quoted string habit) makes sprintf() throw, so the user sees
  "BAD TRANSLATION".
- Apostrophes are a plain `'`, never `\'` or `''`.

**Why:** gh-1081. `LanguageTools::convertToPo()` used to forget to undo the INI `\"` escaping, adding one backslash level
per INI → PO → INI round trip, and translators copied the double-escaped markup from the `msgid`. About 250 translated
strings ended up with broken links (`<a href=\"…\">` in the HTML) or visible backslashes.

**How to apply:** after writing a translation, read the line back and count the backslashes before every `"`.

## Keep the translation structurally identical to en-GB

- Same sprintf() placeholders as en-GB. Reordering needs positional ones (`%2$s … %1$s`). Never `% `, `%$`, `%S`.
- Same HTML tags, byte for byte, including attribute values. Only the text, and `title`/`alt`/`aria-label`/
  `placeholder` values, is translated.
- Typographic quotes (`«…»`, `„…“`, `“…”`) are fine in the text, never inside a tag: `<a href=“%s”>` is a broken link.
- Translate the whole en-GB text. Truncated translations were a recurring problem.
- en-GB is the reference markup, so a markup bug there (e.g. the old `_target='blank'`) is copied into every
  language. Fix it in en-GB and in every `.po` in the same change, or the parity test fails.

**Why:** machine and community translations dropped or mangled placeholders and markup in dozens of strings per
language, which loses information or breaks the page.

**How to apply:** run `vendor/bin/phpunit tests/Unit/Language tests/Unit/Helper/LanguageToolsTest.php` after every
language change. `LanguageFilesTest` loads each INI file through AWF and checks all of the above against en-GB.
