/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

(() =>
{
    const handleLanguageChange = () =>
    {
        document.getElementById("language")
                ?.addEventListener("change", () =>
                {
                    const language = document.getElementById("language").value;
                    const url      = akeeba.System.getOptions("login.url");

                    window.location = url + language;
                });
    }

    const handleLoginError = (message) =>
    {
        alert(message);
    }

    const arrayToBase64String = (a) => btoa(String.fromCharCode(...a));

    const base64url2base64 = (input) =>
    {
        let output = input
            .replace(/-/g, "+")
            .replace(/_/g, "/");

        const pad = output.length % 4;

        if (pad)
        {
            if (pad === 1)
            {
                throw new Error(
                    "InvalidLengthError: Input base64url string is the wrong length to determine padding"
                );
            }
            output += new Array(5 - pad).join("=");
        }

        return output;
    };

    /**
     * Initialize the passwordless login, going through the server to get the registered certificates
     * for the user.
     *
     * @returns {boolean}  Always FALSE to prevent BUTTON elements from reloading the page.
     */
    const passkeyLogin = () =>
    {
        const postURL = akeeba.System.getOptions("passkey").challengeURL;

        akeeba.Ajax.ajax(
            postURL,
            {
                method:  "POST",
                data:    {},
                success: (rawResponse) =>
                         {
                             let jsonData = {};

                             try
                             {
                                 jsonData = JSON.parse(rawResponse);
                             }
                             catch (e)
                             {
                                 /**
                                  * In case of JSON decoding failure fall through; the error will be handled in
                                  * the login challenge handler called below.
                                  */
                             }

                             if (jsonData.error)
                             {
                                 handleLoginError(jsonData.error);

                                 return;
                             }

                             console.log(jsonData);

                             handleLoginChallenge(jsonData);
                         },
                error:   (xhr) =>
                         {
                             handleLoginError(`${xhr.status} ${xhr.statusText}`);
                         },
            }
        );

        return false;
    };

    /**
     * Handles the browser response for the user interaction with the authenticator. Redirects to an
     * internal page which handles the login server-side.
     *
     * @param {  Object}  publicKey     Public key request options, returned from the server
     */
    const handleLoginChallenge = (publicKey) =>
    {
        if (!publicKey.challenge)
        {
            handleLoginError(akeeba.System.Text._("PANOPTICON_PASSKEYS_ERR_INVALID_USERNAME"));

            return;
        }

        publicKey.challenge = Uint8Array.from(
            window.atob(base64url2base64(publicKey.challenge)), (c) => c.charCodeAt(0),
        );

        if (publicKey.allowCredentials)
        {
            publicKey.allowCredentials = publicKey.allowCredentials.map((data) =>
            {
                data.id = Uint8Array.from(window.atob(base64url2base64(data.id)), (c) => c.charCodeAt(0));

                return data;
            });
        }

        navigator.credentials.get({publicKey})
                 .then((data) =>
                 {
                     const publicKeyCredential = {
                         id:       data.id,
                         type:     data.type,
                         rawId:    arrayToBase64String(new Uint8Array(data.rawId)),
                         response: {
                             authenticatorData: arrayToBase64String(new Uint8Array(data.response.authenticatorData)),
                             clientDataJSON:    arrayToBase64String(new Uint8Array(data.response.clientDataJSON)),
                             signature:         arrayToBase64String(new Uint8Array(data.response.signature)),
                             userHandle:        data.response.userHandle
                                                ? arrayToBase64String(new Uint8Array(data.response.userHandle))
                                                : null,
                         },
                     };

                     // Send the response to your server

                     window.location = `${akeeba.System.getOptions("passkey").loginURL}&data=${
                         btoa(JSON.stringify(publicKeyCredential))}`
                 })
                 .catch((error) =>
                 {
                     // Example: timeout, interaction refused...
                     handleLoginError(error);
                 });
    };

    const initPasskeyLogin = () =>
    {
        const loginButtons = [].slice.call(document.querySelectorAll(".passkey_login_button"));

        if (!loginButtons.length)
        {
            console.debug('No passkey login button.');

            return;
        }

        const hasWebAuthn = typeof navigator.credentials !== "undefined";

        loginButtons.forEach((button) =>
        {
            if (!hasWebAuthn)
            {
                button.classList.add("d-none");

                return;
            }

            button.addEventListener("click", (e) =>
            {
                e.preventDefault();

                passkeyLogin();
            });
        });
    }

    const onDOMContentLoaded = () =>
    {
        handleLanguageChange();
        initPasskeyLogin();
    };

    if (document.readyState === "loading")
    {
        document.addEventListener("DOMContentLoaded", onDOMContentLoaded);
    }
    else
    {
        onDOMContentLoaded();
    }
})();