<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Enumerations;

defined('AKEEBA') || die;

/**
 * API token scope definitions.
 *
 * Each scope value is a string in `group:operation` format. When a token's `scopes` column
 * is NULL (or an empty array), the token is treated as having **all** scopes — this is the
 * default for newly-created tokens and provides backwards-compatible behaviour for existing
 * tokens that pre-date scope enforcement.
 *
 * Scopes are organised into **groups** (the part before the colon). A group checkbox in the
 * UI controls the selection of every scope in that group via JavaScript.
 *
 * @since  1.6.0
 */
enum ApiScope: string
{
	// -------------------------------------------------------------------------
	// Sites group
	// -------------------------------------------------------------------------

	/** List and read site details. */
	case SitesRead = 'sites:read';

	/** Add and modify sites. */
	case SitesWrite = 'sites:write';

	/** Read extension lists and manage extension download keys / schedule updates. */
	case SitesExtensions = 'sites:extensions';

	/** Trigger, cancel, or clear CMS update operations. */
	case SitesCmsUpdate = 'sites:cms-update';

	/** Trigger a manual site refresh. */
	case SitesRefresh = 'sites:refresh';

	// -------------------------------------------------------------------------
	// Tasks group
	// -------------------------------------------------------------------------

	/** List and read tasks. */
	case TasksRead = 'tasks:read';

	/** Create and modify tasks. */
	case TasksWrite = 'tasks:write';

	// -------------------------------------------------------------------------
	// System configuration group
	// -------------------------------------------------------------------------

	/** Read non-sensitive system configuration parameters. */
	case SysconfigRead = 'sysconfig:read';

	/** Set system configuration parameters. */
	case SysconfigWrite = 'sysconfig:write';

	// -------------------------------------------------------------------------
	// Self-update group
	// -------------------------------------------------------------------------

	/** Read self-update status and available version information. */
	case SelfupdateRead = 'selfupdate:read';

	/** Download, install, and finalise Panopticon self-updates. */
	case SelfupdateWrite = 'selfupdate:write';

	// =========================================================================

	/**
	 * Return the group identifier for this scope (the part before the colon).
	 *
	 * @return  string
	 * @since   1.6.0
	 */
	public function group(): string
	{
		return match ($this)
		{
			self::SitesRead, self::SitesWrite,
			self::SitesExtensions, self::SitesCmsUpdate,
			self::SitesRefresh => 'sites',

			self::TasksRead, self::TasksWrite => 'tasks',

			self::SysconfigRead, self::SysconfigWrite => 'sysconfig',

			self::SelfupdateRead, self::SelfupdateWrite => 'selfupdate',
		};
	}

	/**
	 * Return the ordered list of all group identifiers.
	 *
	 * @return  string[]
	 * @since   1.6.0
	 */
	public static function groups(): array
	{
		return ['sites', 'tasks', 'sysconfig', 'selfupdate'];
	}

	/**
	 * Return all scopes keyed by their group identifier.
	 *
	 * @return  array<string, ApiScope[]>
	 * @since   1.6.0
	 */
	public static function byGroup(): array
	{
		$result = [];

		foreach (self::groups() as $group)
		{
			$result[$group] = [];
		}

		foreach (self::cases() as $case)
		{
			$result[$case->group()][] = $case;
		}

		return $result;
	}

	/**
	 * Decode a raw scopes JSON value from the database into an array of ApiScope cases.
	 *
	 * Returns NULL when the JSON is null/empty — meaning all scopes are allowed.
	 *
	 * @param   string|null  $json  The raw JSON stored in the `scopes` column.
	 *
	 * @return  ApiScope[]|null  NULL = all scopes allowed; array = specific scopes granted.
	 * @since   1.6.0
	 */
	public static function fromJson(?string $json): ?array
	{
		if (empty($json))
		{
			return null;
		}

		try
		{
			$decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		}
		catch (\JsonException)
		{
			return null;
		}

		if (!is_array($decoded) || empty($decoded))
		{
			return null;
		}

		$result = [];

		foreach ($decoded as $value)
		{
			$case = self::tryFrom((string) $value);

			if ($case !== null)
			{
				$result[] = $case;
			}
		}

		return empty($result) ? null : $result;
	}
}
