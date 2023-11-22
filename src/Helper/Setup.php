<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Helper;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use Awf\Database\Driver;
use Awf\Helper\AbstractHelper;
use Awf\Text\Text;
use Awf\Utils\ParseIni;
use DateTimeZone;

class Setup extends AbstractHelper
{
	public function databaseTypesSelect(string $selected = '', string $name = 'driver'): string
	{
		$connectors = Driver::getConnectors();
		$connectors = array_filter(
			$connectors,
			fn(?string $driverName) => !empty($driverName)
			                           && in_array(
				                           strtolower($driverName), [
					                           'mysql',
					                           'mysqli',
					                           'pdomysql',
				                           ]
			                           )
		);

		$options = array_map(
			fn(string $driver) => $this->getContainer()->html->select
				->option($driver, 'PANOPTICON_SETUP_LBL_DATABASE_DRIVER_' . $driver),
			$connectors
		);

		return $this->getContainer()->html->select
			->genericList(
				data: $options,
				name: $name,
				attribs: ['class' => 'form-select'],
				selected: $selected,
				idTag: $name,
				translate: true
			);
	}

	public function mailerSelect(string $selected = '', string $name = 'mailer'): string
	{
		$scriptTypes = ['mail', 'smtp', 'sendmail'];

		$options = [];

		foreach ($scriptTypes as $scriptType)
		{
			$options[] = $this->getContainer()->html->select
				->option($scriptType, 'PANOPTICON_SYSCONFIG_EMAIL_MAILER_' . $scriptType);
		}

		return $this->getContainer()->html->select
			->genericList(
				data: $options,
				name: $name,
				attribs: ['class' => 'form-select'],
				selected: $selected,
				idTag: $name,
				translate: true
			);
	}

	public function smtpSecureSelect(string $selected = '', string $name = 'smtpsecure'): string
	{
		$selectHelper = $this->getContainer()->html->select;

		$options   = [];
		$options[] = $selectHelper->option(0, 'PANOPTICON_SYSCONFIG_EMAIL_SMTPSECURE_NONE');
		$options[] = $selectHelper->option(1, 'PANOPTICON_SYSCONFIG_EMAIL_SMTPSECURE_SSL');
		$options[] = $selectHelper->option(2, 'PANOPTICON_SYSCONFIG_EMAIL_SMTPSECURE_TLS');

		return $selectHelper
			->genericList(
				data: $options,
				name: $name,
				attribs: ['class' => 'form-select'],
				selected: $selected,
				idTag: $name,
				translate: true
			);
	}

	public function timezoneSelect(
		string $selected = '',
		string $name = 'timezone',
		$disabled = false,
		?string $id = null
	): string
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

			// Only add options in known groups, and for which a locale exists.
			if (!in_array($group, $zoneHeaders) || empty($locale))
			{
				continue;
			}

