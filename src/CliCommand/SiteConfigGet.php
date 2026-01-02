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
use Akeeba\Panopticon\Model\Sites;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'site:config:get',
	description: 'Get the value of a configuration parameter of a specific site',
	hidden: false,
)]
#[ConfigAssertion(true)]
class SiteConfigGet extends AbstractCommand
{
	use PrintFormattedArrayTrait;

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$siteId = $input->getArgument('site');
		$key    = $input->getArgument('key');

		$container = Factory::getContainer();
		/** @var Sites $site */
		$site = $container->mvcFactory->makeTempModel('Sites');

		try
		{
			$site->findOrFail($siteId);
		}
		catch (RuntimeException)
		{
			$this->ioStyle->error('No such site');

			return Command::FAILURE;
		}

		if ($key === 'config')
		{
			$this->ioStyle->error('You cannot get the config JSON object of a site record directly');

			return Command::FAILURE;
		}

		if (substr_count((string) $key, '.') > 2 || (str_contains((string) $key, '.') && !str_starts_with((string) $key, 'config.')))
		{
			$this->ioStyle->error('Invalid key');

			return Command::FAILURE;
		}

		if (!str_contains((string) $key, '.') && !$site->hasField($key))
		{
			$this->ioStyle->error('Invalid key');

			return Command::FAILURE;
		}

		$value = str_contains((string) $key, '.')
			? $site->getConfig()->get($key)
			: $site->getFieldValue($key);
		$format = $input->getOption('format') ?: 'table';

		$this->printFormattedScalar($value, $format);

		return Command::SUCCESS;
	}

	protected function configure(): void
	{
		$this
			->addArgument('site', InputArgument::REQUIRED, 'The numeric ID of the site')
			->addArgument('key', InputArgument::REQUIRED, 'The key of the option to get')
			->addOption(
				'format', 'f', InputOption::VALUE_OPTIONAL, 'Output format (table, json, yaml, csv)', 'table'
			);
	}

}