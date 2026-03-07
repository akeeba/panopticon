# Site Read View Auto-Refresh Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Every 60 seconds, fetch server-rendered HTML for each section of the site read view via a single AJAX request, and update only the sections whose content has changed.

**Architecture:** A new controller task renders each section template via an Html view instance, returns JSON with per-section HTML and content hashes. Client-side JS polls this endpoint, compares hashes, and replaces only changed sections — disposing and re-initializing Bootstrap tooltips, Collapse instances, Choices.js, and event handlers as needed.

**Tech Stack:** PHP 8.3 (AWF MVC), Blade templates, vanilla JS with `akeeba.Ajax`, Bootstrap 5.3, Choices.js

**Security note on DOM replacement:** The auto-refresh JS replaces section content using the same safe pattern as the existing sites overview table auto-refresh in `main.js`. Content is fetched from the same-origin CSRF-protected Panopticon server — it is trusted server-rendered HTML, not user input. This mirrors the established `elTbody.innerHTML = newHtml` pattern at `media/js/main.js:125`.

---

## Task 1: Fix Deterministic Modal IDs

Three templates generate random modal IDs via `hash('md5', random_bytes(120))`. This causes every server render to produce different HTML even when nothing changed, breaking content comparison.

**Files:**
- Modify: `ViewTemplates/Sites/item_joomlaupdate.blade.php:43`
- Modify: `ViewTemplates/Sites/item_wpupdate.blade.php:38`
- Modify: `ViewTemplates/Sites/item_extensions.blade.php:76`

**Step 1: Fix `item_joomlaupdate.blade.php`**

Change line 43 from:
```php
$siteInfoLastErrorModalID = 'silem-' . hash('md5', random_bytes(120)); ?>
```
To:
```php
$siteInfoLastErrorModalID = 'silem-' . hash('md5', 'joomlaupdate-' . $this->item->id); ?>
```

**Step 2: Fix `item_wpupdate.blade.php`**

Change line 38 from:
```php
$siteInfoLastErrorModalID = 'silem-' . hash('md5', random_bytes(120)); ?>
```
To:
```php
$siteInfoLastErrorModalID = 'silem-' . hash('md5', 'wpupdate-' . $this->item->id); ?>
```

**Step 3: Fix `item_extensions.blade.php`**

Change line 76 from:
```php
<?php $extensionsLastErrorModalID = 'exlem-' . hash('md5', random_bytes(120)); ?>
```
To:
```php
<?php $extensionsLastErrorModalID = 'exlem-' . hash('md5', 'extensions-' . $this->item->id); ?>
```

**Step 4: Commit**

```bash
git add ViewTemplates/Sites/item_joomlaupdate.blade.php ViewTemplates/Sites/item_wpupdate.blade.php ViewTemplates/Sites/item_extensions.blade.php
git commit -m "Use deterministic modal IDs in site read section templates"
```

---

## Task 2: Add Section Wrapper Divs

Wrap each section `@include` in `item.blade.php` with a `<div>` that has a stable ID, so the JS can target each section for replacement.

**Files:**
- Modify: `ViewTemplates/Sites/item.blade.php:86-149`

**Step 1: Add wrapper divs around each section include**

The `<div class="container my-3">` block (lines 86-150) contains all sections. Wrap each `@include` with a wrapper div. The final result for the container block should be:

