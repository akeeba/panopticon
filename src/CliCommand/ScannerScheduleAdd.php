<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand;

defined('AKEEBA') || die;

@error_reporting(E_ALL & ~E_DEPRECATED);

use Akeeba\Panopticon\CliCommand\Attribute\ConfigAssertion;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Model\Scannerschedules;
use Awf\Registry\Registry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'scanner:schedule:add',
	description: 'Add a new PHP File Change Scanner schedule',
	hidden: false,
)]
#[ConfigAssertion(true)]
class ScannerScheduleAdd extends AbstractCommand
{
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$container = Factory::getContainer();
		/** @var Scannerschedules $model */
		$model = $container->mvcFactory->makeTempModel('Scannerschedules');

		$siteId = $input->getOption('site-id');
		$cron   = $input->getOption('cron');

		if (empty($siteId))
		{
			$this->ioStyle->error('The --site-id option is required');

			return Command::FAILURE;
		}

		if (empty($cron))
		{
			$this->ioStyle->error('The --cron option is required');

			return Command::FAILURE;
		}

		$params = new Registry([
			'run_once' => '',
		]);

		$data = [
			'site_id'         => (int)$siteId,
			'type'            => 'filescanner',
			'enabled'         => 1,
			'cron_expression' => $cron,
			'params'          => $params->toString(),
			'priority'        => 0,
		];

		$model->bind($data);

		try
		{
			$model->save();
		}
		catch (\Exception $e)
		{
			$this->ioStyle->error('Failed to add scanner schedule: ' . $e->getMessage());

			return Command::FAILURE;
		}

		$this->ioStyle->success(sprintf('Successfully added scanner schedule %d for site %d', $model->id, $siteId));

		return Command::SUCCESS;
	}

	protected function configure(): void
	{
		$this
			->addOption('site-id', 's', InputOption::VALUE_REQUIRED, 'The numeric ID of the site')
			->addOption('cron', null, InputOption::VALUE_REQUIRED, 'CRON expression');
	}

}
