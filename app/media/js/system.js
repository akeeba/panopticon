/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

if (typeof akeeba === "undefined")
{
    var akeeba = {};
}

if (typeof akeeba.System === "undefined")
{
    akeeba.System = {
        documentReady: function (callback, context)
                       {
                       }, params: {
            AjaxURL:              "",
            errorCallback:        null,
            password:             "",
            errorDialogId:        "errorDialog",
            errorDialogMessageId: "errorDialogPre"
        }, notification: {}, modalDialog: null
    };
}

akeeba.System.array_merge = function ()
{
    // Merges elements from passed arrays into one array
    //
    // version: 1103.1210
    // discuss at: http://phpjs.org/functions/array_merge
    // +   original by: Brett Zamir (http://brett-zamir.me)
    // +   bugfixed by: Nate
    // +   input by: josh
    // +   bugfixed by: Brett Zamir (http://brett-zamir.me)
    // *     example 1: arr1 = {"color": "red", 0: 2, 1: 4}
    // *     example 1: arr2 = {0: "a", 1: "b", "color": "green", "shape": "trapezoid", 2: 4}
    // *     example 1: array_merge(arr1, arr2)
    // *     returns 1: {"color": "green", 0: 2, 1: 4, 2: "a", 3: "b", "shape": "trapezoid", 4: 4}
    // *     example 2: arr1 = []
    // *     example 2: arr2 = {1: "data"}
    // *     example 2: array_merge(arr1, arr2)
    // *     returns 2: {0: "data"}
    var args = Array.prototype.slice.call(arguments), retObj = {}, k, j = 0, i = 0, retArr = true;

    for (i = 0; i < args.length; i++)
    {
        if (!(args[i] instanceof Array))
        {
            retArr = false;
            break;
        }
    }

    if (retArr)
    {
        retArr = [];
        for (i = 0; i < args.length; i++)
        {
            retArr = retArr.concat(args[i]);
        }
        return retArr;
    }
    var ct = 0;

    for (i = 0, ct = 0; i < args.length; i++)
    {
        if (args[i] instanceof Array)
        {
            for (j = 0; j < args[i].length; j++)
            {
                retObj[ct++] = args[i][j];
            }
        }
        else
        {
            for (k in args[i])
            {
                if (args[i].hasOwnProperty(k))
                {
                    if (parseInt(k, 10) + "" === k)
                    {
                        retObj[ct++] = args[i][k];
                    }
                    else
                    {
                        retObj[k] = args[i][k];
                    }
                }
            }
        }
    }
    return retObj;
};

// eslint-disable-line camelcase
//  discuss at: http://locutus.io/php/array_diff/
// original by: Kevin van Zonneveld (http://kvz.io)
// improved by: Sanjoy Roy
//  revised by: Brett Zamir (http://brett-zamir.me)
//   example 1: array_diff(['Kevin', 'van', 'Zonneveld'], ['van', 'Zonneveld'])
//   returns 1: {0:'Kevin'}
akeeba.System.array_diff = function (arr1)
{
    var retArr = {};
    var argl   = arguments.length;
    var k1     = "";
    var i      = 1;
    var k      = "";
    var arr    = {};

    arr1keys: for (k1 in arr1)
    {
        for (i = 1; i < argl; i++)
        {
            arr = arguments[i];
            for (k in arr)
            {
                if (arr[k] === arr1[k1])
                {
                    // If it reaches here, it was found in at least one array, so try next value
                    continue arr1keys;
                }
            }
            retArr[k1] = arr1[k1];
        }
    }

    return retArr
};

/**
 * Get a script parameter passed from the backend to the frontend
 *
 * @param {String} key - The name of the parameter to retrieve
 * @param {*} [defaultValue=null] - Default value of the parameter is not yet defined
 *
 * @returns {*}
 */
akeeba.System.getOptions = function (key, defaultValue)
{
    if (typeof defaultValue == "undefined")
    {
        defaultValue = null;
    }

    // Load options if they not exists
    if (!akeeba.System.optionsStorage)
    {
        akeeba.System.loadOptions();
    }

    if (akeeba.System.optionsStorage[key] !== undefined)
    {
        return akeeba.System.optionsStorage[key];
    }

    return defaultValue;
};

/**
 * Load new options from a given options object or from an element
 *
 * If options is not specified it will loop all element with the CSS classed "akeeba-script-options" and "new". The
 * text content of each of these elements is assumed to be a JSON document to be merged with the script options.
 *
 * @param  {Object}  [options]  The options object to load. Eg {"foobar" : {"option1": 1, "option2": 2}}
 */
akeeba.System.loadOptions = function (options)
{
    // Load form a container element
    if (!options)
    {
        var elements = document.querySelectorAll(".akeeba-script-options.new");
        var str;
        var element;
        var option;
        var counter  = 0;

        for (var i = 0, l = elements.length; i < l; i++)
        {
            element = elements[i];
            str     = element.text || element.textContent;
            option  = JSON.parse(str);

            if (option)
            {
                akeeba.System.loadOptions(option);
                counter++;
            }

            akeeba.System.removeClass(element, "new");
            akeeba.System.addClass(element, "loaded");
        }

        if (counter)
        {
            return;
        }
    }

    // Initial loading
    if (!akeeba.System.optionsStorage)
    {
        akeeba.System.optionsStorage = options || {};

        return;
    }

    // Obviously, we have nothing to load.
    if (!options)
    {
        return;
    }

    // Merge with existing
    for (var p in options)
    {
        if (options.hasOwnProperty(p))
        {
            akeeba.System.optionsStorage[p] = options[p];
        }
    }
};

