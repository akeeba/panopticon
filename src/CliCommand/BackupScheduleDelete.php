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
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'backup:schedule:delete',
	description: 'Delete a backup schedule',
	hidden: false,
)]
#[ConfigAssertion(true)]
class BackupScheduleDelete extends AbstractCommand
{
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$container = Factory::getContainer();
		/** @var Backupschedules $model */
		$model = $container->mvcFactory->makeTempModel('Backupschedules');

		$id = intval($input->getArgument('id'));

		try
		{
			$model
				->findOrFail($id)
				->delete();
		}
		catch (\Exception $e)
		{
			$this->ioStyle->error([
				sprintf('Could not delete backup schedule %d', $id),
				$e->getMessage()
			]);

			return Command::FAILURE;
		}

		$this->ioStyle->success(sprintf('Successfully deleted backup schedule %d', $id));

		return Command::SUCCESS;
	}

	protected function configure(): void
	{
		$this
			->addArgument('id', InputArgument::REQUIRED, 'The numeric backup schedule ID to delete');
	}

}
