/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

(() =>
{
    const pageInstructions = document.getElementById("instructions");
    const pageBenchmark    = document.getElementById("benchmark");
    const pageError        = document.getElementById("error");

    var lastElapsed = -1;

    const handleError = (errorText) =>
    {
        pageInstructions.classList.add('d-none');
        pageBenchmark.classList.add('d-none');
        pageError.classList.remove('d-none');

        document.getElementById('errorMessage').innerHTML = errorText;
    }

    const monitorBenchmark = () =>
    {
        const options = akeeba.System.getOptions("panopticon.benchmark")
        akeeba.Ajax.ajax(
            options.url,
            {
                type:    "GET",
                cache:   false,
                success: (responseText, statusText, xhr) =>
                         {
                             let response = null;

                             try
                             {
                                 response = JSON.parse(responseText);
                             }
                             catch (e)
                             {
                                 window.clearInterval(pingTimer);

                                 const doc = new DOMParser().parseFromString(responseText ?? "", "text/html");

                                 handleError(
                                     akeeba.System.Text._("PANOPTICON_SETUP_CRON_ERR_INVALID_JSON") +
                                     "<pre>" +
                                     doc.documentElement.textContent +
                                     "</pre>"
                                 )
                             }

                             console.debug(response);

                             // Make sure we have a task
                             if (!response.hasTask)
                             {
                                 window.clearInterval(pingTimer);

                                 handleError(akeeba.System.Text._("PANOPTICON_SETUP_CRON_ERR_NO_MAXEXEC_TASK"));
                             }

                             // Error handling
                             if (response.error)
                             {
                                 window.clearInterval(pingTimer);

                                 handleError(response.error);
                             }

                             // If the benchmark has not started, bail out
                             if (!response.started)
                             {
                                 return;
                             }

                             // Show the correct page, if necessary.
                             if (pageBenchmark.classList.contains('d-none'))
                             {
                                 pageBenchmark.classList.remove('d-none');
                                 pageInstructions.classList.add('d-none');
                             }

                             // Have we finished?
                             var hasStuck = lastElapsed === response.elapsed;
                             lastElapsed = response.elapsed;

                             if (response.finished || response.elapsed >= 180 || hasStuck)
                             {
                                 window.clearInterval(pingTimer);

                                 window.location = `${options.nextPage}&${options.token}=1&maxexec=${response.elapsed}`;

                                 return;
                             }

                             // Update the progress bar
                             const absoluteElapsed = response.elapsed ?? 0;
                             const percentElapsed = Math.floor(Math.min(1, response.elapsed / 180) * 100);

                             const progressFill = document.getElementById('progressFill');
                             progressFill.style.width = percentElapsed + '%';
                             progressFill.innerText = absoluteElapsed + ' s';
                         },
                error:   (xhr, reason, ignored) =>
                         {
                             window.clearInterval(pingTimer);

                             var error = '';

                             switch (reason)
                             {
                                 case 'timeout':
                                     error = ajax.System.Text._('PANOPTICON_SETUP_CRON_ERR_XHR_TIMEOUT');
                                     break;

                                 case 'abort':
                                     error = ajax.System.Text._('PANOPTICON_SETUP_CRON_ERR_XHR_ABORT');
                                     break;

                                default:
                                 case 'error':
                                     const doc = new DOMParser().parseFromString(Request.responseText ? Request.responseText : "", "text/html");

                                     error = akeeba.System.Text._('PANOPTICON_SETUP_CRON_ERR_AJAX_HEAD') + '<br>'
                                         + akeeba.System.Text._('PANOPTICON_SETUP_CRON_ERR_AJAX_HTTP_STATUS') +
                                         xhr.status + ' (' + xhr.statusText + ')<br>' +
                                         akeeba.System.Text._('PANOPTICON_SETUP_CRON_ERR_AJAX_HTTP_READYSTATE') +
                                         xhr.readyState + '<br>' +
                                         akeeba.System.Text._('PANOPTICON_SETUP_CRON_ERR_AJAX_HTTP_RAW') +
                                         '<div class="p-2 m-2 border rounded bg-light-subtle text-muted">' +
                                         doc.documentElement.textContent
                                         + '</div>';
                                     break;
                             }

                             handleError(error);
                         }
            }
        )
    };

    pageInstructions.classList.remove("d-none");
    pageBenchmark.classList.add("d-none");
    pageError.classList.add("d-none");

    const pingTimer = window.setInterval(monitorBenchmark, 5000);
})()
