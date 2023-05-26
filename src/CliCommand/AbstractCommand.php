<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand;

use Akeeba\Panopticon\CliCommand\Attribute\AppHeader;
use Akeeba\Panopticon\CliCommand\Attribute\ConfigAssertion;
use Akeeba\Panopticon\Factory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

defined('AKEEBA') || die;

abstract class AbstractCommand extends Command
{
	protected SymfonyStyle $ioStyle;

	protected InputInterface $cliInput;

	protected function initialize(InputInterface $input, OutputInterface $output)
	{
		// Initialise the I/O early
		$this->configureSymfonyIO($input, $output);
		$output->getFormatter()->setStyle('debug', new OutputFormatterStyle('gray'));
		$output->getFormatter()->setStyle('loginfo', new OutputFormatterStyle('bright-blue'));
		$output->getFormatter()->setStyle('logwarning', new OutputFormatterStyle('yellow', null, ['bold']));
		$output->getFormatter()->setStyle('lognotice', new OutputFormatterStyle('yellow'));
		// Make sure Panopticon is configured (unless explicitly asked to do otherwise)
		$this->assertConfigured();

		parent::initialize($input, $output);

		$this->header();
	}

	protected function header()
	{
		$showHeader = true;
		$cliApp     = $this->getApplication();

		$refObj         = new \ReflectionObject($this);
		$attributes     = $refObj->getAttributes(AppHeader::class);

		if (count($attributes) > 0)
		{
			$showHeader = $attributes[0]->getArguments()[0];
		}

		if ($showHeader && !$this->ioStyle->isQuiet())
		{
			$this->ioStyle->writeln($cliApp->getName() . ' <info>' . $cliApp->getVersion() . '</info>');

			$year = gmdate('Y');
			$this->ioStyle->writeln([
				"Copyright (c) 2023-$year  Akeeba Ltd",
				"",
				"<debug>Distributed under the terms of the GNU General Public License as published",
				"by the Free Software Foundation, either version 3 of the License, or (at your",
				"option) any later version. See LICENSE.txt.</debug>",
			]);

			$this->ioStyle->title($this->getDescription());
		}
	}

	protected function configureSymfonyIO(InputInterface $input, OutputInterface $output)
	{
		$this->cliInput = $input;
		$this->ioStyle  = new SymfonyStyle($input, $output);
	}

	protected function assertConfigured(): void
	{
		// Check for the #[ConfigAssertion(false)] attribute
		$needsAssertion = true;
		$refObj         = new \ReflectionObject($this);
		$attributes     = $refObj->getAttributes(ConfigAssertion::class);

		if (count($attributes) > 0)
		{
			$needsAssertion = $attributes[0]->getArguments()[0];
		}

		if (!$needsAssertion)
		{
			return;
		}

		$container = Factory::getContainer();

		if (!file_exists($container->appConfig->getDefaultPath()))
		{
			throw new \RuntimeException('You need to configure Akeeba Panopticon before running this command.', 125);
		}

		$container->appConfig->loadConfiguration();
	}
}