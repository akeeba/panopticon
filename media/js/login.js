/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

(() => {
    const onDOMContentLoaded = () => {
        document.getElementById('language')
            ?.addEventListener('change', () => {
                const language = document.getElementById('language').value;
                const url = akeeba.System.getOptions('login.url');

                window.location = url + language;
            });
    };

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", onDOMContentLoaded);
    } else {
        onDOMContentLoaded();
    }
})();