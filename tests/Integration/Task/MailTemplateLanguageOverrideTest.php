<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Task;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Task\ActionSummaryEmail;
use Akeeba\Panopticon\Task\ExtensionsUpdate;
use Akeeba\Panopticon\Task\PluginsUpdate;
use Akeeba\Panopticon\Task\UpdateSummaryEmail;
use Akeeba\Panopticon\Tests\AbstractIntegrationTestCase;
use Awf\Date\Date;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Language-specific email template overrides must be used for both the HTML and the plain-text email body.
 *
 * Regression coverage: the plain-text template names were built as `<name>.<lang>text` (no dot before `text`), so a
 * `<name>.<lang>.text.blade.php` override — e.g. the German ones shipped in user_code — was never found, and the
 * English generic plain-text template (or an HTML-to-text conversion) was used instead. UpdateSummaryEmail also let
 * the generic template overwrite the language-specific rendering of the HTML body.
 *
 * Each test calls the task's private getRenderedResultsForEmail() directly. Overrides that do not ship with Panopticon
 * are written to user_code for the duration of the test only, and only if no such file already exists.
 *
 * @since 2.4.0
 */
class MailTemplateLanguageOverrideTest extends AbstractIntegrationTestCase
{
	private const SENTINEL_TEXT = 'PNPTC_TEST_LANGUAGE_PLAINTEXT_OVERRIDE';

	private const SENTINEL_HTML = 'PNPTC_TEST_LANGUAGE_HTML_OVERRIDE';

	/**
	 * Temporary template files created by the current test, to be removed in tearDown.
	 *
	 * @var string[]
	 */
	private array $temporaryFiles = [];

	protected function tearDown(): void
	{
		foreach ($this->temporaryFiles as $file)
		{
			if (is_file($file))
			{
				@unlink($file);
			}
		}

		$this->temporaryFiles = [];

		parent::tearDown();
	}

	public function testExtensionsUpdateUsesGermanPlainTextOverride(): void
	{
		$task = new ExtensionsUpdate($this->container);

		[$html, $text] = $this->invokeRender($task, 'de-DE', $this->makeUpdateStatus(), $this->makeSite());

		// Only present in user_code/ViewTemplates/Mailtemplates/mail_extensions_update_done.de-DE.text.blade.php
		$this->assertStringContainsString('wurde erfolgreich durchgeführt.', $text);
		$this->assertStringNotContainsString('was successful.', $text);
		// Only present in user_code/ViewTemplates/Mailtemplates/mail_extensions_update_done.de-DE.blade.php
		$this->assertStringContainsString('erfolgreich', $html);
	}

	public function testExtensionsUpdateFallsBackToGenericTemplatesWithoutLanguage(): void
	{
		$task = new ExtensionsUpdate($this->container);

		[$html, $text] = $this->invokeRender($task, '', $this->makeUpdateStatus(), $this->makeSite());

		$this->assertStringContainsString('The extension update for Test Site was successful.', $text);
		$this->assertNotEmpty($html);
	}

	public function testPluginsUpdateUsesLanguagePlainTextOverride(): void
	{
		$this->createTemporaryTemplate('mail_plugins_update_done.de-DE.text', self::SENTINEL_TEXT);

		$task = new PluginsUpdate($this->container);

		[, $text] = $this->invokeRender($task, 'de-DE', $this->makeUpdateStatus('plugin'), $this->makeSite());

		$this->assertStringContainsString(self::SENTINEL_TEXT, $text);
	}

	public function testUpdateSummaryEmailUsesGermanPlainTextOverride(): void
	{
		[, $text] = $this->renderUpdateSummary('de-DE');

		// Only present in user_code/ViewTemplates/Mailtemplates/mail_scheduled_update_summary.de-DE.text.blade.php
		$this->assertStringContainsString('Keine ausstehenden Core-Updates vorhanden.', $text);
		$this->assertStringNotContainsString('There are no pending core updates to report.', $text);
	}

