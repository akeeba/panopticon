<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Html;

defined('AKEEBA') || die;

/**
 * Thin wrapper around HTMLPurifier for sanitising externally-sourced HTML before display.
 */
abstract class Purifier
{
	private static ?\HTMLPurifier $instance = null;

	/**
	 * Sanitise an HTML string, stripping anything that could execute JavaScript.
	 *
	 * Use this before raw-outputting any HTML that originates from user input or
	 * remote-site API responses.
	 */
	public static function purify(?string $html): string
	{
		if (empty($html))
		{
			return '';
		}

		return self::getInstance()->purify($html);
	}

	private static function getInstance(): \HTMLPurifier
	{
		if (self::$instance !== null)
		{
			return self::$instance;
		}

		$config = \HTMLPurifier_Config::createDefault();

		$cacheDir = defined('APATH_CACHE') ? APATH_CACHE . '/htmlpurifier' : sys_get_temp_dir() . '/htmlpurifier';

		if (!is_dir($cacheDir))
		{
			@mkdir($cacheDir, 0755, true);
		}

		$config->set('Cache.SerializerPath', is_dir($cacheDir) ? $cacheDir : null);
		$config->set('Cache.DefinitionImpl', is_dir($cacheDir) ? null : 'Serializer');

		// Allow the tag set TinyMCE can produce; strip everything else.
		$config->set('HTML.Allowed',
			'p,br,strong,b,em,i,u,s,strike,sub,sup,'
			. 'h1,h2,h3,h4,h5,h6,'
			. 'ul,ol,li,dl,dt,dd,'
			. 'blockquote,pre,code,'
			. 'a[href|rel|target|title],'
			. 'img[src|alt|width|height|title],'
			. 'table,thead,tbody,tfoot,tr,th[scope|colspan|rowspan],td[colspan|rowspan],'
			. 'figure,figcaption,hr,div[class],span[class]'
		);

		$config->set('HTML.TargetBlank', true);
		$config->set('HTML.Nofollow', true);
		$config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);

		self::$instance = new \HTMLPurifier($config);

		return self::$instance;
	}
}
