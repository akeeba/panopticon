/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

/**
 * ALTCHA client-side proof-of-work solver.
 *
 * Receives a challenge from the server and iterates through numbers 0..maxnumber,
 * computing SHA-256 hashes to find the solution.
 */
;(function () {
    "use strict";

    /**
     * Compute the SHA-256 hash of a string using the Web Crypto API.
     *
     * @param {string} message
     * @returns {Promise<string>} The hex-encoded hash
     */
    async function sha256(message)
    {
        const encoder = new TextEncoder();
        const data = encoder.encode(message);
        const hashBuffer = await crypto.subtle.digest("SHA-256", data);
        const hashArray = Array.from(new Uint8Array(hashBuffer));

        return hashArray.map(function (b) {
            return b.toString(16).padStart(2, "0");
        }).join("");
    }

    /**
     * Solve the ALTCHA challenge by brute-forcing the secret number.
     *
     * @param {Object} challenge
     * @param {string} challenge.algorithm
     * @param {string} challenge.challenge
     * @param {string} challenge.salt
     * @param {number} challenge.maxnumber
     * @param {string} challenge.signature
     * @returns {Promise<Object|null>} The solution payload or null if not found
     */
    async function solveChallenge(challenge)
    {
        var maxNumber = challenge.maxnumber || 100000;

        for (var i = 0; i <= maxNumber; i++)
        {
            var hash = await sha256(challenge.salt + i);

            if (hash === challenge.challenge)
            {
                return {
                    algorithm: challenge.algorithm,
                    challenge: challenge.challenge,
                    number: i,
                    salt: challenge.salt,
                    signature: challenge.signature
                };
            }
        }

        return null;
    }

    /**
     * Initialise the ALTCHA widget.
     */
    async function initAltcha()
    {
        var widget = document.getElementById("altcha-widget");

        if (!widget)
        {
            return;
        }

        var challengeData = widget.getAttribute("data-challenge");

        if (!challengeData)
        {
            return;
        }

        var challenge;

        try
        {
            challenge = JSON.parse(challengeData);
        }
        catch (e)
        {
            return;
        }

        var solvingEl = widget.querySelector(".altcha-solving");
        var solvedEl = widget.querySelector(".altcha-solved");
        var errorEl = widget.querySelector(".altcha-error");
        var payloadInput = document.getElementById("altcha_payload");
        var submitBtn = widget.closest("form")?.querySelector('button[type="submit"]');

        // Disable submit button while solving
        if (submitBtn)
        {
            submitBtn.disabled = true;
        }

        // Show solving state
        if (solvingEl)
        {
            solvingEl.classList.remove("d-none");
        }

        try
        {
            var solution = await solveChallenge(challenge);

            if (solution && payloadInput)
            {
                // Encode the solution as base64 JSON
                payloadInput.value = btoa(JSON.stringify(solution));

                // Show solved state
                if (solvingEl)
                {
                    solvingEl.classList.add("d-none");
                }

                if (solvedEl)
                {
                    solvedEl.classList.remove("d-none");
                }

                // Re-enable submit button
                if (submitBtn)
                {
                    submitBtn.disabled = false;
                }
            }
            else
            {
                throw new Error("Could not solve challenge");
            }
        }
        catch (e)
        {
            if (solvingEl)
            {
                solvingEl.classList.add("d-none");
            }

            if (errorEl)
            {
                errorEl.classList.remove("d-none");
            }
        }
    }

    // Auto-initialise when the DOM is ready
    if (document.readyState === "loading")
    {
        document.addEventListener("DOMContentLoaded", initAltcha);
    }
    else
    {
        initAltcha();
    }
})();