```blade
<div class="container my-3">
    <div class="row g-3 mb-3">
        <div class="col-12 col-lg-6 order-1 order-lg-0">
            <div id="siteSection-cmsUpdate">
            @if ($this->item->cmsType() === CMSType::JOOMLA)
                {{-- Joomla! sites: Joomla!&reg; Update information --}}
                @include('Sites/item_joomlaupdate')
            @elseif ($this->item->cmsType() === CMSType::WORDPRESS)
                {{-- WordPress sites: WordPress update information --}}
                @include('Sites/item_wpupdate')
            @endif
            </div>
        </div>

        <div class="col-12 col-lg-6 order-0 order-lg-1">
            <div id="siteSection-php">
            @include('Sites/item_php')
            </div>
        </div>
    </div>

    @if($this->hasCollectedServerInfo())
    <div class="row g-3 mb-3">
        <div class="col-12">
            <div id="siteSection-server">
            @include('Sites/item_server')
            </div>
        </div>
    </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-12">
            <div id="siteSection-extensions">
            @if ($this->item->cmsType() === CMSType::JOOMLA)
                {{-- Joomla! sites --}}
                @include('Sites/item_extensions')
            @elseif ($this->item->cmsType() === CMSType::WORDPRESS)
                {{-- WordPress sites --}}
                @include('Sites/item_wpplugins')
            @endif
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12">
            <div id="siteSection-backup">
            @include('Sites/item_backup')
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12">
            <div id="siteSection-admintools">
            @include('Sites/item_admintools')
            </div>
        </div>
    </div>

    @if ($this->item->cmsType() === CMSType::JOOMLA)
    <div class="row g-3 mb-3">
        <div class="col-12">
            <div id="siteSection-corechecksums">
            @include('Sites/item_corechecksums')
            </div>
        </div>
    </div>
    @endif

    @if($this->canEdit)
        <div class="row g-3 mb-3">
            <div class="col-12">
                @include('Sites/item_notes')
            </div>
        </div>
    @endif
</div>
```

Notes section is NOT wrapped — it's excluded from auto-refresh (only changes on deliberate user edit).

**Step 2: Commit**

```bash
git add ViewTemplates/Sites/item.blade.php
git commit -m "Add section wrapper divs for site read auto-refresh"
```

---

## Task 3: Extract `prepareSiteReadData()` from `Html.php`

Extract the data-loading portion of `onBeforeRead()` into a public method so the refresh controller task can reuse it.

**Files:**
- Modify: `src/View/Sites/Html.php:377-682`

**Step 1: Add the `prepareSiteReadData()` method**

Add this new public method to the `Html` class (before `onBeforeRead()`):

```php
/**
 * Loads all data needed by the site read view section templates.
 *
 * Called by onBeforeRead() and by the refreshSections controller task.
 *
 * @return  void
 * @since   1.3.4
 */
public function prepareSiteReadData(): void
{
	/** @noinspection PhpFieldAssignmentTypeMismatchInspection */
	$this->item             = $this->getModel();
	$this->canEdit          = $this->item->canEdit();
	$this->siteConfig       = $this->item->getConfig();
	$this->connectorVersion = $this->siteConfig->get('core.panopticon.version');
	$this->connectorAPI     = $this->siteConfig->get('core.panopticon.api');
	$this->baseUri          = Uri::getInstance($this->item->getBaseUrl());
	$this->adminUri         = Uri::getInstance($this->item->getAdminUrl());
	$this->extensions       = $this->item->getExtensionsList();

	if ($this->item->cmsType() === CMSType::JOOMLA)
	{
		$this->joomlaUpdateRunState = $this->item->getJoomlaUpdateRunState();
	}
	elseif ($this->item->cmsType() === CMSType::WORDPRESS)
	{
		$this->wpUpdateRunState = $this->item->getWordPressUpdateRunState();
	}

	// Modify extensionFilters for WordPress sites
	if ($this->item->cmsType() === CMSType::WORDPRESS)
	{
		unset($this->extensionFilters['filter-dlid']);
		unset($this->extensionFilters['filter-naughty']);
		unset($this->extensionFilters['filter-updatesite']);
	}

	try
	{
		$useCache             = !$this->item->getState('akeebaBackupForce', false, 'bool');
		$this->backupRecords  = $this->item->akeebaBackupGetBackups(
			$useCache, $this->item->getState('akeebaBackupFrom', 0, 'int'),
			$this->item->getState('akeebaBackupLimit', 20, 'int'),
		);
		$this->backupProfiles = $this->item->akeebaBackupGetProfiles($useCache);
	}
	catch (Throwable $e)
	{
		$this->backupRecords = $e;
	}

	$this->hasAdminTools    = $this->hasAdminTools($this->item, false);
	$this->hasAdminToolsPro = $this->hasAdminTools($this->item, true);

	if ($this->hasAdminToolsPro)
	{
		try
		{
			$useCache    = !$this->item->getState('adminToolsForce', false, 'bool');
			$this->scans = $this->getModel()->adminToolsGetScans(
				$useCache, $this->item->getState('adminToolsFrom', 0, 'int'),
				$this->item->getState('adminToolsLimit', 20, 'int'),
			)?->items ?? [];
		}
		catch (Exception $e)
		{
			$this->scans = $e;
		}
	}

	// Core File Integrity Checksums
	if ($this->item->cmsType() === CMSType::JOOMLA)
	{
		$this->coreChecksumsModifiedCount = (int) $this->siteConfig->get('core.coreChecksums.modifiedCount', 0);
		$this->coreChecksumsLastCheck     = $this->siteConfig->get('core.coreChecksums.lastCheck', null);
		$lastStatus                       = $this->siteConfig->get('core.coreChecksums.lastStatus', null);
		$this->coreChecksumsLastStatus    = $lastStatus === null ? null : (bool) $lastStatus;
	}

	$this->cronStuckTime = $this->getCronStuckTime();
}
```