/**
 * An extremely simple error handler, dumping error messages to screen
 *
 * @param  {String} error - The error message string
 */
akeeba.System.defaultErrorHandler = function (error)
{
    if ((error == null) || (typeof error == "undefined"))
    {
        return;
    }

    alert("An error has occurred\n" + error);
};

/**
 * An error handler displayed in a Modal dialog. It requires you to set up a modal dialog div with id "errorDialog"
 *
 * @param  {String} error - The error message string
 */
akeeba.System.modalErrorHandler = function (error)
{
    var dialogId       = akeeba.System.getOptions(
        "akeeba.System.params.errorDialogId", akeeba.System.params.errorDialogId);
    var errorMessageId = akeeba.System.getOptions(
        "akeeba.System.params.errorDialogMessageId", akeeba.System.params.errorDialogMessageId);

    var dialogElement = document.getElementById(dialogId);
    var errorContent  = "error";

    if (dialogElement != null)
    {
        var errorElement       = document.getElementById(errorMessageId);
        errorElement.innerHTML = error;
        errorContent           = dialogElement.innerHTML;
    }

    akeeba.Modal.open({
        content: errorContent, width: "80%"
    });
};

akeeba.System.extractResponse = function (msg)
{
    var ret = {
        isValid: false, data: null
    };

    // Format: delimiter => needsHtmlDecode
    var delimiters = {
        "#\"\\#\\\"#": false, "#&#x22;\\#\\&#x22;#": true, "#&#34;\\#\\&#34;#": true, "###": false
    }

    for (var token_string in delimiters)
    {
        var needsHtmlDecode = delimiters[token_string];
        var valid_pos       = msg.indexOf(token_string);
        var junk            = "";
        var message         = "";

        // If this delimiter is not found move over to the next one
        if (valid_pos === -1)
        {
            continue;
        }

        // Remove the junk BEFORE the delimiter in front of the response.
        message   = valid_pos !== 0 ? msg.substr(valid_pos) : msg;
        // Remove the delimiter in front of the response
        message   = message.substr(token_string.length);
        // Get of rid of any junk after the data
        valid_pos = message.lastIndexOf(token_string);

        // If the delimiter is not found at the end of the data move over to the next delimiter
        if (valid_pos === -1)
        {
            continue;
        }

        // Remove the delimiter at the end of the data and anything after it.
        message = message.substr(0, valid_pos);

        // Do I need to HTML decode the JSON–encoded message?
        if (needsHtmlDecode)
        {
            var dummyTextbox = document.createElement("textarea");

            dummyTextbox.innerHTML = message;
            message                = dummyTextbox.innerText;
        }

        // Let's try to decode the message
        try
        {
            var data = JSON.parse(message);
        }
        catch (err)
        {
            continue;
        }

        ret.isValid = true;
        ret.data    = data;

        return ret;
    }

    return ret;
}

/**
 * Performs an AJAX request and returns the parsed JSON output.
 * akeeba.System.params.AjaxURL is used as the AJAX proxy URL.
 * If there is no errorCallback, the global akeeba.System.params.errorCallback is used.
 *
 * @param  {Object} data - An object with the query data, e.g. a serialized form
 * @param  {String} [data.ajaxURL] - The endpoint URL of the AJAX request, default akeeba.System.params.AjaxURL
 * @param  {function} successCallback - A function accepting a single object parameter, called on success
 * @param  {function} [errorCallback] - A function accepting a single string parameter, called on failure
 * @param  {Boolean} [useCaching=true] - Should we use the cache?
 * @param  {Number} [timeout=60000] - Timeout before cancelling the request in milliseconds
 * @param  {Boolean} [oldToken=false] - Ignored
 */
akeeba.System.doAjax = function (data, successCallback, errorCallback, useCaching, timeout, oldToken)
{
    if (useCaching == null)
    {
        useCaching = true;
    }

    // We always want to burst the cache
    var now                = new Date().getTime() / 1000;
    var s                  = parseInt(now, 10);
    data._cacheBustingJunk = Math.round((now - s) * 1000) / 1000;

    if (timeout == null)
    {
        timeout = 600000;
    }

    var url = akeeba.System.getOptions("akeeba.System.params.AjaxURL", akeeba.System.params.AjaxURL);

    if (data.hasOwnProperty("ajaxURL"))
    {
        url = data.ajaxURL;

        delete data.url;
    }

    var structure = {
        type:     "POST", url: url, cache: false, data: data, timeout: timeout, success: function (msg)
        {
            var extracted = akeeba.System.extractResponse(msg);

            if (!extracted.isValid)
            {
                // Valid data not found in the response
                msg = akeeba.System.sanitizeErrorMessage(msg);
                msg = "Invalid AJAX data: " + msg;

                if (errorCallback == null)
                {
                    if (akeeba.System.params.errorCallback != null)
                    {
                        akeeba.System.params.errorCallback(msg);
                    }
                }
                else
                {
                    errorCallback(msg);
                }

                return;
            }

            // Call the callback function
            successCallback(extracted.data);
        }, error: function (Request, textStatus, errorThrown)
                  {
                      var text    = Request.responseText ? Request.responseText : "";
                      var message = "<strong>AJAX Loading Error</strong><br/>HTTP Status: " + Request.status + " (" + Request.statusText + ")<br/>";

                      message = message + "Internal status: " + textStatus + "<br/>";
                      message = message + "XHR ReadyState: " + Request.readyState + "<br/>";
                      message = message + "Raw server response:<br/>" + akeeba.System.sanitizeErrorMessage(text);

                      if (errorCallback == null)
                      {
                          if (akeeba.System.params.errorCallback != null)
                          {
                              akeeba.System.params.errorCallback(message);
                          }
                      }
                      else
                      {
                          errorCallback(message);
                      }
                  }
    };

    if (useCaching)
    {
        akeeba.Ajax.enqueue(structure);
    }
    else
    {
        akeeba.Ajax.ajax(structure);
    }
};

