<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Library\Enumerations\ReportAction;
use Akeeba\Panopticon\Library\User\User;
use Awf\Container\Container;
use Awf\Database\Query;
use Awf\Date\Date;
use Awf\Exception\App;
use Awf\Mvc\DataModel;
use Awf\Mvc\DataModel\Exception\RecordNotLoaded;
use Awf\Registry\Registry;
use Awf\User\UserInterface;
use BackedEnum;
use DateTime;
use Exception;
use JsonException;
use JsonSerializable;
use Throwable;
use UnitEnum;

/**
 * Data Model for the #__reports table
 *
 * @property int|null          $id
 * @property Site|null         $site_id
 * @property DateTime          $created_on
 * @property User|null         $created_by
 * @property ReportAction|null $action
 * @property Registry          $context
 *
 * Note: the set*Attribute and get*Attribute methods are used internally by the parent DataModel class to transparently
 *   transform the data between its persistent (database, scalar) and working (objects) representations. When working
 *   with this model you'll be using objects for everything but the unique ID field. The database will store them as
 *   appropriate scalar values. You don't need to care how to go from one type to the other; it's magic (at the expense
 *   of a bit of run time and a fair amount of memory).
 *
 * @since  1.0.4
 */
class Reports extends DataModel
{
	protected static $siteActionStrings = [
		'jooomla.fixCoreUpdateSite'  => 'PANOPTICON_REPORTS_LBL_SITEACTION_JOOMLA_FIX_CORE_UPDATE_SITE',
		'admintools.htaccessDisable' => 'PANOPTICON_REPORTS_LBL_SITEACTION_ADMINTOOLS_HTACCESS_DISABLE',
		'admintools.htaccessEnable'  => 'PANOPTICON_REPORTS_LBL_SITEACTION_ADMINTOOLS_HTACCESS_ENABLE',
		'admintools.pluginDisable'   => 'PANOPTICON_REPORTS_LBL_SITEACTION_ADMINTOOLS_PLUGIN_DISABLE',
		'admintools.pluginEnable'    => 'PANOPTICON_REPORTS_LBL_SITEACTION_ADMINTOOLS_PLUGIN_ENABLE',
		'admintools.unblockMyIP'     => 'PANOPTICON_REPORTS_LBL_SITEACTION_ADMINTOOLS_UNBLOCK_MY_IP',
		'akeebabackup.delete'        => 'PANOPTICON_REPORTS_LBL_SITEACTION_AKEEBABACKUP_DELETE',
		'akeebabackup.deleteFiles'   => 'PANOPTICON_REPORTS_LBL_SITEACTION_AKEEBABACKUP_DELETEFILES',
	];

	/** @inheritdoc */
	public function __construct(?Container $container = null)
	{
		$this->tableName   = '#__reports';
		$this->idFieldName = 'id';

		parent::__construct($container);

		$this->addBehaviour('filters');
	}

	/**
	 * Returns a new object for a Core Update Found event
	 *
	 * @param   int          $site_id     The side ID
	 * @param   string|null  $oldVersion  The old version of the CMS
	 * @param   string|null  $newVersion  The latest available version of the CMS
	 *
	 * @since   1.0.4
	 */
	public static function fromCoreUpdateFound(
		int $site_id, ?string $oldVersion, ?string $newVersion
	): static
	{
		/** @var static $item */
		$item             = Factory::getContainer()->mvcFactory->makeTempModel('reports');
		$item->site_id    = $site_id;
		$item->created_on = 'now';
		$item->created_by = 0;
		$item->action     = ReportAction::CORE_UPDATE_FOUND;
		$item->context    = [
			'oldVersion' => $oldVersion,
			'newVersion' => $newVersion,
		];

		return $item;
	}

