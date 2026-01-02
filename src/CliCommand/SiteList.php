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
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'site:list',
	description: 'List configured sites',
	hidden: false,
)]
#[ConfigAssertion(true)]
class SiteList extends AbstractCommand
{
	use PrintFormattedArrayTrait;

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$container = Factory::getContainer();
		/** @var Sites $model */
		$model = $container->mvcFactory->makeTempModel('Sites');

		// Apply filters
		$enabled = $input->getOption('enabled');

		if ($enabled !== null)
		{
			$model->setState('enabled', $enabled ? '1' : '0');
		}

		$search  = $input->getOption('search');

		if ($search !== null)
		{
			$model->setState('search', $search);
		}

		$coreUpdate = $input->getOption('core-update');

		if ($coreUpdate !== null)
		{
			$model->setState('coreUpdates', $coreUpdate ? '1' : '0');
		}

		$extensionUpdate = $input->getOption('extension-update');

		if ($extensionUpdate !== null)
		{
			$model->setState('extUpdates', $extensionUpdate ? '1' : '0');
		}

		$cmsFamily = $input->getOption('cms-family');

		if ($cmsFamily !== null)
		{
			$model->setState('cmsFamily', $cmsFamily);
		}

		$phpFamily = $input->getOption('php-family');

		if ($phpFamily !== null)
		{
			$model->setState('phpFamily', $phpFamily);
		}

		// Get the items, removing the configuration parameters
		$items = $model
			->get(true)
			->map(
				fn(Sites $x) => [
					'id'          => $x->id,
					'name'        => $x->name,
					'url'         => $x->getBaseUrl(),
					'enabled'     => $x->enabled,
					'created_by'  => $x->created_by,
					'created_on'  => $x->created_on,
					'modified_by' => $x->modified_by,
					'modified_on' => $x->modified_on,
				]
			);

		$this->printFormattedArray(
			$items->toArray(),
			$input->getOption('format') ?: 'table'
		);

		return Command::SUCCESS;
	}

	protected function configure(): void
	{
		$this
			->addOption(
				'format', 'f', InputOption::VALUE_OPTIONAL, 'Output format (table, json, yaml, csv, count)', 'table'
			)
			->addOption(
				'enabled', 'e', InputOption::VALUE_NEGATABLE, 'Only show enabled sites'
			)
			->addOption(
				'search', 's', InputOption::VALUE_OPTIONAL, 'Search among titles and URLs'
			)
			->addOption(
				'cms-family', null, InputOption::VALUE_OPTIONAL, 'Only show sites with this CMS family (e.g. 1.2)'
			)
			->addOption(
				'php-family', null, InputOption::VALUE_OPTIONAL, 'Only show sites with this PHP family (e.g. 1.2)'
			)
			->addOption(
				'core-update', null, InputOption::VALUE_NEGATABLE, 'Only show sites with available core updates'
			)
			->addOption(
				'extension-update', null, InputOption::VALUE_NEGATABLE, 'Only show sites with available extension updates'
			)
		;
	}

}