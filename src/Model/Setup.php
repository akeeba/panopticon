<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Container;
use Akeeba\Panopticon\Library\Task\Status;
use Awf\Database\Driver;
use Awf\Database\Installer;
use Awf\Mvc\Model;
use Awf\Registry\Registry;
use Complexify\Complexify;
use Delight\Alphabets\Alphabet;
use Delight\Random\Random;
use Exception;
use PDO;
use RuntimeException;
use stdClass;

/**
 * Setup Model
 *
 * @since  1.0.0
 */
class Setup extends Model
{
	private const DEFAULT_TASKS = [
		'logrotate'                  => '@daily',
		'databasebackup'             => '@daily',
		'ssltlsexpiration'           => '@daily',
		'domainexpiration'           => '0 0 * * 1',
		'usagestats'                 => '0 */12 * * *',
		'selfupdatefinder'           => '0 */6 * * *',
		'refreshsiteinfo'            => '*/15 * * * *',
		'refreshinstalledextensions' => '*/15 * * * *',
		'joomlaupdatedirector'       => '*/3 * * * *',
		'extensionupdatesdirector'   => '*/10 * * * *',
		'sendmail'                   => '* * * * *',
		'uptimemonitor'              => '* * * * *$-1',
		'wordpressupdatedirector'    => '*/3 * * * *',
		'pluginupdatesdirector'      => '*/10 * * * *',
		'registrationcleanup'        => '@daily',
	];

	private static bool|null $isRequiredMet = null;

	private static bool|null $isRecommendedMet = null;

	public function isRequiredMet(): bool
	{
		if (is_bool(self::$isRequiredMet))
		{
			return self::$isRequiredMet;
		}

		$required = $this->getRequired();

		self::$isRequiredMet = array_reduce(
			$required,
			fn(bool $carry, array $setting
			) => $carry && (($setting['warning'] ?? false) || ($setting['current'] ?? false)),
			true
		);

		return self::$isRequiredMet;
	}

	public function isRecommendedMet(): bool
	{
		if (is_bool(self::$isRecommendedMet))
		{
			return self::$isRecommendedMet;
		}

		$required = $this->getRecommended();

		self::$isRecommendedMet = array_reduce(
			$required,
			fn(bool $carry, array $setting) => $carry && ($setting['current'] == $setting['recommended']),
			true
		);

		return self::$isRecommendedMet;
	}

	public function getRequired(): array
	{
		static $phpOptions = [];

		if (empty($phpOptions))
		{
			$minPHPVersion = AKEEBA_PANOPTICON_MINPHP;

			$phpOptions[] = [
				'label'   => $this->getLanguage()->sprintf('PANOPTICON_SETUP_LBL_REQ_PHP_VERSION', $minPHPVersion),
				'current' => version_compare(phpversion(), $minPHPVersion, 'ge'),
				'warning' => false,
			];

			$phpOptions[] = [
				'label'   => $this->getLanguage()->text('PANOPTICON_SETUP_LBL_REQ_REGGLOBALS'),
				'current' => (ini_get('register_globals') == false),
				'warning' => false,
			];

			$phpOptions[] = [
				'label'   => $this->getLanguage()->text('PANOPTICON_SETUP_LBL_REQ_CURL'),
				'current' => extension_loaded('curl'),
				'warning' => false,
			];

			$phpOptions[] = [
				'label'   => $this->getLanguage()->text('PANOPTICON_SETUP_LBL_REQ_XML'),
				'current' => extension_loaded('xml'),
				'warning' => false,
			];

			$phpOptions[] = [
				'label'   => $this->getLanguage()->text('PANOPTICON_SETUP_LBL_REQ_DATABASE'),
				'current' => (
					// MySQLi functions
					function_exists('mysqli_connect')
					|| // PDO MySQL
					(class_exists('\\PDO') && in_array('mysql', PDO::getAvailableDrivers()))
				),
				'warning' => false,
			];

			if (extension_loaded('mbstring'))
			{
				$option           = [
					'label'   => $this->getLanguage()->text('PANOPTICON_SETUP_LBL_REQ_MBLANGISDEFAULT'),
					'current' => (strtolower(ini_get('mbstring.language')) == 'neutral'),
					'warning' => false,
				];
				$option['notice'] = $option['current'] ? null : $this->getLanguage()->text('PANOPTICON_SETUP_MSG_NOTICEMBLANGNOTDEFAULT');
				$phpOptions[]     = $option;

				$option           = [
					'label'   => $this->getLanguage()->text('PANOPTICON_SETUP_LBL_REQ_MBSTRINGOVERLOAD'),
					'current' => (ini_get('mbstring.func_overload') == 0),
					'warning' => false,
				];
				$option['notice'] = $option['current'] ? null : $this->getLanguage()->text('PANOPTICON_SETUP_MSG_NOTICEMBSTRINGOVERLOAD');
				$phpOptions[]     = $option;
			}

			$phpOptions[] = [
				'label'   => $this->getLanguage()->text('PANOPTICON_SETUP_LBL_REQ_INIPARSER'),
				'current' => $this->getIniParserAvailability(),
				'warning' => false,
			];

			$phpOptions[] = [
				'label'   => $this->getLanguage()->text('PANOPTICON_SETUP_LBL_REQ_JSON'),
				'current' => function_exists('json_encode') && function_exists('json_decode'),
				'warning' => false,
			];

			$configPath = $this->container->appConfig->getDefaultPath();

			$configWriteable = (@file_exists($configPath) && @is_writable($configPath))
				|| @is_writable(dirname($configPath));
			$phpOptions[]    = [
				'label'   => $this->getLanguage()->text('PANOPTICON_SETUP_LBL_REQ_CONFIGJSON'),
				'current' => $configWriteable,
				'notice'  => $configWriteable ? null : $this->getLanguage()->text('PANOPTICON_SETUP_MSG_CONFIGURATIONPHP'),
				'warning' => true,
			];
		}

		return $phpOptions;
	}

