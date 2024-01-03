/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

(() => {
    const toggleDarkMode = (dark) => {
        document.documentElement.dataset.bsTheme = dark ? 'dark' : 'light';
    };

    window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", e => toggleDarkMode(e.matches));

    toggleDarkMode(window.matchMedia("(prefers-color-scheme: dark)").matches)
})()
