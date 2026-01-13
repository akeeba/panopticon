<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand;

defined('AKEEBA') || die;

use Akeeba\Panopticon\CliCommand\Attribute\ConfigAssertion;
use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Model\Mailtemplates;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'mailtemplate:set:html',
	description: 'Set the HTML part of an existing mail template',
	hidden: false,
)]
#[ConfigAssertion(true)]
class MailtemplateSetHtml extends AbstractCommand
{
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$container = Factory::getContainer();
		/** @var Mailtemplates $model */
		$model = $container->mvcFactory->makeTempModel('Mailtemplates');

		$id       = $input->getArgument('id');
		$language = $input->getOption('language') ?: '*';
		$html     = $input->getOption('html');

		if ($html === null)
		{
			// Read from stdin
			$html = file_get_contents('php://stdin');
		}

		try
		{
			if (is_numeric($id))
			{
				$model->findOrFail((int) $id);
			}
			else
			{
				$model = $model->where('type', '=', (string) $id)
					->where('language', '=', (string) $language)
					->firstOrFail();
			}
		}
		catch (\Exception $e)
		{
			$this->ioStyle->error(sprintf('Could not find mail template "%s"', $id));

			return Command::FAILURE;
		}

		$model->bind(['html' => $html]);

		try
		{
			$model->save();
		}
		catch (\Exception $e)
		{
			$this->ioStyle->error('Failed to save mail template: ' . $e->getMessage());

			return Command::FAILURE;
		}

		$this->ioStyle->success(sprintf('Successfully updated HTML for mail template "%s"', $id));

		return Command::SUCCESS;
	}

	protected function configure(): void
	{
		$this
			->addArgument('id', InputArgument::REQUIRED, 'The numeric ID or the type of the mail template')
			->addOption(
				'language', 'l', InputOption::VALUE_OPTIONAL, 'The language of the mail template (if ID is a type)', '*'
			)
			->addOption('html', null, InputOption::VALUE_REQUIRED, 'The HTML body of the mail template. If omitted, it will be read from stdin.');
	}

}