	/**
	 * Returns a new object for a Core Update Installed event
	 *
	 * @param   int          $site_id         The side ID
	 * @param   string|null  $oldVersion      The old version of the CMS
	 * @param   string|null  $newVersion      The latest available version of the CMS
	 * @param   bool|null    $success         Has the update completed successfully?
	 * @param   mixed        $furtherContext  Additional context information
	 *
	 * @since   1.0.4
	 */
	public static function fromCoreUpdateInstalled(
		int $site_id, ?string $oldVersion, ?string $newVersion, ?bool $success = null, mixed $furtherContext = null
	): static
	{
		/** @var static $item */
		$item             = Factory::getContainer()->mvcFactory->makeTempModel('reports');
		$item->site_id    = $site_id;
		$item->created_on = 'now';
		$item->created_by = 0;
		$item->action     = ReportAction::CORE_UPDATE_INSTALLED;
		$item->context    = [
			'oldVersion' => $oldVersion,
			'newVersion' => $newVersion,
			'success'    => $success,
			'context'    => self::furtherContextAsArrayOrNull($furtherContext),
		];

		return $item;
	}

	/**
	 * Returns a new object for an Extension Update Found event
	 *
	 * @param   int          $site_id        The side ID
	 * @param   string       $extensionKey   The extension key, e.g. com_example
	 * @param   string       $extensionName  The human-readable extension name, e.g. "An Example"
	 * @param   string|null  $oldVersion     The old version of the extension
	 * @param   string|null  $newVersion     The latest available version of the extension
	 *
	 * @since   1.0.4
	 */
	public static function fromExtensionUpdateFound(
		int $site_id, string $extensionKey, string $extensionName, ?string $oldVersion, ?string $newVersion
	): static
	{
		$item         = self::fromCoreUpdateFound($site_id, $oldVersion, $newVersion);
		$item->action = ReportAction::EXT_UPDATE_FOUND;
		$context      = $item->context;

		$context->set('extension.key', $extensionKey);
		$context->set('extension.name', $extensionName);

		$item->context = $context;

		return $item;
	}

	/**
	 * Returns a new object for an Extension Update Installed event
	 *
	 * @param   int          $site_id         The side ID
	 * @param   string       $extensionKey    The extension key, e.g. com_example
	 * @param   string       $extensionName   The human-readable extension name, e.g. "An Example"
	 * @param   string|null  $oldVersion      The old version of the extension
	 * @param   string|null  $newVersion      The latest available version of the extension
	 * @param   bool|null    $success         Was the update successful?
	 * @param   mixed        $furtherContext  Any further context
	 *
	 * @since   1.0.4
	 */
	public static function fromExtensionUpdateInstalled(
		int $site_id, string $extensionKey, string $extensionName, ?string $oldVersion, ?string $newVersion,
		?bool $success = null, mixed $furtherContext = null
	): static
	{
		$item         = self::fromCoreUpdateInstalled($site_id, $oldVersion, $newVersion, $success, $furtherContext);
		$item->action = ReportAction::EXT_UPDATE_INSTALLED;
		$context      = $item->context;

		$context->set('extension.key', $extensionKey);
		$context->set('extension.name', $extensionName);

		$item->context = $context;

		return $item;
	}

	/**
	 * Returns a new object for a Backup Taken event
	 *
	 * @param   int    $site_id         The site ID
	 * @param   int    $backupProfile   Backup profile used
	 * @param   bool   $status          Did the backup complete okay?
	 * @param   mixed  $furtherContext  Any further context
	 *
	 * @since   1.0.4
	 */
	public static function fromBackup(
		int $site_id, int $backupProfile, bool $status = true, mixed $furtherContext = null
	): static
	{
		/** @var static $item */
		$item             = Factory::getContainer()->mvcFactory->makeTempModel('reports');
		$item->site_id    = $site_id;
		$item->created_on = 'now';
		$item->created_by = 0;
		$item->action     = ReportAction::BACKUP;
		$item->context    = [
			'backupProfile' => $backupProfile,
			'status'        => $status,
			'context'       => self::furtherContextAsArrayOrNull($furtherContext),
		];

		return $item;
	}

