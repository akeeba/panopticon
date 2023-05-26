<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Helper;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Model\Task;
use Awf\Database\Driver;
use Awf\Html\Select;
use Awf\Mvc\DataModel;
use DateTimeZone;

abstract class Setup
{
	public static function databaseTypesSelect(string $selected = '', string $name = 'driver'): string
	{
		$connectors = Driver::getConnectors();
		$connectors = array_filter(
			$connectors,
			fn(?string $driverName) => !empty($driverName) && in_array(strtolower($driverName), [
					'mysql', 'mysqli', 'pdomysql',
				])
		);

		$options = array_map(
			fn(string $driver) => Select::option($driver, 'PANOPTICON_SETUP_LBL_DATABASE_DRIVER_' . $driver),
			$connectors
		);

		return Select::genericList(
			data: $options,
			name: $name,
			attribs: ['class' => 'form-select'],
			selected: $selected,
			idTag: $name,
			translate: true
		);
	}

	public static function mailerSelect(string $selected = '', string $name = 'mailer'): string
	{
		$scriptTypes = ['mail', 'smtp', 'sendmail'];

		$options = [];

		foreach ($scriptTypes as $scriptType)
		{
			$options[] = Select::option($scriptType, 'PANOPTICON_SYSCONFIG_EMAIL_MAILER_' . $scriptType);
		}

		return Select::genericList(
			data: $options,
			name: $name,
			attribs: ['class' => 'form-select'],
			selected: $selected,
			idTag: $name,
			translate: true
		);
	}

	public static function smtpSecureSelect(string $selected = '', string $name = 'smtpsecure'): string
	{
		$options   = [];
		$options[] = Select::option(0, 'PANOPTICON_SYSCONFIG_EMAIL_SMTPSECURE_NONE');
		$options[] = Select::option(1, 'PANOPTICON_SYSCONFIG_EMAIL_SMTPSECURE_SSL');
		$options[] = Select::option(2, 'PANOPTICON_SYSCONFIG_EMAIL_SMTPSECURE_TLS');

		return Select::genericList(
			data: $options,
			name: $name,
			attribs: ['class' => 'form-select'],
			selected: $selected,
			idTag: $name,
			translate: true
		);
	}

	public static function timezoneSelect(string $selected = '', string $name = 'timezone', $disabled = false, ?string $id = null): string
	{
		$groups      = [];
		$zoneHeaders = [
			'Africa',
			'America',
			'Antarctica',
			'Arctic',
			'Asia',
			'Atlantic',
			'Australia',
			'Europe',
			'Indian',
			'Pacific',
		];
		$zones       = DateTimeZone::listIdentifiers();

		// Build the group lists.
		foreach ($zones as $zone)
		{
			// Time zones not in a group we will ignore.
			if (strpos($zone, '/') === false)
			{
				continue;
			}

			// Get the group/locale from the timezone.
			[$group, $locale] = explode('/', $zone, 2);

			// Only use known groups.
			if (in_array($group, $zoneHeaders))
			{
				// Initialize the group if necessary.
				if (!isset($groups[$group]))
				{
					$groups[$group] = [];
				}

				// Only add options where a locale exists.
				if (!empty($locale))
				{
					$groups[$group][$zone] = Select::option($zone, str_replace('_', ' ', $locale));
				}
			}
		}

		// Sort the group lists.
		ksort($groups);

		foreach ($groups as &$location)
		{
			sort($location);
		}

		$defaultGroup = [
			Select::option('UTC', 'UTC'),
		];

		$groups['UTC'] = $defaultGroup;

		ksort($groups);

		$options = [
			'id'          => $id ?? $name,
			'list.select' => $selected,
			'group.items' => null,
			'list.attr'   => [
				'class' => 'form-select',
			],
		];

		if ($disabled)
		{
			$options['list.attr'] = ['disabled' => 'disabled'];
		}

		return Select::groupedList(
			data: $groups,
			name: $name,
			options: $options
		);
	}

	public static function timezoneFormatSelect(string $selected = ''): string
	{
		$rawOptions = [
			'PANOPTICON_SYSCONFIG_BACKEND_TIMEZONETEXT_ABBREVIATION' => 'T',
			'PANOPTICON_SYSCONFIG_BACKEND_TIMEZONETEXT_GMTOFFSET'    => '\\G\\M\\TP',
			'PANOPTICON_SYSCONFIG_BACKEND_TIMEZONETEXT_NONE'         => '',
		];

		$options = array_map(
			fn($text, $value) => Select::option($value, $text)
			,
			array_keys($rawOptions),
			array_values($rawOptions),
		);

		return Select::genericList(
			data: $options,
			name: 'timezonetext',
			attribs: ['class' => 'form-select'],
			selected: $selected,
			idTag: 'timezonetext',
			translate: true
		);
	}

	public static function fsDriverSelect(string $selected = '', bool $showDirect = true): string
	{
		$drivers = [];

		if ($showDirect)
		{
			$drivers[] = 'file';
		}

		if (function_exists('ftp_connect'))
		{
			$drivers[] = 'ftp';
		}

		if (extension_loaded('ssh2'))
		{
			$drivers[] = 'sftp';
		}

		$options = array_map(
			fn($driver) => Select::option($driver, 'PANOPTICON_SETUP_LBL_FS_DRIVER_' . $driver),
			$drivers
		);

		return Select::genericList(
			data: $options,
			name: 'fs_driver',
			attribs: ['class' => 'form-select'],
			selected: $selected,
			idTag: 'fs_driver',
			translate: true
		);
	}

	public static function minstabilitySelect(string $selected = ''): string
	{
		$levels = ['alpha', 'beta', 'rc', 'stable'];

		$options = array_map(
			fn($level) => Select::option($level, 'PANOPTICON_CONFIG_MINSTABILITY_' . $level),
			$levels
		);

		return Select::genericList(
			data: $options,
			name: 'minstability',
			attribs: ['class' => 'form-select'],
			selected: $selected,
			idTag: 'minstability',
			translate: true
		);
	}

	/**
	 * Get a dropdown for the Two-Factor Authentication methods
	 *
	 * @param   string  $name      The name of the field
	 * @param   string  $selected  The pre-selected value
	 *
	 * @return  string  HTML
	 */
	public static function tfaMethods(string $name = 'tfamethod', string $selected = 'none'): string
	{
		$methods = ['none', 'yubikey', 'google'];

		$options = array_map(
			fn($method) => Select::option($method, 'PANOPTICON_USERS_TFA_' . $method),
			$methods
		);

		return Select::genericList(
			data: $options,
			name: $name,
			attribs: ['class' => 'form-select'],
			selected: $selected,
			idTag: $name,
			translate: true
		);
	}
}