/**
 * Sanitize a message before displaying it in an error dialog. Some servers return an HTML page with DOM modifying
 * JavaScript when they block the backup script for any reason (usually with a 5xx HTTP error code). Displaying the
 * raw response in the error dialog has the side-effect of killing our backup resumption JavaScript or even completely
 * destroy the page, making backup restart impossible.
 *
 * @param {String} msg - The message to sanitize
 *
 * @returns {String}
 */
akeeba.System.sanitizeErrorMessage = function (msg)
{
    if (msg.indexOf("<script") > -1)
    {
        msg = "(HTML containing script tags)";
    }

    return msg;
};

/**
 * Requests permission for displaying desktop notifications
 */
akeeba.System.notification.askPermission = function ()
{
    if (!akeeba.System.getOptions("akeeba.System.notification.hasDesktopNotification", false))
    {
        return;
    }

    if (window.Notification === undefined)
    {
        return;
    }

    if (window.Notification.permission === "default")
    {
        window.Notification.requestPermission();
    }
};

/**
 * Displays a desktop notification with the given title and body content. Chrome and Firefox will display our custom
 * icon in the notification. Safari will not display our custom icon but will place the notification in the iOS /
 * Mac OS X notification centre. Firefox displays the icon to the right of the notification and its own icon on the
 * left hand side. It also plays a sound when the notification is displayed. Chrome plays no sound and displays only
 * our icon on the left hand side.
 *
 * The notifications have a default timeout of 5 seconds. Clicking on them, or waiting for 5 seconds, will dismiss
 * them. You can change the timeout using the timeout parameter. Set to 0 for a permanent notification.
 *
 * @param  {String} title - The title of the notification
 * @param  {String} [bodyContent] - The body of the notification (optional)
 * @param  {Number} [timeout=5000] - Notification timeout in milliseconds
 */
akeeba.System.notification.notify = function (title, bodyContent, timeout)
{
    if (!akeeba.System.getOptions("akeeba.System.notification.hasDesktopNotification", false))
    {
        return;
    }

    if (window.Notification === undefined)
    {
        return;
    }

    if (window.Notification.permission !== "granted")
    {
        return;
    }

    if (timeout === undefined)
    {
        timeout = 5000;
    }

    if (bodyContent === undefined)
    {
        body = "";
    }

    var n = new window.Notification(title, {
        "body": bodyContent, "icon": akeeba.System.getOptions("akeeba.System.notification.iconURL")
    });

    if (timeout > 0)
    {
        setTimeout(function (notification)
        {
            return function ()
            {
                notification.close();
            }
        }(n), timeout);
    }
};

/**
 * Get and set data to elements. Use:
 * akeeba.System.data.set(element, property, value)
 * akeeba.System.data.get(element, property, defaultValue)
 *
 * On modern browsers (minimum IE 11, Chrome 8, FF 6, Opera 11, Safari 6) this will use the data-* attributes of the
 * elements where possible. On old browsers it will use an internal cache and manually apply data-* attributes.
 */
akeeba.System.data = (function ()
{
    var lastId = 0, store = {};

    return {
        set: function (element, property, value)
             {
                 // IE 11, modern browsers
                 if (element.dataset)
                 {
                     element.dataset[property] = value;

                     if (value == null)
                     {
                         delete element.dataset[property];
                     }

                     return;
                 }

                 // IE 8 to 10, old browsers
                 var id;

                 if (element.myCustomDataTag === undefined)
                 {
                     id                      = lastId++;
                     element.myCustomDataTag = id;
                 }

                 if (typeof (store[id]) == "undefined")
                 {
                     store[id] = {};
                 }

                 // Store the value in the internal cache...
                 store[id][property] = value;

                 // ...and the DOM

                 // Convert the property to dash-format
                 var dataAttributeName = "data-" + property.split(/(?=[A-Z])/).join("-").toLowerCase();

                 if (element.setAttribute)
                 {
                     element.setAttribute(dataAttributeName, value);
                 }

                 if (value == null)
                 {
                     // IE 8 throws an exception on "delete"
                     try
                     {
                         delete store[id][property];
                         element.removeAttribute(dataAttributeName);
                     }
                     catch (e)
                     {
                         store[id][property] = null;
                     }
                 }
             },

        get: function (element, property, defaultValue)
             {
                 // IE 11, modern browsers
                 if (element.dataset)
                 {
                     if (typeof (element.dataset[property]) == "undefined")
                     {
                         element.dataset[property] = defaultValue;
                     }

                     return element.dataset[property];
                 }
                 // IE 8 to 10, old browsers

                 if (typeof (defaultValue) == "undefined")
                 {
                     defaultValue = null;
                 }

                 // Make sure we have an internal storage
                 if (typeof (store[element.myCustomDataTag]) == "undefined")
                 {
                     store[element.myCustomDataTag] = {};
                 }

                 // Convert the property to dash-format
                 var dataAttributeName = "data-" + property.split(/(?=[A-Z])/).join("-").toLowerCase();

                 // data-* attributes have precedence
                 if (typeof (element[dataAttributeName]) !== "undefined")
                 {
                     store[element.myCustomDataTag][property] = element[dataAttributeName];
                 }

                 // No data-* attribute and no stored value? Use the default.
                 if (typeof (store[element.myCustomDataTag][property]) == "undefined")
                 {
                     this.set(element, property, defaultValue);
                 }

                 // Return the value of the data
                 return store[element.myCustomDataTag][property];
             }
    };
}());