	/**
	 * Returns a new object for a PHP File Change Scanner event
	 *
	 * @param   int        $site_id         The Site ID
	 * @param   bool|null  $status          Did the scanner complete okay?
	 * @param   mixed      $furtherContext  Any further context info
	 *
	 * @since   1.0.4
	 */
	public static function fromFileScanner(
		int $site_id, ?bool $status = null, mixed $furtherContext = null
	): static
	{
		/** @var static $item */
		$item             = Factory::getContainer()->mvcFactory->makeTempModel('reports');
		$item->site_id    = $site_id;
		$item->created_on = 'now';
		$item->created_by = 0;
		$item->action     = ReportAction::FILESCANNER;
		$item->context    = [
			'status'  => $status,
			'context' => self::furtherContextAsArrayOrNull($furtherContext),
		];

		return $item;
	}

	/**
	 * Returns a new object for a generic site action (e.g. a third party code action)
	 *
	 * @param   int        $site_id         The Site ID
	 * @param   string     $action          A key describing the action to the interface
	 * @param   bool|null  $status          Did the action complete okay?
	 * @param   mixed      $furtherContext  Any further context
	 *
	 * @since   1.0.4
	 */
	public static function fromSiteAction(
		int $site_id, string $action, bool $status = true, mixed $furtherContext = null

	): static
	{
		/** @var static $item */
		$item             = Factory::getContainer()->mvcFactory->makeTempModel('reports');
		$item->site_id    = $site_id;
		$item->created_on = 'now';
		$item->created_by = Factory::getContainer()->userManager->getUser();
		$item->action     = ReportAction::SITE_ACTION;
		$item->context    = [
			'action'  => $action,
			'status'  => $status,
			'context' => self::furtherContextAsArrayOrNull($furtherContext),
		];

		return $item;
	}

	public static function fromSiteUptime(
		int $site_id, bool $up = false, ?int $downtimeStart = null, mixed $furtherContext = null
	)
	{
		$context = [
			'context' => self::furtherContextAsArrayOrNull($furtherContext),
		];

		if ($up && !empty($downtimeStart))
		{
			$context['start']    = $downtimeStart;
			$context['end']      = time();
			$context['duration'] = $context['end'] - $context['start'];
		}

		/** @var static $item */
		$item             = Factory::getContainer()->mvcFactory->makeTempModel('reports');
		$item->site_id    = $site_id;
		$item->created_on = 'now';
		$item->created_by = Factory::getContainer()->userManager->getUser();
		$item->action     = $up ? ReportAction::UPTIME_UP : ReportAction::UPTIME_DOWN;
		$item->context    = $context;
	}

	/**
	 * Add a known site action string
	 *
	 * @param   string  $actionKey    The action key e.g. `example.didAThingie`
	 * @param   string  $languageKey  The language key e.g. `EXAMPLE_LBL_DID_A_THINGIE`
	 *
	 * @return  void
	 * @since   1.0.4
	 */
	public static function addSiteActionString(string $actionKey, string $languageKey): void
	{
		if (empty(trim($actionKey)) || empty(trim($languageKey)))
		{
			return;
		}

		self::$siteActionStrings[trim($actionKey)] ??= strtoupper(trim($languageKey));
	}

