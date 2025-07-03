<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand;

defined('AKEEBA') || die;

use Akeeba\Panopticon\CliCommand\Attribute\ConfigAssertion;
use Akeeba\Panopticon\CliCommand\Trait\PrintFormattedArrayTrait;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Model\Groups;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'group:delete',
	description: 'Delete a group',
	hidden: false,
)]
#[ConfigAssertion(true)]
class GroupDelete extends AbstractCommand
{
	use PrintFormattedArrayTrait;

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$container = Factory::getContainer();
		/** @var Groups $model */
		$model = $container->mvcFactory->makeTempModel('Groups');

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
				sprintf('Could not delete group %d', $id),
				$e->getMessage()
			]);

			return Command::FAILURE;
		}

		return Command::SUCCESS;
	}

	protected function configure(): void
	{
		$this
			->addArgument('id', InputArgument::REQUIRED, 'The numeric group ID to delete');
	}

}