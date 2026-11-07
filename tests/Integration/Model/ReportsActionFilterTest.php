<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Model;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Enumerations\ReportAction;
use Akeeba\Panopticon\Model\Reports;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\Tests\AbstractIntegrationTestCase;
use Awf\User\UserInterface;

class ReportsActionFilterTest extends AbstractIntegrationTestCase
{
	private const ALL_ACTIONS = [
		'backup',
		'core_update_found',
		'core_update_installed',
		'ext_update_found',
		'ext_update_installed',
	];

	private int $siteId;

	private UserInterface $previousUser;

	protected function setUp(): void
	{
		parent::setUp();

		$db = $this->container->db;
		$db->setQuery($db->getQuery(true)->delete($db->quoteName('#__reports')))->execute();

		$owner = $this->createUser(['parameters' => ['acl.panopticon.super' => 1]]);

		$manager  = $this->container->userManager;
		$property = (new \ReflectionObject($manager))->getProperty('currentUser');
		$property->setAccessible(true);
		$this->previousUser = $manager->getUser();
		$property->setValue($manager, $owner);

		/** @var Site $site */
		$site = $this->container->mvcFactory->makeTempModel('Site');
		$site->save(
			[
				'name'    => 'Report action filter test',
				'url'     => 'https://report-action-filter.example/',
				'enabled' => 1,
				'config'  => null,
			]
		);
		$this->siteId = (int) $site->getId();

		foreach (
			[
				ReportAction::CORE_UPDATE_FOUND,
				ReportAction::EXT_UPDATE_FOUND,
				ReportAction::CORE_UPDATE_INSTALLED,
				ReportAction::EXT_UPDATE_INSTALLED,
				ReportAction::BACKUP,
			] as $action
		)
		{
			$this->createReport($action);
		}
	}

	protected function tearDown(): void
	{
		$manager  = $this->container->userManager;
		$property = (new \ReflectionObject($manager))->getProperty('currentUser');
		$property->setAccessible(true);
		$property->setValue($manager, $this->previousUser);

		parent::tearDown();
	}

	public function testNoActionFilterReturnsAllActions(): void
	{
		$this->assertSame(self::ALL_ACTIONS, $this->getFilteredActions(null));
	}

	public function testAnEmptyActionFilterReturnsAllActions(): void
	{
		$this->assertSame(self::ALL_ACTIONS, $this->getFilteredActions([]));
	}

	public function testASingleActionFilterReturnsOnlyThatAction(): void
	{
		$this->assertSame(['ext_update_installed'], $this->getFilteredActions('ext_update_installed'));
	}

	public function testMultipleActionsReturnOnlyTheSelectedActions(): void
	{
		$this->assertSame(
			['core_update_installed', 'ext_update_installed'],
			$this->getFilteredActions(['core_update_installed', 'ext_update_installed'])
		);
	}

	public function testInvalidValuesAreRemovedFromTheActionFilter(): void
	{
		$this->assertSame(
			['ext_update_installed'],
			$this->getFilteredActions(['not_a_report_action', 'ext_update_installed'])
		);
	}

	public function testOnlyInvalidValuesDisableTheActionFilter(): void
	{
		$this->assertSame(self::ALL_ACTIONS, $this->getFilteredActions(['not_a_report_action']));
	}

	private function createReport(ReportAction $action): void
	{
		$db    = $this->container->db;
		$query = $db->getQuery(true)
			->insert($db->quoteName('#__reports'))
			->columns(
				[
					$db->quoteName('site_id'),
					$db->quoteName('created_on'),
					$db->quoteName('created_by'),
					$db->quoteName('action'),
					$db->quoteName('context'),
				]
			)
			->values(
				implode(
					', ',
					[
						$this->siteId,
						$db->quote($this->container->dateFactory('now', 'UTC')->toSql()),
						0,
						$db->quote($action->value),
						$db->quote('{}'),
					]
				)
			);

		$db->setQuery($query)->execute();
	}

	private function getFilteredActions(mixed $filter): array
	{
		$model = new Reports($this->container);
		$model->setIgnoreRequest(true);
		$model->setState('action', $filter);

		$actions = array_map(
			fn (Reports $item) => $item->action?->value,
			$model->getItemsArray()
		);
		sort($actions);

		return $actions;
	}
}
