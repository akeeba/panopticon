<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Enumerations\CMSType;
use Awf\Mvc\Model;
use Complexify\Complexify;
use DateTimeZone;
use Exception;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;
use Throwable;

/**
 * System Configuration Model
 *
 * @since  1.0.0
 */
class Sysconfig extends Model
{
	private const EXCLUDED_EXTENSIONS = [
		'mod_contacts', 'phpass', 'phputf8', 'plg_captcha_recaptcha_invisible', 'plg_com_com_ats', 'plg_multifactorauth_email', 'plg_multifactorauth_fixed', 'plg_multifactorauth_totp', 'plg_multifactorauth_webauthn', 'plg_multifactorauth_yubikey', 'plg_sampledata_testing', 'plg_schemaorg_blogposting', 'plg_schemaorg_book', 'plg_schemaorg_book', 'plg_schemaorg_event', 'plg_schemaorg_jobposting', 'plg_schemaorg_organization', 'plg_schemaorg_person', 'plg_schemaorg_recipe', 'plg_system_schedulerunner', 'plg_system_schemaorg', 'plg_system_shortcut', 'plg_task_checkfiles', 'plg_task_demotasks', 'plg_task_globalcheckin', 'plg_task_requests', 'plg_task_sitestatus', 'plg_webservices_installer', 'plg_workflow_featuring', 'plg_workflow_notification', 'plg_workflow_publishing', 'tpl_protostar',
	];

	public function isExcludedShortname(string $shortname): bool
	{
		return in_array($shortname, self::EXCLUDED_EXTENSIONS);
	}

	public function validateValue(string $key, $value): bool
	{
		$complexify = new Complexify();

		return match ($key)
		{
			// System
			'session_timeout'       => filter_var($value, FILTER_VALIDATE_INT) && $value > 1,
			'timezone'              => in_array($value, DateTimeZone::listIdentifiers()),
			'debug'                 => filter_var($value, FILTER_VALIDATE_BOOL),
			'error_reporting'       => in_array($key, ['default', 'none', 'simple', 'maximum',]),
			'finished_setup'        => filter_var($value, FILTER_VALIDATE_BOOL),

			// Display
			'darkmode'              => filter_var($value, FILTER_VALIDATE_INT) && in_array($value, [1, 2, 3]),
			'fontsize'              => filter_var($value, FILTER_VALIDATE_INT) && $value >= 8 && $value <= 48,

			// Automation
			'webcron_key'           => $complexify->evaluateSecurity($value)->valid,
			'cron_stuck_threshold'  => filter_var($value, FILTER_VALIDATE_INT) && $value >= 3,
			'max_execution'         => filter_var($value, FILTER_VALIDATE_INT) && $value >= 5 && $value <= 3600,
			'execution_bias'        => filter_var($value, FILTER_VALIDATE_INT) && $value >= 15 && $value <= 100,

			// Site Operations
			'siteinfo_freq'         => filter_var($value, FILTER_VALIDATE_INT) && $value >= 15 && $value <= 1440,

			// Caching
			'caching_time'          => filter_var($value, FILTER_VALIDATE_INT) && $value >= 1 && $value <= 527040,
			'cache_adapter'         => in_array($value, ['filesystem', 'linuxfs', 'db', 'memcached', 'redis',]),
			'caching_redis_dsn'     => true,
			'caching_memcached_dsn' => true,

			// Logging
			'log_level'             => in_array($value,
				['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug']),
			'log_rotate_compress'   => filter_var($value, FILTER_VALIDATE_BOOL),
			'log_rotate_files'      => filter_var($value, FILTER_VALIDATE_INT) && $value >= 0 && $value <= 100,
			'log_backup_threshold'  => filter_var($value, FILTER_VALIDATE_INT) && $value >= 0 && $value <= 100,

			// Database
			'dbdriver'              => in_array($value, ['mysqli', 'pdomysql']),
			'dbhost'                => true,
			'dbuser'                => true,
			'dbpass'                => true,
			'dbname'                => true,
			'dbprefix'              => !empty($value) && preg_match('#^[a-zA-Z0-9_]{1,6}_$#', $value),
			'dbencryption'          => filter_var($value, FILTER_VALIDATE_BOOL),
			'dbsslca'               => empty($value) || (is_file($value) && is_readable($value)),
			'dbsslkey'              => empty($value) || (is_file($value) && is_readable($value)),
			'dbsslcert'             => empty($value) || (is_file($value) && is_readable($value)),
			'dbsslverifyservercert' => filter_var($value, FILTER_VALIDATE_BOOL),

			// Email
			'mail_online'           => filter_var($value, FILTER_VALIDATE_BOOL),
			'mailer'                => in_array($value, ['mail', 'sendmail', 'smtp']),
			'mailfrom'              => empty($value) || filter_var($value, FILTER_VALIDATE_EMAIL),
			'fromname'              => true,
			'smtphost'              => true,
			'smtpport'              => is_integer($value) && ($value >= 1) && ($value <= 65535),
			'smtpsecure'            => in_array($value, ['none', 'ssl', 'tls']),
			'smtpauth'              => filter_var($value, FILTER_VALIDATE_BOOL),
			'smtpuser'              => true,
			'smtppass'              => true,

			// Anything else, we don't know what it is.
			default                 => false,
		};
	}

