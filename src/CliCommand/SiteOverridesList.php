<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand;

defined('AKEEBA') || die;

use Akeeba\Panopticon\CliCommand\Attribute\ConfigAssertion;
use Akeeba\Panopticon\CliCommand\Trait\PrintFormattedArrayTrait;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Model\Overrides;
use Akeeba\Panopticon\Model\Sites;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'site:overrides:list',
	description: 'List overrides to check for a given site',
	hidden: false,
)]
#[ConfigAssertion(true)]
class SiteOverridesList extends AbstractCommand
{
	use PrintFormattedArrayTrait;

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$container = Factory::getContainer();
		/**
		 * @var Sites     $site
		 * @var Overrides $model
		 */
		$site  = $container->mvcFactory->makeTempModel('Sites');
		$model = $container->mvcFactory->makeTempModel('Overrides');

		$siteId = $input->getArgument('site_id');

		try
		{
			$site->findOrFail($siteId);
		}
		catch (RuntimeException)
		{
			$this->ioStyle->error('No such site');

			return Command::FAILURE;
		}

		$model->setSite($site);
		$model->setState('limitstart', 0);
		$model->setState('limit', 10000000);
		$overrides = $model
			->get(true)
			->map(
				fn(object $x) => [
					'id'       => $x->id,
					'template' => $x->template,
					'file'     => base64_decode($x->hash_id),
					'action'   => $x->action,
					'client'   => match ($x->client_id)
					{
						0 => 'Site',
						1 => 'Administrator',
						2 => 'Installation',
						3 => 'API',
						4 => 'CLI',
						default => '(Unknown)'
					},
					'created_date' => $x->created_date
				]
			)
			->toArray();

		$format = $input->getOption('format');

		$this->printFormattedArray($overrides, $format);

		return Command::SUCCESS;
	}

	protected function configure(): void
	{
		$this
			->addArgument(
				'site_id', InputOption::VALUE_REQUIRED, 'Numeric ID of the site to list template overrides for'
			)
			->addOption(
				'format', 'f', InputOption::VALUE_OPTIONAL, 'Output format (table, json, yaml, csv, count)', 'table'
			);
	}

}