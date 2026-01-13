<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand;

defined('AKEEBA') || die;

use Akeeba\Panopticon\CliCommand\Attribute\ConfigAssertion;
use Akeeba\Panopticon\CliCommand\Trait\PrintFormattedArrayTrait;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Model\Mailtemplates;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'mailtemplate:delete',
	description: 'Delete a mail template',
	hidden: false,
)]
#[ConfigAssertion(true)]
class MailtemplateDelete extends AbstractCommand
{
	use PrintFormattedArrayTrait;

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$container = Factory::getContainer();
		/** @var Mailtemplates $model */
		$model = $container->mvcFactory->makeTempModel('Mailtemplates');

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
				sprintf('Could not delete mail template %d', $id),
				$e->getMessage()
			]);

			return Command::FAILURE;
		}

		$this->ioStyle->success(sprintf('Successfully deleted mail template %d', $id));

		return Command::SUCCESS;
	}

	protected function configure(): void
	{
		$this
			->addArgument('id', InputArgument::REQUIRED, 'The numeric mail template ID to delete');
	}

}
