<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Library\Editor;

use Akeeba\Panopticon\Factory;
use Awf\Uri\Uri;
use Awf\Utils\Template;

defined('AKEEBA') || die;

/**
 * Integration of the TinyMCE editor with Panopticon
 */
abstract class TinyMCE
{
	private static bool $hasInitialised = false;

	public static function editor(string $name, ?string $content): string
	{
		self::initialise();

		$content = $content ?: '';

		return <<<HTML
<textarea class="tinyMceEditor">$content</textarea><a href="https://www.tiny.cloud/powered-by-tiny/?utm_campaign=editor_referral&utm_medium=poweredby&utm_source=tinymce&utm_content=v6" class="text-muted text-decoration-none" style="font-size: 6pt">Powered by TinyMCE</a>
HTML;
	}

	/**
	 * Initialises the TinyMCE editor JavaScript.
	 *
	 * This method is safe to call multiple times. It will only execute once.
	 *
	 * You can modify the editor configuration by handling the onTinyMCEConfig event. The callback signature is:
	 * ```
	 * function onTinyMCEConfig(array &$config): void
	 * ```
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

		// Pass the configuration to the front-end
		$config = [
			'selector'                    => 'textarea.tinyMceEditor',
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
			'content_security_policy'     => "script-src: 'none'",
			'skin'                        => 'akeeba',
			'importcss_exclusive'         => false,
			'browser_spellcheck'          => true,
			'relative_urls'               => false,
			'remove_script_host'          => false,
			'document_base_url'           => Uri::base(),
			'link_default_protocol'       => 'https',
			// link_default_protocol
		];

		$container = Factory::getContainer();

		// Allow plugins to modify the editor configuration
		$container->eventDispatcher->trigger('onTinyMCEConfig', [&$config]);

		$document = $container->application->getDocument();
		$document->addScriptOptions('panopticon.tinymce.config', $config);

		// Include the script to initialise TinyMCE
		$js = <<< JS
window.addEventListener('DOMContentLoaded', () => {
    const panopticonTinyMceInit = () => {
        if (!tinymce) return;
        window.clearInterval(waitHandlerTimeout);
        tinymce.init(akeeba.System.getOptions('panopticon.tinymce.config'));
    };
    
    const waitHandlerTimeout = window.setInterval(panopticonTinyMceInit, 100);
});

JS;
		$document->addScriptDeclaration($js);

	}
}