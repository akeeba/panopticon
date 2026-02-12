/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

window.akeeba          = window.akeeba || {};
window.akeeba.WebPush  = window.akeeba.WebPush || {};

((Ajax, System, WebPush, document) =>
{
    "use strict";

    /**
     * Convert a URL-safe base64 string to a Uint8Array (for applicationServerKey).
     *
     * @param {string} base64String
     * @returns {Uint8Array}
     */
    const urlBase64ToUint8Array = (base64String) =>
    {
        const padding = "=".repeat((4 - base64String.length % 4) % 4);
        const base64  = (base64String + padding)
            .replace(/-/g, "+")
            .replace(/_/g, "/");

        const rawData    = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i)
        {
            outputArray[i] = rawData.charCodeAt(i);
        }

        return outputArray;
    };

    /**
     * Subscribe the browser to Web Push notifications.
     */
    WebPush.subscribe = async () =>
    {
        const options = System.getOptions("panopticon.webpush");

        if (!options || !options.vapidPublicKey || !options.swUrl)
        {
            return;
        }

        try
        {
            const registration = await navigator.serviceWorker.register(options.swUrl);

            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(options.vapidPublicKey),
            });

            const key    = subscription.getKey("p256dh");
            const auth   = subscription.getKey("auth");

            const postData = {
                endpoint:   subscription.endpoint,
                key_p256dh: btoa(String.fromCharCode.apply(null, new Uint8Array(key))),
                key_auth:   btoa(String.fromCharCode.apply(null, new Uint8Array(auth))),
                encoding:   (PushManager.supportedContentEncodings || ["aesgcm"])[0],
                user_agent: navigator.userAgent,
            };

            Ajax.ajax(
                options.subscribeUrl,
                {
                    method: "POST",
                    data:   postData,
                    success: (response) =>
                    {
                        try
                        {
                            const result = JSON.parse(response);

                            if (result.success)
                            {
                                WebPush.updateUI(true);
                            }
                            else
                            {
                                alert(System.Text._("PANOPTICON_WEBPUSH_ERR_SUBSCRIBE_FAILED"));
                            }
                        }
                        catch (e)
                        {
                            alert(System.Text._("PANOPTICON_WEBPUSH_ERR_SUBSCRIBE_FAILED"));
                        }
                    },
                    error: () =>
                    {
                        alert(System.Text._("PANOPTICON_WEBPUSH_ERR_SUBSCRIBE_FAILED"));
                    },
                }
            );
        }
        catch (err)
        {
            if (Notification.permission === "denied")
            {
                alert(System.Text._("PANOPTICON_WEBPUSH_ERR_PERMISSION_DENIED"));
            }
            else
            {
                alert(System.Text._("PANOPTICON_WEBPUSH_ERR_SUBSCRIBE_FAILED"));
            }
        }
    };

    /**
     * Unsubscribe the browser from Web Push notifications.
     */
    WebPush.unsubscribe = async () =>
    {
        const options = System.getOptions("panopticon.webpush");

        if (!options || !options.swUrl)
        {
            return;
        }

        try
        {
            const registration  = await navigator.serviceWorker.register(options.swUrl);
            const subscription  = await registration.pushManager.getSubscription();

            if (!subscription)
            {
                WebPush.updateUI(false);

                return;
            }

            const endpoint = subscription.endpoint;

            await subscription.unsubscribe();

            Ajax.ajax(
                options.unsubscribeUrl,
                {
                    method: "POST",
                    data:   {endpoint: endpoint},
                    success: () =>
                    {
                        WebPush.updateUI(false);
                    },
                    error: () =>
                    {
                        WebPush.updateUI(false);
                    },
                }
            );
        }
        catch (err)
        {
            WebPush.updateUI(false);
        }
    };

    /**
     * Dismiss the WebPush prompt banner.
     *
     * @param {string} action  "remind" or "declined"
     */
    WebPush.dismissPrompt = (action) =>
    {
        const options = System.getOptions("panopticon.webpush");

        if (!options || !options.dismissUrl)
        {
            return;
        }

        Ajax.ajax(
            options.dismissUrl,
            {
                method: "POST",
                data:   {action: action},
                success: () => {},
                error: () => {},
            }
        );

        const promptEl = document.getElementById("webpush-prompt");

        if (promptEl)
        {
            promptEl.style.display = "none";
        }
    };

    /**
     * Update the UI elements to reflect the current subscription state.
     *
     * @param {boolean} isSubscribed
     */
    WebPush.updateUI = (isSubscribed) =>
    {
        const subscribeBtn   = document.getElementById("webpush-subscribe-btn");
        const unsubscribeBtn = document.getElementById("webpush-unsubscribe-btn");
        const statusBadge    = document.getElementById("webpush-status-badge");
        const promptEl       = document.getElementById("webpush-prompt");

        if (subscribeBtn)
        {
            subscribeBtn.style.display = isSubscribed ? "none" : "";
        }

        if (unsubscribeBtn)
        {
            unsubscribeBtn.style.display = isSubscribed ? "" : "none";
        }

        if (statusBadge)
        {
            if (isSubscribed)
            {
                statusBadge.className = "badge bg-success";
                statusBadge.textContent = System.Text._("PANOPTICON_WEBPUSH_LBL_STATUS_ACTIVE");
            }
            else
            {
                statusBadge.className = "badge bg-secondary";
                statusBadge.textContent = System.Text._("PANOPTICON_WEBPUSH_LBL_STATUS_INACTIVE");
            }
        }

        if (promptEl && isSubscribed)
        {
            promptEl.style.display = "none";
        }
    };

    /**
     * Check the current browser push subscription state and update UI accordingly.
     */
    WebPush.checkCurrentSubscription = async () =>
    {
        if (!("serviceWorker" in navigator) || !("PushManager" in window))
        {
            // Hide all webpush-related UI
            document.querySelectorAll(".webpush-requires-support").forEach((el) =>
            {
                el.style.display = "none";
            });

            return;
        }

        try
        {
            const options = System.getOptions("panopticon.webpush");

            if (!options || !options.swUrl)
            {
                WebPush.updateUI(false);

                return;
            }

            const registration = await navigator.serviceWorker.register(options.swUrl);
            const subscription = await registration.pushManager.getSubscription();

            WebPush.updateUI(subscription !== null);
        }
        catch (e)
        {
            WebPush.updateUI(false);
        }
    };

    /**
     * Initialise event handlers on DOM content loaded.
     */
    const onDOMContentLoaded = () =>
    {
        // Check browser support and current subscription state
        WebPush.checkCurrentSubscription();

        // Subscribe button
        const subscribeBtn = document.getElementById("webpush-subscribe-btn");

        if (subscribeBtn)
        {
            subscribeBtn.addEventListener("click", (e) =>
            {
                e.preventDefault();
                WebPush.subscribe();
            });
        }

        // Unsubscribe button
        const unsubscribeBtn = document.getElementById("webpush-unsubscribe-btn");

        if (unsubscribeBtn)
        {
            unsubscribeBtn.addEventListener("click", (e) =>
            {
                e.preventDefault();
                WebPush.unsubscribe();
            });
        }

        // Prompt buttons
        const enableBtn = document.getElementById("webpush-prompt-enable");

        if (enableBtn)
        {
            enableBtn.addEventListener("click", (e) =>
            {
                e.preventDefault();
                WebPush.subscribe();
                WebPush.dismissPrompt("accepted");
            });
        }

        const remindBtn = document.getElementById("webpush-prompt-remind");

        if (remindBtn)
        {
            remindBtn.addEventListener("click", (e) =>
            {
                e.preventDefault();
                WebPush.dismissPrompt("remind");
            });
        }

        const declineBtn = document.getElementById("webpush-prompt-decline");

        if (declineBtn)
        {
            declineBtn.addEventListener("click", (e) =>
            {
                e.preventDefault();
                WebPush.dismissPrompt("declined");
            });
        }
    };

    if (document.readyState === "loading")
    {
        document.addEventListener("DOMContentLoaded", onDOMContentLoaded);
    }
    else
    {
        onDOMContentLoaded();
    }
})(akeeba.Ajax, akeeba.System, window.akeeba.WebPush, document)
