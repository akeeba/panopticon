/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

import {createApp} from "../petite-vue/petite-vue.min.js";

function SitesList(props)
{
    const options = akeeba.System.getOptions("panopticon.dashboard");

    return {
        $template:      "#sitesListTemplate",
        sites:          [],
        MAX_TIMER:      props?.maxTimer ?? (options?.maxTimer ?? 90),
        MAX_PAGES:      props?.maxPages ?? (options?.maxPages ?? 50),
        PAGE_LENGTH:    props?.pageLimit ?? (options?.pageLimit ?? 20),
        restartTimer:   false,
        availableTime:  0,
        countdownTimer: null,
        error:          null, // The method feeding data to the view
        feedMeData(counter)
        {
            // Initialise the display on the very first execution
            if (counter === 0)
            {
                this.sites = [];
            }

            // I will only display up to MAX_PAGES pages at once
            if (counter >= this.MAX_PAGES)
            {
                return;
            }

            akeeba.System.doAjax(
                {
                    ajaxURL: `${options.url}&limitstart=${this.PAGE_LENGTH * counter}&limit=${this.PAGE_LENGTH}`,
                },
                (data) =>
                {
                    this.error = null;

                    // Check if we ran out of data, or we hit the upper limit of data to load.
                    if (typeof data !== "object" || data.length === 0)
                    {
                        if (!this.countdownTimer && this.restartTimer)
                        {
                            this.restartTimer = false
                            this.resetTimer()
                            this.toggleTimer()
                        }

                        return false;
                    }

                    // Append the retrieved data to the internal list.
                    this.sites = this.sites.concat(data)

                    // Get me some more data to display!
                    this.feedMeData(++counter)
                },
                (errorMessage) => {
                    this.error = errorMessage;
                },
                false
            )
        },
        resetTimer()
        {
            this.availableTime = this.MAX_TIMER;
        },
        toggleTimer()
        {
            if (this.countdownTimer)
            {
                window.clearInterval(this.countdownTimer);
                this.availableTime = this.MAX_TIMER

                this.countdownTimer = null;

                return;
            }

            this.countdownTimer = window.setInterval(this.timerTick, 1000);
        },
        stopTimer()
        {
            if (this.countdownTimer)
            {
                this.toggleTimer();
            }
        },
        timerTick()
        {
            this.availableTime -= 1;

            if (this.availableTime === 0)
            {
                this.toggleTimer()
                this.restartTimer = true
                this.feedMeData(0)
            }
        }, // This runs when the component initialises
        mounted()
        {
            this.availableTime = this.MAX_TIMER
            this.toggleTimer()
            this.feedMeData(0)
        },
        reloadData()
        {
            this.restartTimer = this.countdownTimer !== null

            if (this.restartTimer)
            {
                this.toggleTimer()
            }

            this.feedMeData(0)
        }
    }
}

const bsDirective = (ctx) => {
    var myTooltip = null;

    ctx.effect(() => {
        if (ctx.arg === 'tooltip')
        {
            if (myTooltip)
            {
                myTooltip.dispose();
                myTooltip = null;
            }

            myTooltip = new window.bootstrap.Tooltip(
                ctx.el,
                {
                    placement: 'auto',
                    title: (ctx.modifiers?.raw ?? false) ? ctx.exp : ctx.get()
                }
            )
        }

    })

    return () => {
        if (myTooltip)
        {
            myTooltip.dispose();
            myTooltip = null;
        }
    }
};

createApp({SitesList})
    .directive('bs', bsDirective)
    .mount()