/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

(() =>
{
    const heartBeatCheck = () =>
    {
        const options = akeeba.System.getOptions("panopticon.heartbeat")
        akeeba.Ajax.ajax(
            options.url,
            {
                type:    "GET",
                cache:   false,
                success: (responseText, statusText, xhr) =>
                         {
                             let response = null;

                             try
                             {
                                 response = JSON.parse(responseText);
                             }
                             catch (e)
                             {
                                 // Maybe the session expired?
                                 return;
                             }

                             const targetId = options.warningId;

                             if (!targetId)
                             {
                                 return;
                             }

                             const elTarget = document.getElementById(targetId);

                             if (!elTarget)
                             {
                                 return;
                             }

                             elTarget.classList.remove("d-none", "d-block");
                             elTarget.classList.add(response ? "d-none" : "d-block");
                         }
            }
        );
    }

    const usageStats = () =>
    {
        const options = akeeba.System.getOptions("panopticon.usagestats", {});

        if (!options?.enabled)
        {
            return;
        }

        akeeba.Ajax.ajax(
            options.url,
            {
                type:  "GET",
                cache: false,
            }
        );
    };

    let tableBodyRefreshInFlight = false;

    const tableBodyRefresh = () =>
    {
        const options = akeeba.System.getOptions("panopticon.tableRefresh", {});

        if (!options?.url || tableBodyRefreshInFlight)
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

        tableBodyRefreshInFlight = true;

        akeeba.Ajax.ajax(
            options.url,
            {
                type:    "GET",
                cache:   false,
                success: (responseText, statusText, xhr) =>
                         {
                             tableBodyRefreshInFlight = false;

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

                             // Replace with server-rendered content (same-origin, CSRF-protected)
                             elTbody.innerHTML = newHtml;

                             // Re-initialize tooltips on the new content
                             elTbody.querySelectorAll("[data-bs-toggle=\"tooltip\"]")
                                 .forEach((el) => new bootstrap.Tooltip(el));
                         },
                error:   () =>
                         {
                             tableBodyRefreshInFlight = false;
                         }
            }
        );
    };

    const onDOMContentLoaded = () =>
    {
        // Set up the CRON heartbeat check
        window.setInterval(heartBeatCheck, 30000);

        heartBeatCheck();

        // Set up the sites table auto-refresh
        window.setInterval(tableBodyRefresh, 30000);

        window.setTimeout(usageStats, 500);

        // Enable BS tooltips
        const tooltipTriggerList = document.querySelectorAll("[data-bs-toggle=\"tooltip\"]")
        const tooltipList        = [...tooltipTriggerList].map(
            tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl))

        // Enable Choices.js
        document
            .querySelectorAll(".js-choice")
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
    }

    if (document.readyState === "loading")
    {
        document.addEventListener("DOMContentLoaded", onDOMContentLoaded);
    }
    else
    {
        onDOMContentLoaded();
    }
})()