			$groups[$group]        ??= [];
			$groups[$group][$zone] = $this->getContainer()->html->select
				->option(
					$zone,
					str_replace('_', ' ', $locale)
				);
		}

		// Sort the group lists.
		ksort($groups);

		foreach ($groups as &$location)
		{
			sort($location);
		}

		$defaultGroup = [
			$this->getContainer()->html->select->option('UTC', 'UTC'),
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

		return $this->getContainer()->html->select
			->groupedList(
				data: $groups,
				name: $name,
				options: $options
			);
	}

	public function timezoneFormatSelect(string $selected = ''): string
	{
		$rawOptions = [
			'PANOPTICON_SYSCONFIG_BACKEND_TIMEZONETEXT_ABBREVIATION' => 'T',
			'PANOPTICON_SYSCONFIG_BACKEND_TIMEZONETEXT_GMTOFFSET'    => '\\G\\M\\TP',
			'PANOPTICON_SYSCONFIG_BACKEND_TIMEZONETEXT_NONE'         => '',
		];

		$options = array_map(
			fn($text, $value) => $this->getContainer()->html->select->option($value, $text)
			,
			array_keys($rawOptions),
			array_values($rawOptions),
		);

		return $this->getContainer()->html->select
			->genericList(
				data: $options,
				name: 'timezonetext',
				attribs: ['class' => 'form-select'],
				selected: $selected,
				idTag: 'timezonetext',
				translate: true
			);
	}

	public function fsDriverSelect(string $selected = '', bool $showDirect = true): string
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
			fn($driver) => $this->getContainer()->html->select
				->option($driver, 'PANOPTICON_SETUP_LBL_FS_DRIVER_' . $driver),
			$drivers
		);

		return $this->getContainer()->html->select
			->genericList(
				data: $options,
				name: 'fs_driver',
				attribs: ['class' => 'form-select'],
				selected: $selected,
				idTag: 'fs_driver',
				translate: true
			);
	}

	public function minstabilitySelect(string $selected = ''): string
	{
		$levels = ['alpha', 'beta', 'rc', 'stable'];

		$options = array_map(
			fn($level) => $this->getContainer()->html->select
				->option($level, 'PANOPTICON_CONFIG_MINSTABILITY_' . $level),
			$levels
		);

		return $this->getContainer()->html->select
			->genericList(
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
	public function tfaMethods(string $name = 'tfamethod', string $selected = 'none'): string
	{
		$methods = ['none', 'yubikey', 'google'];

		$options = array_map(
			fn($method) => $this->getContainer()->html->select
				->option($method, 'PANOPTICON_USERS_TFA_' . $method),
			$methods
		);

		return $this->getContainer()->html->select
			->genericList(
				data: $options,
				name: $name,
				attribs: ['class' => 'form-select'],
				selected: $selected,
				idTag: $name,
				translate: true
			);
	}

	public function userSelect(
		?int $selected, string $name, ?string $id = null, array $attribs = [], bool $emptyOption = false
	): string
	{
		static $users = null;

		$users ??= call_user_func(
			function () {
				$db    = $this->getContainer()->db;
				$query = $db
					->getQuery(true)
					->select(
						[
							$db->quoteName('id', 'value'),
							$db->quoteName('username', 'text'),
						]
					)
					->from($db->quoteName('#__users'))
					->order($db->quoteName('username') . ' ASC');

				return $db->setQuery($query)->loadObjectList();
			}
		);

		if ($emptyOption)
		{
			array_unshift(
				$users, (object) [
				'value' => 0,
				'text'  => Factory::getContainer()->language
					->text('PANOPTICON_LBL_SELECT_USER'),
			]
			);
		}

		return $this->getContainer()->html->select
			->genericList(
				$users, $name, $attribs, selected: $selected ?? 0, idTag: $id ?? $name, translate: false
			);
	}

	public function siteSelect(
		int|string|null $selected, string $name, ?string $id = null, array $attribs = [], bool $withSystem = true
	)
	{
		$siteList = $this->getContainer()->mvcFactory->makeTempModel('Sites')->keyedList();
		asort($siteList, SORT_NATURAL);

		if ($withSystem)
		{
			$siteList = array_combine(
				array_merge([0], array_keys($siteList)),
				array_merge(
					[
						Factory::getContainer()->language
							->text('PANOPTICON_APP_LBL_SYSTEM_TASK')
					],
					array_values($siteList)
				),
			);
		}

		$siteList = array_combine(
			array_merge([''], array_keys($siteList)),
			array_merge(
				[
					sprintf(
						'– %s –',
						Factory::getContainer()->language
							->text('PANOPTICON_TASKS_LBL_FIELD_SITE_ID')
					)
				],
				array_values($siteList)
			),
		);

		return $this->getContainer()->html->select->genericList(
			$siteList,
			$name, $attribs, selected: $selected ?? '', idTag: $id ?? $name, translate: false
		);
	}

	public function languageOptions(?string $selected, string $name, ?string $id = null, array $attribs = [], bool $addUseDefault = false, bool $namesAlsoInEnglish = true)
	{
		$defaultOptions = [];

		if ($addUseDefault)
		{
			$defaultOptions = [
				'' => sprintf(
					'🌐 %s',
					$this->getContainer()->language->text('PANOPTICON_USERS_LBL_FIELD_FIELD_LANGUAGE_AUTO')
				)
			];
		}

		$options = array_merge($defaultOptions, $this->getLanguageOptions($namesAlsoInEnglish));

		return $this->getContainer()->html->select
			->genericList(
				$options, $name, $attribs, selected: $selected ?? 0, idTag: $id ?? $name, translate: false
			);
	}

	/**
	 * Returns an HTML select list of application templates
	 *
	 * @param   string|null  $selected  Selected template
	 * @param   string       $name      Name of the field, default is `template`
	 * @param   string|null  $id        ID of the field, NULL to use $name
	 * @param   array        $attribs   Additional HTML attributes of the SELECT element
	 *
	 * @return  string
	 * @since   1.0.4
	 */
	public function template(
		?string $selected = 'default', string $name = 'template', ?string $id = null, array $attribs = []
	)
	{
		// List all folders under APATH_THEMES
		$templates = [];
		$di        = new \DirectoryIterator(APATH_THEMES);
		/** @var \DirectoryIterator $file */
		foreach ($di as $file)
		{
			if ($file->isDot() || !$file->isDir())
			{
				continue;
			}

			$basename = $file->getBasename();

			// The "system" template is a special, unselectable case
			if ($basename === 'system')
			{
				continue;
			}

			// The default template always goes straight to the top.
			if ($basename === 'default')
			{
				array_unshift($templates, $basename);

				continue;
			}

			$templates[] = $basename;
		}

		$templates = array_map(
			fn($template) => $this->getTemplateName($template),
			array_combine($templates, $templates)
		);

		return $this->getContainer()->html->select
			->genericList(
				$templates, $name, $attribs, selected: $selected ?? 0, idTag: $id ?? $name, translate: false
			);
	}

	private function getLanguageOptions(bool $namesAlsoInEnglish = true)
	{
		$ret = [];

		$di = new \DirectoryIterator($this->getContainer()->languagePath);
		/** @var \DirectoryIterator $file */
		foreach ($di as $file)
		{
			if (!$file->isFile() || $file->getExtension() !== 'ini')
			{
				continue;
			}

			$retKey  = $file->getBasename('.ini');
			$rawText = @file_get_contents($file->getPathname());

			if ($rawText === false)
			{
				continue;
			}

			$rawText = str_replace('\\"_QQ_\\"', '\"', $rawText);
			$rawText = str_replace('\\"_QQ_"', '\"', $rawText);
			$rawText = str_replace('"_QQ_\\"', '\"', $rawText);
			$rawText = str_replace('"_QQ_"', '\"', $rawText);
			$rawText = str_replace('\\"', '"', $rawText);
			$strings = ParseIni::parse_ini_file($rawText, false, true);

			if (!isset($strings['LANGUAGE_NAME_IN_ENGLISH']))
			{
				continue;
			}

			$retText = sprintf(
				'%s&nbsp;%s',
				$this->countryToEmoji($retKey),
				$strings['LANGUAGE_NAME_IN_ENGLISH']
			);

			if (isset($strings['LANGUAGE_NAME_TRANSLATED'])
			    && $strings['LANGUAGE_NAME_TRANSLATED'] != $strings['LANGUAGE_NAME_IN_ENGLISH'])
			{
				if ($namesAlsoInEnglish)
				{
					$retText = sprintf(
						'%s (%s)',
						$retText,
						$strings['LANGUAGE_NAME_TRANSLATED']
					);
				}
				else
				{
					$retText = sprintf(
						'%s&nbsp;%s',
						$this->countryToEmoji($retKey),
						$strings['LANGUAGE_NAME_TRANSLATED']
					);
				}
			}

			$ret[$retKey] = $retText;
		}

		return $ret;
	}

	private function countryToEmoji(?string $cCode = null): string
	{
		// Convert the country code to all uppercase
		$cCode = strtoupper(trim($cCode ?? ''));

		// If there's a dash it's a language code, not a country code. Keep the country.
		$cCode = str_replace('_', '-', $cCode);

		if (str_contains($cCode, '-'))
		{
			[,$cCode] = explode('-', $cCode, 2);
		}

		// If the country code has a dot, ignore the part after the dot.
		if (str_contains($cCode, '.'))
		{
			[$cCode, ] = explode('.', $cCode, 2);
		}

		// No country? Return a white flag emoji.
		if (empty($cCode))
		{
			return '&#x1F3F3;';
		}

		// Valid country codes
		$countryCodes = [
			'AD', 'AE', 'AF', 'AG', 'AI', 'AL', 'AM', 'AO', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AW', 'AX', 'AZ', 'BA', 'BB',
			'BD', 'BE', 'BF', 'BG', 'BH', 'BI', 'BJ', 'BL', 'BM', 'BN', 'BO', 'BQ', 'BR', 'BS', 'BT', 'BV', 'BW', 'BY',
			'BZ', 'CA', 'CC', 'CD', 'CF', 'CG', 'CH', 'CI', 'CK', 'CL', 'CM', 'CN', 'CO', 'CR', 'CU', 'CV', 'CW', 'CX',
			'CY', 'CZ', 'DE', 'DJ', 'DK', 'DM', 'DO', 'DZ', 'EC', 'EE', 'EG', 'EH', 'ER', 'ES', 'ET', 'FI', 'FJ', 'FK',
			'FM', 'FO', 'FR', 'GA', 'GB', 'GD', 'GE', 'GF', 'GG', 'GH', 'GI', 'GL', 'GM', 'GN', 'GP', 'GQ', 'GR', 'GS',
			'GT', 'GU', 'GW', 'GY', 'HK', 'HM', 'HN', 'HR', 'HT', 'HU', 'ID', 'IE', 'IL', 'IM', 'IN', 'IO', 'IQ', 'IR',
			'IS', 'IT', 'JE', 'JM', 'JO', 'JP', 'KE', 'KG', 'KH', 'KI', 'KM', 'KN', 'KP', 'KR', 'KW', 'KY', 'KZ', 'LA',
			'LB', 'LC', 'LI', 'LK', 'LR', 'LS', 'LT', 'LU', 'LV', 'LY', 'MA', 'MC', 'MD', 'ME', 'MF', 'MG', 'MH', 'MK',
			'ML', 'MM', 'MN', 'MO', 'MP', 'MQ', 'MR', 'MS', 'MT', 'MU', 'MV', 'MW', 'MX', 'MY', 'MZ', 'NA', 'NC', 'NE',
			'NF', 'NG', 'NI', 'NL', 'NO', 'NP', 'NR', 'NU', 'NZ', 'OM', 'PA', 'PE', 'PF', 'PG', 'PH', 'PK', 'PL', 'PM',
			'PN', 'PR', 'PS', 'PT', 'PW', 'PY', 'QA', 'RE', 'RO', 'RS', 'RU', 'RW', 'SA', 'SB', 'SC', 'SD', 'SE', 'SG',
			'SH', 'SI', 'SJ', 'SK', 'SL', 'SM', 'SN', 'SO', 'SR', 'SS', 'ST', 'SV', 'SX', 'SY', 'SZ', 'TC', 'TD', 'TF',
			'TG', 'TH', 'TJ', 'TK', 'TL', 'TM', 'TN', 'TO', 'TR', 'TT', 'TV', 'TW', 'TZ', 'UA', 'UG', 'UM', 'US', 'UY',
			'UZ', 'VA', 'VC', 'VE', 'VG', 'VI', 'VN', 'VU', 'WF', 'WS', 'YE', 'YT', 'ZA', 'ZM', 'ZW',
		];

		// Invalid country? Return a black flag.
		if (!in_array($cCode, $countryCodes))
		{
			return '&#x1F3F4;';
		}

		// Uppercase letter to Unicode Regional Indicator Symbol Letter
		$letterToRISL = [
			'A' => "&#x1F1E6;",
			'B' => "&#x1F1E7;",
			'C' => "&#x1F1E8;",
			'D' => "&#x1F1E9;",
			'E' => "&#x1F1EA;",
			'F' => "&#x1F1EB;",
			'G' => "&#x1F1EC;",
			'H' => "&#x1F1ED;",
			'I' => "&#x1F1EE;",
			'J' => "&#x1F1EF;",
			'K' => "&#x1F1F0;",
			'L' => "&#x1F1F1;",
			'M' => "&#x1F1F2;",
			'N' => "&#x1F1F3;",
			'O' => "&#x1F1F4;",
			'P' => "&#x1F1F5;",
			'Q' => "&#x1F1F6;",
			'R' => "&#x1F1F7;",
			'S' => "&#x1F1F8;",
			'T' => "&#x1F1F9;",
			'U' => "&#x1F1FA;",
			'V' => "&#x1F1FB;",
			'W' => "&#x1F1FC;",
			'X' => "&#x1F1FD;",
			'Y' => "&#x1F1FE;",
			'Z' => "&#x1F1FF;",
		];

		return $letterToRISL[substr($cCode, 0, 1)] . $letterToRISL[substr($cCode, 1, 1)];
	}

	/**
	 * Returns the name of a template
	 *
	 * @param   string  $template
	 *
	 * @return  string
	 * @since   1.0.4
	 */
	private function getTemplateName(string $template): string
	{
		$defaultName = Factory::getContainer()->language->text(
			sprintf(
				'PANOPTICON_APP_TEMPLATE_%s',
				strtoupper(
					preg_replace('#^[a-z0-9_]]#i', '', $template)
				)
			)
		);

		// Does the template have a template.json file?
		$jsonFile = sprintf("%s/%s/template.json", APATH_THEMES, $template);

		if (!is_file($jsonFile) || !is_readable($jsonFile))
		{
			return $defaultName;
		}

		$json = @file_get_contents($jsonFile);

		if ($json === false)
		{
			return $defaultName;
		}

		try
		{
			$templateInfo = json_decode($json, flags: JSON_THROW_ON_ERROR);
		}
		catch (\JsonException $e)
		{
			return $defaultName;
		}

		$templateName = $templateInfo->name ?? null;

		if (preg_match('#^[A-Z0-9_]*$#', $templateName))
		{
			return Factory::getContainer()->language->text($templateName);
		}

		return $templateName ?: $defaultName;
	}
}
