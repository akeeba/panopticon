/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

if (typeof akeeba === "undefined")
{
    var akeeba = {};
}

if (typeof akeeba.Ajax === "undefined")
{
    /**
     * An AJAX abstraction layer for use with Akeeba software
     */
    akeeba.Ajax = {
        // Maps nonsense HTTP status codes to what should actually be returned
        xhrSuccessStatus: {
            // File protocol always yields status code 0, assume 200
            0:    200, // Support: IE <=9 only. Sometimes IE returns 1223 when it should be 204
            1223: 204
        }, // Used for chained AJAX: each request will be launched once the previous one is done (successfully or not)
        requestArray:     [], processingQueue: false
    };
}

/**
 * Performs an asynchronous AJAX request. Mostly compatible with jQuery 1.5+ calling conventions, or at least the
 * subset
 * of the features we used in our software.
 *
 * The parameters can be
 * method        string      HTTP method (GET, POST, PUT, ...). Default: POST.
 * url        string      URL to access over AJAX. Required.
 * timeout    int         Request timeout in msec. Default: 600,000 (ten minutes)
 * data        object      Data to send to the AJAX URL. Default: empty
 * success    function    function(string responseText, string responseStatus, XMLHttpRequest xhr)
 * error        function    function(XMLHttpRequest xhr, string errorType, Exception e)
 * beforeSend    function    function(XMLHttpRequest xhr, object parameters) You can modify xhr, not parameters. Return
 * false to abort the request.
 *
 * @param   url         {string}  URL to send the AJAX request to
 * @param   parameters  {object}  Configuration parameters
 */
akeeba.Ajax.ajax = function (url, parameters)
{
    // Handles jQuery 1.0 calling style of .ajax(parameters), passing the URL as a property of the parameters object
    if (typeof (parameters) == "undefined")
    {
        parameters = url;
        url        = parameters.url;
    }

    // Get the parameters I will use throughout
    var method          = (typeof (parameters.type) == "undefined") ? "POST" : parameters.type;
    method              = method.toUpperCase();
    var data            = (typeof (parameters.data) == "undefined") ? {} : parameters.data;
    var sendData        = null;
    var successCallback = (typeof (parameters.success) == "undefined") ? null : parameters.success;
    var errorCallback   = (typeof (parameters.error) == "undefined") ? null : parameters.error;

    // === Cache busting
    var cache = (typeof (parameters.cache) == "undefined") ? false : parameters.url;

    if (!cache)
    {
        var now                = new Date().getTime() / 1000;
        var s                  = parseInt(now, 10);
        data._cacheBustingJunk = Math.round((now - s) * 1000) / 1000;
    }

    // === Interpolate the data
    if ((method == "POST") || (method == "PUT"))
    {
        sendData = this.interpolateParameters(data);
    }
    else
    {
        url += url.indexOf("?") == -1 ? "?" : "&";
        url += this.interpolateParameters(data);
    }

    // === Get the XHR object
    var xhr = new XMLHttpRequest();
    xhr.open(method, url);

    // === Handle POST / PUT data
    if ((method == "POST") || (method == "PUT"))
    {
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    }

    // --- Set the load handler
    xhr.onload = function (event)
    {
        var status         = akeeba.Ajax.xhrSuccessStatus[xhr.status] || xhr.status;
        var statusText     = xhr.statusText;
        var isBinaryResult = (xhr.responseType || "text") !== "text" || typeof xhr.responseText !== "string";
        var responseText   = isBinaryResult ? xhr.response : xhr.responseText;
        var headers        = xhr.getAllResponseHeaders();

        if (status === 200)
        {
            if (successCallback != null)
            {
                akeeba.Ajax.triggerCallbacks(successCallback, responseText, statusText, xhr);
            }

            return;
        }

        if (errorCallback)
        {
            akeeba.Ajax.triggerCallbacks(errorCallback, xhr, "error", null);
        }
    };

    // --- Set the error handler
    xhr.onerror = function (event)
    {
        if (errorCallback)
        {
            akeeba.Ajax.triggerCallbacks(errorCallback, xhr, "error", null);
        }
    };

    // IE 8 is a pain the butt
    if (window.attachEvent && !window.addEventListener)
    {
        xhr.onreadystatechange = function ()
        {
            if (this.readyState === 4)
            {
                var status = akeeba.Ajax.xhrSuccessStatus[this.status] || this.status;

                if (status >= 200 && status < 400)
                {
                    // Success!
                    xhr.onload();
                }
                else
                {
                    xhr.onerror();
                }
            }
        };
    }

    // --- Set the timeout handler
    xhr.ontimeout = function ()
    {
        if (errorCallback)
        {
            akeeba.Ajax.triggerCallbacks(errorCallback, xhr, "timeout", null);
        }
    };

    // --- Set the abort handler
    xhr.onabort = function ()
    {
        if (errorCallback)
        {
            akeeba.Ajax.triggerCallbacks(errorCallback, xhr, "abort", null);
        }
    };

    // --- Apply the timeout before running the request
    var timeout = (typeof (parameters.timeout) == "undefined") ? 600000 : parameters.timeout;

    if (timeout > 0)
    {
        xhr.timeout = timeout;
    }

    // --- Call the beforeSend event handler. If it returns false the request is canceled.
    if (typeof (parameters.beforeSend) != "undefined")
    {
        if (parameters.beforeSend(xhr, parameters) === false)
        {
            return;
        }
    }

    xhr.send(sendData);
};

