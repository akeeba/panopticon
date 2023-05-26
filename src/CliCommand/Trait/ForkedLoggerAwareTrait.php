<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand\Trait;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Library\Logger\ForkedLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

trait ForkedLoggerAwareTrait
{
	private function getForkedLogger(OutputInterface $output, array $otherLoggers = []): LoggerInterface
	{
		$loggers = array_merge(
			[
				Factory::getContainer()->logger,
				new ConsoleLogger(
					$output,
					[
						LogLevel::EMERGENCY => OutputInterface::VERBOSITY_NORMAL,
						LogLevel::ALERT     => OutputInterface::VERBOSITY_NORMAL,
						LogLevel::CRITICAL  => OutputInterface::VERBOSITY_NORMAL,
						LogLevel::ERROR     => OutputInterface::VERBOSITY_NORMAL,
						LogLevel::WARNING   => OutputInterface::VERBOSITY_NORMAL,
						LogLevel::NOTICE    => OutputInterface::VERBOSITY_NORMAL,
						LogLevel::INFO      => OutputInterface::VERBOSITY_NORMAL,
						LogLevel::DEBUG     => OutputInterface::VERBOSITY_VERBOSE,
					],
					[
						LogLevel::EMERGENCY => 'error',
						LogLevel::ALERT     => 'error',
						LogLevel::CRITICAL  => 'error',
						LogLevel::ERROR     => 'error',
						LogLevel::WARNING   => 'logwarning',
						LogLevel::NOTICE    => 'lognotice',
						LogLevel::INFO      => 'loginfo',
						LogLevel::DEBUG     => 'debug',
					]
				),
			],
			$otherLoggers
		);

		$loggers = array_filter($loggers, fn($x) => $x instanceof LoggerInterface);

		return new ForkedLogger($loggers);
	}

}