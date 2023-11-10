/*!*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

(() =>
{
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

    const rememberCollapsibles = () =>
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

        [].slice.call(document.querySelectorAll(".collapse")).forEach((elCollapsible) =>
        {
            const id = elCollapsible.id;

            elCollapsible.addEventListener("hide.bs.collapse", () =>
            {
                if (collapsed.includes(id))
                {
                    return;
                }

                collapsed.push(id);

                window.localStorage.setItem(localStorageKey, JSON.stringify(collapsed));
            });

            elCollapsible.addEventListener("show.bs.collapse", () =>
            {
                const idx = collapsed.indexOf(id);

                if (idx < 0)
                {
                    return;
                }

                collapsed.splice(idx, 1);

                window.localStorage.setItem(localStorageKey, JSON.stringify(collapsed));
            })
        });
    };

    const restoreCollapsibles = () =>
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
            const elCollapsible = document.getElementById(id);

            if (!elCollapsible)
            {
                return;
            }

            elCollapsible.classList.remove("show");
        });
    };

    /**
     * Runs when the DOM is ready (page has loaded)
     */
    const onDOMContentLoaded = () =>
    {
        // Akeeba Backup buttons
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

        // Extension filter tooltips
        const tooltipTriggerList = document.querySelectorAll("[data-toggle-tooltip=\"tooltip\"]")
        const tooltipList        = [...tooltipTriggerList].map(
            tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

        // Extension filters
        restoreExtensionFilters();

        [].slice.call(document.querySelectorAll(".extensionFilter")).forEach((el) =>
        {
            el.addEventListener("click", applyExtensionFilters)
        });

        document.getElementById('extensions-filter-search-button')?.
            addEventListener("click", applyExtensionFilters);

        applyExtensionFilters();

        // Remember collapsible status
        restoreCollapsibles();
        rememberCollapsibles();
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