/**
 * Adds an event listener to an element
 *
 * @param {Element|String} element - The element or DOM ID to set the event listener to
 * @param {String} eventName - The name of the event to handle, e.g. "click", "change", "error", ...
 * @param {function} listener - The event listener to add
 */
akeeba.System.addEventListener = function (element, eventName, listener)
{
    // Allow the passing of an element ID string instead of the DOM elem
    if (typeof element === "string")
    {
        element = document.getElementById(element);
    }

    if (element == null)
    {
        return;
    }

    if (typeof element != "object")
    {
        return;
    }

    if (!(element instanceof Element))
    {
        return;
    }

    // Handles the listener in a way that returning boolean false will cancel the event propagation
    function listenHandler(e)
    {
        var ret = listener.apply(this, arguments);

        if (ret === false)
        {
            if (e.stopPropagation())
            {
                e.stopPropagation();
            }

            if (e.preventDefault)
            {
                e.preventDefault();
            }
            else
            {
                e.returnValue = false;
            }
        }

        return (ret);
    }

    // Equivalent of listenHandler for IE8
    function attachHandler()
    {
        // Normalize the target of the event –– PhpStorm detects this as an error
        // window.event.target = window.event.srcElement;

        var ret = listener.call(element, window.event);

        if (ret === false)
        {
            window.event.returnValue  = false;
            window.event.cancelBubble = true;
        }

        return (ret);
    }

    if (element.addEventListener)
    {
        element.addEventListener(eventName, listenHandler, false);

        return;
    }

    element.attachEvent("on" + eventName, attachHandler);
};

/**
 * Remove an event listener from an element
 *
 * @param {Element|String} element - The element or DOM ID to remove the event listener from
 * @param {String} eventName - The name of the event to handle, e.g. "click", "change", "error", ...
 * @param {function} listener - The event listener to remove
 */
akeeba.System.removeEventListener = function (element, eventName, listener)
{
    // Allow the passing of an element ID string instead of the DOM elem
    if (typeof element === "string")
    {
        element = document.getElementById(element);
    }

    if (element == null)
    {
        return;
    }

    if (typeof element != "object")
    {
        return;
    }

    if (!(element instanceof Element))
    {
        return;
    }

    if (element.removeEventListener)
    {
        element.removeEventListener(eventName, listener);

        return;
    }

    element.detachEvent("on" + eventName, listener);
};

/**
 * Trigger an event on a DOM element
 *
 * @param {Element|String} element - The element or DOM ID to trigger the event on
 * @param {String} eventName - The name of the event to trigger, e.g. "click", "change", "error", ...
 */
akeeba.System.triggerEvent = function (element, eventName)
{
    if (typeof element == "undefined")
    {
        return;
    }

    if (element === null)
    {
        return;
    }

    // Allow the passing of an element ID string instead of the DOM elem
    if (typeof element === "string")
    {
        element = document.getElementById(element);
    }

    if (element === null)
    {
        return;
    }

    if (typeof element != "object")
    {
        return;
    }

    if (!(element instanceof Element))
    {
        return;
    }

    if (eventName === "click" && (typeof element.click === "function"))
    {
        console.log(element);
        element.click();

        return;
    }

    // Use jQuery and be done with it!
    if (typeof window.jQuery === "function")
    {
        window.jQuery(element).trigger(eventName);

        return;
    }

    // Internet Explorer way
    if (document.fireEvent && (typeof window.Event == "undefined"))
    {
        element.fireEvent("on" + eventName);

        return;
    }

    // This works on Chrome and Edge but not on Firefox. Ugh.
    var event = null;

    event = document.createEvent("Event");
    event.initEvent(eventName, true, true);
    element.dispatchEvent(event);
};

