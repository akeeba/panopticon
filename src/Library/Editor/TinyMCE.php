<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Editor;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use Awf\Container\Container;
use Awf\Filesystem\File;
use Awf\Uri\Uri;
use Awf\Utils\Template;
use Delight\Random\Random;

/**
 * Integration of the TinyMCE editor with Panopticon
 */
abstract class TinyMCE
{
	private static bool $hasInitialised = false;

	private static Container $container;

	/**
	 * TinyMCE language map
	 * @var string[]
	 * @see https://www.tiny.cloud/get-tiny/language-packages/
	 */
	private const TINYMCE_LANG_MAP = [
		'ar'    => 'PANOPTICON_TINYMCE_LANG_AR',
		'ar_SA' => 'PANOPTICON_TINYMCE_LANG_AR_SA',
		'az'    => 'PANOPTICON_TINYMCE_LANG_AZ',
		'be'    => 'PANOPTICON_TINYMCE_LANG_BE',
		'bg_BG' => 'PANOPTICON_TINYMCE_LANG_BG_BG',
		'bn_BD' => 'PANOPTICON_TINYMCE_LANG_BN_BD',
		'ca'    => 'PANOPTICON_TINYMCE_LANG_CA',
		'cs'    => 'PANOPTICON_TINYMCE_LANG_CS',
		'cy'    => 'PANOPTICON_TINYMCE_LANG_CY',
		'da'    => 'PANOPTICON_TINYMCE_LANG_DA',
		'de'    => 'PANOPTICON_TINYMCE_LANG_DE',
		'el'    => 'PANOPTICON_TINYMCE_LANG_EL',
		'eo'    => 'PANOPTICON_TINYMCE_LANG_EO',
		'es'    => 'PANOPTICON_TINYMCE_LANG_ES',
		'es_MX' => 'PANOPTICON_TINYMCE_LANG_ES_MX',
		'et'    => 'PANOPTICON_TINYMCE_LANG_ET',
		'eu'    => 'PANOPTICON_TINYMCE_LANG_EU',
		'fa'    => 'PANOPTICON_TINYMCE_LANG_FA',
		'fi'    => 'PANOPTICON_TINYMCE_LANG_FI',
		'fr_FR' => 'PANOPTICON_TINYMCE_LANG_FR_FR',
		'ga'    => 'PANOPTICON_TINYMCE_LANG_GA',
		'gl'    => 'PANOPTICON_TINYMCE_LANG_GL',
		'he_IL' => 'PANOPTICON_TINYMCE_LANG_HE_IL',
		'hr'    => 'PANOPTICON_TINYMCE_LANG_HR',
		'hu_HU' => 'PANOPTICON_TINYMCE_LANG_HU_HU',
		'hy'    => 'PANOPTICON_TINYMCE_LANG_HY',
		'id'    => 'PANOPTICON_TINYMCE_LANG_ID',
		'is_IS' => 'PANOPTICON_TINYMCE_LANG_IS_IS',
		'it'    => 'PANOPTICON_TINYMCE_LANG_IT',
		'ja'    => 'PANOPTICON_TINYMCE_LANG_JA',
		'ka_GE' => 'PANOPTICON_TINYMCE_LANG_KA_GE',
		'kab'   => 'PANOPTICON_TINYMCE_LANG_KAB',
		'kk'    => 'PANOPTICON_TINYMCE_LANG_KK',
		'ko_KR' => 'PANOPTICON_TINYMCE_LANG_KO_KR',
		'ku'    => 'PANOPTICON_TINYMCE_LANG_KU',
		'lt'    => 'PANOPTICON_TINYMCE_LANG_LT',
		'lv'    => 'PANOPTICON_TINYMCE_LANG_LV',
		'nb_NO' => 'PANOPTICON_TINYMCE_LANG_NB_NO',
		'ne'    => 'PANOPTICON_TINYMCE_LANG_NE',
		'nl'    => 'PANOPTICON_TINYMCE_LANG_NL',
		'nl_BE' => 'PANOPTICON_TINYMCE_LANG_NL_BE',
		'oc'    => 'PANOPTICON_TINYMCE_LANG_OC',
		'pl'    => 'PANOPTICON_TINYMCE_LANG_PL',
		'pt_BR' => 'PANOPTICON_TINYMCE_LANG_PT_BR',
		'pt_PT' => 'PANOPTICON_TINYMCE_LANG_PT_PT',
		'ro'    => 'PANOPTICON_TINYMCE_LANG_RO',
		'ru'    => 'PANOPTICON_TINYMCE_LANG_RU',
		'sk'    => 'PANOPTICON_TINYMCE_LANG_SK',
		'sl_SI' => 'PANOPTICON_TINYMCE_LANG_SL_SI',
		'sq'    => 'PANOPTICON_TINYMCE_LANG_SQ',
		'sr'    => 'PANOPTICON_TINYMCE_LANG_SR',
		'sv_SE' => 'PANOPTICON_TINYMCE_LANG_SV_SE',
		'ta'    => 'PANOPTICON_TINYMCE_LANG_TA',
		'tg'    => 'PANOPTICON_TINYMCE_LANG_TG',
		'th_TH' => 'PANOPTICON_TINYMCE_LANG_TH_TH',
		'tr'    => 'PANOPTICON_TINYMCE_LANG_TR',
		'ug'    => 'PANOPTICON_TINYMCE_LANG_UG',
		'uk'    => 'PANOPTICON_TINYMCE_LANG_UK',
		'uz'    => 'PANOPTICON_TINYMCE_LANG_UZ',
		'vi'    => 'PANOPTICON_TINYMCE_LANG_VI',
		'zh_CN' => 'PANOPTICON_TINYMCE_LANG_ZH_CN',
		'zh_HK' => 'PANOPTICON_TINYMCE_LANG_ZH_HK',
		'zh_MO' => 'PANOPTICON_TINYMCE_LANG_ZH_MO',
		'zh_SG' => 'PANOPTICON_TINYMCE_LANG_ZH_SG',
		'zh_TW' => 'PANOPTICON_TINYMCE_LANG_ZH_TW',
	];