	public function getRecommended(): array
	{
		static $phpOptions = [];

		if (empty($phpOptions))
		{
			$phpOptions[] = [
				'label'       => $this->getLanguage()->text('PANOPTICON_SETUP_LBL_REC_DISPERRORS'),
				'current'     => (bool)ini_get('display_errors'),
				'recommended' => false,
			];

			$phpOptions[] = [
				'label'       => $this->getLanguage()->text('PANOPTICON_SETUP_LBL_REC_OUTBUF'),
				'current'     => (bool)ini_get('output_buffering'),
				'recommended' => false,
			];

			$phpOptions[] = [
				'label'       => $this->getLanguage()->text('PANOPTICON_SETUP_LBL_REC_SESSIONAUTO'),
				'current'     => (bool)ini_get('session.auto_start'),
				'recommended' => false,
			];

			$phpOptions[] = [
				'label'       => $this->getLanguage()->text('PANOPTICON_SETUP_LBL_REC_FTP'),
				'current'     => function_exists('ftp_connect'),
				'recommended' => true,
			];

			$phpOptions[] = [
				'label'       => $this->getLanguage()->text('PANOPTICON_SETUP_LBL_REC_SSH2'),
				'current'     => extension_loaded('ssh2') || extension_loaded('curl'),
				'recommended' => true,
			];

		}

		return $phpOptions;
	}

	public function getDatabaseParameters(): array
	{
		$session = $this->container->segment;

		$dbParameters = [
			'driver' => $session->get('db_driver', 'mysqli'),
			'host'   => $session->get('db_host', 'localhost'),
			'user'   => $session->get('db_user', ''),
			'pass'   => $session->get('db_pass', ''),
			'name'   => $session->get('db_name', ''),
			'prefix' => $session->get('db_prefix', 'pnptc_'),
			'ssl'    => $session->get('db_ssl', []),
		];

		$queryDbParameters = [
			'driver' => $this->input->get('driver', null, 'cmd'),
			'host'   => $this->input->get('host', null, 'raw'),
			'user'   => $this->input->get('user', null, 'raw'),
			'pass'   => $this->input->get('pass', null, 'raw'),
			'name'   => $this->input->get('name', null, 'raw'),
			'prefix' => $this->input->get('prefix', null, 'raw'),
			'ssl'    => [
				'enable'             => (bool)$this->input->getInt('dbencryption', 0),
				'cipher'             => $this->input->get('dbsslcipher', '', 'raw'),
				'ca'                 => $this->input->get('dbsslca', '', 'raw'),
				'capath'             => '',
				'key'                => $this->input->get('dbsslkey', '', 'raw'),
				'cert'               => $this->input->get('dbsslcert', '', 'raw'),
				'verify_server_cert' => (bool)$this->input->getInt('dbsslverifyservercert', 0),
			],
		];

		foreach ($queryDbParameters as $k => $v)
		{
			if (is_null($v))
			{
				continue;
			}

			if ($k === 'ssl')
			{
				if ($this->input->getInt('dbencryption', null) === null)
				{
					continue;
				}
			}

			$dbParameters[$k] = $v;
			$session->set('db_' . $k, $v);
		}

		return $dbParameters;
	}

