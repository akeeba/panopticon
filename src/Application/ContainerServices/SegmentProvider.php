<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Application\ContainerServices;


use Akeeba\Panopticon\Library\Session\EncryptingEncoder;
use Awf\Container\Container;
use Awf\Session\Segment;

defined('AKEEBA') || die;

class SegmentProvider
{
	public function __invoke(Container $c): Segment
	{
		$segmentName = hash_hmac('md5', $c->application_name, $c->appConfig->get('secret', ''));

		$newSegment = $c->session->newSegment($segmentName);

		$newSegment->setEncoder(new EncryptingEncoder($c, true));

		return $newSegment;
	}
}