# Sites Table Auto-Refresh Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Periodically refresh the sites overview table (default layout) every 30 seconds by fetching server-rendered tbody HTML and swapping it in if content has changed.

**Architecture:** Extract the `<tbody>` content from `default.blade.php` into a standalone partial. Add a new `tableBody` controller task + Raw view that renders only that partial. JavaScript fetches it every 30s via AJAX, compares with current DOM, and swaps if different.

**Tech Stack:** PHP 8.3 (AWF MVC), Blade templates (AWF dialect, not Laravel), vanilla JS with `akeeba.Ajax`

**Codebase conventions (MUST follow):**
- PHP: Tabs for indentation, Allman brace style (opening braces on new line), `else`/`catch` on new line
- JS: 4-space indentation, double quotes, semicolons, Allman brace style
- All PHP files start with `defined('AKEEBA') || die;`
- All PHP files have `@package panopticon` / `@copyright` / `@license` docblock
- Namespace: `Akeeba\Panopticon\{Component}` with PSR-4 from `src/`

**Security note:** The innerHTML replacement in Task 6 uses trusted content from the same-origin, CSRF-protected server endpoint. The response is server-rendered Blade template output, not user-provided content.

---

### Task 1: Create the tbody partial template

**Files:**
- Create: `ViewTemplates/Main/default_tbody.blade.php`

**Step 1: Create the new partial**

Extract lines 85-228 from `ViewTemplates/Main/default.blade.php` into a new file. The partial must include the `<tbody>` open/close tags and everything between them. Add `id="sitesTableBody"` to the `<tbody>` tag. The file needs the `CMSType` use statement since it's used in the row loop.

```blade
<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

use Akeeba\Panopticon\Library\Enumerations\CMSType;

defined('AKEEBA') || die;

?>
<tbody id="sitesTableBody" class="table-group-divider">
    <?php /** @var \Akeeba\Panopticon\Model\Site $item */ ?>
    @foreach($this->items as $item)
        {{-- ... exact copy of row content from default.blade.php lines 90-216 ... --}}
    @endforeach
    @if ($this->itemsCount == 0)
        {{-- ... exact copy of "no results" block from default.blade.php lines 219-226 ... --}}
    @endif
</tbody>
```

The content between the `<tbody>` tags is an exact copy of lines 86-227 from `default.blade.php`. No logic changes.

---

### Task 2: Update default.blade.php to use the partial

**Files:**
- Modify: `ViewTemplates/Main/default.blade.php:85-228`

**Step 1: Replace the extracted lines**

Replace lines 85-228 (from `<tbody class="table-group-divider">` through `</tbody>`) with:

```blade
            @include('Main/default_tbody')
```

The `CMSType` use statement on line 10 of `default.blade.php` can remain.

**Step 2: Verify visually**

Load the application in a browser. Confirm the default sites table renders identically.

**Step 3: Commit**

```bash
git add ViewTemplates/Main/default_tbody.blade.php ViewTemplates/Main/default.blade.php
git commit -m "Refactor: extract sites table tbody into a reusable partial"
```

---

### Task 3: Create the Raw view class

**Files:**
- Create: `src/View/Main/Raw.php`

**Step 1: Create the Raw view**

This view extends `\Awf\Mvc\DataView\Raw` and provides `onBeforeTableBody()` to prepare data for the tbody partial. It also duplicates the 4 helper methods from `Html` that the sub-templates call on `$this` (`site_extensions.blade.php` lines 19-22, `site_backup.blade.php` line 22).

Methods needed by sub-templates on `$this`:
- `getExtensions(Registry $config): array`
- `getNumberOfExtensionUpdates(array $extensions): int`
- `getNumberOfKeyMissingExtensions(array $extensions): int`
- `getLastExtensionsUpdateError(Registry $config): string`
- `isTooOldBackup(...)` (from `AkeebaBackupTooOldTrait`)

Also used: `$this->groupMap`, `$this->items`, `$this->itemsCount`, `$this->getContainer()`.

```php
<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Main;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Model\Groups;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\View\Trait\AkeebaBackupTooOldTrait;
use Awf\Registry\Registry;

class Raw extends \Awf\Mvc\DataView\Raw
{
	use AkeebaBackupTooOldTrait;

	public array $groupMap = [];

	protected function onBeforeTableBody(): bool
	{
		/** @var Groups $groupsModel */
		$groupsModel    = $this->getModel('groups');
		$this->groupMap = $groupsModel->getGroupMap();

		$this->lists = new \stdClass();

		/** @var Site $model */
		$model = $this->getModel();
		$model->setState('enabled', 1);
		$model->savestate(1);

		$this->lists->order     = $model->getState('filter_order', 'name', 'cmd');
		$this->lists->order_Dir = $model->getState('filter_order_Dir', 'ASC', 'cmd');
		$this->lists->limitStart = $model->getState('limitstart', 0, 'int');
		$this->lists->limit      = $model->getState('limit', 50, 'int');

		$model->setState('filter_order', $this->lists->order);
		$model->setState('filter_order_Dir', $this->lists->order_Dir);
		$model->setState('limitstart', $this->lists->limitStart);
		$model->setState('limit', $this->lists->limit);

		$this->items      = $model->get();
		$this->itemsCount = $model->count();

		$this->setLayout('default_tbody');

		return true;
	}

	protected function getExtensions(Registry $config): array
	{
		return get_object_vars($config->get('extensions.list', new \stdClass()));
	}

	protected function getNumberOfExtensionUpdates(array $extensions): int
	{
		return array_reduce(
			$extensions,
			function (int $carry, object $item): int {
				$current = $item?->version?->current;
				$new     = $item?->version?->new;

				if (empty($new))
				{
					return $carry;
				}

				return $carry + ((empty($current) || version_compare($current, $new, 'ge')) ? 0 : 1);
			},
			0
		);
	}

	protected function getNumberOfKeyMissingExtensions(array $extensions): int
	{
		return array_reduce(
			$extensions,
			function (int $carry, object $item): int {
				$downloadkey = $item?->downloadkey ?? null;

				return $carry + (
					!$downloadkey?->supported || $downloadkey?->valid ? 0 : 1
					);
			},
			0
		);
	}

	protected function getLastExtensionsUpdateError(Registry $config): string
	{
		return trim($config->get('extensions.lastErrorMessage') ?? '');
	}
}
```