	public function applyDatabaseParameters(): void
	{
		$config = $this->container->appConfig;

		$dbParameters = $this->getDatabaseParameters();

		foreach ($dbParameters as $k => $v)
		{
			if ($k === 'ssl')
			{
				continue;
			}

			if ($k != 'prefix')
			{
				$k = 'db' . $k;
				$config->set($k, $v);
			}
			else
			{
				$config->set($k, $v);
			}
		}

		// Hardcoded connection character set for PDOMySQL
		$config->set('dbcharset', 'utf8mb4');

		// Set the SSL connection parameters
		$config->set('dbencryption', $dbParameters['ssl']['enable'] ?? false);
		$config->set('dbsslcipher', $dbParameters['ssl']['cipher'] ?? '');
		$config->set('dbsslca', $dbParameters['ssl']['ca'] ?? '');
		$config->set('dbsslkey', $dbParameters['ssl']['key'] ?? '');
		$config->set('dbsslcert', $dbParameters['ssl']['cert'] ?? '');
		$config->set('dbsslverifyservercert', $dbParameters['ssl']['verify_server_cert'] ?? false);

		/**
		 * At this point, if we are reinstalling, the db driver is already initialized with the previous settings. We
		 * need to connect to the new database. How do we do that? By abusing the container, resetting the db instance
		 * stored in it.
		 */
		$this->container->offsetUnset('db');
		$this->container->offsetSet('db', Driver::fromContainer(...));
	}

	public function installDatabase(): void
	{
		$dbInstaller = new Installer($this->container);
		$dbInstaller->setXmlDirectory($this->container->basePath . '/src/schema');
		$dbInstaller->updateSchema();
	}

	public function getSetupParameters(): array
	{
		return [
			'timezone'        => $this->getSetupParameter('timezone', date_default_timezone_get()),
			'live_site'       => $this->getSetupParameter('live_site', ''),
			'session_timeout' => $this->getSetupParameter('session_timeout', '1440'),
			'fs.driver'       => $this->getSetupParameter('fs.driver', 'file'),
			'fs.host'         => $this->getSetupParameter('fs.host', ''),
			'fs.port'         => $this->getSetupParameter('fs.port', ''),
			'fs.username'     => $this->getSetupParameter('fs.username', ''),
			'fs.password'     => $this->getSetupParameter('fs.password', ''),
			'fs.directory'    => $this->getSetupParameter('fs.directory', '/'),
			'fs.ssl'          => $this->getSetupParameter('fs.ssl', false),
			'fs.passive'      => $this->getSetupParameter('fs.passive', true),
			'user.username'   => $this->getSetupParameter('user.username', 'admin'),
			'user.password'   => $this->getSetupParameter('user.password', ''),
			'user.password2'  => $this->getSetupParameter('user.password2', ''),
			'user.email'      => $this->getSetupParameter('user.email', ''),
			'user.name'       => $this->getSetupParameter('user.name', ''),
		];
	}

	public function setSetupParameters(): void
	{
		$params = $this->getSetupParameters();

		$session = $this->container->segment;
		$config  = $this->container->appConfig;

		foreach ($params as $k => $v)
		{
			$altKey = str_replace('.', '_', $k);

			$v = $this->input->get($altKey, $v, 'raw');

			$session->set('setup_' . $altKey, $v);

			// Do not store user parameters in the application configuration
			if (str_starts_with((string) $k, 'user.'))
			{
				continue;
			}

			if ($k == 'fs.directory')
			{
				$k = 'fs.dir';
			}

			$config->set($k, $v);
		}
	}

