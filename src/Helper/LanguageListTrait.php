<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Helper;

use Akeeba\Panopticon\Factory;
use Awf\Utils\ParseIni;

defined('AKEEBA') || die;

trait LanguageListTrait
{
	protected function getAllKnownLanguages(): array
	{
		static $languages = null;

		if (is_array($languages))
		{
			return $languages;
		}

		$languages = [];
		$container = Factory::getContainer();
		$di        = new \DirectoryIterator($container->languagePath);

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

			$languages[] = $retKey;
		}

		return $languages;
	}
}