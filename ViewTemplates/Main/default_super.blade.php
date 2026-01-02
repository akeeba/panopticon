<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

/** @var \Akeeba\Panopticon\View\Main\Html $this */

if (!$this->container->userManager->getUser()->getPrivilege('panopticon.super'))
{
    return;
}
?>

@include('Main/heartbeat')
@include('Main/cronfellbehind')
@include('Main/php_warnings')
@include('Main/selfupdate')

