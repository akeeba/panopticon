<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Application\ContainerServices;


use Awf\Container\Container;
use Awf\Session\Encoder\TransparentEncoder;
use Awf\Session\Segment;

defined('AKEEBA') || die;

class SegmentProvider
{
	public function __invoke(Container $c): Segment
	{
		$newSegment = $c->session->newSegment($c->session_segment_name);

		$newSegment->setEncoder(new TransparentEncoder());

		return $newSegment;
	}
}