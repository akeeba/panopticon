<?php
defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\PhpVersion\PhpVersion;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\View\Main\Html;

/**
 * @var Html                  $this
 * @var Site                  $item
 * @var Awf\Registry\Registry $config
 * @var string                $php
 */

$phpVersion = new PhpVersion;

?>

@if (empty($php))
    <span class="badge bg-secondary-subtle">Unknown</span>
@elseif ($phpVersion->isEOL($php))
    <span class="text-danger">{{ $php }}</span>
@elseif ($phpVersion->isSecurity($php))
    <span class="text-body-tertiary">{{ $php }}</span>
@else
    <span class="text-body">{{ $php }}</span>
@endif
