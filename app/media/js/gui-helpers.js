/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

if (typeof (akeeba) === "undefined")
{
    var akeeba = {};
}

/*
 *	https://raw.githubusercontent.com/douglascrockford/JSON-js/master/json2.js
 *  2016-05-01
 *  Public Domain.
 *  NO WARRANTY EXPRESSED OR IMPLIED. USE AT YOUR OWN RISK.
 *  See http://www.JSON.org/js.html
 */
// Create a JSON object only if one does not already exist. We create the
// methods in a closure to avoid creating global variables.

if (typeof JSON !== "object")
{
    JSON = {};
}

(function ()
{
    "use strict";

    var rx_one       = /^[\],:{}\s]*$/;
    var rx_two       = /\\(?:["\\\/bfnrt]|u[0-9a-fA-F]{4})/g;
    var rx_three     = /"[^"\\\n\r]*"|true|false|null|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?/g;
    var rx_four      = /(?:^|:|,)(?:\s*\[)+/g;
    var rx_escapable = /[\\\"\u0000-\u001f\u007f-\u009f\u00ad\u0600-\u0604\u070f\u17b4\u17b5\u200c-\u200f\u2028-\u202f\u2060-\u206f\ufeff\ufff0-\uffff]/g;
    var rx_dangerous = /[\u0000\u00ad\u0600-\u0604\u070f\u17b4\u17b5\u200c-\u200f\u2028-\u202f\u2060-\u206f\ufeff\ufff0-\uffff]/g;

    function f(n)
    {
        // Format integers to have at least two digits.
        return n < 10 ? "0" + n : n;
    }

    function this_value()
    {
        return this.valueOf();
    }

    if (typeof Date.prototype.toJSON !== "function")
    {

        Date.prototype.toJSON = function ()
        {

            return isFinite(this.valueOf()) ? this.getUTCFullYear() + "-" + f(this.getUTCMonth() + 1) + "-" + f(
                this.getUTCDate()) + "T" + f(this.getUTCHours()) + ":" + f(this.getUTCMinutes()) + ":" + f(
                this.getUTCSeconds()) + "Z" : null;
        };

        Boolean.prototype.toJSON = this_value;
        Number.prototype.toJSON  = this_value;
        String.prototype.toJSON  = this_value;
    }

    var gap;
    var indent;
    var meta;
    var rep;


    function quote(string)
    {

// If the string contains no control characters, no quote characters, and no
// backslash characters, then we can safely slap some quotes around it.
// Otherwise we must also replace the offending characters with safe escape
// sequences.

        rx_escapable.lastIndex = 0;
        return rx_escapable.test(string) ? "\"" + string.replace(rx_escapable, function (a)
        {
            var c = meta[a];
            return typeof c === "string" ? c : "\\u" + ("0000" + a.charCodeAt(0).toString(16)).slice(-4);
        }) + "\"" : "\"" + string + "\"";
    }


    function str(key, holder)
    {

// Produce a string from holder[key].

        var i;          // The loop counter.
        var k;          // The member key.
        var v;          // The member value.
        var length;
        var mind  = gap;
        var partial;
        var value = holder[key];

// If the value has a toJSON method, call it to obtain a replacement value.

        if (value && typeof value === "object" && typeof value.toJSON === "function")
        {
            value = value.toJSON(key);
        }

// If we were called with a replacer function, then call the replacer to
// obtain a replacement value.

        if (typeof rep === "function")
        {
            value = rep.call(holder, key, value);
        }

// What happens next depends on the value's type.

        switch (typeof value)
        {
            case "string":
                return quote(value);

            case "number":

// JSON numbers must be finite. Encode non-finite numbers as null.

                return isFinite(value) ? String(value) : "null";

            case "boolean":
            case "null":

// If the value is a boolean or null, convert it to a string. Note:
// typeof null does not produce "null". The case is included here in
// the remote chance that this gets fixed someday.

                return String(value);

// If the type is "object", we might be dealing with an object or an array or
// null.

            case "object":

// Due to a specification blunder in ECMAScript, typeof null is "object",
// so watch out for that case.

                if (!value)
                {
                    return "null";
                }

// Make an array to hold the partial results of stringifying this object value.

                gap += indent;
                partial = [];

// Is the value an array?

                if (Object.prototype.toString.apply(value) === "[object Array]")
                {

// The value is an array. Stringify every element. Use null as a placeholder
// for non-JSON values.

                    length = value.length;
                    for (i = 0; i < length; i += 1)
                    {
                        partial[i] = str(i, value) || "null";
                    }

// Join all of the elements together, separated with commas, and wrap them in
// brackets.

                    v   =
                        partial.length === 0 ? "[]" : gap ? "[\n" + gap + partial.join(
                            ",\n" + gap) + "\n" + mind + "]" : "[" + partial.join(",") + "]";
                    gap = mind;
                    return v;
                }

// If the replacer is an array, use it to select the members to be stringified.

                if (rep && typeof rep === "object")
                {
                    length = rep.length;
                    for (i = 0; i < length; i += 1)
                    {
                        if (typeof rep[i] === "string")
                        {
                            k = rep[i];
                            v = str(k, value);
                            if (v)
                            {
                                partial.push(quote(k) + (gap ? ": " : ":") + v);
                            }
                        }
                    }
                }
                else
                {

// Otherwise, iterate through all of the keys in the object.

                    for (k in value)
                    {
                        if (Object.prototype.hasOwnProperty.call(value, k))
                        {
                            v = str(k, value);
                            if (v)
                            {
                                partial.push(quote(k) + (gap ? ": " : ":") + v);
                            }
                        }
                    }
                }

// Join all of the member texts together, separated with commas,
// and wrap them in braces.

                v   =
                    partial.length === 0 ? "{}" : gap ? "{\n" + gap + partial.join(
                        ",\n" + gap) + "\n" + mind + "}" : "{" + partial.join(",") + "}";
                gap = mind;
                return v;
        }
    }

// If the JSON object does not yet have a stringify method, give it one.

    if (typeof JSON.stringify !== "function")
    {
        meta           = {    // table of character substitutions
            "\b": "\\b", "\t": "\\t", "\n": "\\n", "\f": "\\f", "\r": "\\r", "\"": "\\\"", "\\": "\\\\"
        };
        JSON.stringify = function (value, replacer, space)
        {

// The stringify method takes a value and an optional replacer, and an optional
// space parameter, and returns a JSON text. The replacer can be a function
// that can replace values, or an array of strings that will select the keys.
// A default replacer method can be provided. Use of the space parameter can
// produce text that is more easily readable.

            var i;
            gap    = "";
            indent = "";

// If the space parameter is a number, make an indent string containing that
// many spaces.

            if (typeof space === "number")
            {
                for (i = 0; i < space; i += 1)
                {
                    indent += " ";
                }

// If the space parameter is a string, it will be used as the indent string.

            }
            else if (typeof space === "string")
            {
                indent = space;
            }

// If there is a replacer, it must be a function or an array.
// Otherwise, throw an error.

            rep = replacer;
            if (replacer && typeof replacer !== "function" && (typeof replacer !== "object" || typeof replacer.length !== "number"))
            {
                throw new Error("JSON.stringify");
            }

// Make a fake root object containing our value under the key of "".
// Return the result of stringifying the value.

            return str("", {"": value});
        };
    }


// If the JSON object does not yet have a parse method, give it one.

    if (typeof JSON.parse !== "function")
    {
        JSON.parse = function (text, reviver)
        {

// The parse method takes a text and an optional reviver function, and returns
// a JavaScript value if the text is a valid JSON text.

            var j;

            function walk(holder, key)
            {

// The walk method is used to recursively walk the resulting structure so
// that modifications can be made.

                var k;
                var v;
                var value = holder[key];
                if (value && typeof value === "object")
                {
                    for (k in value)
                    {
                        if (Object.prototype.hasOwnProperty.call(value, k))
                        {
                            v = walk(value, k);
                            if (v !== undefined)
                            {
                                value[k] = v;
                            }
                            else
                            {
                                delete value[k];
                            }
                        }
                    }
                }
                return reviver.call(holder, key, value);
            }


// Parsing happens in four stages. In the first stage, we replace certain
// Unicode characters with escape sequences. JavaScript handles many characters
// incorrectly, either silently deleting them, or treating them as line endings.

            text                   = String(text);
            rx_dangerous.lastIndex = 0;
            if (rx_dangerous.test(text))
            {
                text = text.replace(rx_dangerous, function (a)
                {
                    return "\\u" + ("0000" + a.charCodeAt(0).toString(16)).slice(-4);
                });
            }

// In the second stage, we run the text against regular expressions that look
// for non-JSON patterns. We are especially concerned with "()" and "new"
// because they can cause invocation, and "=" because it can cause mutation.
// But just to be safe, we want to reject all unexpected forms.

// We split the second stage into 4 regexp operations in order to work around
// crippling inefficiencies in IE's and Safari's regexp engines. First we
// replace the JSON backslash pairs with "@" (a non-JSON character). Second, we
// replace all simple value tokens with "]" characters. Third, we delete all
// open brackets that follow a colon or comma or that begin the text. Finally,
// we look to see that the remaining characters are only whitespace or "]" or
// "," or ":" or "{" or "}". If that is so, then the text is safe for eval.

            if (rx_one.test(text
                .replace(rx_two, "@")
                .replace(rx_three, "]")
                .replace(rx_four, "")))
            {

// In the third stage we use the eval function to compile the text into a
// JavaScript structure. The "{" operator is subject to a syntactic ambiguity
// in JavaScript: it can begin a block or an object literal. We wrap the text
// in parens to eliminate the ambiguity.

                j = eval("(" + text + ")");

// In the optional fourth stage, we recursively walk the new structure, passing
// each name/value pair to a reviver function for possible transformation.

                return (typeof reviver === "function") ? walk({"": j}, "") : j;
            }

// If the text is not JSON parseable, then a SyntaxError is thrown.

            throw new SyntaxError("JSON.parse");
        };
    }
}());

/*!
 Math.uuid.js (v1.4)
 http://www.broofa.com
 mailto:robert@broofa.com

 Copyright (c) 2009 Robert Kieffer
 Dual licensed under the MIT and GPL licenses.

 Usage: Math.uuid()
 */
Math.uuid = (function ()
{
    // Private array of chars to use
    var CHARS = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz".split("");

    return function (len, radix)
    {
        var chars = CHARS, uuid = [];
        radix     = radix || chars.length;

        if (len)
        {
            // Compact form
            for (var i = 0; i < len; i++)
            {
                uuid[i] = chars[0 | Math.random() * radix];
            }
        }
        else
        {
            // rfc4122, version 4 form
            var r;

            // rfc4122 requires these characters
            uuid[8]  = uuid[13] = uuid[18] = uuid[23] = "-";
            uuid[14] = "4";

            // Fill in random data.  At i==19 set the high bits of clock sequence as
            // per rfc4122, sec. 4.1.5
            for (var i = 0; i < 36; i++)
            {
                if (!uuid[i])
                {
                    r       = 0 | Math.random() * 16;
                    uuid[i] = chars[(i == 19) ? (r & 0x3) | 0x8 : r];
                }
            }
        }

        return uuid.join("");
    };
})();

/*
 * Courtesy of PHPjs -- http://phpjs.org
 * @license GPL, version 2
 */
function basename(path, suffix)
{
    var b = path.replace(/^.*[\/\\]/g, "");
    if (typeof (suffix) == "string" && b.substr(b.length - suffix.length) == suffix)
    {
        b = b.substr(0, b.length - suffix.length);
    }
    return b;
}

function number_format(number, decimals, dec_point, thousands_sep)
{
    var n = number, c = isNaN(decimals = Math.abs(decimals)) ? 2 : decimals;
    var d = dec_point == undefined ? "," : dec_point;
    var t = thousands_sep == undefined ? "." : thousands_sep, s = n < 0 ? "-" : "";
    var i = parseInt(n = Math.abs(+n || 0).toFixed(c)) + "", j = (j = i.length) > 3 ? j % 3 : 0;

    return s + (j ? i.substr(0, j) + t : "") + i.substr(j).replace(/(\d{3})(?=\d)/g, "$1" + t) + (c ? d + Math.abs(
        n - i).toFixed(c).slice(2) : "");
}

function size_format(filesize)
{
    if (filesize >= 1073741824)
    {
        filesize = number_format(filesize / 1073741824, 2, ".", "") + " GB";
    }
    else
    {
        if (filesize >= 1048576)
        {
            filesize = number_format(filesize / 1048576, 2, ".", "") + " MB";
        }
        else
        {
            filesize = number_format(filesize / 1024, 2, ".", "") + " KB";
        }
    }
    return filesize;
}

/**
 * Checks if a varriable is empty. From the php.js library.
 */
function empty(mixed_var)
{
    var key;

    if (mixed_var === "" || mixed_var === 0 || mixed_var === "0" || mixed_var === null || mixed_var === false || typeof mixed_var === "undefined")
    {
        return true;
    }

    if (typeof mixed_var == "object")
    {
        for (key in mixed_var)
        {
            return false;
        }
        return true;
    }

    return false;
}

function ltrim(str, charlist)
{
    // Strips whitespace from the beginning of a string
    //
    // version: 1008.1718
    // discuss at: http://phpjs.org/functions/ltrim    // +   original by: Kevin van Zonneveld
    // (http://kevin.vanzonneveld.net) +      input by: Erkekjetter +   improved by: Kevin van Zonneveld
    // (http://kevin.vanzonneveld.net) +   bugfixed by: Onno Marsman *     example 1: ltrim('    Kevin van Zonneveld
    // ');    // *     returns 1: 'Kevin van Zonneveld    '
    charlist = !charlist ? " \\s\u00A0" : (charlist + "").replace(/([\[\]\(\)\.\?\/\*\{\}\+\$\^\:])/g, "$1");
    var re   = new RegExp("^[" + charlist + "]+", "g");
    return (str + "").replace(re, "");
}

function array_shift(inputArr)
{
    // http://kevin.vanzonneveld.net
    // +   original by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +   improved by: Martijn Wieringa
    // %        note 1: Currently does not handle objects
    // *     example 1: array_shift(['Kevin', 'van', 'Zonneveld']);
    // *     returns 1: 'Kevin'

    var props = false, shift = undefined, pr = "", allDigits = /^\d$/, int_ct = -1,
        _checkToUpIndices                                                     = function (arr, ct, key)
        {
            // Deal with situation, e.g., if encounter index 4 and try to set it to 0, but 0 exists later in loop (need
            // to increment all subsequent (skipping current key, since we need its value below) until find unused)
            if (arr[ct] !== undefined)
            {
                var tmp = ct;
                ct += 1;
                if (ct === key)
                {
                    ct += 1;
                }
                ct      = _checkToUpIndices(arr, ct, key);
                arr[ct] = arr[tmp];
                delete arr[tmp];
            }
            return ct;
        };


    if (inputArr.length === 0)
    {
        return null;
    }
    if (inputArr.length > 0)
    {
        return inputArr.shift();
    }
}

function trim(str, charlist)
{
    var whitespace, l = 0, i = 0;
    str += "";

    if (!charlist)
    {
        // default list
        whitespace =
            " \n\r\t\f\x0b\xa0\u2000\u2001\u2002\u2003\u2004\u2005\u2006\u2007\u2008\u2009\u200a\u200b\u2028\u2029\u3000";
    }
    else
    {
        // preg_quote custom list
        charlist += "";
        whitespace = charlist.replace(/([\[\]\(\)\.\?\/\*\{\}\+\$\^\:])/g, "$1");
    }

    l = str.length;
    for (i = 0; i < l; i++)
    {
        if (whitespace.indexOf(str.charAt(i)) === -1)
        {
            str = str.substring(i);
            break;
        }
    }

    l = str.length;
    for (i = l - 1; i >= 0; i--)
    {
        if (whitespace.indexOf(str.charAt(i)) === -1)
        {
            str = str.substring(0, i + 1);
            break;
        }
    }

    return whitespace.indexOf(str.charAt(0)) === -1 ? str : "";
}

function array_merge()
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
}

function array_diff(arr1)
{ // eslint-disable-line camelcase
    //  discuss at: http://locutus.io/php/array_diff/
    // original by: Kevin van Zonneveld (http://kvz.io)
    // improved by: Sanjoy Roy
    //  revised by: Brett Zamir (http://brett-zamir.me)
    //   example 1: array_diff(['Kevin', 'van', 'Zonneveld'], ['van', 'Zonneveld'])
    //   returns 1: {0:'Kevin'}

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
}

//=============================================================================
// Object.keys polyfill
//=============================================================================

// From https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Object/keys
if (!Object.keys)
{
    Object.keys = (function ()
    {
        "use strict";
        var hasOwnProperty                                                                   = Object.prototype.hasOwnProperty,
            hasDontEnumBug = !({toString: null}).propertyIsEnumerable("toString"), dontEnums = [
                "toString",
                "toLocaleString",
                "valueOf",
                "hasOwnProperty",
                "isPrototypeOf",
                "propertyIsEnumerable",
                "constructor"
            ], dontEnumsLength                                                               = dontEnums.length;

        return function (obj)
        {
            if (typeof obj !== "object" && (typeof obj !== "function" || obj === null))
            {
                throw new TypeError("Object.keys called on non-object");
            }

            var result = [], prop, i;

            for (prop in obj)
            {
                if (hasOwnProperty.call(obj, prop))
                {
                    result.push(prop);
                }
            }

            if (hasDontEnumBug)
            {
                for (i = 0; i < dontEnumsLength; i++)
                {
                    if (hasOwnProperty.call(obj, dontEnums[i]))
                    {
                        result.push(dontEnums[i]);
                    }
                }
            }
            return result;
        };
    }());
}

/**
 * Is the variable an array?
 *
 * Part of php.js
 *
 * @see  http://phpjs.org/
 *
 * @param   mixed_var  {mixed}  The variable
 *
 * @returns  boolean  True if it is an array or an object
 */
function is_array(mixed_var)
{
    var key         = "";
    var getFuncName = function (fn)
    {
        var name = (/\W*function\s+([\w\$]+)\s*\(/).exec(fn);

        if (!name)
        {
            return "(Anonymous)";
        }

        return name[1];
    };

    if (!mixed_var)
    {
        return false;
    }

    // BEGIN REDUNDANT
    this.php_js     = this.php_js || {};
    this.php_js.ini = this.php_js.ini || {};
    // END REDUNDANT

    if (typeof mixed_var === "object")
    {
        if (this.php_js.ini["phpjs.objectsAsArrays"] &&  // Strict checking for being a JavaScript array (only check this way if
            // call ini_set('phpjs.objectsAsArrays', 0) to disallow objects as arrays)
            ((this.php_js.ini["phpjs.objectsAsArrays"].local_value.toLowerCase && this.php_js.ini["phpjs.objectsAsArrays"].local_value.toLowerCase() === "off") || parseInt(
                this.php_js.ini["phpjs.objectsAsArrays"].local_value, 10) === 0))
        {
            return mixed_var.hasOwnProperty("length") && // Not non-enumerable because of being on parent class
                !mixed_var.propertyIsEnumerable("length") && // Since is own property, if not enumerable, it must be a
                // built-in function
                getFuncName(mixed_var.constructor) !== "String"; // exclude String()
        }

        if (mixed_var.hasOwnProperty)
        {
            for (key in mixed_var)
            {
                // Checks whether the object has the specified property
                // if not, we figure it's not an object in the sense of a php-associative-array.
                if (false === mixed_var.hasOwnProperty(key))
                {
                    return false;
                }
            }
        }

        // Read discussion at: http://kevin.vanzonneveld.net/techblog/article/javascript_equivalent_for_phps_is_array/
        return true;
    }

    return false;
}

function escapeHTML(rawData)
{
    return rawData.split("&").join("&amp;").split("<").join("&lt;").split(">").join("&gt;");
}