	public function createAdminUser(): void
	{
		$params = $this->getSetupParameters();

		if (empty($params['user.username']))
		{
			throw new RuntimeException($this->getLanguage()->text('PANOPTICON_SETUP_ERR_USER_EMPTYUSERNAME'), 500);
		}

		if (empty($params['user.password']))
		{
			throw new RuntimeException($this->getLanguage()->text('PANOPTICON_SETUP_ERR_USER_EMPTYPASSWORD'), 500);
		}

		if ($params['user.password'] != $params['user.password2'])
		{
			throw new RuntimeException($this->getLanguage()->text('PANOPTICON_SETUP_ERR_USER_PASSWORDSDONTMATCH'), 500);
		}

		if (empty($params['user.email']))
		{
			throw new RuntimeException($this->getLanguage()->text('PANOPTICON_SETUP_ERR_USER_EMPTYEMAIL'), 500);
		}

		if (empty($params['user.name']))
		{
			throw new RuntimeException($this->getLanguage()->text('PANOPTICON_SETUP_ERR_USER_EMPTYNAME'), 500);
		}

		$manager = $this->container->userManager;
		$user    = $manager->getUserByUsername($params['user.username']);

		if (empty($user))
		{
			$user = $manager->getUser(0);
		}

		$data = [
			'username' => $params['user.username'],
			'name'     => $params['user.name'],
			'email'    => $params['user.email'],
		];

		$user->bind($data);
		$user->setPassword($params['user.password']);
		$user->setPrivilege('panopticon.super', true);
		$user->setPrivilege('panopticon.admin', true);
		$user->setPrivilege('panopticon.run', true);
		$user->setPrivilege('panopticon.view', true);

		$manager->saveUser($user);
		$manager->loginUser($params['user.username'], $params['user.password']);
	}

	public function conditionallyCreateWebCronKey(): void
	{
		$currentKey = $this->container->appConfig->get('webcron_key', '');
		$complexify = new Complexify();

		if (!empty($currentKey) && $complexify->evaluateSecurity($currentKey)->valid)
		{
			return;
		}

		do
		{
			$newPassword = Random::stringFromAlphabet(Alphabet::BASE_64_URL, 32);

			if (!$complexify->evaluateSecurity($newPassword)->valid)
			{
				$newPassword = '';
			}
		} while (empty($newPassword));

		$this->container->appConfig->set('webcron_key', $newPassword);
		$this->container->appConfig->saveConfiguration();

		if (function_exists('opcache_invalidate'))
		{
			opcache_invalidate(APATH_CONFIGURATION . '/config.php', true);
		}
	}

	public function reRegisterMaxExecTask(): void
	{
		$db = $this->container->db;

		// Delete any existing task
		$query = $db->getQuery(true)
					->delete($db->quoteName('#__tasks'))
					->where($db->quoteName('type') . ' = ' . $db->quote('maxexec'));
		$db->setQuery($query)->execute();

		$query = $db->getQuery(true)
					->delete($db->quoteName('#__akeeba_common'))
					->where($db->quoteName('key') . ' LIKE ' . $db->quote('maxexec.%'));
		$db->setQuery($query)->execute();

		$newTask = (object)[
			'id'              => null,
			'site_id'         => null,
			'type'            => 'maxexec',
			'cron_expression' => '@daily',
			'enabled'         => 1,
			'last_exit_code'  => Status::INITIAL_SCHEDULE->value,
			'next_execution'  => ($this->container->dateFactory('now', 'UTC'))->toSql(),
			'params'          => (new Registry('{"run_once": "delete"}'))->toString(),
		];
		$db->insertObject('#__tasks', $newTask);
	}

