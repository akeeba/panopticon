# Site Parameters

The `config` column of the `#__sites` table is used to store the configuration of the Joomla!™ site, as well as information collected about the site by automation tasks.

In the following document, the dot in key names represents a subkey. For example, the key name `foo.bar.baz` corresponds to the following JSON document and, in this example, has the string value of `something`:

```json
{
	"foo": {
		"bar": {
            "baz": "something"
		}
	}
}
```

## The `config` key

Controls how we connect and interact with this particular site.

### `config.apiKey`

The Joomla! API Token of a Joomla! Super User account.

This is required if the remote site has the “API Authentication - Web Services Joomla Token” and “User - Joomla API Token” plugins enabled.

This is the preferred connection method to a Joomla! site.

❗️**IMPORTANT**: Either this key, or the `config.username` and `config.password` pair, must be defined to be able to connect to the site. 

### `config.username` and `config.password`

The username and password of a Joomla! Super User account.

This is required if the remote site only has the “API Authentication - Web Services Basic Auth” plugin enabled.

This is an insecure connection method and should be avoided. In fact, the “API Authentication - Web Services Basic Auth” plugin should never be enabled.

❗️**IMPORTANT**: Either this key pair, or the `config.apiKey` key, must be defined to be able to connect to the site.

### Joomla Update configuration keys

```json5
{
    "config": {
        "core_update" : {
            // What should I do if an update is found? One of:
            // "" (use global), "none", "email", "patch", "minor", "major"
			"install": "",
            // When should the auto-update be scheduled for? One of "immediately", "time"
			"when": "immediately",
            // The time of day to install the auto-update when config.core_update.install = "time"
			"time" : {
                "hour": 0,
                "minute": 0,
            },
			"email": {
                // Email addresses to be CC'ed
                "cc": ""
            },
            // Send an email if the auto-update fails?
			"email_error": true,
            // Send an email if the auto-update succeeds?
			"email_after": true,
        }
    }
}
```

## The `core` key

Caches the information collected about core Joomla! and the server environment. It looks like this:

```json5
{
    // Currently installed Joomla version information
	"current": {
        "version": "4.3.0",
        "stability": "stable"
    },
    // Latest available version information
    "latest": {
		"version": "4.3.1",
		"stability": "stable"
    },
    // PHP version
    "php": "8.1.2",
    // Can this site be upgraded?
    "canUpgrade": true,
    // Sanity check: does the site have the files_joomla pseudo-extension installed?
	"extensionAvailable": true,
    // Sanity check: does the site have the core Joomla update site installed and enabled?
	"updateSiteAvailable": true,
    // How many hours does Joomla! cache the updates for?
	"maxCacheHours": 6,
    // What is the minimum update stability allowed?
	"minimumStability": "stable",
    // When did Joomla! last check for core updates?
	"lastUpdateTimestamp": 1682418579,
    // When was Panopticon's last attempt to fetch this information from Joomla (UNIX timestamp)?
    "lastAttempt": 1683481398,
    // Which version did Panopticon try to automatically install, or notified you about, or skipped over per config?
    "lastAutoUpdateVersion": "4.3.0", 
}
```

### The `extensions` key

It has two subkeys:
* `list` A list of installed top-level extensions. This means it does not include core or third party sub-extensions, which are part of another package.
* `lastAttempt` When was Panopticon's last attempt to fetch this information from Joomla (UNIX timestamp)?

Each extension in the `list` is keyed by its extension ID and contains an object with the following items:

```json5
{
	"extension_id": 217,
    // Extension name (human-readable) as reported by Joomla!.
	"name": "file_fof30",
    // Description, human-readable
	"description": "\n\t\t\n\t\tFramework-on-Framework (FOF) 3.x - The rapid application development framework for Joomla!.<br/>\n\t\t<b>WARNING</b>: This is NOT a duplicate of the FOF library already installed with Joomla!. It is a different version used by other extensions on your site. Do NOT uninstall either FOF package. If you do you will break your site.\n\t\t\n\t",
    // Extension type (component, module, plugin, template, package, file, library)
	"type": "file",
	// Plugin folder
	"folder": "",
	// Extension element (depends on extension type)
	"element": "file_fof30",
    // Joomla Application ID
	"client_id": 0,
    // Extension author
	"author": "Nicholas K. Dionysopoulos / Akeeba Ltd",
	// Extension author's URL
	"authorUrl": "https://www.akeebabackup.com",
	// Extension author's email address
	"authorEmail": "nicholas@akeebabackup.com",
    // Is the extension locked (core)?
	"locked": 0,
    // Is the extension protected?
	"protected": 0,
    // Is the extension published?
	"enabled": 1,
    // Version information
	"version": {
        // Currently installed
		"current": "revB061E1B9",
        // Latest available, if different
		"new": null,
	},
	// Extension type, human-readable
	"type_s": "File",
	// Application side, human-readable
	"client_s": "Site",
    // Plugin folder, human-readable
	"folder_s": "N/A",
    // Download Key information
	"downloadkey": {
        // Is a key required?
		"supported": false,
        // Is there a valid key present?
		"valid": false,
	},
    // Does the extension have any associated update sites?
	"hasUpdateSites": false
}
```