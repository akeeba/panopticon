<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Panopticon\Api\Model;

(defined('AKEEBA') || defined('_JEXEC')) || die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Throwable;

class ExtensionsModel extends ListModel
{
	public function __construct($config = [], MVCFactoryInterface $factory = null)
	{
		$config['filter_fields'] = $config['filter_fields'] ?? [
			'updatable',
			'protected',
			'id',
			'core',
		];

		parent::__construct($config, $factory);
	}

	protected function getListQuery()
	{
		$protected = $this->getState('filter.protected', 0);
		$protected = ($protected !== '' && is_numeric($protected)) ? intval($protected) : null;

		$db    = method_exists($this, 'getDatabase') ? $this->getDatabase() : $this->getDbo();
		$query = $db->getQuery(true)
			->select([
				$db->quoteName('e.extension_id', 'id'),
				$db->quoteName('e') . '.*',
				$db->quoteName('u.version', 'new_version'),
				$db->quoteName('u.detailsurl'),
				$db->quoteName('u.infourl'),
				$db->quoteName('u.changelogurl'),
			])
			->from($db->quoteName('#__extensions', 'e'))
			->leftJoin(
				$db->quoteName('#__updates', 'u'),
				$db->quoteName('u.extension_id') . ' = ' . $db->quoteName('e.extension_id')
			)
			->where($db->quoteName('package_id') . ' = 0');

		if (is_int($protected) && $protected >= 0)
		{
			$protected = boolval($protected) ? 1 : 0;
			$query->where($db->quoteName('protected') . ' = :protected')
				->bind(':protected', $protected, ParameterType::INTEGER);
		}

		$updatable = $this->getState('filter.updatable', '');

		if ($updatable !== '' && $updatable)
		{
			$query->where($db->quoteName('u.version') . ' IS NOT NULL');
		}

		$eid = $this->getState('filter.id', '');
		$eid = ($eid !== '' && is_numeric($eid)) ? intval($eid) : null;

		if (is_int($eid) && $eid > 0)
		{
			$query->where($db->quoteName('e.extension_id') . ' = :eid')
				->bind(':eid', $eid, ParameterType::INTEGER);
		}

		return $query;
	}

	protected function _getList($query, $limitstart = 0, $limit = 0)
	{
		// Get all items from the database. We deliberately don't apply any limits just yet.
		$items = parent::_getList($query);

		// Pull information from the manifest cache
		$items = array_map(
			function (object $item): object {
				try
				{
					$manifestCache = @json_decode($item->manifest_cache ?? '{}') ?? new \stdClass();
				}
				catch (\Exception $e)
				{
					$manifestCache = new \stdClass();
				}

				$item->author      = $manifestCache->author ?? '';
				$item->authorUrl   = $manifestCache->authorUrl ?? '';
				$item->authorEmail = $manifestCache->authorEmail ?? '';
				$item->version     = $manifestCache->version ?? '0.0.0';
				$item->description = $manifestCache->description ?? '';

				return $item;
			},
			$items
		);

		// Apply the filter.core filter, if requested
		$coreFilter = $this->getState('filter.core', '');
		$coreFilter = ($coreFilter !== '' && is_numeric($coreFilter)) ? intval($coreFilter) === 1 : null;

		if (!is_null($coreFilter))
		{
			$items = array_filter(
				$items,
				fn($item) => !$coreFilter xor str_contains($item->authorUrl, 'www.joomla.org')
			);
		}

		// Translate some information
		$jLang = Factory::getApplication()->getLanguage();
		// -- Load the com_installer language files; they are used below
		$jLang->load('com_installer', JPATH_API, null);
		$jLang->load('com_installer', JPATH_ADMINISTRATOR, null);

		$items = array_map(
			function (object $item) use ($jLang): object {
				// Translate the client, extension type, and folder
				$item->client_translated = Text::_([
					0 => 'JSITE', 1 => 'JADMINISTRATOR', 3 => 'JAPI',
				][$item->client_id] ?? 'JSITE');
				$item->type_translated   = Text::_('COM_INSTALLER_TYPE_' . strtoupper($item->type));
				$item->folder_translated = @$item->folder ? $item->folder : Text::_('COM_INSTALLER_TYPE_NONAPPLICABLE');

				// Load an extension's language files (if applicable)
				$path = $item->client_id ? JPATH_ADMINISTRATOR : JPATH_SITE;

				switch ($item->type)
				{
					case 'component':
						$extension = $item->element;
						$source    = JPATH_ADMINISTRATOR . '/components/' . $extension;
						$jLang->load("$extension.sys", JPATH_ADMINISTRATOR) || $jLang->load("$extension.sys", $source);
						break;

					case 'file':
						$extension = 'files_' . $item->element;
						$jLang->load("$extension.sys", JPATH_SITE);
						break;

					case 'library':
						$parts     = explode('/', $item->element);
						$vendor    = (isset($parts[1]) ? $parts[0] : null);
						$extension = 'lib_' . ($vendor ? implode('_', $parts) : $item->element);

						if (!$jLang->load("$extension.sys", $path))
						{
							$source = $path . '/libraries/' . ($vendor ? $vendor . '/' . $parts[1] : $item->element);
							$jLang->load("$extension.sys", $source);
						}
						break;

					case 'module':
						$extension = $item->element;
						$source    = $path . '/modules/' . $extension;
						$jLang->load("$extension.sys", $path) || $jLang->load("$extension.sys", $source);
						break;

					case 'plugin':
						$extension = 'plg_' . $item->folder . '_' . $item->element;
						$source    = JPATH_PLUGINS . '/' . $item->folder . '/' . $item->element;
						$jLang->load("$extension.sys", JPATH_ADMINISTRATOR) || $jLang->load("$extension.sys", $source);
						break;

					case 'template':
						$extension = 'tpl_' . $item->element;
						$source    = $path . '/templates/' . $item->element;
						$jLang->load("$extension.sys", $path) || $jLang->load("$extension.sys", $source);
						break;

					case 'package':
					default:
						$extension = $item->element;
						$jLang->load("$extension.sys", JPATH_SITE);
						break;
				}

				// Translate the extension name, if applicable
				$item->name = Text::_($item->name);

				// Translate the description, if applicable
				$item->description = empty($item->description) ? '' : Text::_($item->description);

				return $item;
			},
			$items
		);

		// If no limits were requested return the whole array
		if ($limitstart === 0 && $limit === 0)
		{
			return $items;
		}

		// There's a pagination start, but no limit. A peculiar choice, but I'll oblige.
		if ($limit === 0)
		{
			return array_slice($items, $limitstart);
		}

		// There's both a pagination start and a pagination limit. Apply this choice.
		return array_slice($items, $limitstart, $limit);
	}