	/**
	 * Converts the further context to an array representation
	 *
	 * @param   mixed  $furtherContext  Any kind of variable
	 *
	 * @return  array|null
	 */
	private static function furtherContextAsArrayOrNull(mixed $furtherContext): ?array
	{
		// NULL and array values are returned as-is
		if ($furtherContext === null || is_array($furtherContext))
		{
			return $furtherContext;
		}

		// String values can be JSON-encoded data, or arbitrary strings. Figure out which one.
		if (is_string($furtherContext))
		{
			try
			{
				// If it's JSON-encded data, convert to an array
				$furtherContext = json_decode(trim($furtherContext), true, flags: JSON_THROW_ON_ERROR);
			}
			catch (JsonException)
			{
				// Nah, plain old arbitrary string. Cast to array with a single item.
				$furtherContext = [
					'value' => $furtherContext,
				];
			}

			return $furtherContext;
		}

		// Non-string scalar values (int, float, bool): cast to array with a single item.
		if (is_scalar($furtherContext))
		{
			return [
				'value' => $furtherContext,
			];
		}

		// Resources are ignored and returned as a NULL value
		if (is_resource($furtherContext))
		{
			return null;
		}

		// Registry objects are cast to array
		if ($furtherContext instanceof Registry)
		{
			$furtherContext = $furtherContext->toArray();
		}

		/**
		 * This branch should never execute!
		 *
		 * We've checked for scalars, arrays, and resources. The only intrinsic data type left is object. This branch is
		 * here in the implausible case that PHP adds a non-object intrinsic data type. I say "implausible" because even
		 * Enums (added in PHP 8.1) are objects under the hood. Even if PHP decides to support something like C#'s
		 * Structs, or LINQ, they should still be (special kinds of) objects. But you never know, and an if-block is
		 * cheap, so there you go!
		 */
		if (!is_object($furtherContext))
		{
			trigger_error(
				"We have come across a context value which is not a scalar, array, resource, or object. What is this sorcery?!",
				E_USER_WARNING
			);

			return null;
		}

		// Throwables are converted to a special array representation
		if ($furtherContext instanceof Throwable)
		{
			return [
				'exception' => [
					'code'    => $furtherContext->getCode(),
					'message' => $furtherContext->getMessage(),
					'file'    => $furtherContext->getFile(),
					'line'    => $furtherContext->getLine(),
					'trace'   => $furtherContext->getTraceAsString(),
				],
			];
		}

		// Non-backed (pure) enums are treated as strings equal to their name
		if (interface_exists('UnitEnum', false) && $furtherContext instanceof UnitEnum)
		{
			return [
				'value' => $furtherContext->name,
			];
		}

		// Backed enums are treated as scalars equal to their backed value
		if (interface_exists('BackedEnum', false) && $furtherContext instanceof BackedEnum)
		{
			return [
				'value' => $furtherContext->value,
			];
		}

		// Objects which can be JSON-serialised get converted to arrays via their JSON-serialised representation.
		if ($furtherContext instanceof JsonSerializable)
		{
			return json_decode(json_encode($furtherContext), true);
		}

		// DataModel objects are converted to their array representation.
		if ($furtherContext instanceof DataModel)
		{
			return $furtherContext->getData();
		}

		// Generic objects. Try to cast as an array. If that fails, return NULL.
		try
		{
			return (array) $furtherContext;
		}
		catch (Throwable)
		{
			return null;
		}
	}

	/**
	 * Get the site action as a translated, human-readable string.
	 *
	 * The conversion uses the self::$siteActionStrings array for mapping.
	 *
	 * @return  string|null
	 * @since   1.0.4
	 * @see     self::addSiteActionString()
	 */
	public function siteActionAsString(): ?string
	{
		// My dude, this is not a Site Action entry!
		if (!$this->action === ReportAction::SITE_ACTION)
		{
			return null;
		}

		// Get the site action
		$siteAction = $this->context->get('action', null);

		// No site action. Something went off-script here!
		if (empty($siteAction))
		{
			return $this->getLanguage()->text('PANOPTICON_REPORTS_LBL_NO_ACTION');
		}

		// Unknown action?
		if (isset(self::$siteActionStrings[$siteAction]))
		{
			return $this->getLanguage()->text(self::$siteActionStrings[$siteAction]);
		}

		// Okay, we have a lang string to return.
		return $this->getLanguage()->sprintf('PANOPTICON_REPORTS_LBL_UNKNOWN_SITE_ACTION', $siteAction);
	}

