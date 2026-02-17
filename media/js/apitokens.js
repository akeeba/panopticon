/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

window.akeeba            = window.akeeba || {};
window.akeeba.ApiTokens  = window.akeeba.ApiTokens || {};

((Ajax, System, ApiTokens, document) =>
{
    "use strict";

    let options = {};

    /**
     * Make an AJAX POST request to a controller task.
     *
     * @param {string} url     The URL to call.
     * @param {Object} data    POST data (key-value pairs).
     * @param {Function} onSuccess  Callback on success, receives parsed response.
     * @param {Function} onError    Callback on error.
     */
    const ajaxPost = (url, data, onSuccess, onError) =>
    {
        data.token = options.csrfToken;

        Ajax.ajax(
            url,
            {
                method: "POST",
                data: data,
                success: (responseText) =>
                {
                    try
                    {
                        const result = JSON.parse(responseText);
                        onSuccess(result);
                    }
                    catch (e)
                    {
                        if (onError)
                        {
                            onError(e);
                        }
                    }
                },
                error: (xhr) =>
                {
                    if (onError)
                    {
                        onError(xhr);
                    }
                },
            }
        );
    };

    /**
     * Create a new API token.
     */
    ApiTokens.create = () =>
    {
        const descField = document.getElementById("newTokenDescription");
        const description = descField ? descField.value.trim() : "";

        ajaxPost(
            options.createUrl,
            {description: description},
            (result) =>
            {
                if (!result.success)
                {
                    alert(result.message || "Failed to create token");

                    return;
                }

                // Show the new token value
                const alertEl = document.getElementById("newTokenAlert");
                const valueEl = document.getElementById("newTokenValue");

                if (alertEl && valueEl)
                {
                    valueEl.value = result.token;
                    alertEl.classList.remove("d-none");
                }

                // Clear the description field
                if (descField)
                {
                    descField.value = "";
                }

                // Reload the page to show the updated table
                window.location.reload();
            },
            () =>
            {
                alert("Failed to create token");
            }
        );
    };

    /**
     * Toggle a token's enabled/disabled status.
     *
     * @param {number} id  The token ID.
     */
    ApiTokens.toggle = (id) =>
    {
        ajaxPost(
            options.toggleUrl,
            {id: id},
            (result) =>
            {
                if (!result.success)
                {
                    alert(result.message || "Failed to toggle token");

                    return;
                }

                window.location.reload();
            },
            () =>
            {
                alert("Failed to toggle token");
            }
        );
    };

    /**
     * Delete a token.
     *
     * @param {number} id  The token ID.
     */
    ApiTokens.remove = (id) =>
    {
        if (!confirm(System.Text._("PANOPTICON_APITOKENS_BTN_CONFIRM_DELETE")))
        {
            return;
        }

        ajaxPost(
            options.removeUrl,
            {id: id},
            (result) =>
            {
                if (!result.success)
                {
                    alert(result.message || "Failed to delete token");

                    return;
                }

                // Remove the row from the table
                const row = document.querySelector("tr[data-token-id=\"" + id + "\"]");

                if (row)
                {
                    row.remove();
                }
            },
            () =>
            {
                alert("Failed to delete token");
            }
        );
    };

    /**
     * Show/hide a token's value.
     *
     * @param {number} id  The token ID.
     */
    ApiTokens.showToken = (id) =>
    {
        const group = document.querySelector(".token-value-group[data-id=\"" + id + "\"]");

        if (!group)
        {
            return;
        }

        // If already visible, toggle it off
        if (!group.classList.contains("d-none"))
        {
            group.classList.add("d-none");

            return;
        }

        ajaxPost(
            options.tokenUrl,
            {id: id},
            (result) =>
            {
                if (!result.success)
                {
                    alert(result.message || "Failed to get token value");

                    return;
                }

                const input = group.querySelector(".token-value-input");

                if (input)
                {
                    input.value = result.token;
                }

                group.classList.remove("d-none");
            },
            () =>
            {
                alert("Failed to get token value");
            }
        );
    };

    /**
     * Initialise event listeners on DOM ready.
     */
    const onDOMContentLoaded = () =>
    {
        options = System.getOptions("panopticon.apitokens");

        if (!options)
        {
            return;
        }

        // Create button
        const btnCreate = document.getElementById("btnCreateToken");

        if (btnCreate)
        {
            btnCreate.addEventListener("click", ApiTokens.create);
        }

        // Copy new token button
        const btnCopyNew = document.getElementById("btnCopyNewToken");

        if (btnCopyNew)
        {
            btnCopyNew.addEventListener("click", () =>
            {
                const input = document.getElementById("newTokenValue");

                if (input)
                {
                    navigator.clipboard.writeText(input.value);
                }
            });
        }

        // Toggle buttons
        document.querySelectorAll(".btn-toggle-token").forEach((btn) =>
        {
            btn.addEventListener("click", () =>
            {
                ApiTokens.toggle(parseInt(btn.dataset.id, 10));
            });
        });

        // Show token buttons
        document.querySelectorAll(".btn-show-token").forEach((btn) =>
        {
            btn.addEventListener("click", () =>
            {
                ApiTokens.showToken(parseInt(btn.dataset.id, 10));
            });
        });

        // Delete buttons
        document.querySelectorAll(".btn-delete-token").forEach((btn) =>
        {
            btn.addEventListener("click", () =>
            {
                ApiTokens.remove(parseInt(btn.dataset.id, 10));
            });
        });

        // Copy token value buttons
        document.querySelectorAll(".btn-copy-token").forEach((btn) =>
        {
            btn.addEventListener("click", () =>
            {
                const input = btn.closest(".token-value-group").querySelector(".token-value-input");

                if (input)
                {
                    navigator.clipboard.writeText(input.value);
                }
            });
        });
    };

    if (document.readyState === "loading")
    {
        document.addEventListener("DOMContentLoaded", onDOMContentLoaded);
    }
    else
    {
        onDOMContentLoaded();
    }
})(akeeba.Ajax, akeeba.System, window.akeeba.ApiTokens, document);
