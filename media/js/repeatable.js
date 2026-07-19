/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

/**
 * A minimal repeatable (multi-row) form control.
 *
 * Markup contract:
 *
 * <div class="js-repeatable" data-repeatable-name="options[foo]">
 *     <div class="js-repeatable-rows">
 *         <div class="js-repeatable-row"> ... input ... <button class="js-repeatable-remove"> </div>
 *     </div>
 *     <template class="js-repeatable-template"> ...one empty row... </template>
 *     <button class="js-repeatable-add">
 * </div>
 *
 * Rows post as an unindexed array (name="options[foo][]"), so the server receives a plain list and row order in the
 * DOM is the only thing that matters. There is no index bookkeeping to get out of step.
 */
(() =>
{
    "use strict";

    /**
     * Remove a row, guaranteeing at least one row always remains.
     *
     * Leaving the control with zero rows would make it impossible to add a value back without reloading the page.
     *
     * @param {HTMLElement} container The repeatable container
     * @param {HTMLElement} row       The row to remove
     */
    const removeRow = (container, row) =>
    {
        const rows = container.querySelectorAll(".js-repeatable-row");

        if (rows.length <= 1)
        {
            // Last row: clear it instead of removing it.
            row.querySelectorAll("input").forEach((input) =>
            {
                input.value = "";
            });

            return;
        }

        row.remove();
    };

    /**
     * Append a new, empty row and focus its first input.
     *
     * @param {HTMLElement} container The repeatable container
     */
    const addRow = (container) =>
    {
        const template = container.querySelector(".js-repeatable-template");
        const rowsHost = container.querySelector(".js-repeatable-rows");

        if (!template || !rowsHost)
        {
            return;
        }

        const clone = template.content.cloneNode(true);

        rowsHost.appendChild(clone);

        const added = rowsHost.querySelector(".js-repeatable-row:last-child input");

        if (added)
        {
            added.focus();
        }
    };

    /**
     * Wire up a single repeatable container.
     *
     * Events are delegated from the container so that rows added after initialisation work without re-binding.
     *
     * @param {HTMLElement} container The repeatable container
     */
    const initRepeatable = (container) =>
    {
        if (container.dataset.repeatableReady === "1")
        {
            return;
        }

        container.dataset.repeatableReady = "1";

        container.addEventListener("click", (event) =>
        {
            const addButton = event.target.closest(".js-repeatable-add");

            if (addButton && container.contains(addButton))
            {
                event.preventDefault();
                addRow(container);

                return;
            }

            const removeButton = event.target.closest(".js-repeatable-remove");

            if (removeButton && container.contains(removeButton))
            {
                event.preventDefault();

                const row = removeButton.closest(".js-repeatable-row");

                if (row)
                {
                    removeRow(container, row);
                }
            }
        });

        // Enter in a row input adds a new row rather than submitting the whole configuration form.
        container.addEventListener("keydown", (event) =>
        {
            if (event.key !== "Enter")
            {
                return;
            }

            if (!event.target.closest(".js-repeatable-row"))
            {
                return;
            }

            event.preventDefault();
            addRow(container);
        });
    };

    document.addEventListener("DOMContentLoaded", () =>
    {
        document.querySelectorAll(".js-repeatable").forEach(initRepeatable);
    });
})();