**Step 2: Replace the data-loading code in `onBeforeRead()` with a call to `prepareSiteReadData()`**

In `onBeforeRead()`, find the block that starts with:
```php
/** @noinspection PhpFieldAssignmentTypeMismatchInspection */
$this->item             = $this->getModel();
```
(around line 417)

And ends just before:
```php
$hasAkeebaBackupPro = $this->item->hasAkeebaBackup() && $this->siteConfig->get('akeebabackup.info.api') > 1;
```
(around line 486)

Replace that entire block with:
```php
$this->prepareSiteReadData();
```

Everything before that block (JS includes, WebPush setup, strict layout, toolbar "back" button, title) stays as-is. Everything after (toolbar dropdowns, script options) stays as-is.

**Step 3: Verify the method works**

Load any site read page in the browser and confirm it renders identically to before.

**Step 4: Commit**

```bash
git add src/View/Sites/Html.php
git commit -m "Extract prepareSiteReadData() from onBeforeRead()"
```

---

## Task 4: Add `refreshSections` Controller Task

Add a controller task that creates an Html view, prepares its data, renders each section template, and returns JSON.

**Files:**
- Modify: `src/Controller/Sites.php`

**Step 1: Add necessary `use` statements at the top of `Sites.php`**

Check if these imports already exist; add any that are missing:
```php
use Akeeba\Panopticon\Library\Enumerations\CMSType;
use Akeeba\Panopticon\View\Sites\Html as SitesHtmlView;
```

**Step 2: Add the `refreshSections()` method**

Add this method to the `Sites` controller class. It follows the same output pattern as `Pushsubscriptions.php:220-222` and `Apitokens.php:277-279`.

