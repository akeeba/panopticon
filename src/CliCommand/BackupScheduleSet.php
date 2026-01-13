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
	name: 'backup:schedule:set',
	description: 'Update an existing backup schedule',
	hidden: false,
)]
#[ConfigAssertion(true)]
class BackupScheduleSet extends AbstractCommand
{
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$container = Factory::getContainer();
		/** @var Backupschedules $model */
		$model = $container->mvcFactory->makeTempModel('Backupschedules');

		$id = intval($input->getArgument('id'));

		try
		{
			$model->findOrFail($id);
		}
		catch (\Exception $e)
		{
			$this->ioStyle->error(sprintf('Could not find backup schedule %d', $id));

			return Command::FAILURE;
		}

		$cron        = $input->getOption('cron');
		$profileId   = $input->getOption('profile-id');
		$description = $input->getOption('description');
		$emailSuccess = $input->getOption('email-success');
		$emailFail    = $input->getOption('email-fail');

		if ($cron !== null)
		{
			$model->cron_expression = $cron;
		}

		$params = $model->getParams();

		if ($profileId !== null)
		{
			$params->set('profile_id', $profileId);
		}

		if ($description !== null)
		{
			$params->set('description', $description);
		}

		if ($emailSuccess !== null)
		{
			$params->set('email_success', (int)$emailSuccess);
		}

		if ($emailFail !== null)
		{
			$params->set('email_fail', (int)$emailFail);
		}

		$model->params = $params->toString();

		try
		{
			$model->save();
		}
		catch (\Exception $e)
		{
			$this->ioStyle->error('Failed to save backup schedule: ' . $e->getMessage());

			return Command::FAILURE;
		}

		$this->ioStyle->success(sprintf('Successfully updated backup schedule %d', $id));

		return Command::SUCCESS;
	}

	protected function configure(): void
	{
		$this
			->addArgument('id', InputOption::VALUE_REQUIRED, 'The numeric ID of the backup schedule')
			->addOption('cron', null, InputOption::VALUE_REQUIRED, 'CRON expression')
			->addOption('profile-id', null, InputOption::VALUE_REQUIRED, 'The numeric profile ID')
			->addOption('description', null, InputOption::VALUE_REQUIRED, 'Backup description')
			->addOption('email-success', null, InputOption::VALUE_REQUIRED, 'Send email on success? (1 or 0)')
			->addOption('email-fail', null, InputOption::VALUE_REQUIRED, 'Send email on failure? (1 or 0)');
	}

}
