/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

if (typeof akeeba === "undefined")
{
    var akeeba = {};
}

/**
 * Uses Joomla's ShowOn field behaviour with the following copyright notice:
 *
 * @copyright  (C) 2018 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */
class Showon
{
    /**
     * Constructor
     *
     * @param {HTMLElement} cont Container element
     */
    constructor(cont)
    {
        const self     = this;
        this.container = cont || document;
        this.fields    = {
            // origin-field-name: {
            //   origin:  ['collection of all the trigger nodes'],
            //   targets: ['collection of nodes to be controlled control']
            // }
        };

        this.showonFields = [].slice.call(this.container.querySelectorAll("[data-showon]"));
        // Populate the fields data
        if (this.showonFields.length)
        {
            // @todo refactor this, dry
            this.showonFields.forEach((field) =>
            {
                // Set up only once
                if (field.hasAttribute("data-showon-initialised"))
                {
                    return;
                }
                field.setAttribute("data-showon-initialised", "");

                const jsondata   = field.getAttribute("data-showon") || "";
                const showonData = JSON.parse(jsondata);
                let localFields;

                if (showonData.length)
                {
                    localFields =
                        [].slice.call(self.container.querySelectorAll(
                            `[name="${showonData[0].field}"], [name="${showonData[0].field}[]"]`));

                    if (!this.fields[showonData[0].field])
                    {
                        this.fields[showonData[0].field] = {
                            origin: [], targets: [],
                        };
                    }

                    // Add trigger elements
                    localFields.forEach((cField) =>
                    {
                        if (this.fields[showonData[0].field].origin.indexOf(cField) === -1)
                        {
                            this.fields[showonData[0].field].origin.push(cField);
                        }
                    });

                    // Add target elements
                    this.fields[showonData[0].field].targets.push(field);

                    // Data showon can have multiple values
                    if (showonData.length > 1)
                    {
                        showonData.forEach((value, index) =>
                        {
                            if (index === 0)
                            {
                                return;
                            }

                            localFields =
                                [].slice.call(self.container.querySelectorAll(
                                    `[name="${value.field}"], [name="${value.field}[]"]`));

                            if (!this.fields[showonData[0].field])
                            {
                                this.fields[showonData[0].field] = {
                                    origin: [], targets: [],
                                };
                            }

                            // Add trigger elements
                            localFields.forEach((cField) =>
                            {
                                if (this.fields[showonData[0].field].origin.indexOf(cField) === -1)
                                {
                                    this.fields[showonData[0].field].origin.push(cField);
                                }
                            });

                            // Add target elements
                            if (this.fields[showonData[0].field].targets.indexOf(field) === -1)
                            {
                                this.fields[showonData[0].field].targets.push(field);
                            }
                        });
                    }
                }
            });

            // Do some binding
            this.linkedOptions = this.linkedOptions.bind(this);

            // Attach events to referenced element, to check condition on change and keyup
            Object.keys(this.fields).forEach((key) =>
            {
                if (this.fields[key].origin.length)
                {
                    this.fields[key].origin.forEach((elem) =>
                    {
                        // Initialize the showon behaviour for the given HTMLElement
                        self.linkedOptions(key);

                        // Setup listeners
                        elem.addEventListener("change", () =>
                        {
                            self.linkedOptions(key);
                        });
                        elem.addEventListener("keyup", () =>
                        {
                            self.linkedOptions(key);
                        });
                        elem.addEventListener("click", () =>
                        {
                            self.linkedOptions(key);
                        });
                    });
                }
            });
        }
    }

    /**
     *
     * @param key
     */
    linkedOptions(key)
    {
        // Loop through the elements that need to be either shown or hidden
        this.fields[key].targets.forEach((field) =>
        {
            const elementShowonDatas = JSON.parse(field.getAttribute("data-showon")) || [];
            let showfield            = true;
            let itemval;

            // Check if target conditions are satisfied
            elementShowonDatas.forEach((elementShowonData, index) =>
            {
                const condition = elementShowonData || {};
                condition.valid = 0;

                // Test in each of the elements in the field array if condition is valid
                this.fields[key].origin.forEach((originField) =>
                {
                    if (originField.name.replace("[]", "") !== elementShowonData.field)
                    {
                        return;
                    }

                    const originId = originField.id;

                    // If checkbox or radio box the value is read from properties
                    if (originField.getAttribute("type") && ["checkbox", "radio"].includes(
                        originField.getAttribute("type").toLowerCase()))
                    {
                        if (!originField.checked)
                        {
                            // Unchecked fields will return a blank and so always match
                            // a != condition so we skip them
                            return;
                        }

                        itemval = document.getElementById(originId).value;
                    }
                    else if (originField.nodeName === "SELECT" && originField.hasAttribute("multiple"))
                    {
                        itemval = Array.from(originField.querySelectorAll("option:checked")).map((el) => el.value);
                    }
                    else
                    {
                        // Select lists, text-area etc. Note that multiple-select list returns
                        // an Array here s0 we can always treat 'itemval' as an array
                        itemval = document.getElementById(originId).value;
                        // A multi-select <select> $field  will return null when no elements are
                        // selected so we need to define itemval accordingly
                        if (itemval === null && originField.tagName.toLowerCase() === "select")
                        {
                            itemval = [];
                        }
                    }

                    // Convert to array to allow multiple values in the field (e.g. type=list multiple)
                    // and normalize as string
                    if (!(typeof itemval === "object"))
                    {
                        itemval = JSON.parse(`["${itemval}"]`);
                    }

                    // Test if any of the values of the field exists in showon conditions
                    itemval.forEach((val) =>
                    {
                        // ":" Equal to one or more of the values condition
                        if (condition.sign === "=" && condition.values.indexOf(val) !== -1)
                        {
                            condition.valid = 1;
                        }
                        // "!:" Not equal to one or more of the values condition
                        if (condition.sign === "!=" && condition.values.indexOf(val) === -1)
                        {
                            condition.valid = 1;
                        }
                    });
                });

                // Verify conditions
                // First condition (no operator): current condition must be valid
                if (condition.op === "")
                {
                    if (condition.valid === 0)
                    {
                        showfield = false;
                    }
                }
                else
                {
                    // Other conditions (if exists)
                    // AND operator: both the previous and current conditions must be valid
                    if (condition.op === "AND" && condition.valid + elementShowonDatas[index - 1].valid < 2)
                    {
                        showfield       = false;
                        condition.valid = 0;
                    }
                    // OR operator: one of the previous and current conditions must be valid
                    if (condition.op === "OR" && condition.valid + elementShowonDatas[index - 1].valid > 0)
                    {
                        showfield       = true;
                        condition.valid = 1;
                    }
                }
            });

            // If conditions are satisfied show the target field(s), else hide
            if (field.tagName !== "option")
            {
                if (showfield)
                {
                    field.classList.remove("d-none");
                    field.dispatchEvent(new CustomEvent("akeeba:showon-show", {
                        bubbles: true,
                    }));
                }
                else
                {
                    field.classList.add("d-none");
                    field.dispatchEvent(new CustomEvent("akeeba:showon-hide", {
                        bubbles: true,
                    }));
                }
            }
            else
            {
                // @todo: If chosen or choices.js is active we should update them
                field.disabled = !showfield;
            }
        });
    }
}

// Provide a public API
if (!akeeba.Showon)
{
    akeeba.Showon = {
        initialise: (container) => new Showon(container),
    };
}

/**
 * Initialize 'showon' feature at an initial page load
 */
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => {
        akeeba.Showon.initialise(document);
    });
} else {
    akeeba.Showon.initialise(document);
}
