/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

(() =>
{
	const onTabClick = (e) =>
	{
		const options = akeeba.System.getOptions("panopticon.rememberTab");
		let itemName  = options?.key ?? "rememberTab";

		window.localStorage.setItem(itemName, e.target.id || "");
	};

	const onDOMContentLoaded = () => {
		document.querySelectorAll("button[data-bs-toggle=\"tab\"]").forEach((el) =>
		{
			el.addEventListener("click", onTabClick);
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
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", onDOMContentLoaded);
	} else {
		onDOMContentLoaded();
	}
})();