/**
 * Adds an AJAX request to the request queue and begins processing the queue if it's not already started. The request
 * queue is a FIFO buffer. Each request will be executed as soon as the one preceeding it has completed processing
 * (successfully or otherwise).
 *
 * It's the same syntax as .ajax() with the difference that the request is queued instead of executed right away.
 *
 * @param   url         {string}  The URL to send the request to
 * @param   parameters  {object}  Configuration parameters
 */
akeeba.Ajax.enqueue = function (url, parameters)
{
    // Handles jQuery 1.0 calling style of .ajax(parameters), passing the URL as a property of the parameters object
    if (typeof (parameters) == "undefined")
    {
        parameters = url;
        url        = parameters.url;
    }

    parameters.url = url;
    akeeba.Ajax.requestArray.push(parameters);

    akeeba.Ajax.processQueue();
};

/**
 * Converts a simple object containing query string parameters to a single, escaped query string
 *
 * @param    object   {object}  A plain object containing the query parameters to pass
 * @param    prefix   {string}  Prefix for array-type parameters
 *
 * @returns  {string}
 *
 * @access  private
 */
akeeba.Ajax.interpolateParameters = function (object, prefix)
{
    prefix            = prefix || "";
    var encodedString = "";

    for (var prop in object)
    {
        if (object.hasOwnProperty(prop))
        {
            if (encodedString.length > 0)
            {
                encodedString += "&";
            }

            if (typeof object[prop] !== "object")
            {
                if (prefix === "")
                {
                    encodedString += encodeURIComponent(prop) + "=" + encodeURIComponent(object[prop]);
                }
                else
                {
                    encodedString +=
                        encodeURIComponent(prefix) + "[" + encodeURIComponent(prop) + "]=" + encodeURIComponent(
                            object[prop]);
                }

                continue;
            }

            // Objects need special handling
            encodedString += akeeba.Ajax.interpolateParameters(object[prop], prop);
        }
    }
    return encodedString;
};

/**
 * Goes through a list of callbacks and calls them in succession. Accepts a variable number of arguments.
 */
akeeba.Ajax.triggerCallbacks = function ()
{
    // converts arguments to real array
    var args         = Array.prototype.slice.call(arguments);
    var callbackList = args.shift();

    if (typeof (callbackList) == "function")
    {
        return callbackList.apply(null, args);
    }

    if (callbackList instanceof Array)
    {
        for (var i = 0; i < callbackList.length; i++)
        {
            var callBack = callbackList[i];

            if (callBack.apply(null, args) === false)
            {
                return false;
            }
        }
    }

    return null;
};

/**
 * This helper function triggers the request queue processing using a short (50 msec) timer. This prevents a long
 * function nesting which could cause some browser to abort processing.
 *
 * @access  private
 */
akeeba.Ajax.processQueueHelper = function ()
{
    akeeba.Ajax.processingQueue = false;

    setTimeout(akeeba.Ajax.processQueue, 50);
};

/**
 * Processes the request queue
 *
 * @access  private
 */
akeeba.Ajax.processQueue = function ()
{
    // If I don't have any more requests reset and return
    if (!akeeba.Ajax.requestArray.length)
    {
        akeeba.Ajax.processingQueue = false;
        return;
    }

    // If I am already processing an AJAX request do nothing (I will be called again when the request completes)
    if (akeeba.Ajax.processingQueue)
    {
        return;
    }

    // Extract the URL from the parameters
    var parameters = akeeba.Ajax.requestArray.shift();
    var url        = parameters.url;

    /**
     * Add our queue processing helper to the top of the success and error callback function stacks, ensuring that we
     * will process the next request in the queue as soon as the previous one completes (successfully or not)
     */
    var successCallback = (typeof (parameters.success) == "undefined") ? [] : parameters.success;
    var errorCallback   = (typeof (parameters.error) == "undefined") ? [] : parameters.error;

    if ((typeof (successCallback) != "object") || !(successCallback instanceof Array))
    {
        successCallback = [successCallback];
    }

    if ((typeof (errorCallback) != "object") || !(errorCallback instanceof Array))
    {
        errorCallback = [errorCallback];
    }

    successCallback.unshift(akeeba.Ajax.processQueueHelper);
    errorCallback.unshift(akeeba.Ajax.processQueueHelper);

    parameters.success = successCallback;
    parameters.error   = errorCallback;

    // Mark the queue as currently being processed, blocking further requests until this one completes
    akeeba.Ajax.processingQueue = true;

    // Perform the actual request
    akeeba.Ajax.ajax(url, parameters);
};
