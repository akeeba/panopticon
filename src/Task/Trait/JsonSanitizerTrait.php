<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Task\Trait;

defined('AKEEBA') || die;

trait JsonSanitizerTrait
{
	/**
	 * Attempts to sanitise a string which maybe contains JSON.
	 *
	 * The idea here is that most API calls fail because a third party plugin on the site is emitting junk, or causes
	 * PHP to emit warnings, notices, and deprecation / strict notices. In those cases we have junk followed by usable
	 * JSON data. We try to divine where the junk ends and where the usable JSON data starts, returning only the usable
	 * JSON encoded data.
	 *
	 * This is a variation of what we're doing with the Akeeba Backup JSON API library.
	 *
	 * @param   string  $raw
	 *
	 * @return  string
	 * @since   1.0.5
	 * @see     \Akeeba\BackupJsonApi\HttpAbstraction\AbstractHttpClient::removeResponseJunk
	 */
	private function sanitizeJson(string $raw): string
	{
		// Quick exit on empty data, or valid JSON data.
		if (empty(trim($raw)) || $this->jsonValidate($raw))
		{
			return $raw;
		}

		// Joomla! JSON API data starts with `{"links"`. We can use that as a "cheat".
		$temp = trim($raw);

		if (str_contains($temp, '{"links"'))
		{
			$raw = substr($temp, strpos($temp, '{"links"'));

			if ($this->jsonValidate($raw))
			{
				return $raw;
			}
		}

		// Try to narrow down by an open curly brace – deals with JSON object data prefixed by junk.
		$temp = trim($raw);

		while(str_contains($temp, '{'))
		{
			$newStart = strpos($temp, '{', 1);

			if ($newStart === false)
			{
				break;
			}

			$temp = substr($temp, $newStart);

			if ($this->jsonValidate($temp))
			{
				return $temp;
			}

			if ($newStart === 0)
			{
				break;
			}
		}

		// Try to narrow down by an open bracket – deals with JSON array data prefixed by junk.
		$temp = trim($raw);

		while(str_contains($temp, '['))
		{
			$newStart = strpos($temp, '[');

			if ($newStart === false)
			{
				break;
			}

			$temp = substr($temp, $newStart);

			if ($this->jsonValidate($temp))
			{
				return $temp;
			}

			if ($newStart === 0)
			{
				break;
			}
		}

		// No idea, man. I'll let you fail.
		return $raw;
	}

	/**
	 * Is the given string valid JSON?
	 *
	 * On PHP 8.3 and later it uses PHP's built-in `json_validate` method.
	 *
	 * On PHP 8.1 and 8.2 it simulates this method using `json_decode` which is slower and more memory-intensive.
	 *
	 * @param   string  $json   The string to inspect
	 * @param   int     $depth  The depth to analyse the string
	 * @param   int     $flags  JSON decoding flags
	 *
	 * @return  bool
	 * @since   1.0.5
	 */
	private function jsonValidate(string $json, int $depth = 512, int $flags = 0): bool
	{
		$json = trim($json);

		if (function_exists('json_validate'))
		{
			return json_validate($json, $depth, $flags);
		}

		try
		{
			$dummy = json_decode($json, false, $depth, $flags);
		}
		catch (\JsonException)
		{
			return false;
		}

		if ($dummy === null && strtolower($json) !== 'null')
		{
			return false;
		}

		return $dummy !== null;
	}
}