	/**
	 * Returns a list of known extensions, their basic metadata, and their preferences
	 *
	 * @param   int|null  $siteId
	 * @param   bool      $force
	 *
	 * @return  array
	 * @throws  InvalidArgumentException
	 */
	public function getExtensionPreferencesAndMeta(?int $siteId = null, bool $force = false): array
	{
		/** @var CacheInterface $cachePool */
		$cachePool = $this->container->cacheFactory->pool('extensions');

		$key  = empty($siteId) ? 'all' : "site.$siteId";
		$beta = $force ? INF : null;

		return $cachePool->get($key, function () use ($siteId): array
		{
			$preferences = $this->getExtensionUpdatePreferences($siteId);

			$db    = $this->container->db;
			$query =
				$db->getQuery(true)
					->select($db->quoteName('config'))
					->from($db->quoteName('#__sites'))
					->where($db->quoteName('enabled') . ' = 1');

			if (!empty($siteId))
			{
				$query->where($db->quoteName('id') . ' = ' . (int) $siteId);
			}

			$iterator = $db->setQuery($query)->getIterator();

			if (!count($iterator))
			{
				return [];
			}

			$extensions = [];

			foreach ($iterator as $item)
			{
				try
				{
					$config = @json_decode($item->config);
				}
				catch (Exception $e)
				{
					continue;
				}

				if (empty($config))
				{
					continue;
				}

				$cmsType = $config?->cmsType ?? CMSType::JOOMLA->value;

				foreach ($config?->extensions?->list ?? [] as $ext)
				{
					switch ($cmsType)
					{
						default:
						case CMSType::UNKNOWN->value:
							continue 2;

						case CMSType::WORDPRESS->value:
							$extKey = (($ext->type ?? null) === 'plugin' ? 'plg_' : 'tpl_')
								. ($ext->extension_id ?? '');

							if (empty($ext->extension_id ?? ''))
							{
								continue 2;
							}

							if (($ext->element ?? '') === 'panopticon.php')
							{
								$preferences[$extKey] = 'major';
							}

							$ext->client_id = 0;

							break;

						case CMSType::JOOMLA->value:
							$extKey = $this->getExtensionShortname($ext->type, $ext->element, $ext->folder, $ext->client_id);

							if (empty($extKey) || in_array($extKey, self::EXCLUDED_EXTENSIONS))
							{
								continue 2;
							}

							if ($ext->element === 'pkg_panopticon')
							{
								$preferences[$extKey] = 'major';
							}

							break;
					}

					$extensions[$extKey] = (object)[
						'cmsType'     => $cmsType,
						'element'     => $ext->element,
						'type'        => $ext->type,
						'folder'      => $ext->folder,
						'client'      => $ext->client_id,
						'name'        => $ext->name ?: $extKey,
						'author'      => $ext->author,
						'authorUrl'   => $ext->authorUrl,
						'authorEmail' => $ext->authorEmail,
						'preference'  => $preferences[$extKey] ?? '',
					];
				}
			}

			uasort($extensions, function($a, $b) {
				if ($a->cmsType !== $b->cmsType)
				{
					return $a->cmsType <=> $b->cmsType;
				}

				return $a->name <=> $b->name;
			});

			return $extensions;
		}, $beta);
	}

	/**
	 * Save the new extension preferences to the database.
	 *
	 * @param   array     $data    The data we're saving in the form [ ['extension'=>'preference'], ... ].
	 * @param   int|null  $siteId  The site ID for which we are saving preferences
	 *
	 * @return  void
	 */
	public function saveExtensionPreferences(array $data, ?int $siteId = null): void
	{
		// Filter the data values so they make sense
		$data = array_filter(
			$data,
			fn($v) => is_string($v) && in_array($v, ['', 'email', 'none', 'major', 'minor', 'patch'])
		);

		// Filter the data keys
		$data = array_filter(
			$data,
			[$this, 'isvalidShortname'],
			ARRAY_FILTER_USE_KEY
		);

		if (empty($data))
		{
			return;
		}

		// Merge the incoming data with the current settings
		$currentSettings = $this->getExtensionUpdatePreferences($siteId);
		$data            = array_merge($currentSettings, $data);

		// Save into the database
		$key   = $this->getExtensionsPreferencesKey($siteId);
		$db    = $this->container->db;
		$query = $db
			->getQuery(true)
			->replace($db->quoteName('#__akeeba_common'))
			->columns([
				$db->quoteName('key'),
				$db->quoteName('value'),
			])
			->values(
				$db->quote($key) . ',' .
				$db->quote(json_encode($data))
			);

		$db->setQuery($query)->execute();

		// Finally, bust the cache
		$cacheKey = empty($siteId) ? 'all' : "site.$siteId";
		$this->container->cacheFactory->pool('extensions')->delete($cacheKey);
	}