// document.ready equivalent from https://github.com/jfriend00/docReady/blob/master/docready.js
(function (funcName, baseObj)
{
    funcName = funcName || "documentReady";
    baseObj  = baseObj || akeeba.System;

    var readyList                   = [];
    var readyFired                  = false;
    var readyEventHandlersInstalled = false;

    // Call this when the document is ready. This function protects itself against being called more than once.
    function ready()
    {
        if (!readyFired)
        {
            // This must be set to true before we start calling callbacks
            readyFired = true;

            for (var i = 0; i < readyList.length; i++)
            {
                /**
                 * If a callback here happens to add new ready handlers, this function will see that it already
                 * fired and will schedule the callback to run right after this event loop finishes so all handlers
                 * will still execute in order and no new ones will be added to the readyList while we are
                 * processing the list.
                 */
                readyList[i].fn.call(window, readyList[i].ctx);
            }

            // Allow any closures held by these functions to free
            readyList = [];
        }
    }

    /**
     * Solely for the benefit of Internet Explorer
     */
    function readyStateChange()
    {
        if (document.readyState === "complete")
        {
            ready();
        }
    }

    /**
     * This is the one public interface:
     *
     * akeeba.System.documentReady(fn, context);
     *
     * @param   callback   The callback function to execute when the document is ready.
     * @param   context    Optional. If present, it will be passed as an argument to the callback.
     */
    //
    //
    //
    baseObj[funcName] = function (callback, context)
    {
        // If ready() has already fired, then just schedule the callback to fire asynchronously
        if (readyFired)
        {
            setTimeout(function ()
            {
                callback(context);
            }, 1);

            return;
        }

        // Add the function and context to the queue
        readyList.push({fn: callback, ctx: context});

        /**
         * If the document is already ready, schedule the ready() function to run immediately.
         *
         * Note: IE is only safe when the readyState is "complete", other browsers are safe when the readyState is
         * "interactive"
         */
        if (document.readyState === "complete" || (!document.attachEvent && document.readyState === "interactive"))
        {
            setTimeout(ready, 1);

            return;
        }

        // If the handlers are already installed just quit
        if (readyEventHandlersInstalled)
        {
            return;
        }

        // We don't have event handlers installed, install them
        readyEventHandlersInstalled = true;

        // -- We have an addEventListener method in the document, this is a modern browser.

        if (document.addEventListener)
        {
            // Prefer using the DOMContentLoaded event
            document.addEventListener("DOMContentLoaded", ready, false);

            // Our backup is the window's "load" event
            window.addEventListener("load", ready, false);

            return;
        }

        // -- Most likely we're stuck with an ancient version of IE

        // Our primary method of activation is the onreadystatechange event
        document.attachEvent("onreadystatechange", readyStateChange);

        // Our backup is the windows's "load" event
        window.attachEvent("onload", ready);
    }
})("documentReady", akeeba.System);

/**
 * Add one or more CSS classes to an element, if they do not already exist
 *
 * @param {Element} element - The element to add new classes to
 * @param {String|String[]} newClasses - One or more classes to append
 */
akeeba.System.addClass = function (element, newClasses)
{
    if (!element || !element.className)
    {
        return;
    }

    var currentClasses = element.className.split(" ");

    if ((typeof newClasses) == "string")
    {
        newClasses = newClasses.split(" ");
    }

    currentClasses = akeeba.System.array_merge(currentClasses, newClasses);

    element.className = "";

    for (var property in currentClasses)
    {
        if (currentClasses.hasOwnProperty(property))
        {
            element.className += currentClasses[property] + " ";
        }
    }

    if (element.className.trim)
    {
        element.className = element.className.trim();
    }
};

/**
 * Remove one or more CSS classes to an element, if they already exist
 *
 * @param {Element} element - The element to remove classes from
 * @param {String|String[]} oldClasses - One or more classes to remove
 */
akeeba.System.removeClass = function (element, oldClasses)
{
    if (!element || !element.className)
    {
        return;
    }

    var currentClasses = element.className.split(" ");

    if ((typeof oldClasses) == "string")
    {
        oldClasses = oldClasses.split(" ");
    }

    currentClasses = akeeba.System.array_diff(currentClasses, oldClasses);

    element.className = "";

    for (property in currentClasses)
    {
        if (currentClasses.hasOwnProperty(property))
        {
            element.className += currentClasses[property] + " ";
        }
    }

    if (element.className.trim)
    {
        element.className = element.className.trim();
    }
};

/**
 * Does the element have the specified CSS class?
 *
 * @param {Element} element - The DOM element to query for CSS classes
 * @param {String} aClass - The CSS class to check for existence
 *
 * @returns {boolean} - True if element has the CSS class aClass
 */
akeeba.System.hasClass = function (element, aClass)
{
    if (!element || !element.className)
    {
        return;
    }

    var currentClasses = element.className.split(" ");

    for (i = 0; i < currentClasses.length; i++)
    {
        if (currentClasses[i] == aClass)
        {
            return true;
        }
    }

    return false;
};

/**
 * Toggle a CSS class on an element.
 *
 * If the CSS class does not exist on the element it will be appended. If it already exists it will be removed.
 *
 * @param {Element} element - The element to toggle the CSS class on
 * @param {String} aClass - The name of the CSS class to toggle
 */
akeeba.System.toggleClass = function (element, aClass)
{
    if (akeeba.System.hasClass(element, aClass))
    {
        akeeba.System.removeClass(element, aClass);

        return;
    }

    akeeba.System.addClass(element, aClass);
};

/**
 * Toggles the check state of a group of boxes
 *
 * Checkboxes must have an id attribute in the form cb0, cb1...
 *
 * @param    {Number} checkbox  The number of box to 'check', for a checkbox element
 * @param    {String} [stub]    An alternative field name
 */
