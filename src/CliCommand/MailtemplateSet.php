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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'mailtemplate:set',
	description: 'Create or update a mail template',
	hidden: false,
)]
#[ConfigAssertion(true)]
class MailtemplateSet extends AbstractCommand
{
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$container = Factory::getContainer();
		/** @var Mailtemplates $model */
		$model = $container->mvcFactory->makeTempModel('Mailtemplates');

		$type      = $input->getOption('type');
		$language  = $input->getOption('language') ?: '*';
		$subject   = $input->getOption('subject');
		$html      = $input->getOption('html');
		$plaintext = $input->getOption('plaintext');

		if ($html === null)
		{
			// Read from stdin
			$html = file_get_contents('php://stdin');
		}

		if (empty($type))
		{
			$this->ioStyle->error('The --type option is required');

			return Command::FAILURE;
		}

		// Try to find existing template
		$existing = $model->where('type', '=', $type)->where('language', '=', $language)->first();

		if ($existing)
		{
			$model = $existing;
			$this->ioStyle->info(sprintf('Updating existing mail template for type "%s" and language "%s"', $type, $language));
		}
		else
		{
			$this->ioStyle->info(sprintf('Creating new mail template for type "%s" and language "%s"', $type, $language));
		}

		$data = [
			'type'     => $type,
			'language' => $language,
		];

		if ($subject !== null)
		{
			$data['subject'] = $subject;
		}

		if ($html !== null)
		{
			$data['html'] = $html;
		}

		if ($plaintext !== null)
		{
			$data['plaintext'] = $plaintext;
		}

		$model->bind($data);

		try
		{
			$model->save();
		}
		catch (\Exception $e)
		{
			$this->ioStyle->error('Failed to save mail template: ' . $e->getMessage());

			return Command::FAILURE;
		}

		$this->ioStyle->success(sprintf('Successfully saved mail template for type "%s" and language "%s"', $type, $language));

		return Command::SUCCESS;
	}

	protected function configure(): void
	{
		$this
			->addOption('type', 't', InputOption::VALUE_REQUIRED, 'The type of the mail template')
			->addOption('language', 'l', InputOption::VALUE_REQUIRED, 'The language of the mail template', '*')
			->addOption('subject', 's', InputOption::VALUE_REQUIRED, 'The subject of the mail template')
			->addOption('html', null, InputOption::VALUE_REQUIRED, 'The HTML body of the mail template. If omitted, it will be read from stdin.')
			->addOption('plaintext', 'p', InputOption::VALUE_REQUIRED, 'The plaintext body of the mail template. If omitted, it will be automatically generated from the HTML body.');
	}

}
