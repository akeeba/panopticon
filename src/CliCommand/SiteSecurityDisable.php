<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand;

defined('AKEEBA') || die;

use Akeeba\Panopticon\CliCommand\Attribute\ConfigAssertion;
use Akeeba\Panopticon\Container;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Model\Site;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'site:security:disable',
	description: 'Disable the Admin Tools system plugin on a site',
	hidden: false,
)]
#[ConfigAssertion(true)]
class SiteSecurityDisable extends AbstractCommand
{
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$container = Factory::getContainer();
		/**
		 * @var Site $site
		 */
		$site = $container->mvcFactory->makeTempModel('Sites');

		$siteId = $input->getArgument('site_id');

		try
		{
			$site->findOrFail($siteId);
		}
		catch (\Exception $e)
		{
			$this->ioStyle->error('No such site');

			return Command::FAILURE;
		}

		try
		{
			$result = $site->adminToolsPluginDisable();

			$config = $site->getConfig();
			$config->set('core.admintools.renamed', $result->renamed);
			$site->config = $config;
			$site->save();

			if (!$result->didChange)
			{
				$this->ioStyle->error('The System - Admin Tools plugin could not be disabled');

				return Command::FAILURE;
			}

			$this->ioStyle->success('The System - Admin Tools plugin is disabled');

			return Command::SUCCESS;
		}
		catch (\Exception $e)
		{
			$this->ioStyle->error([
				'Could not disable the System - Admin Tools plugin. API communication failure.',
				$e->getMessage()
			]);

			return Command::FAILURE;
		}
	}

	protected function configure(): void
	{
		$this
			->addArgument(
				'site_id', InputOption::VALUE_REQUIRED, 'Numeric ID of the site to disable the plugin in'
			);
	}
}