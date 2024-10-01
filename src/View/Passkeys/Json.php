<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Passkeys;

defined('AKEEBA') || die;

use Awf\Mvc\DataView\Json as BaseView;

class Json extends BaseView
{
	public mixed $response = null;

	public function display($tpl = null)
	{
		echo json_encode($this->response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}
}