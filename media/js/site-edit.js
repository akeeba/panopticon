/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

(() =>
{
    const BoUInitialise = () =>
    {
        document.getElementById("backupOnUpdateReload").addEventListener("click", onBOUReloadExtensionsClick);
        document.getElementById("backupOnUpdateRelink").addEventListener("click", onBOURelinkClick);
        document.getElementById("backupOnUpdateReloadProfiles").addEventListener("click", onBOUReloadProfiles);

        akeeba.Showon.initialise(document.getElementById("backupOnUpdateInterface"));
    };

    const BoUReapplyInterface = (text) =>
    {
        document.getElementById("backupOnUpdateInterface").outerHTML = text;

        BoUInitialise();
    };

    const BoUShowSpinner = () =>
    {
        document.getElementById("backupOnUpdateInteractive").classList.add("d-none");
        document.getElementById("backupOnUpdateSpinner").classList.remove("d-none");
    }

    const BoUHideSpinner = () =>
    {
        document.getElementById("backupOnUpdateInteractive").classList.remove("d-none");
        document.getElementById("backupOnUpdateSpinner").classList.add("d-none");
    }

    const onBOUReloadExtensionsClick = (e) =>
    {
        const options = akeeba.System.getOptions("panopticon.backupOnUpdate");
        const url     = options.reload;

        BoUShowSpinner();

        window.akeeba.Ajax.ajax(url, {
            method:  "GET",
            success: (text) =>
                     {
                         BoUReapplyInterface(text)
                     }
        });
    };

    const onBOURelinkClick = (e) =>
    {
        const options = akeeba.System.getOptions("panopticon.backupOnUpdate");
        const url     = options.relink;

        BoUShowSpinner();

        window.akeeba.Ajax.ajax(url, {
            method:  "GET",
            success: (text) =>
                     {
                         BoUReapplyInterface(text)
                     }
        });
    }

    const onBOUReloadProfiles = (e) =>
    {
        const options        = akeeba.System.getOptions("panopticon.backupOnUpdate");
        const url            = options.refresh;
        const profilesSelect = document.getElementById("backupOnUpdateProfiles");

        BoUShowSpinner();

        window.akeeba.Ajax.ajax(url + "&selected=" + profilesSelect.value, {
            method:  "GET",
            success: (text) =>
                     {
                         profilesSelect.outerHTML = text;
                         document.getElementById("backupOnUpdateReloadProfiles")
                                 .addEventListener("click", onBOUReloadProfiles);

                         BoUHideSpinner();
                     }
        });
    }

    const onDOMContentLoaded = () =>
    {
        BoUInitialise();

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