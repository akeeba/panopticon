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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'mailtemplate:get',
	description: 'Get a mail template',
	hidden: false,
)]
#[ConfigAssertion(true)]
class MailtemplateGet extends AbstractCommand
{
	use PrintFormattedArrayTrait;

	protected function header(InputInterface $input)
	{
		if ($input->hasOption('only-body') && $input->getOption('only-body'))
		{
			return;
		}

		if ($input->getOption('only-html'))
		{
			return;
		}

		if ($input->getOption('only-plaintext'))
		{
			return;
		}

		parent::header($input);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$container = Factory::getContainer();
		/** @var Mailtemplates $model */
		$model = $container->mvcFactory->makeTempModel('Mailtemplates');

		$id            = $input->getArgument('id');
		$onlyBody      = $input->hasOption('only-body') && $input->getOption('only-body');
		$onlyHtml      = $input->getOption('only-html') || $onlyBody;
		$onlyPlaintext = $input->getOption('only-plaintext');

		try
		{
			if (is_numeric($id))
			{
				$model->findOrFail((int) $id);
			}
			else
			{
				// Assume it's a type. We might also need language.
				$language = $input->getOption('language') ?: '*';
				$model    = $model->where('type', '=', (string) $id)
					->where('language', '=', (string) $language)
					->firstOrFail();
			}
		}
		catch (\Exception $e)
		{
			$this->ioStyle->error(sprintf('Could not find mail template "%s"', $id));

			return Command::FAILURE;
		}

		if ($onlyHtml)
		{
			$output->write($model->html);

			return Command::SUCCESS;
		}

		if ($onlyPlaintext)
		{
			$output->write($model->plaintext);

			return Command::SUCCESS;
		}

		$data = [
			'id'        => $model->id,
			'type'      => $model->type,
			'language'  => $model->language,
			'subject'   => $model->subject,
			'html'      => $model->html,
			'plaintext' => $model->plaintext,
		];

		$this->printFormattedArray(
			$data,
			$input->getOption('format') ?: 'table'
		);

		return Command::SUCCESS;
	}

	protected function configure(): void
	{
		$this
			->addArgument('id', InputArgument::REQUIRED, 'The numeric ID or the type of the mail template')
			->addOption(
				'language', 'l', InputOption::VALUE_OPTIONAL, 'The language of the mail template (if ID is a type)', '*'
			)
			->addOption('only-html', null, InputOption::VALUE_NONE, 'Return only the HTML body of the template')
			->addOption('only-body', null, InputOption::VALUE_NONE, 'Alias for --only-html')
			->addOption(
				'only-plaintext', null, InputOption::VALUE_NONE, 'Return only the plaintext body of the template'
			)
			->addOption(
				'format', 'f', InputOption::VALUE_OPTIONAL, 'Output format (table, json, yaml, csv, count)', 'table'
			);
	}

}
