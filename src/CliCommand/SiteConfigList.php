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
use Akeeba\Panopticon\Model\Sites;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'site:config:list',
	description: 'List configuration parameters of a specific site',
	hidden: false,
)]
#[ConfigAssertion(true)]
class SiteConfigList extends AbstractCommand
{
	use PrintFormattedArrayTrait;

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$siteId = $input->getArgument('site');

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

		$items  = array_merge(
			$site->getData(),
			$this->flattenObject($site->getConfig()->get('config'), 'config.')
		);
		$format = $input->getOption('format') ?: 'table';

		unset($items['config']);

		if ($format === 'table')
		{
			$temp = [];

			foreach ($items as $k => $v)
			{
				$temp[] = [$k, $v];
			}

			$this->ioStyle->table(['Key', 'Value'], $temp);

			return Command::SUCCESS;
		}

		$this->printFormattedArray(
			$items,
			$format
		);

		return Command::SUCCESS;
	}

	protected function flattenObject(object $object, string $prefix = ''): array
	{
		$ret = [];

		foreach ($object as $k => $v)
		{
			$key = $prefix . $k;

			if (is_scalar($v))
			{
				$ret[$key] = $v;

				continue;
			}

			$ret = array_merge($ret, $this->flattenObject((object) $v, $key . '.'));
		}

		return $ret;
	}

	protected function configure(): void
	{
		$this
			->addArgument('site', InputArgument::REQUIRED, 'The numeric ID of the site')
			->addOption(
				'format', 'f', InputOption::VALUE_OPTIONAL, 'Output format (table, json, yaml, csv, count)', 'table'
			);
	}
}