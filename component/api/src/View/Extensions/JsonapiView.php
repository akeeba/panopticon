<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Panopticon\Api\View\Extensions;

(defined('AKEEBA') || defined('_JEXEC')) || die;

use Joomla\CMS\MVC\View\JsonApiView as BaseJsonApiView;

class JsonapiView extends BaseJsonApiView
{
	protected $fieldsToRenderList = [
		"extension_id",
		"type",
		"folder",
		"element",
		"client_id",
		"client_translated",
		"type_translated",
		"folder_translated",
		"state",
		"enabled",
		"access",
		"protected",
		"locked",
		"name",
		"description",
		"author",
		"authorUrl",
		"authorEmail",
		"version",
		"new_version",
		"detailsurl",
		"infourl",
		"changelogurl",
	];

	protected $fieldsToRenderItem = [
		"extension_id",
		"type",
		"folder",
		"element",
		"client_id",
		"client_translated",
		"type_translated",
		"folder_translated",
		"state",
		"enabled",
		"access",
		"protected",
		"locked",
		"name",
		"description",
		"author",
		"authorUrl",
		"authorEmail",
		"version",
		"new_version",
		"detailsurl",
		"infourl",
		"changelogurl",
	];
}