	/**
	 * Returns the raw data which is (or will be) stored in the database.
	 *
	 * This is only intended for debugging.
	 *
	 * @return  array
	 * @since   1.0.4
	 */
	public function getRawData(): array
	{
		return $this->recordData;
	}

	/**
	 * Returns the latest report log entry which matches the search criteria.
	 *
	 * @param   int           $site_id         The site ID
	 * @param   ReportAction  $action          The action to match
	 * @param   array         $contextMatches  Any additional WHERE clauses
	 *
	 * @return  $this|null
	 * @throws  Exception
	 * @since   1.0.4
	 */
	public function findLatestRelevantEntry(int $site_id, ReportAction $action, array $contextMatches): ?static
	{
		$db    = $this->getDbo();
		$query = $db->getQuery(true)->select('*')->from($db->quoteName($this->tableName))->where(
			[
				$db->quoteName('site_id') . ' = ' . $db->quote($site_id),
				$db->quoteName('action') . ' = ' . $db->quote($action->value),
			]
		)->order($db->quoteName('id') . ' DESC');

		if (!empty($contextMatches))
		{
			$query->where($contextMatches);
		}

		$query->setLimit(1, 0);

		$data = $db->setQuery($query)->loadAssoc();

		if (empty($data))
		{
			return null;
		}

		return $this->getClone()->reset(true, true)->bind($data);
	}

	public function buildQuery($overrideLimits = false)
	{
		$query = parent::buildQuery($overrideLimits);

		$this->applySiteVisibilityFilter($query);

		$db = $this->container->db;

		// Filter: Site ID
		$fltSiteId = $this->getState('site_id', null);

		if (is_int($fltSiteId) && $fltSiteId > 0)
		{
			$query->where($db->quoteName('site_id') . ' = ' . (int) $fltSiteId);
		}

		// Filter: from / to dates
		[$defFrom, $defTo] = $this->defaultDateFilters();
		$fltDateFrom = $this->dateFromDateString($this->getState('from_date', null) ?? $defFrom);
		$fltDateTo   = $this->dateFromDateString($this->getState('to_date', null) ?? $defTo);
		$this->setState('from_date', $fltDateFrom);
		$this->setState('to_date', $fltDateTo);

		if (empty($fltDateFrom) && !empty($fltDateTo))
		{
			$query->where($db->quoteName('created_on') . ' <= ' . $db->quote($fltDateTo->toSql()));
		}
		elseif (!empty($fltDateFrom) && empty($fltDateTo))
		{
			$query->where($db->quoteName('created_on') . ' >= ' . $db->quote($fltDateFrom->toSql()));
		}
		elseif (!empty($fltDateFrom) && !empty($fltDateTo))
		{
			$query->where(
				$db->quoteName('created_on') . ' BETWEEN '
				. $db->quote($fltDateFrom->toSql()) . ' AND '
				. $db->quote($fltDateTo->toSql())
			);
		}

		return $query;
	}

