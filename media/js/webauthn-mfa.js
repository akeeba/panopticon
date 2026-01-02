/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

// Namespace
let akeeba = akeeba || {};

akeeba.MFA = akeeba.MFA || {};

akeeba.MFA.webauthn = akeeba.MFA.webauthn || {
    authData: null
};

/**
 * Utility function to Convert array data to base64 strings
 */
akeeba.MFA.webauthn.arrayToBase64String = (a) =>
{
    return btoa(String.fromCharCode(...a));
};

akeeba.MFA.webauthn.base64url2base64 = function (input)
{
    let output = input
        .replace(/-/g, "+")
        .replace(/_/g, "/");
    const pad  = output.length % 4;
    if (pad)
    {
        if (pad === 1)
        {
            throw new Error("InvalidLengthError: Input base64url string is the wrong length to determine padding");
        }
        output += new Array(5 - pad).join("=");
    }
    return output;
}

/**
 * Ask the user to link an authenticator using the provided public key (created server-side).
 */
akeeba.MFA.webauthn.setUp = (e) =>
{
    e.preventDefault();

    // Make sure the browser supports Webauthn
    if (!("credentials" in navigator))
    {
        alert(akeeba.System.Text._("PANOPTICON_MFA_PASSKEYS_ERR_NOTAVAILABLE_HEAD"));

        console.log("This browser does not support PassKeys");

        return false;
    }

    const rawPKData = document.forms["mfa-method-edit"].querySelectorAll("input[name=\"pkRequest\"]")[0].value;
    const publicKey = JSON.parse(atob(rawPKData));

    // Convert the public key information to a format usable by the browser's credentials manager
    publicKey.challenge = Uint8Array.from(
        window.atob(akeeba.MFA.webauthn.base64url2base64(publicKey.challenge)), (c) => c.charCodeAt(0),
    );

    publicKey.user.id = Uint8Array.from(window.atob(publicKey.user.id), (c) => c.charCodeAt(0));

    if (publicKey.excludeCredentials)
    {
        publicKey.excludeCredentials = publicKey.excludeCredentials.map((data) =>
        {
            data.id =
                Uint8Array.from(
                    window.atob(akeeba.MFA.webauthn.base64url2base64(data.id)),
                    (c) => c.charCodeAt(0)
                );
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
                     rawId:    akeeba.MFA.webauthn.arrayToBase64String(new Uint8Array(data.rawId)),
                     response: {
                         clientDataJSON:    akeeba.MFA.webauthn.arrayToBase64String(
                             new Uint8Array(data.response.clientDataJSON)),
                         attestationObject: akeeba.MFA.webauthn.arrayToBase64String(
                             new Uint8Array(data.response.attestationObject))
                     }
                 };

                 // Store the WebAuthn reply
                 document.getElementById("passkeys-method-code").value = btoa(JSON.stringify(publicKeyCredential));

                 // Submit the form
                 document.forms["mfa-method-edit"].submit();
             }, (error) =>
             {
                 // An error occurred: timeout, request to provide the authenticator refused, hardware / software
                 // error...
                 akeeba.MFA.webauthn.handle_error(error);
             });
};

akeeba.MFA.webauthn.handle_error = (message) =>
{
    try
    {
        document.getElementById("passkeys_button").style.disabled = "null";
    }
    catch (e)
    {
    }

    alert(message);

    console.log(message);
};

akeeba.MFA.webauthn.validate = () =>
{
    // Make sure the browser supports Webauthn
    if (!("credentials" in navigator))
    {
        alert(akeeba.System.Text._("PANOPTICON_MFA_PASSKEYS_ERR_NOTAVAILABLE_HEAD"));

        console.log("This browser does not support PassKeys");

        return;
    }

    const publicKey = akeeba.MFA.webauthn.authData;

    if (!publicKey.challenge)
    {
        akeeba.MFA.webauthn.handle_error(akeeba.System.Text._("PANOPTICON_MFA_PASSKEYS_ERR_NO_STORED_CREDENTIAL"));

        return;
    }

    publicKey.challenge = Uint8Array.from(
        window.atob(akeeba.MFA.webauthn.base64url2base64(publicKey.challenge)), (c) => c.charCodeAt(0),
    );

    if (publicKey.allowCredentials)
    {
        publicKey.allowCredentials = publicKey.allowCredentials.map((data) =>
        {
            data.id =
                Uint8Array.from(
                    window.atob(akeeba.MFA.webauthn.base64url2base64(data.id)),
                    (c) => c.charCodeAt(0)
                );
            return data;
        });
    }

    navigator.credentials.get({publicKey})
             .then(data =>
             {
                 const publicKeyCredential = {
                     id:       data.id,
                     type:     data.type,
                     rawId:    akeeba.MFA.webauthn.arrayToBase64String(new Uint8Array(data.rawId)),
                     response: {
                         authenticatorData: akeeba.MFA.webauthn.arrayToBase64String(
                             new Uint8Array(data.response.authenticatorData)),
                         clientDataJSON:    akeeba.MFA.webauthn.arrayToBase64String(
                             new Uint8Array(data.response.clientDataJSON)),
                         signature:         akeeba.MFA.webauthn.arrayToBase64String(
                             new Uint8Array(data.response.signature)),
                         userHandle:        data.response.userHandle ? akeeba.MFA.webauthn.arrayToBase64String(
                             new Uint8Array(data.response.userHandle)) : null
                     }
                 };

                 document.getElementById("mfaCode").value = btoa(JSON.stringify(publicKeyCredential));
                 document.forms["captive-form"].submit();
             }, (error) =>
             {
                 // Example: timeout, interaction refused...
                 console.log(error);
                 akeeba.MFA.webauthn.handle_error(error);
             });
};

akeeba.MFA.webauthn.onValidateClick = function (event)
{
    event.preventDefault();

    akeeba.MFA.webauthn.authData = JSON.parse(window.atob(akeeba.System.getOptions("mfa.authData")));

    document.getElementById("passkeys_button").style.disabled = "disabled";
    akeeba.MFA.webauthn.validate();

    return false;
}

document.getElementById("passkeys-missing").classList.add("d-none");

if (typeof (navigator.credentials) == "undefined") {
    document.getElementById("passkeys-missing").classList.replace("d-none", "d-block");
    document.getElementById("passkeys-controls").classList.add("d-none");
    document.getElementById("passkeys_button").style.disabled = "disabled";
}

window.addEventListener("DOMContentLoaded", function ()
{
    if (akeeba.System.getOptions("mfa.pagetype") === "validate")
    {
        const elButton = document.getElementById("passkeys_button");
        elButton.addEventListener("click", akeeba.MFA.webauthn.onValidateClick);
        elButton.focus();

        document.getElementById("captive-button-submit")
                .addEventListener("click", akeeba.MFA.webauthn.onValidateClick);

    }
    else
    {
        const elButton = document.getElementById("passkeys_button");
        elButton.addEventListener("click", akeeba.MFA.webauthn.setUp);
        elButton.focus();
    }

    document.querySelectorAll(".mfa_webauthn_setup").forEach(function (btn)
    {
        btn.addEventListener("click", akeeba.MFA.webauthn.setUp);
    });
})
