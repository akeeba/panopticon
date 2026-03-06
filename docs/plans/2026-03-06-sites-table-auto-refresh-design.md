# Sites Table Auto-Refresh Design

## Goal

Periodically refresh the sites overview table (default layout) every 30 seconds by fetching server-rendered tbody HTML and swapping it in if the content has changed.

## Approach

Fetch the server-rendered `<tbody>` HTML via a new raw endpoint. Compare with current DOM content. Replace if different. Skip if a modal is open.

## Changes

### 1. Template Refactoring

**New file: `ViewTemplates/Main/default_tbody.blade.php`**
- Extract lines 85-228 from `default.blade.php` (the `<tbody>` tag, the `@foreach` loop, all per-row partial includes, and the "no results" row)

**Modified: `ViewTemplates/Main/default.blade.php`**
- Replace extracted lines with `@include('Main/default_tbody')`
- Add `id="sitesTableBody"` to the `<tbody>` tag (inside the partial)

### 2. Controller + Raw View

**New file: `src/View/Main/Raw.php`**
- Raw view class with `onBeforeTableBody()` method
- Loads Site model with `enabled=1` and saved session state (filters, ordering, pagination)
- Builds `$this->groupMap` from the Groups model
- Sets `$this->items` and `$this->itemsCount`
- Sets layout to `default_tbody`
- Does NOT load toolbar, JavaScript, self-update info, or other full-page concerns

**Modified: `src/Controller/Main.php`**
- New `tableBody()` task with CSRF protection
- Loads saved model state, calls `$this->display()`
- Endpoint: `index.php?view=main&task=tableBody&format=raw&{token}=1`

### 3. JavaScript

**Modified: `media/js/main.js`**
- New `tableBodyRefresh()` function on a 30-second interval
- Reads URL from `panopticon.tableRefresh` script options (only set on default layout)
- Each tick: skip if a Bootstrap modal is open, fetch tbody HTML, compare with current innerHTML, replace if different, re-initialize Bootstrap tooltips on new content
- Silently skip on network/session errors

**Modified: `src/View/Main/Html.php`**
- In `onBeforeMain()`, when not dashboard, add `panopticon.tableRefresh` script options with the endpoint URL