```php
/**
 * Returns a JSON object with server-rendered HTML for each site read section.
 *
 * Used by the periodic auto-refresh in the site read view.
 *
 * @return  void
 * @since   1.3.4
 */
public function refreshSections(): void
{
	$this->csrfProtection();

	$id = $this->input->getInt('id', 0);

	/** @var \Akeeba\Panopticon\Model\Site $model */
	$model = $this->getModel();
	$model->find($id);

	// Create an Html view instance to render section templates
	$htmlView = new SitesHtmlView($this->container);
	$htmlView->setDefaultModel($model);
	$htmlView->prepareSiteReadData();

	$cmsType    = $model->cmsType();
	$siteConfig = $model->getConfig();
	$sections   = [];

	// CMS Update
	if ($cmsType === CMSType::JOOMLA)
	{
		$html = $htmlView->loadAnyTemplate('Sites/item_joomlaupdate');
		$sections['cmsUpdate'] = ['html' => $html, 'hash' => md5($html)];
	}
	elseif ($cmsType === CMSType::WORDPRESS)
	{
		$html = $htmlView->loadAnyTemplate('Sites/item_wpupdate');
		$sections['cmsUpdate'] = ['html' => $html, 'hash' => md5($html)];
	}

	// PHP
	$html = $htmlView->loadAnyTemplate('Sites/item_php');
	$sections['php'] = ['html' => $html, 'hash' => md5($html)];

	// Server (conditional — only if server info has been collected)
	if (
		$siteConfig->get('core.panopticon.api') >= 101
		&& $siteConfig->get('core.serverInfo')
		&& $siteConfig->get('core.serverInfo.collected')
	)
	{
		$html = $htmlView->loadAnyTemplate('Sites/item_server');
		$sections['server'] = ['html' => $html, 'hash' => md5($html)];
	}

	// Extensions
	if ($cmsType === CMSType::JOOMLA)
	{
		$html = $htmlView->loadAnyTemplate('Sites/item_extensions');
		$sections['extensions'] = ['html' => $html, 'hash' => md5($html)];
	}
	elseif ($cmsType === CMSType::WORDPRESS)
	{
		$html = $htmlView->loadAnyTemplate('Sites/item_wpplugins');
		$sections['extensions'] = ['html' => $html, 'hash' => md5($html)];
	}

	// Backup
	$html = $htmlView->loadAnyTemplate('Sites/item_backup');
	$sections['backup'] = ['html' => $html, 'hash' => md5($html)];

	// Admin Tools
	$html = $htmlView->loadAnyTemplate('Sites/item_admintools');
	$sections['admintools'] = ['html' => $html, 'hash' => md5($html)];

	// Core Checksums (Joomla only)
	if ($cmsType === CMSType::JOOMLA)
	{
		$html = $htmlView->loadAnyTemplate('Sites/item_corechecksums');
		$sections['corechecksums'] = ['html' => $html, 'hash' => md5($html)];
	}

	header('Content-Type: application/json; charset=utf-8');
	echo json_encode($sections, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	$this->container->application->close();
}
```

**Step 3: Commit**

```bash
git add src/Controller/Sites.php
git commit -m "Add refreshSections controller task for site read auto-refresh"
```

---

## Task 5: Add Script Options for Refresh URL

Pass the auto-refresh endpoint URL to the client via script options.

**Files:**
- Modify: `src/View/Sites/Html.php` — in `onBeforeRead()`, near the end (after the `akeebabackup` script options block)

**Step 1: Add the script options**

At the end of `onBeforeRead()`, just before `return true;`, add:

```php
$document->addScriptOptions(
	'panopticon.siteRefresh', [
		'url' => $router->route(
			sprintf(
				'index.php?view=site&task=refreshSections&id=%d&format=raw&%s=1',
				$this->item->id,
				$this->container->session->getCsrfToken()->getValue()
			)
		),
	]
);
```

Note: the `$document` and `$router` variables already exist in scope from earlier in `onBeforeRead()`.

**Step 2: Commit**

```bash
git add src/View/Sites/Html.php
git commit -m "Add auto-refresh script options to site read view"
```

---

## Task 6: Update `site-read.js` with Auto-Refresh

Add polling, hash-based comparison, and section replacement with proper disposal/re-initialization of JS-powered UI components.

**Files:**
- Modify: `media/js/site-read.js`

This is the most complex task. The changes must preserve all existing functionality while adding the auto-refresh capability.

**Key design decisions:**
- **Hash-based comparison** instead of DOM innerHTML comparison. This avoids false positives from Choices.js DOM modifications in the backup section. The server returns an MD5 hash per section; the client stores and compares hashes.
- **Synchronous replacement + reinitialization.** JS is single-threaded; the browser does not repaint between the content replacement and the subsequent reinitialization calls. So there is no visible "flash" of unfiltered/uncollapsed content.
- **Scoped reinitialization.** Only re-initialize tooltips, collapse listeners, etc. within the replaced section's wrapper div — not the entire document.
- **Same-origin trusted content.** Section HTML comes from the same CSRF-protected Panopticon server endpoint. This is the identical trust model as the existing sites overview table auto-refresh in `media/js/main.js:125`.

