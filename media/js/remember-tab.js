/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

(() =>
{
    const onTabClick = (e) =>
    {
        const options = akeeba.System.getOptions("panopticon.rememberTab");
        let itemName  = options?.key ?? "rememberTab";

        window.localStorage.setItem(itemName, e.target.id || "");
    }

    document.addEventListener("DOMContentLoaded", () =>
    {
        document.querySelectorAll("button[data-bs-toggle=\"tab\"]").forEach((el) =>
        {
            el.addEventListener("click", onTabClick)
        });

        const options          = akeeba.System.getOptions("panopticon.rememberTab");
        let itemName           = options?.key ?? "rememberTab";
        let activeItem         = null;
        let activeItemSelector = window.localStorage.getItem(itemName);

        if (activeItemSelector)
        {
            activeItem = document.getElementById(activeItemSelector);
        }

        if (activeItem)
        {
            activeItem.click();
        }
    })
})()