	protected function applySiteVisibilityFilter(Query $query): void
	{
		if (defined('AKEEBA_CLI'))
		{
			return;
		}

		// Get the user, so we can apply per group privilege checks
		$user = $this->container->userManager->getUser();

		// If the user has a global view privilege, we have no checks to make
		if ($user->getPrivilege('panopticon.view'))
		{
			return;
		}

		// Get the subquery for the EXISTS clause
		$db       = $this->getDbo();
		$subQuery = $db->getQuery(true)
			->select('1')
			->from($db->quoteName('#__sites', 's'))
			->where($db->quoteName('s.id') . ' = ' . $db->quoteName('#__reports.site_id'));

		// In any other case, get the list of groups for the user and limit listing sites visible to these groups
		$groupPrivileges = $user->getGroupPrivileges();

		if (empty($groupPrivileges))
		{
			// There are no groups the user belongs to. Therefore, the user can only see their own sites.
			$subQuery->where($db->quoteName('created_by') . ' = ' . $db->quote($user->getId()));

			$query->where('EXISTS(' . $subQuery . ')');

			return;
		}

		// Filter out groups with read privileges
		$groupPrivileges = array_filter(
			$groupPrivileges,
			fn($privileges) => in_array('panopticon.view', $privileges)
		);

		if (empty($groupPrivileges))
		{
			// There are no groups with read privileges the user belongs to. Therefore, the user can only see their own sites.
			$subQuery->where($db->quoteName('created_by') . ' = ' . $db->quote($user->getId()));

			$query->where('EXISTS(' . $subQuery . ')');

			return;
		}

		// We allow the user to view their own sites
		$clauses = [
			$db->quoteName('created_by') . ' = ' . $db->quote($user->getId()),
		];

		// Basically: a bunch of JSON_CONTAINS(`config`, '1', '$.config.groups') with ORs between them
		foreach (array_keys($groupPrivileges) as $gid)
		{
			$clauses[] = $query->jsonContains(
				$db->quoteName('config'), $db->quote('"' . (int) $gid . '"'), $db->quote('$.config.groups')
			);
			$clauses[] = $query->jsonContains(
				$db->quoteName('config'), $db->quote((int) $gid), $db->quote('$.config.groups')
			);
		}

		$subQuery->extendWhere('AND', $clauses, 'OR');

		$query->where('EXISTS(' . $subQuery . ')');
	}

	protected function defaultDateFilters(): array
	{
		$firstOfMonth = $this->getContainer()->dateFactory();
		$firstOfMonth = $firstOfMonth->setDate($firstOfMonth->year, $firstOfMonth->month, 1);
		$firstOfMonth = $firstOfMonth->setTime(0, 0, 0, 0);

		$lastOfMonth = (clone $firstOfMonth)->add(new \DateInterval('P1M'));

		return [$firstOfMonth, $lastOfMonth];
	}

	protected function dateFromDateString(?string $dateString): ?Date
	{
		// Make sure it's not completely empty
		if (empty($dateString) || empty(trim($dateString)))
		{
			return null;
		}

		$dateString = trim($dateString);

		// Make sure it kinda follows what we expect a date string to look like
		if (!preg_match('#^\d{2,4}-\d{1,2}-\d{1,2}(\s*T?\s*\d{1,2}:\d{1,2}(:\d{1,2})?)?$#', $dateString))
		{
			return null;
		}

		try
		{
			return $this->getContainer()->dateFactory($dateString, 'UTC');
		}
		catch (Throwable)
		{
			return null;
		}
	}


	/**
	 * Get the site_id value as a Site object instance.
	 *
	 * This method is automatically called by the getFieldValue, getData, and __get methods.
	 *
	 * @return  Site|null
	 * @since   1.0.4
	 */
	protected function getSiteIdAttribute(): ?Site
	{
		if (empty($this->recordData['site_id'] ?? null))
		{
			return null;
		}

		try
		{
			return $this->getContainer()->mvcFactory->makeTempModel('Site')->findOrFail($this->recordData['site_id']);
		}
		catch (RecordNotLoaded)
		{
			return null;
		}
	}

	/**
	 * Set the site_id value.
	 *
	 * This method is automatically called by the setFieldValue, and __set methods.
	 *
	 * @param   int|Site|null  $site_id
	 *
	 * @return  void
	 * @since   1.0.4
	 */
	protected function setSiteIdAttribute(int|Site|null $site_id): void
	{
		if ($site_id instanceof Site)
		{
			$this->recordData['site_id'] = $site_id->getId();

			return;
		}

		$this->recordData['site_id'] = $site_id;
	}