akeeba.System.checkAll = function (checkbox, stub)
{
    if (!stub)
    {
        stub = "cb";
    }

    if (checkbox.form)
    {
        var c = 0;

        for (var i = 0, n = checkbox.form.elements.length; i < n; i++)
        {
            var e = checkbox.form.elements[i];

            if (e.type == checkbox.type)
            {
                if ((stub && e.id.indexOf(stub) == 0) || !stub)
                {
                    e.checked = checkbox.checked;
                    c += (e.checked == true ? 1 : 0);
                }
            }
        }

        if (checkbox.form.boxchecked)
        {
            checkbox.form.boxchecked.value = c;
        }

        return true;
    }

    return false;
};

/**
 * Update the boxchecked field of a form when a checkbox is checked. Based on Joomla.isChecked of Joomla! 3.
 *
 * @param   {Boolean} isItChecked  The checkbox
 * @param   {Element} [form]       The form to update
 */
akeeba.System.isChecked = function (isItChecked, form)
{
    if (typeof (form) === "undefined")
    {
        form = document.getElementById("adminForm");
    }

    if (isItChecked == true)
    {
        form.boxchecked.value++;
    }
    else
    {
        form.boxchecked.value--;
    }
};

/**
 * Apply the grid view table ordering
 *
 * @param  {String} order - Name of the field to order the form by
 * @param  {String} dir - The sort direction, "asc" or "desc"
 * @param  {String} [task] - The task to apply to the form
 * @param  {Element} [form] - The form, defaults to adminForm
 */
akeeba.System.tableOrdering = function (order, dir, task, form)
{
    if (typeof form === "undefined")
    {
        form = document.getElementById("adminForm");
    }

    form.filter_order.value     = order;
    form.filter_order_Dir.value = dir;
    akeeba.System.submitForm(task, form);
};

/**
 * Submit a form
 *
 * @param  {String} [task] - The given task, null to leave the form's task as currently set
 * @param  {Element} [form] - The form element, defaults to adminForm
 * @param  {boolean} [validate] - Should I apply HTML5 form validation? If not defined it's the same as false
 */
akeeba.System.submitForm = function (task, form, validate)
{
    if (!form)
    {
        form = document.getElementById("adminForm");
    }

    if (task)
    {
        form.task.value = task;
    }

    // Toggle HTML5 validation
    form.noValidate = !validate;

    if (!validate)
    {
        form.setAttribute("novalidate", "");
    }
    else if (form.hasAttribute("novalidate"))
    {
        form.removeAttribute("novalidate");
    }

    // Create an input type="submit"
    var button           = document.createElement("input");
    button.style.display = "none";
    button.type          = "submit";

    // Append the button and click it
    form.appendChild(button);

    button.click();

    // If "submit" was prevented, make sure we don't get a build up of buttons
    form.removeChild(button);
};

/**
 * Apply the grid view table ordering based on the sortTable options
 *
 * This is meant to be used as the change event handler for the Sort By and Sort Ordering dropdowns in grid views
 */
akeeba.System.orderTable = function ()
{
    var table     = document.getElementById("sortTable");
    var direction = document.getElementById("directionTable");
    var order     = table.options[table.selectedIndex].value;
    var dir       = "asc";

    if (order === akeeba.System.getOptions("akeeba.System.tableOrder", "asc"))
    {
        dir = direction.options[direction.selectedIndex].value;
    }

    akeeba.System.tableOrdering(order, dir);
};

/**
 * Apply a callback to a list of DOM elements
 *
 * This is useful when applying an event handler to all objects that have a specific CSS class. Example:
 * akeeba.System.iterateNodes("superClickable", function (el) {
 *     akeeba.System.addEventListener(el, "click", mySuperClickableHandler);
 * });
 *
 * @param {String|NodeList} elements - The NodeList to iterate or a CSS query selector pass to document.querySelectorAll
 * @param {function} callback - The callback to execute for each node
 * @param {*} [context] - Optional additional parameter to pass to the callback
 */
akeeba.System.iterateNodes = function (elements, callback, context)
{
    if (typeof callback != "function")
    {
        return;
    }

    // Allow passing a CSS selector string instead of a NodeList object
    if (typeof elements === "string")
    {
        elements = document.querySelectorAll(elements);
    }

    if (elements.length === 0)
    {
        return;
    }

    var i;
    var el;

    for (i = 0; i < elements.length; i++)
    {
        el = elements[i];

        if (typeof context !== "undefined")
        {
            callback(el, context);

            continue;
        }

        callback(el);
    }
};

/**
 * Assign the default AJAX error handler that best matches the document.
 *
 * If the akeeba.System.params.errorDialogId and .errorDialogMessageId script options are set, they correspond to
 * existing elements and the akeeba.Modal is a valid object we'll be using the modalErrorHandler. Otherwise we fall back
 * to the dead simple defaultErrorHandler that simply shows an alert.
 */
