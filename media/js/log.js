/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */


(() => {
    let timeout = 10;
    let intervalHandle;

    const reloadLog = (myCallback) => {
        const url = akeeba.System.getOptions('log', {})?.url;

        if (!url)
        {
            return;
        }

        console.log(akeeba.Ajax)

        window.akeeba.Ajax.ajax(url, {
            method: 'GET',
            data: {
                size:  document.getElementById('size').value,
                lines: document.getElementById('lines').value,
            },
            success: (text) => {
                document.getElementById('logTableContainer').innerHTML = text;

                if (typeof myCallback === 'function')
                {
                    myCallback();
                }
            }
        })
    }

    const startAutoRefresh = () => {
        stopAutoRefresh();

        document.getElementById('autoRefreshContainer').classList.remove('d-none');

        intervalHandle = window.setInterval(() => {
            timeout--;
            document.getElementById('autoRefreshProgress').attributes.ariaNow = timeout;
            document.getElementById('autoRefreshBar').style.width = (timeout * 10) + '%';

            if (timeout == 0)
            {
                stopAutoRefresh();
                reloadLog(() => {
                    startAutoRefresh();
                })
            }
        }, 1000);
    }

    const stopAutoRefresh = () => {
        if (intervalHandle) {
            window.clearInterval(intervalHandle);
        }

        document.getElementById('autoRefreshContainer').classList.add('d-none');
        document.getElementById('autoRefreshProgress').attributes.ariaNow = 10;
        document.getElementById('autoRefreshBar').style.width = '100%';

        timeout = 10;
    }

    const onDOMContentLoaded = () => {
        document.getElementById('autoRefresh').addEventListener('change', (e) => {
           const elCheckbox = e.target;

           if (elCheckbox.checked) {
               startAutoRefresh();
           } else {
               stopAutoRefresh();
           }
        });

        document.getElementById('size').addEventListener('change', reloadLog)
        document.getElementById('lines').addEventListener('change', reloadLog)
        document.getElementById('reloadLog').addEventListener('click', reloadLog)
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", onDOMContentLoaded);
    } else {
        onDOMContentLoaded();
    }
})();