	/**
	 * Get the created_on value as a DateTime object.
	 *
	 * This method is automatically called by the getFieldValue, getData, and __get methods.
	 *
	 * @return  DateTime
	 * @throws  Exception
	 * @since   1.0.4
	 */
	protected function getCreatedOnAttribute(): DateTime
	{
		if (($this->recordData['created_on'] ?? null) === null)
		{
			return new DateTime();
		}

		if (is_int($this->recordData['created_on'] ?? null))
		{
			return new DateTime('@' . $this->recordData['created_on']);
		}

		return new DateTime($this->recordData['created_on']);
	}

	/**
	 * Set the created_on value.
	 *
	 * This method is automatically called by the setFieldValue, and __set methods.
	 *
	 * @param   DateTime|int|string|null  $created_on
	 *
	 * @return  void
	 * @throws  App
	 * @since   1.0.4
	 */
	protected function setCreatedOnAttribute(DateTime|int|string|null $created_on): void
	{
		if (is_int($created_on))
		{
			$created_on = (new DateTime('@' . $created_on))->format(DATE_ATOM);
		}

		if ($created_on instanceof Date)
		{
			$created_on = $created_on->toAtom();
		}
		elseif ($created_on instanceof DateTime)
		{
			$created_on = $created_on->format(DATE_ATOM);
		}

		if ($created_on === null)
		{
			$created_on = 'now';
		}

		$this->recordData['created_on'] = $this->getContainer()->dateFactory($created_on)->toSql();
	}

	/**
	 * Get the created_by value as a User object.
	 *
	 * This method is automatically called by the getFieldValue, getData, and __get methods.
	 *
	 * @return  User|null
	 * @since   1.0.4
	 */
	protected function getCreatedByAttribute(): ?User
	{
		/** @noinspection PhpIncompatibleReturnTypeInspection */
		return $this->getContainer()->userManager->getUser($this->recordData['created_by'] ?? null);
	}

	/**
	 * Set the created_by value.
	 *
	 * This method is automatically called by the setFieldValue, and __set methods.
	 *
	 * @param   int|User|null  $created_by
	 *
	 * @return  void
	 * @since   1.0.4
	 */
	protected function setCreatedByAttribute(int|User|null $created_by): void
	{
		if ($created_by instanceof UserInterface)
		{
			$created_by = $created_by->getId();
		}

		$this->recordData['created_by'] = $created_by;
	}

	/**
	 * Get the action value as a ReportAction enum object.
	 *
	 * This method is automatically called by the getFieldValue, getData, and __get methods.
	 *
	 * @return  ReportAction|null
	 * @since   1.0.4
	 */
	protected function getActionAttribute(): ?ReportAction
	{
		return ReportAction::tryFrom($this->recordData['action'] ?? null);
	}

	/**
	 * Set the action value.
	 *
	 * This method is automatically called by the setFieldValue, and __set methods.
	 *
	 * @param   ReportAction|string|null  $action
	 *
	 * @return  void
	 * @since   1.0.4
	 */
	protected function setActionAttribute(ReportAction|string|null $action): void
	{
		if (is_string($action))
		{
			$action = ReportAction::tryFrom($action);
		}

		$this->recordData['action'] = $action?->value;
	}

	/**
	 * Get the context attribute as a Registry object.
	 *
	 * This method is automatically called by the getFieldValue, getData, and __get methods.
	 *
	 * @return  Registry
	 * @since   1.0.4
	 */
	protected function getContextAttribute(): Registry
	{
		return new Registry($this->recordData['context'] ?? null);
	}

	/**
	 * Set the context attribute.
	 *
	 * This method is automatically called by the setFieldValue, and __set methods.
	 *
	 * @param   string|array|object  $context
	 *
	 * @return  void
	 * @since   1.0.4
	 */
	protected function setContextAttribute(string|array|object $context): void
	{
		if (is_string($context))
		{
			$this->recordData['context'] = $context;

			return;
		}

		if (is_array($context) || (is_object($context) && !$context instanceof Registry))
		{
			$context = new Registry($context);
		}

		$this->recordData['context'] = $context->toString('JSON');
	}
}