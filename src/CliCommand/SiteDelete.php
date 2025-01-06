<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand;

defined('AKEEBA') || die;

use Akeeba\Panopticon\CliCommand\Attribute\ConfigAssertion;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Model\Sites;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'site:delete',
	description: 'Delete a site',
	hidden: false,
)]
#[ConfigAssertion(true)]
class SiteDelete extends AbstractCommand
{
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$container = Factory::getContainer();
		/** @var Sites $model */
		$model = $container->mvcFactory->makeTempModel('Sites');

		$id = intval($input->getArgument('id'));

		$model
			->findOrFail($id)
			->delete();

		return Command::SUCCESS;
	}

	protected function configure(): void
	{
		$this
			->addArgument('id', InputArgument::REQUIRED, 'The numeric site ID to delete');
	}

}