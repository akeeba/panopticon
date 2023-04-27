/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

(() => {
    const toggleDarkMode = (dark) => {
        const topBar = document.getElementById('topNavbar');
        document.documentElement.dataset.bsTheme = dark ? 'dark' : 'light';
        document.getElementById('panopticonLogoColour').dataset.bsTheme = dark ? 'dark' : 'light';
        topBar.classList.remove('bg-body-tertiary', 'bg-body-secondary');
        topBar.classList.add(dark ? 'bg-body-tertiary' : 'bg-body-secondary');
    };

    window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", e => toggleDarkMode(e.matches));

    toggleDarkMode(window.matchMedia("(prefers-color-scheme: dark)").matches)
})()