**Step 1: Replace the entire content of `media/js/site-read.js`**

```javascript
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

(() =>
{
    // =========================================================================
    // Extension Filters
    // =========================================================================

    /**
     * Apply the extensions filters
     */
    const applyExtensionFilters = () =>
    {
        const filterButtons = document.querySelectorAll(".extensionFilter");
        const filterRows    = document.querySelectorAll("tr.extension-row");

        if (!filterButtons || filterButtons.length < 1 || !filterRows || filterRows.length < 1)
        {
            return;
        }

        let allowedFilters = [];

        filterButtons.forEach((el) =>
        {
            if (!el.dataset.extFilter || !el.dataset.bsToggle || el.dataset.bsToggle !== "button" || el.ariaPressed !== "true")
            {
                return;
            }

            if (allowedFilters.includes(el.dataset.extFilter))
            {
                return;
            }

            allowedFilters.push(el.dataset.extFilter);
        });

        const options = akeeba.System.getOptions("panopticon.siteRemember", {});

        if (options.extensionsFilters)
        {
            window.localStorage.setItem(options.extensionsFilters, JSON.stringify(allowedFilters));
        }

        const filterString = document.getElementById("extensions-filter-search")?.value?.toLowerCase();

        filterRows.forEach((row) =>
        {
            let shouldShow = true;

            if (allowedFilters.length >= 1)
            {
                allowedFilters.forEach((filterClass) =>
                {
                    shouldShow = shouldShow && row.classList.contains(filterClass);
                });
            }

            let matchesString = false;

            if (typeof filterString === "string" && filterString.length > 0)
            {
                const elName   = row.querySelectorAll(".extensions-filterable-name");
                const elKey    = row.querySelectorAll(".extensions-filterable-key");
                const elAuthor = row.querySelectorAll(".extensions-filterable-author");

                if (elName.length)
                {
                    matchesString = matchesString || elName[0]?.innerText?.toLowerCase()?.includes(filterString);
                }

                if (elKey.length)
                {
                    matchesString = matchesString || elKey[0]?.innerText?.toLowerCase()?.includes(filterString);
                }

                if (elAuthor.length)
                {
                    matchesString = matchesString || elAuthor[0]?.innerText?.toLowerCase()?.includes(filterString);
                }
            }
            else
            {
                matchesString = true;
            }

            if (shouldShow && matchesString)
            {
                row.classList.remove("d-none");
            }
            else
            {
                row.classList.add("d-none");
            }
        });
    }

    /**
     * Restore the extensions filters state from the browser's local storage
     */
    const restoreExtensionFilters = () =>
    {
        const filterButtons = document.querySelectorAll(".extensionFilter");

        if (!filterButtons || filterButtons.length < 1)
        {
            return;
        }

        let activeFilters = [];

        try
        {
            const options = akeeba.System.getOptions("panopticon.siteRemember", {});
            const json    = window.localStorage.getItem(options.extensionsFilters);

            activeFilters = JSON.parse(json);
        }
        catch (e)
        {
            return;
        }

        if (!activeFilters)
        {
            return;
        }

        filterButtons.forEach((el) =>
        {
            if (!el.dataset.extFilter || !el.dataset.bsToggle || el.dataset.bsToggle !== "button")
            {
                return;
            }

            if (activeFilters.includes(el.dataset.extFilter))
            {
                akeeba.System.triggerEvent(el, "click");
            }
        });

    };

    /**
     * Attach click handlers to extension filter buttons and search button.
     */
    const attachExtensionFilterHandlers = () =>
    {
        [].slice.call(document.querySelectorAll(".extensionFilter")).forEach((el) =>
        {
            el.addEventListener("click", applyExtensionFilters)
        });

        document.getElementById("extensions-filter-search-button")?.
            addEventListener("click", applyExtensionFilters);
    };

    // =========================================================================
    // Collapsible State
    // =========================================================================

    /**
     * Attach hide/show event listeners to collapsible elements for state
     * persistence. Reads the collapsed array from localStorage on each event
     * so it works correctly after section replacement.
     *
     * @param {Element|Document} root  The root element to search within.
     */
    const rememberCollapsibles = (root) =>
    {
        const options = akeeba.System.getOptions("panopticon.siteRemember", {});

        if (!options.collapsible)
        {
            return;
        }

        const localStorageKey = options.collapsible;

        [].slice.call(root.querySelectorAll(".collapse")).forEach((elCollapsible) =>
        {
            const id = elCollapsible.id;

            elCollapsible.addEventListener("hide.bs.collapse", () =>
            {
                let collapsed = [];

                try
                {
                    collapsed = JSON.parse(window.localStorage.getItem(localStorageKey) ?? "[]");
                }
                catch (e)
                {
                    collapsed = [];
                }

                if (typeof collapsed !== "object")
                {
                    collapsed = [];
                }

                if (!collapsed.includes(id))
                {
                    collapsed.push(id);
                    window.localStorage.setItem(localStorageKey, JSON.stringify(collapsed));
                }
            });

            elCollapsible.addEventListener("show.bs.collapse", () =>
            {
                let collapsed = [];

                try
                {
                    collapsed = JSON.parse(window.localStorage.getItem(localStorageKey) ?? "[]");
                }
                catch (e)
                {
                    collapsed = [];
                }

                if (typeof collapsed !== "object")
                {
                    collapsed = [];
                }

                const idx = collapsed.indexOf(id);

                if (idx >= 0)
                {
                    collapsed.splice(idx, 1);
                    window.localStorage.setItem(localStorageKey, JSON.stringify(collapsed));
                }
            })
        });
    };

    /**
     * Restore collapsed state from localStorage, scoped to a root element.
     *
     * @param {Element|Document} root  The root element to search within.
     */
    const restoreCollapsibles = (root) =>
    {
        const options = akeeba.System.getOptions("panopticon.siteRemember", {});

        if (!options.collapsible)
        {
            return;
        }

        const localStorageKey = options.collapsible;
        const json            = window.localStorage.getItem(localStorageKey) ?? "";
        let collapsed         = [];

        try
        {
            collapsed = JSON.parse(json);
        }
        catch (e)
        {
            collapsed = [];
        }

        if (typeof collapsed !== "object")
        {
            collapsed = [];
        }

        [].slice.call(collapsed).forEach((id) =>
        {
            const elCollapsible = root.querySelector
                ? root.querySelector("#" + CSS.escape(id))
                : document.getElementById(id);

            if (!elCollapsible)
            {
                return;
            }

            elCollapsible.classList.remove("show");
        });
    };

    // =========================================================================
    // Backup Button
    // =========================================================================

    /**
     * Attach the "Take Backup" button click handler.
     */
    const attachBackupButtonHandler = () =>
    {
        document.getElementById("akeebaBackupTakeButton")?.addEventListener("click", (event) =>
        {
            const profileId = document.getElementById("akeebaBackupTakeProfile")?.value;
            const url       = akeeba.System.getOptions("akeebabackup")?.enqueue + "&profile_id=" + profileId;

            if (!profileId)
            {
                return;
            }

            window.location = url;
        });
    };

    // =========================================================================
    // UI Component Helpers (scoped to a root element)
    // =========================================================================

    const TOOLTIP_SELECTOR = "[data-toggle-tooltip=\"tooltip\"],[data-bs-toggle=\"tooltip\"],[data-bs-tooltip=\"tooltip\"]";

    /**
     * Dispose all Bootstrap Tooltip instances within a root element.
     */
    const disposeTooltipsIn = (root) =>
    {
        root.querySelectorAll(TOOLTIP_SELECTOR)
            .forEach((el) =>
            {
                const tooltip = bootstrap.Tooltip.getInstance(el);

                if (tooltip)
                {
                    tooltip.dispose();
                }
            });
    };

    /**
     * Initialize Bootstrap Tooltips within a root element.
     */
    const initTooltipsIn = (root) =>
    {
        root.querySelectorAll(TOOLTIP_SELECTOR)
            .forEach((el) => new bootstrap.Tooltip(el));
    };

    /**
     * Dispose all Bootstrap Collapse instances within a root element.
     */
    const disposeCollapsiblesIn = (root) =>
    {
        root.querySelectorAll(".collapse")
            .forEach((el) =>
            {
                const instance = bootstrap.Collapse.getInstance(el);

                if (instance)
                {
                    instance.dispose();
                }
            });
    };

    /**
     * Initialize Choices.js on select elements within a root element.
     */
    const initChoicesIn = (root) =>
    {
        if (typeof Choices === "undefined")
        {
            return;
        }

        root.querySelectorAll(".js-choice")
            .forEach((element) =>
            {
                new Choices(
                    element,
                    {
                        allowHTML:        false,
                        placeholder:      true,
                        placeholderValue: "",
                        removeItemButton: true
                    }
                );
            });
    };

    // =========================================================================
    // Auto-Refresh
    // =========================================================================

    let siteRefreshInFlight = false;
    let siteRefreshTimer    = null;
    let sectionHashes       = {};

    /**
     * Reinitialize JS-powered UI components within a replaced section.
     *
     * @param {string}  sectionKey  The section key (e.g., "extensions", "backup").
     * @param {Element} wrapper     The section wrapper div.
     */
    const reinitializeSection = (sectionKey, wrapper) =>
    {
        // Restore collapsible state (must happen before any Bootstrap Collapse init)
        restoreCollapsibles(wrapper);

        // Tooltips
        initTooltipsIn(wrapper);

        // Collapsible memory listeners
        rememberCollapsibles(wrapper);

        // Section-specific reinitialization
        if (sectionKey === "extensions")
        {
            attachExtensionFilterHandlers();
            restoreExtensionFilters();
            applyExtensionFilters();
        }

        if (sectionKey === "backup")
        {
            initChoicesIn(wrapper);
            attachBackupButtonHandler();
        }
    };

    /**
     * Fetch refreshed section HTML and update changed sections.
     *
     * Content comes from the same-origin CSRF-protected Panopticon server.
     * This is the same trust model as the sites overview table auto-refresh
     * in main.js.
     */
    const siteRefresh = () =>
    {
        const options = akeeba.System.getOptions("panopticon.siteRefresh", {});

        if (!options?.url || siteRefreshInFlight)
        {
            return;
        }

        // Skip refresh if a Bootstrap modal is currently open
        if (document.querySelector(".modal.show"))
        {
            return;
        }

        siteRefreshInFlight = true;

        akeeba.Ajax.ajax(
            options.url,
            {
                type:    "GET",
                cache:   false,
                success: (responseText, statusText, xhr) =>
                         {
                             siteRefreshInFlight = false;

                             let data = null;

                             try
                             {
                                 data = JSON.parse(responseText);
                             }
                             catch (e)
                             {
                                 return;
                             }

                             if (!data || typeof data !== "object")
                             {
                                 return;
                             }

                             for (const [key, section] of Object.entries(data))
                             {
                                 if (!section?.html || !section?.hash)
                                 {
                                     continue;
                                 }

                                 // Skip if content hasn't changed
                                 if (sectionHashes[key] === section.hash)
                                 {
                                     continue;
                                 }

                                 const wrapper = document.getElementById("siteSection-" + key);

                                 if (!wrapper)
                                 {
                                     continue;
                                 }

                                 // Dispose existing UI component instances
                                 disposeTooltipsIn(wrapper);
                                 disposeCollapsiblesIn(wrapper);

                                 // Replace with same-origin server-rendered content (CSRF-protected)
                                 wrapper.innerHTML = section.html;

                                 // Reinitialize UI components (synchronous — no visual flash)
                                 reinitializeSection(key, wrapper);

                                 // Store new hash
                                 sectionHashes[key] = section.hash;
                             }
                         },
                error:   () =>
                         {
                             siteRefreshInFlight = false;

                             // Stop polling on error (session expired, network issue, etc.)
                             if (siteRefreshTimer)
                             {
                                 window.clearInterval(siteRefreshTimer);
                                 siteRefreshTimer = null;
                             }
                         }
            }
        );
    };

    // =========================================================================
    // DOM Ready
    // =========================================================================

    /**
     * Runs when the DOM is ready (page has loaded)
     */
    const onDOMContentLoaded = () =>
    {
        // Akeeba Backup buttons
        attachBackupButtonHandler();

        // Extension filter tooltips
        initTooltipsIn(document);

        // Extension filters
        restoreExtensionFilters();
        attachExtensionFilterHandlers();
        applyExtensionFilters();

        // Remember collapsible status
        restoreCollapsibles(document);
        rememberCollapsibles(document);

        // Enable Choices.js
        initChoicesIn(document);

        // Auto-refresh every 60 seconds
        siteRefreshTimer = window.setInterval(siteRefresh, 60000);
    };

    // Workaround for this file loading before the DOM has been loaded on fast servers.
    if (document.readyState === "loading")
    {
        document.addEventListener("DOMContentLoaded", onDOMContentLoaded);
    }
    else
    {
        onDOMContentLoaded();
    }
})();
```

