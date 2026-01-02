/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

(() =>
{
    /**
     * Applies filtering to the extensions list table
     *
     * @since 1.0.4
     */
    const applyFiltering = () =>
    {
        const elFilterField  = document.getElementById("extensions-filter-search");
        const elFilteredRows = document.querySelectorAll("tr.extensions-filterable-row");

        if (!elFilterField || !elFilteredRows || !elFilteredRows.length)
        {
            return;
        }

        const filterString = elFilterField.value.toLowerCase();

        elFilteredRows.forEach((elRow) =>
        {
            if (!filterString)
            {
                elRow.classList.remove("d-none");

                return;
            }

            let isMatch = false;

            const elName   = elRow.querySelectorAll(".extensions-filterable-name");
            const elKey    = elRow.querySelectorAll(".extensions-filterable-key");
            const elAuthor = elRow.querySelectorAll(".extensions-filterable-author");

            if (elName.length)
            {
                isMatch = isMatch || elName[0]?.innerText?.toLowerCase()?.includes(filterString);
            }

            if (elKey.length)
            {
                isMatch = isMatch || elKey[0]?.innerText?.toLowerCase()?.includes(filterString);
            }

            if (elAuthor.length)
            {
                isMatch = isMatch || elAuthor[0]?.innerText?.toLowerCase()?.includes(filterString);
            }

            if (isMatch)
            {
                elRow.classList.remove("d-none");
            }
            else
            {
                elRow.classList.add("d-none");
            }
        });
    };

    /**
     * Applies the event hooks when the page has finished loading
     *
     * @since 1.0.4
     */
    const onDOMContentLoaded = () =>
    {
        const elButton  = document.getElementById('extensions-filter-search-button');

        if (!elButton)
        {
            return;
        }

        elButton.addEventListener('click', applyFiltering);
    };

    if (document.readyState === "loading")
    {
        document.addEventListener("DOMContentLoaded", onDOMContentLoaded);
    }
    else
    {
        onDOMContentLoaded();
    }
})()