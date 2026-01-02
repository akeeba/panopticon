<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

?>
@include('Mailtemplates/mail_action_summary', [
    'site'    => $this->getContainer()->mvcFactory->makeTempModel('Sites')->findOrFail($this->getModel()->getState('site_id', null)),
    'records' => $this->getModel()->get(true),
    'start'   => $this->getModel()->getState('from_date'),
    'end'     => $this->getModel()->getState('to_date')
])