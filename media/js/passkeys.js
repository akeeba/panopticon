/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

window.akeeba              = window.akeeba || {};
window.akeeba.Passwordless = window.akeeba.Passwordless || {};

((System, Passwordless, document) =>
{
    "use strict";

    const reportErrorToUser = (message) =>
    {
        alert(message);
    }

    Passwordless.initCreateCredentials = (resident) =>
    {
        // Make sure the browser supports Webauthn
        if (!("credentials" in navigator))
        {
            reportErrorToUser(System.Text._("PANOPTICON_PASSKEYS_ERR_NO_BROWSER_SUPPORT"));

            return;
        }


        // Get the public key creation options through AJAX.
        const postBackData = {
            resident: resident ? 1 : 0
        };

        System.ajax(
            System.getOptions("panopticon.passkey.initURL"),
            {
                method:  "POST",
                data:    postBackData,
                success: (response) =>
                         {
                             try
                             {
                                 const publicKey = JSON.parse(response);

                                 Passwordless.createCredentials(publicKey);
                             }
                             catch (exception)
                             {
                                 reportErrorToUser(Joomla.Text._("PANOPTICON_PASSKEYS_ERR_XHR_INITCREATE"));
                             }
                         },
                error:   (xhr) =>
                         {
                             reportErrorToUser(`${xhr.status} ${xhr.statusText}`);
                         },
            }
        );
    }

    /**
     * Ask the user to link an authenticator using the provided public key (created server-side).
     * Posts the credentials to the URL defined in post_url using AJAX.
     * That URL must re-render the management interface.
     *
     * @param {Object} publicKey The public key request parameters loaded from the server
     */
    // eslint-disable-next-line no-unused-vars
    Passwordless.createCredentials = (publicKey) =>
    {
        const postURL             = System.getOptions("panopticon.passkey.createURL");
        const arrayToBase64String = (a) => btoa(String.fromCharCode(...a));
        const base64url2base64    = (input) =>
        {
            let output = input
                .replace(/-/g, "+")
                .replace(/_/g, "/");
            const pad  = output.length % 4;
            if (pad)
            {
                if (pad === 1)
                {
                    throw new Error(
                        "InvalidLengthError: Input base64url string is the wrong length to determine padding");
                }
                output += new Array(5 - pad).join("=");
            }
            return output;
        };

        // Convert the public key information to a format usable by the browser's credentials manager
        publicKey.challenge = Uint8Array.from(
            window.atob(base64url2base64(publicKey.challenge)), (c) => c.charCodeAt(0),
        );

        publicKey.user.id = Uint8Array.from(window.atob(publicKey.user.id), (c) => c.charCodeAt(0));

        if (publicKey.excludeCredentials)
        {
            publicKey.excludeCredentials = publicKey.excludeCredentials.map((data) =>
            {
                data.id = Uint8Array.from(window.atob(base64url2base64(data.id)), (c) => c.charCodeAt(0));
                return data;
            });
        }

        // Ask the browser to prompt the user for their authenticator
        navigator.credentials.create({publicKey})
                 .then((data) =>
                 {
                     const publicKeyCredential = {
                         id:       data.id,
                         type:     data.type,
                         rawId:    arrayToBase64String(new Uint8Array(data.rawId)),
                         response: {
                             clientDataJSON:    arrayToBase64String(new Uint8Array(data.response.clientDataJSON)),
                             attestationObject: arrayToBase64String(new Uint8Array(data.response.attestationObject)),
                         },
                     };

                     // Send the response to your server
                     const postBackData = {
                         data: btoa(JSON.stringify(publicKeyCredential)),
                     };

                     Joomla.request(
                         postURL,
                         {
                             method:  "POST",
                             data:    postBackData,
                             success: (responseHTML) =>
                                      {
                                          const elements = document.querySelectorAll(
                                              "#passkey-management-interface");

                                          if (!elements)
                                          {
                                              return;
                                          }

                                          const elContainer = elements[0];

                                          elContainer.outerHTML = responseHTML;

                                          Passwordless.initManagement();
                                          Passwordless.reactivateTooltips();
                                      },
                             onError: (xhr) =>
                                      {
                                          reportErrorToUser(`${xhr.status} ${xhr.statusText}`);
                                      },
                         }
                     );
                 })
                 .catch((error) =>
                 {
                     // An error occurred: timeout, request to provide the authenticator refused, hardware /
                     // software error...
                     reportErrorToUser(error);
                 });
    };

    // TODO Edit me

    /**
     * Edit label button
     *
     * @param   {Element} that      The button being clicked
     */
    // eslint-disable-next-line no-unused-vars
    Passwordless.editLabel = (that) =>
    {
        const paths   = Joomla.getOptions("system.paths");
        const postURL = `${paths ? `${paths.base}/index.php` : window.location.pathname}`;

        // Find the UI elements
        const elTR         = that.parentElement.parentElement;
        const credentialId = elTR.dataset.credential_id;
        const elTDs        = elTR.querySelectorAll(".plg_system_passwordless-cell");
        const elLabelTD    = elTDs[0];
        const elButtonsTD  = elTDs[1];
        const elButtons    = elButtonsTD.querySelectorAll("button");
        const elEdit       = elButtons[0];
        const elDelete     = elButtons[1];
        const elLabel      = elLabelTD.querySelectorAll(".plg_system_passwordless-label")[0];

        // Show the editor
        const oldLabel = elLabel.innerText;

        const elInput        = document.createElement("input");
        elInput.className    = "flex-grow-1 me-1 mb-1";
        elInput.type         = "text";
        elInput.name         = "label";
        elInput.defaultValue = oldLabel;

        const elGUIContainer     = document.createElement("div");
        elGUIContainer.className = "d-flex flex-column flex-lg-row"

        const elButtonContainer     = document.createElement("div");
        elButtonContainer.className = "d-flex mt-2 mb-3 mt-lg-0 mb-lg-0";

        const elSave     = document.createElement("button");
        elSave.className = "btn btn-success btn-sm me-1 mb-1 flex-grow-1";
        elSave.innerText = Joomla.Text._("PLG_SYSTEM_PASSWORDLESS_MANAGE_BTN_SAVE_LABEL");
        elSave.addEventListener("click", () =>
        {
            const elNewLabel = elInput.value;

            if (elNewLabel !== "")
            {
                const postBackData                            = {
                    option:        "com_ajax",
                    group:         "system",
                    plugin:        "passwordless",
                    format:        "json",
                    encoding:      "json",
                    akaction:      "savelabel",
                    credential_id: credentialId,
                    new_label:     elNewLabel,
                };
                postBackData[Joomla.getOptions("csrf.token")] = 1;

                Joomla.request({
                    url:    postURL,
                    method: "POST",
                    data:   interpolateParameters(postBackData),
                    onSuccess(rawResponse)
                    {
                        let result = false;

                        try
                        {
                            result = JSON.parse(rawResponse);
                        }
                        catch (exception)
                        {
                            result = (rawResponse === "true");
                        }

                        if (result !== true)
                        {
                            reportErrorToUser(
                                Joomla.Text._("PLG_SYSTEM_PASSWORDLESS_ERR_LABEL_NOT_SAVED"),
                            );

                            elCancel.click();
                        }
                    },
                    onError: (xhr) =>
                             {
                                 reportErrorToUser(
                                     `${Joomla.Text._("PLG_SYSTEM_PASSWORDLESS_ERR_LABEL_NOT_SAVED")
                                     } -- ${xhr.status} ${xhr.statusText}`,
                                 );

                                 elCancel.click();
                             },
                });
            }

            elLabel.innerText = elNewLabel;
            elEdit.disabled   = false;
            elDelete.disabled = false;

            return false;
        }, false);

        const elCancel     = document.createElement("button");
        elCancel.className = "btn btn-danger btn-sm me-1 mb-1 flex-grow-1";
        elCancel.innerText = Joomla.Text._("PLG_SYSTEM_PASSWORDLESS_MANAGE_BTN_CANCEL_LABEL");
        elCancel.addEventListener("click", () =>
        {
            elLabel.innerText = oldLabel;
            elEdit.disabled   = false;
            elDelete.disabled = false;

            return false;
        }, false);

        elButtonContainer.appendChild(elSave);
        elButtonContainer.appendChild(elCancel);

        elGUIContainer.appendChild(elInput);
        elGUIContainer.appendChild(elButtonContainer);
        elLabel.innerHTML = "";
        elLabel.appendChild(elGUIContainer);
        elEdit.disabled   = true;
        elDelete.disabled = true;

        return false;
    };

    /**
     * Delete button
     *
     * @param   {Element} that      The button being clicked
     */
    // eslint-disable-next-line no-unused-vars
    Passwordless.delete = (that) =>
    {
        const paths   = Joomla.getOptions("system.paths");
        const postURL = `${paths ? `${paths.base}/index.php` : window.location.pathname}`;

        // Find the UI elements
        const elTR         = that.parentElement.parentElement;
        const credentialId = elTR.dataset.credential_id;
        const elTDs        = elTR.querySelectorAll(".plg_system_passwordless-cell");
        const elButtonsTD  = elTDs[1];
        const elButtons    = elButtonsTD.querySelectorAll("button");
        const elEdit       = elButtons[0];
        const elDelete     = elButtons[1];

        elEdit.disabled   = true;
        elDelete.disabled = true;

        // Delete the record
        const postBackData                            = {
            option:        "com_ajax",
            group:         "system",
            plugin:        "passwordless",
            format:        "json",
            encoding:      "json",
            akaction:      "delete",
            credential_id: credentialId,
        };
        postBackData[Joomla.getOptions("csrf.token")] = 1;

        Joomla.request({
            url:    postURL,
            method: "POST",
            data:   interpolateParameters(postBackData),
            onSuccess(rawResponse)
            {
                let result = false;

                try
                {
                    result = JSON.parse(rawResponse);
                }
                catch (e)
                {
                    result = (rawResponse === "true");
                }

                if (result !== true)
                {
                    reportErrorToUser(
                        Joomla.Text._("PLG_SYSTEM_PASSWORDLESS_ERR_NOT_DELETED"),
                    );

                    elEdit.disabled   = false;
                    elDelete.disabled = false;

                    return;
                }

                elTR.parentElement.removeChild(elTR);
            },
            onError: (xhr) =>
                     {
                         elEdit.disabled   = false;
                         elDelete.disabled = false;
                         reportErrorToUser(
                             `${Joomla.Text._("PLG_SYSTEM_PASSWORDLESS_ERR_NOT_DELETED")
                             } -- ${xhr.status} ${xhr.statusText}`,
                         );
                     },
        });

        return false;
    };

    Passwordless.reactivateTooltips = () =>
    {
        const tooltips = Joomla.getOptions("bootstrap.tooltip");
        if (typeof tooltips === "object" && tooltips !== null)
        {
            Object.keys(tooltips).forEach((tooltip) =>
            {
                const opt     = tooltips[tooltip];
                const options = {
                    animation:         opt.animation ? opt.animation : true,
                    container:         opt.container ? opt.container : false,
                    delay:             opt.delay ? opt.delay : 0,
                    html:              opt.html ? opt.html : false,
                    selector:          opt.selector ? opt.selector : false,
                    trigger:           opt.trigger ? opt.trigger : "hover focus",
                    fallbackPlacement: opt.fallbackPlacement ? opt.fallbackPlacement : null,
                    boundary:          opt.boundary ? opt.boundary : "clippingParents",
                    title:             opt.title ? opt.title : "",
                    customClass:       opt.customClass ? opt.customClass : "",
                    sanitize:          opt.sanitize ? opt.sanitize : true,
                    sanitizeFn:        opt.sanitizeFn ? opt.sanitizeFn : null,
                    popperConfig:      opt.popperConfig ? opt.popperConfig : null,
                };

                if (opt.placement)
                {
                    options.placement = opt.placement;
                }
                if (opt.template)
                {
                    options.template = opt.template;
                }
                if (opt.allowList)
                {
                    options.allowList = opt.allowList;
                }

                const elements = Array.from(document.querySelectorAll(tooltip));
                if (elements.length)
                {
                    elements.map((el) => new window.bootstrap.Tooltip(el, options));
                }
            });
        }
    }

    /**
     * Add New Authenticator button click handler
     *
     * @param   {MouseEvent} event  The mouse click event
     *
     * @returns {boolean} Returns false to prevent the default browser button behavior
     */
    Passwordless.addOnClick = (event) =>
    {
        event.preventDefault();

        Passwordless.initCreateCredentials(false);

        return false;
    };

    /**
     * Add New Authenticator button click handler
     *
     * @param   {MouseEvent} event  The mouse click event
     *
     * @returns {boolean} Returns false to prevent the default browser button behavior
     */
    Passwordless.addPasskeyOnClick = (event) =>
    {
        event.preventDefault();

        Passwordless.initCreateCredentials(true);

        return false;
    };

    /**
     * Edit Name button click handler
     *
     * @param   {MouseEvent} event  The mouse click event
     *
     * @returns {boolean} Returns false to prevent the default browser button behavior
     */
    Passwordless.editOnClick = (event) =>
    {
        event.preventDefault();

        Passwordless.editLabel(event.currentTarget);

        return false;
    };

    /**
     * Remove button click handler
     *
     * @param   {MouseEvent} event  The mouse click event
     *
     * @returns {boolean} Returns false to prevent the default browser button behavior
     */
    Passwordless.deleteOnClick = (event) =>
    {
        event.preventDefault();

        Passwordless.delete(event.currentTarget);

        return false;
    };

    /**
     * Initialization on page load.
     */
    Passwordless.initManagement = () =>
    {
        const addButton = document.getElementById("plg_system_passwordless-manage-add");

        if (addButton)
        {
            addButton.addEventListener("click", Passwordless.addOnClick);
        }

        const addPasskeyButton = document.getElementById("plg_system_passwordless-manage-addresident");

        if (addPasskeyButton)
        {
            addPasskeyButton.addEventListener("click", Passwordless.addPasskeyOnClick);
        }

        const editLabelButtons = [].slice.call(document.querySelectorAll(".plg_system_passwordless-manage-edit"));
        if (editLabelButtons.length)
        {
            editLabelButtons.forEach((button) =>
            {
                button.addEventListener("click", Passwordless.editOnClick);
            });
        }

        const deleteButtons = [].slice.call(document.querySelectorAll(".plg_system_passwordless-manage-delete"));
        if (deleteButtons.length)
        {
            deleteButtons.forEach((button) =>
            {
                button.addEventListener("click", Passwordless.deleteOnClick);
            });
        }
    };

    // Initialization. Runs on DOM content loaded since this script is always loaded deferred.
    Passwordless.initManagement();

    const onDOMContentLoaded = () =>
    {

    }

    if (document.readyState === "loading")
    {
        document.addEventListener("DOMContentLoaded", onDOMContentLoaded);
    }
    else
    {
        onDOMContentLoaded();
    }
})(akeeba.System, window.akeeba.Passwordless, document)