akeeba.System.assignDefaultErrorHandler = function ()
{
    // Use the modal error handler unless there is a reason not to
    akeeba.System.params.errorCallback = akeeba.System.modalErrorHandler;

    // If the Modal code is not present always and immediately fall back to the simpler error handler
    if (typeof akeeba.Modal === "undefined")
    {
        akeeba.System.params.errorCallback = akeeba.System.defaultErrorHandler;

        return;
    }

    var dialogId       = akeeba.System.getOptions(
        "akeeba.System.params.errorDialogId", akeeba.System.params.errorDialogId);
    var errorMessageId = akeeba.System.getOptions(
        "akeeba.System.params.errorDialogMessageId", akeeba.System.params.errorDialogMessageId);

    // If the modal configuration is not present fall back to the simpler error handler
    if ((dialogId === "") || (dialogId === null) || (errorMessageId === "") || (errorMessageId === null))
    {
        akeeba.System.params.errorCallback = akeeba.System.defaultErrorHandler;

        return;
    }

    // If either element used in the modal code is not present fall fall back to the simpler error handler
    var dialogElement = document.getElementById(dialogId);
    var errorElement  = document.getElementById(errorMessageId);

    if ((dialogElement === null) || (errorElement === null))
    {
        akeeba.System.params.errorCallback = akeeba.System.defaultErrorHandler;
    }

};


/**
 * JavaScript internationalization
 *
 * @type {{}}
 *
 * Allows you to call akeeba.System.Text._() to get a translated JavaScript string pushed in via script options.
 */
akeeba.System.Text = {
    strings: {},

    /**
     * Translates a string into the current language.
     *
     * @param {String} key   The string to translate
     * @param {String} [def] Default string
     *
     * @returns {String}
     */
    "_": function (key, def)
         {

             // Check for new strings in the optionsStorage, and load them
             var newStrings = akeeba.System.getOptions("akeeba.text");

             if (newStrings)
             {
                 akeeba.System.Text.load(newStrings);

                 var dummyObject            = {};
                 dummyObject["akeeba.text"] = null;

                 akeeba.System.loadOptions(dummyObject);
             }

             def = def === undefined ? "" : def;
             key = key.toUpperCase();

             if (akeeba.System.Text.strings[key] !== undefined)
             {
                 return akeeba.System.Text.strings[key];
             }

             return def;
         },

    /**
     * Load new strings in the Text object
     *
     * @param {Object} object  Object with new strings
     * @returns {akeeba.System.Text}
     */
    load: function (object)
          {
              for (var key in object)
              {
                  if (!object.hasOwnProperty(key))
                  {
                      continue;
                  }
                  this.strings[key.toUpperCase()] = object[key];
              }

              return this;
          }
};

/**
 * Performs an AJAX request to the restoration script (restore.php).
 *
 * @param  {Object}   data              - An object with the query data, e.g. a serialized form
 * @param  {function} successCallback   - A function accepting a single object parameter, called on success
 * @param  {function} [errorCallback]   - A function accepting a single string parameter, called on failure
 * @param  {Boolean}  [useCaching=true] - Should we use the cache?
 * @param  {Number}   [timeout=60000]   - Timeout before cancelling the request (default 60s)
 * @param  {Boolean}  [oldToken=false]  - Should I search for the old token instead of the new one?
 *
 * @return
 */
akeeba.System.doEncryptedAjax = function (data, successCallback, errorCallback, useCaching, timeout, oldToken)
{
    var url = akeeba.System.getOptions("akeeba.System.params.AjaxURL", akeeba.System.params.AjaxURL);

    if (data.hasOwnProperty("ajaxURL"))
    {
        url = data.ajaxURL;

        delete data.url;
    }

    var json      = JSON.stringify(data);
    var post_data = {
        json: json, ajaxURL: url
    };
    var password  = akeeba.System.getOptions("akeeba.System.params.password", akeeba.System.params.password);

    if (password.length > 0)
    {
        post_data.password = password;
    }

    return akeeba.System.doAjax(post_data, successCallback, errorCallback, useCaching, timeout, oldToken);
};

/**
 * Creates a modal box based on the data object. The keys to this object are:
 * - title          The title of the modal dialog, skip to not create a title
 * - body           The body content of the dialog. Not applicable if href is defined
 * - href           A URL to open in an IFrame inside the body
 * - iFrameHeight   The height of the IFrame, applicable if href is set
 * - iFrameWidth    The width of the IFrame, applicable if href is set
 * - OkLabel        The label of the OK (primary) button
 * - CancelLabel    The label of the Cancel button
 * - OkHandler      Run this when the OK button is pressed, before closing the modal
 * - CancelHandler  Run this when the Cancel button is pressed, after closing the modal
 * - showButtons    Set to false to not show the buttons
 *
 * Alternatively you can pass a reference to an element. In this case we expect that the rel attribute of the element
 * contains a JSON-encoded string of the data object.
 *
 * @param   data  The configuration data (see above)
 */
