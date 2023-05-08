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
    // What was Panopticon's last attempt to fetch this information from Joomla?
    "lastAttempt": 1683481398,
}
```
