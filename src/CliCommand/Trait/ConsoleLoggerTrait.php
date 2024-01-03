<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\CliCommand\Trait;

defined('AKEEBA') || die;

use Psr\Log\LogLevel;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

trait ConsoleLoggerTrait
{
	private function getConsoleLogger(OutputInterface $output, array $verbosityLevel = [], array $formatMap = []
	): ConsoleLogger
	{
		return new ConsoleLogger(
			$output,
			array_merge([
				LogLevel::EMERGENCY => OutputInterface::VERBOSITY_NORMAL,
				LogLevel::ALERT     => OutputInterface::VERBOSITY_NORMAL,
				LogLevel::CRITICAL  => OutputInterface::VERBOSITY_NORMAL,
				LogLevel::ERROR     => OutputInterface::VERBOSITY_NORMAL,
				LogLevel::WARNING   => OutputInterface::VERBOSITY_NORMAL,
				LogLevel::NOTICE    => OutputInterface::VERBOSITY_NORMAL,
				LogLevel::INFO      => OutputInterface::VERBOSITY_NORMAL,
				LogLevel::DEBUG     => OutputInterface::VERBOSITY_VERBOSE,
			], $verbosityLevel),
			array_merge([
				LogLevel::EMERGENCY => 'error',
				LogLevel::ALERT     => 'error',
				LogLevel::CRITICAL  => 'error',
				LogLevel::ERROR     => 'error',
				LogLevel::WARNING   => 'logwarning',
				LogLevel::NOTICE    => 'lognotice',
				LogLevel::INFO      => 'loginfo',
				LogLevel::DEBUG     => 'debug',
			], $formatMap)
		);
	}
}