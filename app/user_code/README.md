# The `user_code` folder

Here you can place code specific to your installation, to further customise Akeeba Panopticon to your liking.

## Your `bootstrap.php` file

The file `bootstrap.php` in this folder will be loaded at the end of the regular application bootstrap code found in `includes/bootstrap.php`. The variable `$config` will be defined in it, an instance of the `AConfig` configuration class.

**IMPORTANT!** The `bootstrap.php` file in this folder will _only_ be included if you have finished configuring Akeeba Panopticon, i.e. there is a `config.php` file at the root folder of your installation.

**IMPORTANT!** The `bootstrap.php` file is loaded in all execution contexts: web and CLI. Do not assume that you have a STDOUT / STDERR as these may not be available in the web context. Do not assume you can grab an HTML document and modify it as this will not work in the CLI context.

## What you can do with it

### Load your own Composer dependencies

Create your own `composer.json` in the `user_code` folder. You can then load your custom Composer dependencies by adding the following code to your `bootstrap.php` file:

```php
require_once APATH_USER_CODE . '/vendor/autoload.php';
```

### Override the container

Define the following function:

```php
function user_get_container(): \Akeeba\Panopticon\Container
```

You can either configure the regular `\Akeeba\Panopticon\Container` object by passing it configuration variables, or return an instance of a class extending from `\Akeeba\Panopticon\Container`.

### Override the application

Define the following function:

```php
function user_get_application(): \Akeeba\Panopticon\Application
```

You can either configure the regular `\Akeeba\Panopticon\Application` object, or return an instance of a class extending from `\Akeeba\Panopticon\Application`.

### Handle / decorate CLI tasks

```php
function user_decorate_cli_task(
    \Akeeba\Panopticon\Library\Task\CallbackInterface $callback
): \Akeeba\Panopticon\Library\Task\CallbackInterface
```


### Extend the loggers

Akeeba Panopticon uses Monolog for logging. The logger instances are created by a logger factory service object which is accessible through the Container object.

The logger factory service object allows you to define callbacks which can be used to (re)configure the logger objects.

For example, you can add this to your bootstrap.php to send all log messages to the system log (syslog) and stop logging anything to files:

```php
\Akeeba\Panopticon\Factory::getContainer()
    ->loggerFactory
    ->addCallback(
        function (\Psr\Log\LoggerInterface $logger) {
            if (!$logger instanceof \Monolog\Logger) {
                return;
            }
            
            $logger->setHandlers([
                new \Monolog\Handler\SyslogHandler(
                    'panopticon_' . $logger->getName(),
                    LOG_USER,
                    \Monolog\Level::Debug
                )
            ]);
        }
    );
```

A web agency with hundreds of sites can use this trick to push Akeeba Panopticon's log messages to a log aggregator service. This could be used, for example, to raise alerts if there are a bunch of sites running Joomla versions with critical vulnerabilities so that you can prioritise them for upgrades — without even having to visit Panopticon's web interface.

### Replace the loggers

We already discussed how Akeeba Panopticon uses Monolog for logging. Well, this is not the entire truth of it. The logger factor service _technically_ returns objects adhering to the [PSR-3](https://www.php-fig.org/psr/psr-3/) specification's `\Psr\Log\LoggerInterface` interface. You can of course replace Monolog with any logging interface you want.

For example, let's say you want to use [Analog](https://packagist.org/packages/analog/analog), another popular logging framework, with its PDO driver to log into a SQLite database — which, by the way, is not recommended due to the volume of log entries produced in debug mode. But, hey, this is an example!

First, create a `composer.json` inside your `user_code` directory with the following contents:

```json
{
	"name": "johndoe/panopticon_user_code",
	"type": "project",
	"require": {
		"php": ">=8.1",
        "analog/analog": "^1.10"
	}
}
```

Install the dependencies by running `composer install` from inside your `user_code` folder.

Create a `bootstrap.php` file with the following contents:

```php
defined('AKEEBA') || die;

require_once APATH_USER_CODE . '/vendor/autoload.php';

\Akeeba\Panopticon\Factory::getContainer()
    ->loggerFactory
    ->addCallback(
        function (\Psr\Log\LoggerInterface $logger) {
            $pdo = new PDO ('sqlite:' . APATH_USER_CODE . '/logs.sqlite', '', '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ
            ]);
            
            $table = 'logs';

            Analog\Handler\PDO::createTable($pdo, $table);
            
            $logger = new Analog\Logger();
            $logger->handler(Analog\Handler\PDO::init($pdo, $table));
        });
```

As you can see, we overwrote the `$logger` variable passed to our callback with a new logger. This will still work fine with Akeeba Panopticon because all of its log consumers expect a PSR-3 object, _not_ a Monolog logger.