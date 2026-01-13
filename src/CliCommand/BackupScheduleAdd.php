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
use Akeeba\Panopticon\Model\Backupschedules;
use Awf\Registry\Registry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'backup:schedule:add',
	description: 'Add a new backup schedule',
	hidden: false,
)]
#[ConfigAssertion(true)]
class BackupScheduleAdd extends AbstractCommand
{
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$container = Factory::getContainer();
		/** @var Backupschedules $model */
		$model = $container->mvcFactory->makeTempModel('Backupschedules');

		$siteId      = $input->getOption('site-id');
		$cron        = $input->getOption('cron');
		$profileId   = $input->getOption('profile-id') ?: 1;
		$description = $input->getOption('description');
		$emailSuccess = $input->getOption('email-success') ?? 0;
		$emailFail    = $input->getOption('email-fail') ?? 1;

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
			'run_once'      => '',
			'profile_id'    => $profileId,
			'description'   => $description ?: 'Scheduled remote backup with Panopticon taken on {DATE_FORMAT_LC7}',
			'email_success' => (int)$emailSuccess,
			'email_fail'    => (int)$emailFail,
		]);

		$data = [
			'site_id'         => (int)$siteId,
			'type'            => 'akeebabackup',
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
			$this->ioStyle->error('Failed to add backup schedule: ' . $e->getMessage());

			return Command::FAILURE;
		}

		$this->ioStyle->success(sprintf('Successfully added backup schedule %d for site %d', $model->id, $siteId));

		return Command::SUCCESS;
	}

	protected function configure(): void
	{
		$this
			->addOption('site-id', 's', InputOption::VALUE_REQUIRED, 'The numeric ID of the site')
			->addOption('cron', null, InputOption::VALUE_REQUIRED, 'CRON expression')
			->addOption('profile-id', null, InputOption::VALUE_REQUIRED, 'The numeric profile ID', 1)
			->addOption('description', null, InputOption::VALUE_OPTIONAL, 'Backup description')
			->addOption('email-success', null, InputOption::VALUE_REQUIRED, 'Send email on success? (1 or 0)', 0)
			->addOption('email-fail', null, InputOption::VALUE_REQUIRED, 'Send email on failure? (1 or 0)', 1);
	}

}