	protected function _getListCount($query)
	{
		return count($this->_getList($query, 0, 0));
	}

	private function extensionNameToCriteria(string $extensionName): array
	{
		$parts = explode('_', $extensionName, 3);

		switch ($parts[0])
		{
			case 'pkg':
				return [
					'type'    => 'package',
					'element' => $extensionName,
				];

			case 'com':
				return [
					'type'    => 'component',
					'element' => $extensionName,
				];

			case 'plg':
				return [
					'type'    => 'plugin',
					'folder'  => $parts[1],
					'element' => $parts[2],
				];

			case 'mod':
				return [
					'type'      => 'module',
					'element'   => $extensionName,
					'client_id' => 0,
				];

			// That's how we note admin modules
			case 'amod':
				return [
					'type'      => 'module',
					'element'   => substr($extensionName, 1),
					'client_id' => 1,
				];

			case 'files':
				return [
					'type'    => 'file',
					'element' => $parts[1],
				];

			case 'file':
				return [
					'type'    => 'file',
					'element' => $extensionName,
				];

			case 'lib':
				return [
					'type'    => 'library',
					'element' => $parts[1],
				];
		}

		return [];
	}

	public function getExtensionIdFromElement(string $extensionName): ?int
	{
		$criteria = $this->extensionNameToCriteria($extensionName);

		if (empty($criteria))
		{
			return null;
		}

		$db    = method_exists($this, 'getDatabase') ? $this->getDatabase() : $this->getDbo();
		$query = $db->getQuery(true)
			->select($db->quoteName('extension_id'))
			->from($db->quoteName('#__extensions'));

		foreach ($criteria as $key => $value)
		{
			$type = is_numeric($value) ? ParameterType::INTEGER : ParameterType::STRING;
			$type = is_bool($value) ? ParameterType::BOOLEAN : $type;
			$type = is_null($value) ? ParameterType::NULL : $type;

			/**
			 * This is required since $value is passed by reference in bind(). If we do not do this unholy trick the
			 * $value variable is overwritten in the next foreach() iteration, therefore all criteria values will be
			 * equal to the last value iterated. Groan...
			 */
			$varName    = 'queryParam' . ucfirst($key);
			${$varName} = $value;

			$query->where($db->qn($key) . ' = :' . $key)
				->bind(':' . $key, ${$varName}, $type);
		}

		try
		{
			return ((int) $db->setQuery($query)->loadResult()) ?: null;
		}
		catch (Throwable $e)
		{
			return null;
		}
	}

}