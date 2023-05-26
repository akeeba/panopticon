<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Editor;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use Awf\Uri\Uri;
use Awf\Utils\Template;
use Delight\Random\Random;

/**
 * Integration of the TinyMCE editor with Panopticon
 */
abstract class TinyMCE
{
	private static bool $hasInitialised = false;

	public static function editor(string $name, ?string $content, array $options = []): string
	{
		self::initialise();

		$container = Factory::getContainer();
		$document  = $container->application->getDocument();
		$content   = $content ?: '';
		$id        = $options['id'] ?? ('tinyMceEditor_' . Random::alphaLowercaseString(32));

		if (isset($options['id']))
		{
			unset($options['id']);
		}

		$options = array_merge(
			[
				'selector'                    => '#' . $id,
				'plugins'                     => [
					'advlist', 'autolink', 'autoresize', 'code', 'directionality', 'fullscreen', 'image',
					'importcss', 'link', 'lists', 'media', 'preview', 'quickbars', 'searchreplace', 'table',
					'visualblocks', 'wordcount',
				],
				'toolbar_mode'                => 'sliding',
				'toolbar'                     => [
					'fullscreen | undo redo | copy cut paste pastetext | blocks | bold italic strikethrough underline | link image media | alignleft aligncenter alignright alignjustify | numlist bullist | styles fontfamily fontsize forecolor backcolor | ltr rtl',
				],
				'quickbars_selection_toolbar' => 'bold italic | blocks | quicklink blockquote',
				'height'                      => 'min(500px, 33vh)',
				'width'                       => 'min(1000px, 100%)',
				'autoresize_bottom_margin'    => 100,
				'resize'                      => true,
				'promotion'                   => false,
				'branding'                    => false,
				'content_security_policy'     => "script-src 'none'",
				'skin'                        => 'akeeba',
				'importcss_exclusive'         => false,
				'browser_spellcheck'          => true,
				'relative_urls'               => false,
				'remove_script_host'          => false,
				'document_base_url'           => Uri::base(),
				'link_default_protocol'       => 'https',
			],
			$options
		);

		// Allow plugins to modify the editor configuration
		$container->eventDispatcher->trigger('onTinyMCEConfig', [$name, $id, &$options]);

		$document->addScriptOptions('panopticon.tinymce.config', [
			$id => $options
		]);

		$js = <<< JS
window.addEventListener('DOMContentLoaded', () => {
    const panopticonTinyMceInit = () => {
        if (typeof tinymce === "undefined") return;
        window.clearInterval(waitHandlerTimeout);
        tinymce.init(akeeba.System.getOptions('panopticon.tinymce.config')['$id']);
    };
    
    const waitHandlerTimeout = window.setInterval(panopticonTinyMceInit, 100);
});

JS;
		$document->addScriptDeclaration($js);

		return <<<HTML
<textarea class="tinyMceEditor" name="$name" id="$id">$content</textarea><a href="https://www.tiny.cloud/powered-by-tiny/?utm_campaign=editor_referral&utm_medium=poweredby&utm_source=tinymce&utm_content=v6" class="text-muted text-decoration-none" style="font-size: 6pt">Powered by TinyMCE</a>
HTML;
	}

	/**
	 * Initialises the TinyMCE editor JavaScript.
	 *
	 * @return void
	 */
	protected static function initialise()
	{
		if (self::$hasInitialised)
		{
			return;
		}

		self::$hasInitialised = true;

		// Include the JavaScript
		Template::addJs('media://tinymce/tinymce.js', async: true);
	}
}