akeeba.System.modal = function (data)
{
    try
    {
        if (typeof (data.rel) !== "undefined")
        {
            rel  = data.rel;
            data = JSON.parse(rel);
        }
    }
    catch (e)
    {
    }

    // Outer modal markup
    var modalWrapper = document.createElement("div");
    modalBody.setAttribute("tabindex", "-1");
    modalBody.setAttribute("role", "dialog");
    modalBody.setAttribute("aria-hidden", "true");

    var modalDialog       = document.createElement("div");
    modalDialog.className = "modal-dialog";
    modalWrapper.appendChild(modalDialog);

    var modalContent       = document.createElement("div");
    modalContent.className = "modal-content";
    modalDialog.appendChild(modalContent);

    // Modal Header
    if (typeof (data.title) !== "undefined")
    {
        var modalHeader       = document.createElement("div");
        modalHeader.className = "modal-header";
        modalContent.appendChild(modalHeader);

        var headerCloseButton       = document.createElement("button");
        headerCloseButton.className = "close";
        headerCloseButton.innerHTML = "&times;";
        modalHeader.appendChild(headerCloseButton);

        var modalHeaderTitle       = document.createElement("h4");
        modalHeaderTitle.className = "modal-title";
        modalHeaderTitle.innerHTML = data.title;
        modalHeader.appendChild(modalHeaderTitle);

        // Assign events
        if (typeof (data.CancelHandler) !== "undefined")
        {
            akeeba.System.addEventListener(headerCloseButton, "click", function (e)
            {
                var callback = data.CancelHandler;

                akeeba.System.modalDialog.close();

                callback(modalWrapper);
                e.preventDefault();
            });
        }
    }

    // Modal body
    var modalBody       = document.createElement("div");
    modalBody.className = "modal-body";
    modalContent.appendChild(modalBody);

    if (typeof (data.href) === "undefined")
    {
        // HTML body
        modalBody.innerHTML = data.body;
    }
    else if (data.href.substr(0, 1) == "#")
    {
        // Inherited content
        var inheritedElement = window.document.querySelector(data.href);

        while (inheritedElement.childNodes.length > 0)
        {
            modalBody.appendChild(inheritedElement.childNodes[0]);
        }
    }
    else
    {
        // IFrame

        var iFrame               = document.createElement("iframe");
        iFrame.src               = data.href;
        iFrame.width             = "100%";
        iFrame.height            = 400;
        iFrame.frameborder       = 0;
        iFrame.allowtransparency = true;
        modalBody.appendChild(iFrame);

        if (typeof (data.iFrameHeight) !== "undefined")
        {
            iFrame.height = data.iFrameHeight;
        }

        if (typeof (data.iFrameWidth) !== "undefined")
        {
            iFrame.width = data.iFrameWidth;
        }
    }

    // Should I show the buttons?
    var showButtons = true;

    if (typeof (data.showButtons) !== "undefined")
    {
        showButtons = data.showButtons;
    }

    // Modal buttons
    if (showButtons)
    {
        // Create the modal footer
        var modalFooter       = document.createElement("div");
        modalFooter.className = "modal-footer";
        modalContent.appendChild(modalFooter);

        // Get the button labels
        var okLabel     = akeeba.System.Text._("UI-MODAL-OK");
        var cancelLabel = akeeba.System.Text._("UI-MODAL-CANCEL");

        if (typeof (data.OkLabel) !== "undefined")
        {
            okLabel = data.OkLabel;
        }

        if (typeof (data.CancelLabel) !== "undefined")
        {
            cancelLabel = data.CancelLabel;
        }

        // Create buttons
        var cancelButton       = document.createElement("button");
        cancelButton.className = "btn btn-default";
        cancelButton.setAttribute("type", "button");
        cancelButton.innerHTML = cancelLabel;
        modalFooter.appendChild(cancelLabel);

        var okButton       = document.createElement("button");
        okButton.className = "btn btn-primary";
        okButton.setAttribute("type", "button");
        okButton.innerHTML = okLabel;
        modalFooter.appendChild(okButton);

        // Assign handlers
        if (typeof (data.CancelHandler) !== "undefined")
        {
            akeeba.System.addEventListener(cancelButton, "click", function (e)
            {
                var callback = data.CancelHandler;
                akeeba.System.modalDialog.close();
                callback(modalWrapper);
                e.preventDefault();
            });
        }

        if (typeof (data.OkHandler) !== "undefined")
        {
            akeeba.System.addEventListener(okButton, "click", function (e)
            {
                var callback = data.OkHandler;
                akeeba.System.modalDialog.close();
                callback(modalWrapper);
                e.preventDefault();
            });
        }
        else
        {
            akeeba.System.addEventListener(okButton, "click", function (e)
            {
                akeeba.System.modalDialog.close();
                e.preventDefault();
            });
        }

        // Hide unnecessary buttons
        if (okLabel.trim() == "")
        {
            okButton.style.display = "none";
        }

        if (cancelLabel.trim() == "")
        {
            cancelButton.style.display = "none";
        }
    }

    // Show modal
    akeeba.System.modalDialog = akeeba.Modal.open({
        inherit: modalWrapper, width: "450", height: "280"
    });
};

// Initialization
akeeba.System.documentReady(function ()
{
    // Assign the correct default error handler
    akeeba.System.assignDefaultErrorHandler();

    // Grid Views: click event handler for the Check All checkbox
    akeeba.System.iterateNodes(".akeebaGridViewCheckAll", function (el)
    {
        akeeba.System.addEventListener(el, "click", function ()
        {
            Joomla.checkAll(this);
        })
    });

    // Grid Views: change event handler for the ordering field and direction dropdowns
    akeeba.System.iterateNodes(".akeebaGridViewOrderTable", function (el)
    {
        akeeba.System.addEventListener(el, "change", akeeba.System.orderTable)
    });

    // Grid Views: change event handler for search fields which autosubmit the form on change
    akeeba.System.iterateNodes(".akeebaGridViewAutoSubmitOnChange", function (el)
    {
        akeeba.System.addEventListener(el, "change", function ()
        {
            akeeba.System.submitForm();
        })
    });
});
