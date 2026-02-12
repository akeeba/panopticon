/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

;// noinspection JSUnusedGlobalSymbols
((document, akeeba) =>
{
    "use strict";

    const STORAGE_KEY = "panopticon.extensioninstall.selectedSites";

    /**
     * Get the selected site IDs from localStorage.
     *
     * @returns {number[]}
     */
    function getStoredSelection()
    {
        try
        {
            const raw = localStorage.getItem(STORAGE_KEY);

            return raw ? JSON.parse(raw) : [];
        }
        catch (e)
        {
            return [];
        }
    }

    /**
     * Save the selected site IDs to localStorage.
     *
     * @param {number[]} ids
     */
    function saveSelection(ids)
    {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(ids));
    }

    /**
     * Toggle a site ID in the stored selection.
     *
     * @param {number} siteId
     * @param {boolean} checked
     */
    function toggleSite(siteId, checked)
    {
        let ids = getStoredSelection();

        if (checked)
        {
            if (!ids.includes(siteId))
            {
                ids.push(siteId);
            }
        }
        else
        {
            ids = ids.filter(id => id !== siteId);
        }

        saveSelection(ids);
    }

    /**
     * Restore checkbox states from localStorage on page load.
     */
    function restoreCheckboxes()
    {
        const ids = getStoredSelection();
        const checkboxes = document.querySelectorAll(".extensioninstall-site-cb");

        checkboxes.forEach(cb =>
        {
            const siteId = parseInt(cb.dataset.siteId, 10);

            cb.checked = ids.includes(siteId);
        });

        updateCheckAllState();
    }

    /**
     * Update the "check all" checkbox state based on individual checkboxes.
     */
    function updateCheckAllState()
    {
        const checkAll = document.getElementById("checkAll");

        if (!checkAll)
        {
            return;
        }

        const checkboxes = document.querySelectorAll(".extensioninstall-site-cb");
        const allChecked = checkboxes.length > 0
            && Array.from(checkboxes).every(cb => cb.checked);

        checkAll.checked = allChecked;
    }

    /**
     * Handle a checkbox change event.
     */
    akeeba.ExtensionInstall = akeeba.ExtensionInstall || {};

    akeeba.ExtensionInstall.onCheckboxChange = function()
    {
        const checkboxes = document.querySelectorAll(".extensioninstall-site-cb");

        checkboxes.forEach(cb =>
        {
            const siteId = parseInt(cb.dataset.siteId, 10);

            toggleSite(siteId, cb.checked);
        });

        updateCheckAllState();
    };

    /**
     * Toggle all checkboxes on the current page.
     *
     * @param {boolean} checked
     */
    akeeba.ExtensionInstall.toggleAll = function(checked)
    {
        const checkboxes = document.querySelectorAll(".extensioninstall-site-cb");

        checkboxes.forEach(cb =>
        {
            cb.checked = checked;

            const siteId = parseInt(cb.dataset.siteId, 10);

            toggleSite(siteId, checked);
        });
    };

    /**
     * Collect all selected site IDs and submit the form to the review task.
     */
    akeeba.ExtensionInstall.submitSelection = function()
    {
        const ids = getStoredSelection();

        if (ids.length === 0)
        {
            alert("Please select at least one site.");

            return;
        }

        const field = document.getElementById("site_ids");

        if (field)
        {
            field.value = JSON.stringify(ids);
        }

        // Clear the stored selection after submitting
        localStorage.removeItem(STORAGE_KEY);

        document.getElementById("adminForm").submit();
    };

    // Restore checkboxes on page load
    document.addEventListener("DOMContentLoaded", restoreCheckboxes);

})(document, window.akeeba = window.akeeba || {});
