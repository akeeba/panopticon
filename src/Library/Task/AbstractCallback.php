<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Task;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Container;
use Akeeba\Panopticon\Library\Logger\ForkedLogger;
use Akeeba\Panopticon\Library\Task\Attribute\AsTask;
use Awf\Container\ContainerAwareInterface;
use Awf\Container\ContainerAwareTrait;
use Awf\Text\Language;
use Awf\Text\LanguageAwareInterface;
use Awf\Text\LanguageAwareTrait;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use ReflectionObject;

abstract class AbstractCallback
	implements CallbackInterface, LoggerAwareInterface, ContainerAwareInterface, LanguageAwareInterface
{
	use ContainerAwareTrait;
	use LanguageAwareTrait;

	protected readonly string $name;

	protected readonly string $description;

	protected ForkedLogger $logger;

	public function __construct(Container $container, ?Language $language = null)
	{
		$this->setContainer($container);
		$this->setLanguage($language ?? $container->language);

		$refObj     = new ReflectionObject($this);
		$attributes = $refObj->getAttributes(AsTask::class);

		if (!empty($attributes))
		{
			/** @var AsTask $attribute */
			$attribute         = $attributes[0]->newInstance();
			$this->name        = $attribute->getName();
			$this->description = $attribute->getDescription();
		}

		$this->logger = new ForkedLogger(
			[
				$this->getContainer()->loggerFactory->get($this->name),
			]
		);
	}

	public function setLogger(LoggerInterface $logger): void
	{
		$this->logger->pushLogger($logger);
	}

	final public function getTaskType(): string
	{
		return $this->name;
	}

	final public function getDescription(): string
	{
		return $this->description;
	}
}