	public function getHeartbeat(): object
	{
		$ret           = new stdClass();
		$ret->hasTask  = false;
		$ret->started  = false;
		$ret->finished = false;
		$ret->elapsed  = 0;
		$ret->error    = null;

		$db = $this->container->db;

		$query = $db->getQuery(true)
					->select('*')
					->from($db->quoteName('#__tasks'))
					->where($db->quoteName('type') . ' = ' . $db->quote('maxexec'));
		try
		{
			$taskObject = $db->setQuery($query, 0, 1)->loadObject();
		}
		catch (Exception $e)
		{
			$ret->error = $e->getMessage();

			return $ret;
		}

		$ret->hasTask = true;

		try
		{
			$query      = $db->getQuery(true)
							 ->select('*')
							 ->from($db->quoteName('#__akeeba_common'))
							 ->where($db->quoteName('key') . ' LIKE ' . $db->quote('maxexec.%'));
			$tempValues = $db->setQuery($query)->loadAssocList('key', 'value');
		}
		catch (Exception $e)
		{
			$ret->error = $e->getMessage();

			return $ret;
		}

		$ret->started  = ($tempValues['maxexec.lasttick'] ?? 0) !== 0;
		$ret->finished = ($tempValues['maxexec.done'] ?? 0) == 1;
		$ret->elapsed  = (int)$tempValues['maxexec.lasttick'] ?? 0;

		return $ret;
	}

	public function removeMaxExecTask(): void
	{
		$db    = $this->container->db;
		$query = $db->getQuery(true)
					->delete($db->quoteName('#__tasks'))
					->where($db->quoteName('type') . ' = ' . $db->quote('maxexec'));

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (Exception)
		{
		}
	}

	public function installDefaultTasks(): void
	{
		foreach (self::DEFAULT_TASKS as $type => $cronExpression)
		{
			$this->deleteSystemTasks($type);

			/** @var Task $task */
			$task = $this->container->mvcFactory->makeModel('Task');

			$parts          = explode('$', $cronExpression, 2);
			$cronExpression = $parts[0];
			$priority       = (int) ($parts[1] ?? 0);

			$task->save(
				[
					'site_id'         => null,
					'type'            => $type,
					'cron_expression' => $cronExpression,
					'enabled'         => 1,
					'last_exit_code'  => Status::INITIAL_SCHEDULE->value,
					'priority'        => $priority,
				]
			);
		}
	}

	public function checkDefaultTasks(): void
	{
		$db = $this->container->db;

		$query = $db->getQuery(true)
					->select([
						$db->quoteName('type'),
						$db->quoteName('cron_expression'),
						$db->quoteName('priority'),
					])
					->from($db->quoteName('#__tasks'))
					->where(
						[
							$db->quoteName('site_id') . ' IS NULL',
							$db->quoteName('type') . 'IN(' . implode(',', array_map($db->quote(...), array_keys(self::DEFAULT_TASKS))) . ')',
						]
					);

		$installedTypes = $db->setQuery($query)->loadObjectList('type');
		$dirty          = false;

		foreach (self::DEFAULT_TASKS as $type => $cronExpression)
		{
			$parts          = explode('$', $cronExpression, 2);
			$cronExpression = $parts[0];
			$priority       = (int) ($parts[1] ?? 0);

			$installed = $installedTypes[$type] ?? null;

			if ($installed === null)
			{
				$dirty = true;

				break;
			}

			if ($installed->cron_expression != $cronExpression || $installed->priority != $priority)
			{
				$dirty = true;

				break;
			}
		}

		if (!$dirty)
		{
			return;
		}

		$this->installDefaultTasks();
	}

	private function deleteSystemTasks(string $type)
	{
		$db = $this->container->db;

		$query = $db->getQuery(true)
					->delete($db->quoteName('#__tasks'))
					->where([
						$db->quoteName('type') . ' = ' . $db->quote($type),
						$db->quoteName('site_id') . ' IS NULL',
					]);

		$db->setQuery($query)->execute();
	}

	private function getIniParserAvailability(): bool
	{
		$disabled_functions = ini_get('disable_functions');

		if (!empty($disabled_functions))
		{
			// Attempt to detect them in the disable_functions black list
			$disabled_functions           = explode(',', trim($disabled_functions));
			$number_of_disabled_functions = count($disabled_functions);

			for ($i = 0; $i < $number_of_disabled_functions; $i++)
			{
				$disabled_functions[$i] = trim($disabled_functions[$i]);
			}

			return !in_array('parse_ini_string', $disabled_functions);
		}

		// Attempt to detect their existence; even pure PHP implementation of them will trigger a positive response, though.
		return function_exists('parse_ini_string');
	}

	private function getSetupParameter(string $key, mixed $default = null): mixed
	{
		$session = $this->container->segment;
		$config  = $this->container->appConfig;

		$altKey = str_replace('.', '_', $key);

		return $session->get('setup_' . $altKey, $config->get($key, $default));
	}
}