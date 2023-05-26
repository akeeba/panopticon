# Advanced Customisation (User Code) 

You can customise Akeeba Panopticon with your own executable PHP code _without_ modifying the core application itself. Modifying the core application is a discouraged practice colloquially referred to as “core hacking”.

All of your code must be placed in the `user_code` folder, under the main directory of Akeeba Panopticon.

## The `bootstrap.php` file

The file `bootstrap.php` in the `user_code` folder will be loaded at the end of the regular application bootstrap code found in `includes/bootstrap.php`. The variable `$config` will be defined in it, an instance of the `AConfig` configuration class (in other words, your Panopticon installation's `config.php`).

**❗️IMPORTANT**: The `bootstrap.php` file in this folder will _only_ be included if you have finished configuring Akeeba Panopticon, i.e. there is a `config.php` file at the root folder of your installation.

**❗️IMPORTANT**: The `bootstrap.php` file is loaded in _all_ execution contexts: web and CLI. Do not assume that you have a STDOUT / STDERR as these may not be available in the web context. Do not assume you can grab an HTML document and modify it as this will not work in the CLI context.

## What you can do with `user_code/bootstrap.php`

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

**⚠️ WARNING**: If you subclass, or completely replace, `\Akeeba\Panopticon\Container` make sure to check your code in each minor version (when the y component of the version number x.y.z changes) to ensure that you are not breaking expected behaviour. 

### Override the application

Define the following function:

```php
function user_get_application(): \Akeeba\Panopticon\Application
```

You can either configure the regular `\Akeeba\Panopticon\Application` object, or return an instance of a class extending from `\Akeeba\Panopticon\Application`.

**⚠️ WARNING**: If you subclass, or completely replace, `\Akeeba\Panopticon\Application` make sure to check your code on _every_ new version to ensure that you are not breaking expected behaviour. 

### Decorate (or provide alternative handles for) tasks

You can modify the handling of a task type, or register your own custom task types, by declaring the function 
`user_decorate_task` with the following signature:

```php
function user_decorate_task(
    ?string $taskType,
    ?\Akeeba\Panopticon\Library\Task\CallbackInterface $callback
): \Akeeba\Panopticon\Library\Task\CallbackInterface
```

The `$taskType` parameter is a string, communicating the task type stored in the `#__tasks` table in the database. The `$callback` parameters contains the currently registered callback (typically, an instance of a callable class) for this task type.

You can either modify the existing callback, or replace it with a custom one.

**⚠️ WARNING**: If you subclass, or completely replace, an existing task handler make sure to check your code on _every_ new version to ensure that you are not breaking expected behaviour.

### Extend the loggers

Akeeba Panopticon uses [Monolog](http://seldaek.github.io/monolog/) for logging. The logger instances are created by a logger factory service object which is accessible through the Container object.

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

A web agency with hundreds of sites can use this kind of customisation to push Akeeba Panopticon's log messages to a log aggregator service. This could be used, for example, to raise alerts if there are a bunch of sites running Joomla versions with critical vulnerabilities so that you can prioritise them for upgrades — without even having to visit Panopticon's web interface.

### Replace the loggers

We already discussed how Akeeba Panopticon uses Monolog for logging. Well, this is the default implementation but not the only possibility. The logger factor service returns objects adhering to the [PSR-3](https://www.php-fig.org/psr/psr-3/) specification's `\Psr\Log\LoggerInterface` interface and Panopticon's code only expects that logger objects adhere to the `\Psr\Log\LoggerInterface` of version 3 of the PSR-3 specification. Therefore, you can of course replace Monolog with any logging interface you want.

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

As you can see, we overwrote the `$logger` variable passed to our callback with a new logger. This will still work fine with Akeeba Panopticon because all of its log consumers expect a PSR-3 object, _not_ a Monolog object.

## Overriding the output

The output of the application is generated similarly to Joomla! itself: using view templates. Unlike Joomla, the view templates are not plain .php file. They use a template language called Blade. These files are “compiled” into regular `.php` files, therefore you can add normal PHP code as well.

The original files can be found in the `ViewTemplates` folder. Each subfolder in there corresponds to the `view=...` part of a URL.

You can create your won `ViewTemplates` folder inside the `user_code` folder to _override_ the view templates shipped with Panopticon. For example, you can copy `ViewTemplates/main/default.blade.php` into `user_code/ViewTemplates/main/default.blade.php` to customise Panopticon's main page.

**Tip**: If you do not see your changes taking effect immediately, delete the contents of the `tmp/compiled_templates` folder.

## Overriding core code (NOT RECOMMENDED — DANGER)

The `user_code` folder acts as a secondary root for the `Akeeba\Panopticon` namespace, the primary location being the `src` folder. If you need to modify the core code in Panopticon you can copy the respective file from its location under the `src` folder into the same location under the `user_code` folder and modify your copy.

For example, let's say you want to modify the `\Akeeba\Panopticon\Application\Configuration` class to use [DotEnv](https://github.com/vlucas/phpdotenv) to load configuration overrides from a `.env` file. To do so, copy the file `src/Application/Configuration.php` into `user_code/Application/Configuration.php`. You can now edit your `user_code/Application/Configuration.php` file to accomplish your goal.

**MAJOR DANGER! IT IS YOUR RESPONSIBILITY TO UPDATE YOUR CORE CODE OVERRIDES WITH EVERY RELEASE OF AKEEBA PANOPTICON. FAILURE TO DO SO WILL MOST DEFINITELY RESULT IN A BROKEN INSTALLATION. YOU HAVE BEEN WARNED.**

The real purpose of this feature is to allow you to create your own, new MVC views to add bespoke features to Panopticon.

In the vast majority of use cases where you need to make small modifications you don't really want to override core classes. You just need to hook into plugin events.

### Mind the license!

Akeeba Panopticon is released under the GNU Affero General Public License, version 3 of the license or, at your option, any later version of the license published by the Free Software Foundation.

Unlike the plain old GNU General Public License (GPL), the GNU Affero General Public License (AGPL) **requires** you to publish any and all code making use of AGPL software —including modifications to the software itself— under the AGPL license, for free, to anyone using your software. That's spelled out in article 13 of the license. In other words, if you try to create a custom site monitoring service using Panopticon you will need to provide the full source code of your service free of charge to anyone interacting with your service. Do remember that even the act of logging in is an interaction. Anything else violates the software license, and constitutes copyright infringement.

## Plugin events

Panopticon's container (which you can get by calling `\Akeeba\Panopticon\Factory::getContainer()`) has an _event dispatcher_ object: `$dispatcher = \Akeeba\Panopticon\Factory::getContainer()->eventDispatcher;`.

The event dispatcher is an object instance of the `\Awf\Event\Dispatcher` class. This is used throughout Panopticon and the framework it's using (Akeeba Web Framework, a.k.a. AWF — the very same framework we are using for Akeeba Solo and Akeeba Backup for WordPress since 2013). The various MVC classes raise events which can be handled by _observers_ known to the event dispatcher. This is the same as Joomla plugins, really. An “observer” is, essentially, a plugin.

The observer object must be a class extending from the `\Awf\Event\Observer` superclass. All public methods of the class are event handlers. 

You can define and register observers (plugins) like so:

```php
class MyPlugin extends \Awf\Event\Observer
{
    public onControllerBeforeBrowse(string $controllerName, Controller $controller): bool
    {
        if ($controllerName !== 'sites') {
            return true;
        }
        
        $controller->getView()->fooBar = 'Hello from the plugin event';
    }
}

\Akeeba\Panopticon\Factory::getContainer()
   ->eventDispatcher
   ->attach($observer);
```