**Key changes from the original:**
1. `rememberCollapsibles(root)` and `restoreCollapsibles(root)` now accept a root parameter — reads `collapsed` array from localStorage on every event (not from a closure variable) so it works correctly after section replacement.
2. `attachExtensionFilterHandlers()` and `attachBackupButtonHandler()` extracted as named functions for reuse after replacement.
3. `disposeTooltipsIn(root)`, `initTooltipsIn(root)`, `disposeCollapsiblesIn(root)`, `initChoicesIn(root)` — scoped helper functions.
4. `siteRefresh()` — the polling function with hash-based comparison.
5. `reinitializeSection(key, wrapper)` — per-section reinitialization dispatcher.
6. Tooltip selector includes all three variants used in the codebase: `data-toggle-tooltip`, `data-bs-toggle`, and `data-bs-tooltip`.

**Step 2: Commit**

```bash
git add media/js/site-read.js
git commit -m "Add periodic auto-refresh to site read view"
```

---

## Task 7: Rebuild Minified JS Assets

The project uses Babel to transpile JS and generates minified assets via Composer scripts.

**Step 1: Run the build**

```bash
composer run-script npm-deps
```

This runs `npm install`, copies dependencies, transpiles JS via Babel, compiles SCSS, and generates TinyMCE language files.

**Step 2: Verify the minified file was updated**

Check that `media/js/site-read.min.js` was updated:
```bash
git diff --stat media/js/site-read.min.js
```

**Step 3: Commit**

```bash
git add media/js/site-read.min.js media/js/site-read.min.js.map
git commit -m "Rebuild minified JS assets"
```

---

## Verification

After all tasks are complete:

1. Open a site read page (e.g., `?view=site&task=read&id=2`)
2. Open browser DevTools Network tab
3. Wait 60 seconds — verify a single AJAX request to `refreshSections` fires
4. Verify the response is JSON with section keys and hash values
5. If nothing changed, verify no DOM replacement occurs (hashes match)
6. Trigger a background task that changes data (e.g., run site info refresh), wait for the next poll, and verify the changed section updates
7. Expand/collapse a section, wait for refresh — verify collapse state is preserved
8. Apply extension filters, wait for refresh — verify filters remain applied
9. Open a modal, wait for refresh — verify refresh is skipped while modal is open
10. Leave the page open with an expired session — verify polling stops on error
