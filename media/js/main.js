/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
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

    const onDOMContentLoaded = () =>
    {
        // Set up the CRON heartbeat check
        window.setInterval(heartBeatCheck, 30000);

        heartBeatCheck();

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