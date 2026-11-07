<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Controller\Reports;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Application;
use Akeeba\Panopticon\Controller\Reports;
use Akeeba\Panopticon\Library\Enumerations\ReportAction;
use Akeeba\Panopticon\Tests\Integration\Controller\AbstractControllerIntegrationTestCase;
use Awf\Application\ApplicationServiceProvider;
use Awf\Uri\Uri;
use DOMDocument;
use DOMElement;
use DOMXPath;

class ActionFilterTest extends AbstractControllerIntegrationTestCase
{
	private array $savedServer = [];

	private array $savedMvcConfig = [];

	protected function setUp(): void
	{
		parent::setUp();

		$this->savedMvcConfig = $this->container['mvc_config'] ?? [];
		$this->container['mvc_config'] = [];

		if (!($this->container->application instanceof Application))
		{
			unset($this->container['application']);
			$this->container['application'] = new ApplicationServiceProvider();
		}

		$this->savedServer = $_SERVER;

		$this->loginAs((int) $this->createUser(['parameters' => ['acl.panopticon.super' => 1]])->getId());
	}

	protected function tearDown(): void
	{
		$_SERVER = $this->savedServer;
		$this->container['mvc_config'] = $this->savedMvcConfig;

		Uri::reset();

		parent::tearDown();
	}

	public function testTheFormContainsAMultiSelectWithEveryReportAction(): void
	{
		$select = $this->getActionSelect($this->renderReports());

		$this->assertSame('action[]', $select->getAttribute('name'));
		$this->assertTrue($select->hasAttribute('multiple'));
		$this->assertSame(
			array_map(fn (ReportAction $action) => $action->value, ReportAction::cases()),
			$this->getOptionValues($select, false)
		);
	}

	public function testTheFormRetainsTheSelectedActions(): void
	{
		$selected = ['core_update_installed', 'ext_update_installed'];
		$select   = $this->getActionSelect($this->renderReports(['action' => $selected]));

		$this->assertSame($selected, $this->getOptionValues($select, true));
	}

	public function testTheFormCanSubmitAnEmptyActionSelection(): void
	{
		$select = $this->getActionSelect($this->renderReports());
		$xpath  = new DOMXPath($select->ownerDocument);
		$inputs = $xpath->query('//input[@type="hidden" and @name="action[]" and @value=""]');

		$this->assertSame(1, $inputs->length);
	}

	public function testAnEmptyActionSubmissionClearsPreviouslySelectedActions(): void
	{
		$this->renderReports(['action' => ['ext_update_installed']]);
		$select = $this->getActionSelect($this->renderReports(['action' => ['']]));

		$this->assertSame([], $this->getOptionValues($select, true));
	}

	private function renderReports(array $extra = []): string
	{
		$query = array_merge(
			[
				'view' => 'reports',
				'task' => 'browse',
			],
			$extra
		);

		$_SERVER['HTTP_HOST']   = 'panopticon.test';
		$_SERVER['PHP_SELF']    = '/index.php';
		$_SERVER['REQUEST_URI'] = '/index.php?' . http_build_query($query);
		unset($_SERVER['HTTPS']);

		Uri::reset();

		ob_start();

		try
		{
			$this->dispatch(Reports::class, 'browse', $query, false);
		}
		finally
		{
			$html = ob_get_clean();
		}

		return $html;
	}

	private function getActionSelect(string $html): DOMElement
	{
		$dom = new DOMDocument();
		$dom->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
		$xpath = new DOMXPath($dom);
		$select = $xpath->query('//select[@id="action"]')->item(0);

		$this->assertInstanceOf(DOMElement::class, $select);

		return $select;
	}

	private function getOptionValues(DOMElement $select, bool $selectedOnly): array
	{
		$values = [];

		foreach ($select->getElementsByTagName('option') as $option)
		{
			if ($selectedOnly && !$option->hasAttribute('selected'))
			{
				continue;
			}

			$value = $option->getAttribute('value');

			if (!$selectedOnly && $value === '')
			{
				continue;
			}

			$values[] = $value;
		}

		return $values;
	}
}
