<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

use Awf\Mvc\Model;

/**
 * Legal Policies (Terms of Service, Privacy Policy) Model
 *
 * @since  1.3.0
 */
class Policies extends Model
{
	/**
	 * Get the content of a policy document.
	 *
	 * Looks up keys in order: {type}.{language}, {type}.en-GB, {type}.*
	 *
	 * @param   string       $type      The policy type (e.g. 'tos', 'privacy')
	 * @param   string|null  $language  The language code, or null to use the current language
	 *
	 * @return  string  The HTML content of the policy
	 * @since   1.3.0
	 */
	public function getContent(string $type, ?string $language = null): string
	{
		$language ??= $this->container->language->getLangCode();

		$db = $this->container->db;

		// Try the exact language
		$query = $db->getQuery(true)
			->select($db->quoteName('value'))
			->from($db->quoteName('#__akeeba_common'))
			->where($db->quoteName('key') . ' = ' . $db->quote($type . '.' . $language));

		$result = $db->setQuery($query)->loadResult();

		if (!empty($result))
		{
			return $result;
		}

		// Fallback to en-GB
		if ($language !== 'en-GB')
		{
			$query = $db->getQuery(true)
				->select($db->quoteName('value'))
				->from($db->quoteName('#__akeeba_common'))
				->where($db->quoteName('key') . ' = ' . $db->quote($type . '.en-GB'));

			$result = $db->setQuery($query)->loadResult();

			if (!empty($result))
			{
				return $result;
			}
		}

		// Fallback to wildcard
		$query = $db->getQuery(true)
			->select($db->quoteName('value'))
			->from($db->quoteName('#__akeeba_common'))
			->where($db->quoteName('key') . ' = ' . $db->quote($type . '.*'));

		return $db->setQuery($query)->loadResult() ?: '';
	}

	/**
	 * Save the content of a policy document.
	 *
	 * @param   string  $type      The policy type (e.g. 'tos', 'privacy')
	 * @param   string  $language  The language code
	 * @param   string  $html      The HTML content
	 *
	 * @return  void
	 * @since   1.3.0
	 */
	public function setContent(string $type, string $language, string $html): void
	{
		$db    = $this->container->db;
		$key   = $type . '.' . $language;
		$query = $db->getQuery(true)
			->replace($db->quoteName('#__akeeba_common'))
			->columns([
				$db->quoteName('key'),
				$db->quoteName('value'),
			])
			->values(
				$db->quote($key) . ',' . $db->quote($html)
			);

		$db->setQuery($query)->execute();
	}

	/**
	 * Get the list of available languages for a policy type.
	 *
	 * @param   string  $type  The policy type (e.g. 'tos', 'privacy')
	 *
	 * @return  array  Array of language codes
	 * @since   1.3.0
	 */
	public function getAvailableLanguages(string $type): array
	{
		$db    = $this->container->db;
		$query = $db->getQuery(true)
			->select($db->quoteName('key'))
			->from($db->quoteName('#__akeeba_common'))
			->where($db->quoteName('key') . ' LIKE ' . $db->quote($type . '.%'));

		$keys = $db->setQuery($query)->loadColumn() ?: [];

		return array_map(
			fn(string $key) => substr($key, strlen($type) + 1),
			$keys
		);
	}
}
