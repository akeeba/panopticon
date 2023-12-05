<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Editor;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use Awf\Container\Container;
use Awf\Utils\Template;
use Delight\Random\Random;

/**
 * Integration of the TinyMCE editor with Panopticon
 */
abstract class ACE
{
	private static bool $hasInitialised = false;

	private static Container $container;

	public static function setContainer(Container $container): void
	{
		self::$container = $container;
	}

	/**
	 * Displays an ACE source code editor.
	 *
	 * The available syntax highlighting modes are:
	 * - `plain_text` No syntax highlighting
	 * - `css` CSS
	 * - `html` HTML5
	 *
	 * @param   string       $name     The name of the form input control
	 * @param   string|null  $content  Initial content
	 * @param   string       $mode     Syntax highlighting mode
	 *
	 * @return string
	 */
	public static function editor(string $name, ?string $content, string $mode = 'css', array $options = []): string
	{
		self::initialise();

		$container = Factory::getContainer();
		$document  = $container->application->getDocument();
		$content   = $content ?: '';
		$id        = $options['id'] ?? ('c9aceEditor_' . Random::alphaLowercaseString(32));
		$height    = $options['height'] ?? '70vh';

		if (isset($options['id']))
		{
			unset($options['id']);
		}

		if (isset($options['height']))
		{
			unset($options['height']);
		}

		if (!in_array($mode, ['plain_text', 'css', 'html']))
		{
			$mode = 'plain_text';
		}


		// See https://github.com/ajaxorg/ace/wiki/Configuring-Ace
		$options = array_merge(
			[
				'highlightActiveLine'       => true,
				'behavioursEnabled'         => true,
				'copyWithEmptySelection'    => true,
				'highlightGutterLine'       => true,
				'showPrintMargin'           => false,
				'themeLight'                => 'ace/theme/github',
				'themeDark'                 => 'ace/theme/dracula',
				'newLineMode'               => 'unix',
				'mode'                      => 'ace/mode/' . $mode,
				'enableBasicAutocompletion' => true,
				'enableLiveAutocompletion'  => true,
			],
			$options
		);

		$container->eventDispatcher->trigger('onACEEditorConfig', [$name, $id, &$options]);

		$document->addScriptOptions('panopticon.aceEditor', [
			$id => $options,
		]);

		$js = <<< JS
window.addEventListener('DOMContentLoaded', () => {
    const panopticonAceInit = () => {
        if (typeof ace === "undefined") return;
        window.clearInterval(waitHandlerTimeout);

        const editor = ace.edit('$id');
        let options = akeeba.System.getOptions('panopticon.aceEditor')['$id'];
        
        if (options['themeLight'] && options['themeDark'])
        {
            options['theme'] = (window.matchMedia("(prefers-color-scheme: dark)").matches ? options['themeDark'] : options['themeLight'])
        }
        
        editor.setOptions(options);
        editor.session.on('change', (delta) => {
            document.getElementById('{$id}_textarea').value = editor.getValue();
        })
    };
    
    const waitHandlerTimeout = window.setInterval(panopticonAceInit, 100);
});

JS;
		$document->addScriptDeclaration($js);

		$contentAlt = htmlentities($content);

		return <<<HTML
<textarea id="{$id}_textarea" name="$name" class="d-none">$content</textarea>
<div id="$id" style="white-space: pre; height: $height">$contentAlt</div>

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
		$app = self::$container->application;
		Template::addJs('media://ace/ace.js', $app);
		Template::addJs('media://ace/ext-language_tools.js', $app);
		Template::addCss('media://ace/css/ace.css', $app);
	}
}