---

### Task 4: Add the tableBody controller task

**Files:**
- Modify: `src/Controller/Main.php` (add after the `sites()` method, after line 143)

**Step 1: Add the tableBody method**

```php
	public function tableBody()
	{
		$this->csrfProtection();

		// Use the saved model state
		if ($this->input->get('savestate', -999, 'int') == -999)
		{
			$this->input->set('savestate', true);
		}

		$this->display();
	}
```

**Step 2: Commit**

```bash
git add src/View/Main/Raw.php src/Controller/Main.php
git commit -m "Add tableBody endpoint for AJAX table refresh"
```

---

### Task 5: Add script options for the refresh URL

**Files:**
- Modify: `src/View/Main/Html.php` (inside `onBeforeMain()`)

**Step 1: Add the script options**

In `onBeforeMain()`, inside the existing `if (!$isDashboard)` block (line 156), after the pagination setup (line 164), add:

```php
			// Table body auto-refresh URL
			$token = $this->getContainer()->session->getCsrfToken()->getValue();
			$doc->addScriptOptions(
				'panopticon.tableRefresh', [
					'url' => $router->route(
						'index.php?view=main&task=tableBody&format=raw&' . $token . '=1'
					),
				]
			);
```

**Note:** `$token` is also declared at line 221 for WebPush. Either move line 221 up before this block and reuse it, or use the same `$token` variable name here (it will be re-assigned the same value at line 221). The simplest fix: just declare it here; the later assignment at line 221 is harmless since it's the same CSRF token value.

**Step 2: Commit**

```bash
git add src/View/Main/Html.php
git commit -m "Add table refresh URL to script options for default layout"
```

---

### Task 6: Add the periodic refresh JavaScript

**Files:**
- Modify: `media/js/main.js`

**Step 1: Add the tableBodyRefresh function**

Add after the `usageStats` function (after line 68), before `onDOMContentLoaded`:

```javascript
    const tableBodyRefresh = () =>
    {
        const options = akeeba.System.getOptions("panopticon.tableRefresh", {});

        if (!options?.url)
        {
            return;
        }

        // Skip refresh if a Bootstrap modal is currently open
        if (document.querySelector(".modal.show"))
        {
            return;
        }

        const elTbody = document.getElementById("sitesTableBody");

        if (!elTbody)
        {
            return;
        }

        akeeba.Ajax.ajax(
            options.url,
            {
                type:    "GET",
                cache:   false,
                success: (responseText, statusText, xhr) =>
                         {
                             const newHtml = responseText.trim();

                             if (!newHtml || newHtml === elTbody.innerHTML.trim())
                             {
                                 return;
                             }

                             // Dispose existing tooltips before replacing content
                             elTbody.querySelectorAll("[data-bs-toggle=\"tooltip\"]")
                                 .forEach((el) =>
                                 {
                                     const tooltip = bootstrap.Tooltip.getInstance(el);

                                     if (tooltip)
                                     {
                                         tooltip.dispose();
                                     }
                                 });

                             elTbody.innerHTML = newHtml;

                             // Re-initialize tooltips on the new content
                             elTbody.querySelectorAll("[data-bs-toggle=\"tooltip\"]")
                                 .forEach((el) => new bootstrap.Tooltip(el));
                         }
            }
        );
    };
```

**Step 2: Wire it up in onDOMContentLoaded**

After the heartbeat setup (after line 75), add:

```javascript
        // Set up the sites table auto-refresh
        window.setInterval(tableBodyRefresh, 30000);
```

Do NOT call `tableBodyRefresh()` immediately — the page just loaded with fresh data.

**Step 3: Commit**

```bash
git add media/js/main.js
git commit -m "Add periodic 30-second sites table auto-refresh via AJAX"
```

---

### Task 7: Manual verification

**Step 1: Full page load test**

1. Load `index.php` (default sites table view)
2. Verify the table renders identically to before
3. Open browser DevTools Network tab
4. Wait 30 seconds — confirm a `tableBody` request fires
5. Confirm the response is raw `<tbody>` HTML without page wrapper

**Step 2: Content change detection**

1. Load the sites table
2. Trigger a data change (e.g. refresh site info via another tab)
3. Wait for the 30-second cycle
4. Confirm the table updates without a full page reload
5. Confirm tooltips work on updated content

**Step 3: Modal protection**

1. Open an error detail modal on a site row
2. Wait 30+ seconds
3. Confirm the table does NOT refresh while the modal is open
4. Close the modal, wait for next cycle — confirm refresh works

**Step 4: Dashboard unaffected**

1. Switch to dashboard layout (`index.php?layout=dashboard`)
2. Confirm no `tableBody` requests fire (script option not set)

**Step 5: Commit design docs**

```bash
git add docs/plans/
git commit -m "Add design docs for sites table auto-refresh feature"
```
