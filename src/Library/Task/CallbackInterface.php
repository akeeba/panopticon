<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Task;

defined('AKEEBA') || die;

use Awf\Registry\Registry;

interface CallbackInterface
{
	public function __invoke(object $task, Registry $storage): int;

	public function getTaskType(): string;

	public function getDescription(): string;
}