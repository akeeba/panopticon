/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

self.addEventListener("install", (event) =>
{
    self.skipWaiting();
});

self.addEventListener("activate", (event) =>
{
    event.waitUntil(self.clients.claim());
});

self.addEventListener("push", (event) =>
{
    if (!event.data)
    {
        return;
    }

    let data;

    try
    {
        data = event.data.json();
    }
    catch (e)
    {
        data = {
            title: "Panopticon",
            body: event.data.text(),
        };
    }

    const options = {
        body: data.body || "",
        icon: data.icon || "",
        tag: data.tag || "panopticon-notification",
        data: {
            url: data.url || "/",
        },
    };

    event.waitUntil(
        self.registration.showNotification(data.title || "Panopticon", options)
    );
});

self.addEventListener("notificationclick", (event) =>
{
    event.notification.close();

    const url = event.notification.data && event.notification.data.url
        ? event.notification.data.url
        : "/";

    event.waitUntil(
        self.clients.matchAll({type: "window", includeUncontrolled: true})
            .then((clientList) =>
            {
                for (const client of clientList)
                {
                    if (client.url === url && "focus" in client)
                    {
                        return client.focus();
                    }
                }

                if (self.clients.openWindow)
                {
                    return self.clients.openWindow(url);
                }
            })
    );
});
