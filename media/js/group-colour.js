/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

(() =>
{
    // Bootstrap's actual text-light / text-dark colours, mirroring src/Helper/Colour.php
    const TEXT_LIGHT = "#f8f9fa";
    const TEXT_DARK  = "#212529";
    const FALLBACK   = "#6c757d";

    const sanitiseHex = (hex) =>
    {
        hex = (hex ?? "").trim();

        if (hex === "")
        {
            return null;
        }

        hex = hex.replace(/^#/, "");

        if (/^[0-9a-fA-F]{3}$/.test(hex))
        {
            hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
        }

        if (!/^[0-9a-fA-F]{6}$/.test(hex))
        {
            return null;
        }

        return "#" + hex.toLowerCase();
    };

    const linearise = (c) => c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);

    const relativeLuminance = (hex) =>
    {
        hex = sanitiseHex(hex) ?? FALLBACK;

        const r = linearise(parseInt(hex.substring(1, 3), 16) / 255);
        const g = linearise(parseInt(hex.substring(3, 5), 16) / 255);
        const b = linearise(parseInt(hex.substring(5, 7), 16) / 255);

        return 0.2126 * r + 0.7152 * g + 0.0722 * b;
    };

    const contrastRatio = (a, b) =>
    {
        const lA = relativeLuminance(a);
        const lB = relativeLuminance(b);

        const lighter = Math.max(lA, lB);
        const darker  = Math.min(lA, lB);

        return (lighter + 0.05) / (darker + 0.05);
    };

    const foregroundClass = (hex) =>
    {
        if (hex === null)
        {
            return "text-light";
        }

        hex = sanitiseHex(hex) ?? FALLBACK;

        const contrastWithLight = contrastRatio(hex, TEXT_LIGHT);
        const contrastWithDark  = contrastRatio(hex, TEXT_DARK);

        return contrastWithLight >= contrastWithDark ? "text-light" : "text-dark";
    };

    const onDOMContentLoaded = () =>
    {
        const container = document.getElementById("group-colour-picker");

        if (!container)
        {
            return;
        }

        const noneRadio     = container.querySelector(".js-group-colour-none");
        const paletteRadios = container.querySelectorAll("input[type=\"radio\"].js-group-colour-swatch");
        const customRadio   = document.getElementById("colour_custom_radio");
        const customColour  = container.querySelector(".js-group-colour-custom-picker");
        const customHex     = container.querySelector(".js-group-colour-custom-hex");
        const preview       = container.querySelector(".js-group-colour-preview .badge");

        const updatePreview = (hex) =>
        {
            if (!preview)
            {
                return;
            }

            preview.classList.remove("text-light", "text-dark");

            if (hex === null)
            {
                preview.classList.remove("border");
                preview.style.backgroundColor = "";
                preview.classList.add("bg-secondary", "text-light");

                return;
            }

            preview.classList.remove("bg-secondary");
            preview.style.backgroundColor = hex;
            preview.classList.add(foregroundClass(hex));
        };

        const currentColour = () =>
        {
            const checked = container.querySelector("input[name=\"colour\"]:checked");

            return checked && checked.value !== "" ? checked.value : null;
        };

        const refreshPreview = () => updatePreview(currentColour());

        const clearCustom = () =>
        {
            if (customHex)
            {
                customHex.value = "";
            }

            if (customColour)
            {
                customColour.value = "#000000";
            }

            if (customRadio)
            {
                customRadio.value = "";
            }
        };

        noneRadio?.addEventListener("change", () =>
        {
            clearCustom();
            refreshPreview();
        });

        paletteRadios.forEach((radio) =>
        {
            radio.addEventListener("change", () =>
            {
                clearCustom();
                refreshPreview();
            });
        });

        const applyCustomHex = (hex) =>
        {
            if (hex === null)
            {
                return;
            }

            if (customRadio)
            {
                customRadio.value = hex;
                customRadio.checked = true;
            }

            if (customHex)
            {
                customHex.value = hex;
            }

            if (customColour)
            {
                customColour.value = hex;
            }

            refreshPreview();
        };

        customColour?.addEventListener("input", () => applyCustomHex(sanitiseHex(customColour.value)));
        customHex?.addEventListener("input", () => applyCustomHex(sanitiseHex(customHex.value)));

        refreshPreview();
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
