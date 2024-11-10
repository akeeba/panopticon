<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

?>
@include('Setup/cron', [
	'hideWhatsNext'      => true,
	'ctaLangString'      => 'PANOPTICON_MAIN_CRON_SUBHEAD_CTA',
	'disablePhpAccurate' => false,
])