	public function testUpdateSummaryEmailUsesGermanHtmlOverride(): void
	{
		[$html] = $this->renderUpdateSummary('de-DE');

		// Only present in user_code/ViewTemplates/Mailtemplates/mail_scheduled_update_summary.de-DE.blade.php
		$this->assertStringContainsString('Keine ausstehenden Core-Updates vorhanden.', $html);
		$this->assertStringNotContainsString('There are no pending core updates to report.', $html);
	}

	public function testActionSummaryEmailUsesLanguagePlainTextOverride(): void
	{
		$this->createTemporaryTemplate('mail_action_summary.de-DE', '<p>' . self::SENTINEL_HTML . '</p>');
		$this->createTemporaryTemplate('mail_action_summary.de-DE.text', self::SENTINEL_TEXT);

		$task = new ActionSummaryEmail($this->container);

		[$html, $text] = $this->invokeRender(
			$task, 'de-DE', [], $this->makeSite(), new Date('2026-01-01 00:00:00'), new Date('2026-01-02 00:00:00')
		);

		$this->assertStringContainsString(self::SENTINEL_HTML, $html);
		$this->assertStringContainsString(self::SENTINEL_TEXT, $text);
		$this->assertStringNotContainsString(self::SENTINEL_HTML, $text);
	}

	public function testActionSummaryEmailUsesGenericPlainTextOverrideWithoutLanguage(): void
	{
		$this->createTemporaryTemplate('mail_action_summary.text', self::SENTINEL_TEXT);

		$task = new ActionSummaryEmail($this->container);

		[$html, $text] = $this->invokeRender(
			$task, '', [], $this->makeSite(), new Date('2026-01-01 00:00:00'), new Date('2026-01-02 00:00:00')
		);

		$this->assertNotEmpty($html);
		$this->assertStringContainsString(self::SENTINEL_TEXT, $text);
	}

	private function renderUpdateSummary(string $language): array
	{
		$task = new UpdateSummaryEmail($this->container);

		$this->setPrivate($task, 'site', $this->makeSite());
		$this->setPrivate($task, 'coreUpdate', null);
		$this->setPrivate($task, 'extensionUpdates', []);
		$this->setPrivate($task, 'reportCoreUpdates', true);
		$this->setPrivate($task, 'reportExtensionUpdates', true);

		return $this->invokeRender($task, $language);
	}

	private function invokeRender(object $task, mixed ...$args): array
	{
		$method = new ReflectionMethod($task, 'getRenderedResultsForEmail');

		return $method->invoke($task, ...$args);
	}

	private function setPrivate(object $object, string $property, mixed $value): void
	{
		(new ReflectionProperty($object, $property))->setValue($object, $value);
	}

	private function makeSite(): Site
	{
		/** @var Site $site */
		$site = $this->container->mvcFactory->makeTempModel('Site');
		$site->bind(
			[
				'id'     => 1,
				'name'   => 'Test Site',
				'url'    => 'https://www.example.invalid/api',
				'config' => json_encode(['cmsType' => 'joomla']),
			]
		);

		return $site;
	}

	private function makeUpdateStatus(string $type = 'component'): array
	{
		return [
			[
				'type'       => $type,
				'name'       => 'Example Component',
				'status'     => 'success',
				'messages'   => [],
				'oldVersion' => '1.0.0',
				'newVersion' => '1.0.1',
			],
		];
	}

	private function createTemporaryTemplate(string $templateName, string $content): void
	{
		$file = APATH_USER_CODE . '/ViewTemplates/Mailtemplates/' . $templateName . '.blade.php';

		if (file_exists($file))
		{
			$this->markTestSkipped(sprintf('A user override %s already exists; not touching it.', $file));
		}

		if (!is_dir(dirname($file)))
		{
			$this->markTestSkipped(sprintf('The user override folder %s does not exist.', dirname($file)));
		}

		file_put_contents($file, "<?php defined('AKEEBA') || die; ?>\n" . $content . "\n");

		$this->temporaryFiles[] = $file;
	}
}