	public static function setContainer(Container $container): void
	{
		self::$container = $container;
	}

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
				'license_key'                 => 'gpl',
				'plugins'                     => [
					'advlist',
					'autolink',
					'autoresize',
					'code',
					'directionality',
					'fullscreen',
					'image',
					'importcss',
					'link',
					'lists',
					'media',
					'preview',
					'quickbars',
					'searchreplace',
					'table',
					'visualblocks',
					'wordcount',
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
				'skin'                        => ['oxide-dark', 'akeeba'],
				'content_css'                 => ['dark', 'akeeba'],
				'importcss_exclusive'         => false,
				'browser_spellcheck'          => true,
				'relative_urls'               => false,
				'remove_script_host'          => false,
				'document_base_url'           => Uri::base(),
				'link_default_protocol'       => 'https',
			],
			self::getTinyMCETranslationOptions(),
			$options
		);

		// Allow plugins to modify the editor configuration
		$container->eventDispatcher->trigger('onTinyMCEConfig', [$name, $id, &$options]);

		$document->addScriptOptions(
			'panopticon.tinymce.config', [
				$id => $options,
			]
		);

		$js = <<< JS
window.addEventListener('DOMContentLoaded', () => {
    const panopticonTinyMceInit = () => {
        if (typeof tinymce === "undefined") return;
        window.clearInterval(waitHandlerTimeout);
        let myConfig = akeeba.System.getOptions('panopticon.tinymce.config')['$id'];
        
        if (typeof myConfig["skin"] === "object") {
            const darkSkin = myConfig["skin"][0];
            const lightSkin = myConfig["skin"][1];
            myConfig["skin"] = (window.matchMedia("(prefers-color-scheme: dark)").matches ? darkSkin : lightSkin)
        }
        
        if (typeof myConfig['content_css'] === "object") {
            const darkCSS = myConfig["content_css"][0];
            const lightCSS = myConfig["content_css"][1];
            myConfig['content_css'] = (window.matchMedia("(prefers-color-scheme: dark)").matches ? darkCSS : lightCSS);
        }
        
        tinymce.init(myConfig);
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
		Template::addJs('media://tinymce/tinymce.js', self::$container->application, defer: true);
	}

	/**
	 * Attempt to find the most relevant TinyMCE language file.
	 *
	 * The preference cascade goes like this (most to least relevant):
	 * - Exact user's preference, e.g. de-DE
	 * - Best match user's preference, e.g. de
	 * - Exact system preference, e.g. de-DE
	 * - Best match system preference, e.g. de
	 * - English (default fallback if the language file does not exist)
	 *
	 * @return  array
	 */
	protected static function getTinyMCETranslationOptions(): array
	{
		$langFolder = APATH_MEDIA . '/tinymce/langs';

		if (!file_exists($langFolder) || !is_dir($langFolder) || !is_readable($langFolder))
		{
			return [];
		}

		$availableLanguages = [];
		$di                 = new \DirectoryIterator($langFolder);
		/** @var \DirectoryIterator $file */
		foreach ($di as $file)
		{
			if ($file->isDot() || $file->getExtension() !== 'js')
			{
				continue;
			}

			$baseLanguage = $file->getBasename('.' . $file->getExtension());

			if (!isset(self::TINYMCE_LANG_MAP[$baseLanguage]))
			{
				continue;
			}

			$availableLanguages[] = $baseLanguage;
		}

		$languagePreferences = [
			self::$container->userManager->getUser()->getParameters()->get('language'),
			self::$container->language->getLangCode(),
			'en-GB',
			'en',
		];

		$languagePreferences = array_filter($languagePreferences);
		$languagePreferences = array_unique($languagePreferences);

		$selectedLanguage = null;

		foreach ($languagePreferences as $lang)
		{
			$lang = str_replace('-', '_', $lang);

			if (in_array($lang, $availableLanguages))
			{
				$selectedLanguage = $lang;
				break;
			}

			if (!str_contains($lang, '_'))
			{
				continue;
			}

			[$partialLang, $country] = explode('_', $lang, 2);

			if (in_array($partialLang, $availableLanguages))
			{
				$selectedLanguage = $partialLang;
				break;
			}

			foreach ($availableLanguages as $mceLang)
			{
				if ($mceLang === $partialLang)
				{
					$selectedLanguage = $partialLang;
					break;
				}
			}
		}

		if (empty($selectedLanguage))
		{
			return [];
		}

		return [
			'language_load' => true,
			'language'      => $selectedLanguage,
		];
	}
}