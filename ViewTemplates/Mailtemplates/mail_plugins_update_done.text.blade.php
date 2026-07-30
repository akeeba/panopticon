<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

/**
 * @var \Akeeba\Panopticon\View\Mailtemplates\Html $this
 * @var array                                      $updateStatus
 * @var \Akeeba\Panopticon\Model\Site              $site
 */

defined('AKEEBA') || die;

$updateStatus = array_map(fn($x) => (array)$x, $updateStatus);

$hasFailed = array_reduce(
	$updateStatus,
	fn(bool $carry, array $item) => $carry || $item['status'] !== 'success',
	false
);

$hasSuccess = array_reduce(
	$updateStatus,
	fn(bool $carry, array $item) => $carry || $item['status'] === 'success',
	false
);

$moreThanOne = count($updateStatus) > 1;

?>
@if ($hasFailed && !$hasSuccess)
@if($moreThanOne)
The software updates for {{{ $site->name }}} have failed.
@else
The software update for {{{ $site->name }}} has failed.
@endif
@elseif ($hasFailed)
Some software updates for {{{ $site->name }}} have failed.
@else
@if($moreThanOne)
The software updates for {{{ $site->name }}} were successful.
@else
The software update for {{{ $site->name }}} was successful.
@endif
@endif

===========================================================================


@if($hasSuccess)
The following software has been updated successfully:
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

@foreach($updateStatus as $info)
<?php if ($info['status'] !== 'success') continue ?>
<?php
    $messages = array_map(function($item) {
	    $item    = is_object($item) ? (array) $item : $item;
	    $message = is_array($item) ? ($item['message'] ?? '') : $item;
	    $message = is_string($message) ? $message : '';
		$message = strip_tags($message);

		$type = is_array($item) ? ($item['type'] ?? 'info') : 'info';
		$type = is_string($type) ? $type : 'info';

		return sprintf('[%s] %s', strtoupper($type), strip_tags($message));
    }, $info['messages']);

    $oldVersion = is_scalar($info['oldVersion'] ?? null) ? trim((string) $info['oldVersion']) : '';
    $newVersion = is_scalar($info['newVersion'] ?? null) ? trim((string) $info['newVersion']) : '';
?>

@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_WP_' . $info['type']) “{{{ strip_tags($info['name']) }}}”.

@if ($oldVersion !== '' && $newVersion !== '')
  Updated from version {{{ $oldVersion }}} to version {{{ $newVersion }}}.
@elseif ($newVersion !== '')
  Updated to version {{{ $newVersion }}}.
@endif

@if (!empty($info['messages']))
  Update messages:

  {{ implode("\n  ", $messages ) }}
@endif
@endforeach
@endif

@if($hasFailed)
The following software has failed to update:
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

@foreach($updateStatus as $info)
<?php if ($info['status'] === 'success') continue ?>
<?php
$messages = array_map(function($item) {
	$item    = is_object($item) ? (array) $item : $item;
    $message = is_array($item) ? ($item['message'] ?? '') : $item;
    $message = is_string($message) ? $message : '';
    $message = strip_tags($message);

    $type = is_array($item) ? ($item['type'] ?? 'info') : 'info';
    $type = is_string($type) ? $type : 'info';

    return sprintf('[%s] %s', strtoupper($type), strip_tags($message));
}, $info['messages']);

$oldVersion = is_scalar($info['oldVersion'] ?? null) ? trim((string) $info['oldVersion']) : '';
$newVersion = is_scalar($info['newVersion'] ?? null) ? trim((string) $info['newVersion']) : '';
?>
@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_WP_' . $info['type']) “{{{ strip_tags($info['name']) }}}”.

@if ($oldVersion !== '' && $newVersion !== '')
  The update from version {{{ $oldVersion }}} to version {{{ $newVersion }}} failed.
  Version {{{ $oldVersion }}} is presumably still installed.
@elseif ($newVersion !== '')
  The update to version {{{ $newVersion }}} failed.
@endif

@if ($info['status'] === 'exception')
  An application or network error occurred.
@elseif ($info['status'] === 'invalid_json')
  The site's server returned a response we do not understand.
@elseif ($info['status'] === 'error')
  Your WordPress site encountered an error trying to install the updated version.
@endif

@if (!empty($info['messages']))
  Update messages:

  {{ implode("\n  ", $messages ) }}
@endif
@endforeach
@endif
