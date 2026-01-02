/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

(() =>
{
    const onDOMContentLoaded = () =>
    {
        // Enable Choices.js
        if (typeof Choices !== "undefined")
        {
            document.querySelectorAll(".js-choice")
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
    }

    if (document.readyState === "loading")
    {
        document.addEventListener("DOMContentLoaded", onDOMContentLoaded);
    }
    else
    {
        onDOMContentLoaded();
    }
})();