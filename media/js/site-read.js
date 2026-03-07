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
