<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Task;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Container;
use Akeeba\Panopticon\Library\Task\AbstractCallback;
use Akeeba\Panopticon\Library\Task\Attribute\AsTask;
use Akeeba\Panopticon\Library\Task\Status;
use Awf\Registry\Registry;
use Cesargb\Log\Exceptions\RotationFailed;
use Cesargb\Log\Rotation;
use DirectoryIterator;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

#[AsTask(
	name: 'logrotate',
	description: 'PANOPTICON_TASKTYPE_LOGROTATE'
)]
class LogRotate extends AbstractCallback
{
	public function __construct(Container $container)
	{
		parent::__construct($container);

		$this->logger->clearLoggers();
	}

	public function __invoke(object $task, Registry $storage): int
	{
		$appConfig          = $this->container->appConfig;
		$compress           = $appConfig->get('log_rotate_compress', true);
		$rotateFiles        = $appConfig->get('log_rotate_files', 3);
		$backupLogThreshold = $appConfig->get('log_backup_threshold', 14);

		if ($backupLogThreshold === 0 && $rotateFiles === 0)
		{
			$this->logger->notice('Nothing to do! Cannot rotate log files and/or remove old backup log files according to the current options.');

			return Status::OK->value;
		}

		$rotator = (new Rotation())
			->files($rotateFiles)
			->minSize(1048576)
			->truncate()
			->then(function (?string $filenameTarget, ?string $filenameRotated) {
				$this->logger->info(
					sprintf(
						'Rotated and compressed log file %s',
						$filenameTarget
					)
				);
			})
			->catch(function (RotationFailed $exception) {
				$this->logger->notice(
					sprintf(
						'Failed to rotate log file %s',
						$exception->getFilename()
					)
				);
			});

		if ($compress)
		{
			$rotator->compress();
		}

		$this->logger->info(sprintf('Scanning log folder %s', APATH_LOG));

		$di = new DirectoryIterator(APATH_LOG);

		foreach ($di as $file)
		{
			if (!$file->isFile() || $file->getExtension() !== 'log')
			{
				continue;
			}

			// SPECIAL CASE: Backup log files (backup_*.log)
			if (str_starts_with($file->getBasename(), 'backup_'))
			{
				if ($backupLogThreshold === 0)
				{
					continue;
				}

				$then = new \DateTimeImmutable('@' . $file->getCTime());
				$now  = new \DateTimeImmutable();
				$diff = $then->diff($now);

				if ($diff->days > $backupLogThreshold)
				{
					if (unlink($file->getPathname()))
					{
						$this->logger->info(
							sprintf('Deleted the old backup log file %s', $file->getPathname())
						);
					}
					else
					{
						$this->logger->notice(
							sprintf('Failed to delete the old backup log file %s', $file->getPathname())
						);
					}
				}

				continue;
			}

			if ($rotateFiles === 0)
			{
				continue;
			}

			$this->logger->debug(sprintf('Evaluating log file rotation for %s', $file->getBasename()));
			$rotator->rotate($file->getPathname());
		}

		return Status::OK->value;
	}
}