	/**
	 * Get the extension short name given a number of qualifiers.
	 *
	 * This is mostly, but not exactly, the same as what Joomla itself does. The difference is that admin templates and
	 * modules get an `a` prefix. For example, `amod_example` is an **administrator** module, whereas `mod_example` is
	 * a **site** module.
	 *
	 * The idea is that every installed extension gets a unique, deterministic, global identifier.
	 *
	 * This is the full list of supported extension types:
	 * - `pkg_something` Package type extension.
	 * - `com_something` Component.
	 * - `plg_folder_something` Plugin.
	 * - `mod_something` Site module.
	 * - `amod_something` Administrator module. **This is custom**.
	 * - `file_something` File type extension.
	 * - `lib_something` Library type extension.
	 * - `tpl_something` Site template.
	 * - `atpl_something` Administrator template. **This is custom**.
	 *
	 * @param   string       $type       The extension type. One of component, file, library, plugin, module, or
	 *                                   template.
	 * @param   string       $element    The extension's element key in the site's `#__extensions` database table.
	 * @param   string|null  $folder     The plugin folder. Only necessary for plugins.
	 * @param   int          $client_id  The application ID (0 = administrator, 1 = site). Only for module, template.
	 *
	 * @return  string|null
	 */
	public function getExtensionShortname(string $type, string $element, ?string $folder, int $client_id): ?string
	{
		return match ($type)
		{
			'component', 'file', 'files', 'library', 'package' => $element,
			'plugin' => 'plg_' . ($folder ?? 'unknown') . '_' . $element,
			'module' => ($client_id === 0 ? 'a' : '') . $element,
			'template' => ($client_id === 0 ? 'atpl_' : 'tpl_') . $element,
			default => null,
		};
	}

	/**
	 * Is this a valid short name for a Joomla! extension?
	 *
	 * @param   string  $shortname  The shortname to check.
	 *
	 * @return  bool
	 */
	public function isvalidShortname(string $shortname): bool
	{
		if (!str_contains($shortname, '_'))
		{
			return false;
		}

		$parts = explode('_', $shortname, 3);

		if (!in_array($parts[0], ['pkg', 'com', 'plg', 'amod', 'mod', 'file', 'lib', 'tpl', 'atpl']))
		{
			return false;
		}

		$noEmptyparts = array_reduce(
			$parts,
			fn(bool $carry, $item) => $carry && (is_string($item) && !empty($item)),
			true
		);

		switch ($parts[0])
		{
			case 'pkg':
			case 'com':
			case 'file':
			case 'lib':
			case 'mod':
			case 'amod':
			case 'tpl':
			case 'atpl':
				return count($parts) >= 2 && $noEmptyparts;

			case 'plg':
				return count($parts) >= 3 && $noEmptyparts;
		}

		return false;
	}

	public function getUptimeOptions()
	{
		$results = $this->getContainer()->eventDispatcher->trigger('onGetUptimeProvider');
		$results = array_filter($results, fn($x) => is_array($x) && !empty($x));

		return array_reduce(
			$results,
			fn(array $carry, array $item) => array_merge($carry, $item),
			[
				'none' => 'PANOPTICON_SYSCONFIG_OPT_UPTIME_NONE',
			]
		);
	}

	/**
	 * Returns the extension update preferences, global or per site.
	 *
	 * @param   int|null  $siteId  The site for which to get the preferences; NULL for global preferences.
	 * @param   bool      $force   Should I force-reload the list of extensions, ignoring the cache?
	 *
	 * @return  array
	 */
	private function getExtensionUpdatePreferences(?int $siteId = null): array
	{
		$db    = $this->container->db;
		$query =
			$db->getQuery(true)->select($db->quoteName('value'))->from($db->quoteName('#__akeeba_common'))->where($db->quoteName('key') . ' = ' . $db->quote($this->getExtensionsPreferencesKey($siteId)));

		$json = $db->setQuery($query)->loadResult() ?? '{}';

		try
		{
			$decoded = @json_decode($json, true);
		}
		catch (Throwable $e)
		{
			$decoded = null;
		}

		return $decoded ?: [];
	}

	/**
	 * Returns the `#__akeeba_common` key for extension update preferences
	 *
	 * @param   int|null  $siteId  The site ID; null for the global preferences
	 *
	 * @return  string  The key for the `#__akeeba_common` table.
	 */
	private function getExtensionsPreferencesKey(?int $siteId): string
	{
		return 'extensions.' . (empty($siteId) ? 'all' : "site.{$siteId}");
	}
}