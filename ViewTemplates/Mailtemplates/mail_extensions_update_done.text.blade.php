<?php
/**
 * @var \Akeeba\Panopticon\View\Main\Html $this
 * @var array                             $updateStatus
 * @var \Akeeba\Panopticon\Model\Site     $site
 */

defined('AKEEBA') || die;

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
The extension updates for {{ $site->name }} have failed.
@else
The extension update for {{ $site->name }} has failed.
@endif
@elseif ($hasFailed)
Some extension updates for {{ $site->name }} have failed.
@else
@if($moreThanOne)
The extension updates for {{ $site->name }} were successful.
@else
The extension update for {{ $site->name }} was successful.
@endif
@endif

===========================================================================


@if($hasSuccess)
The following extensions have been updated successfully:
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

@foreach($updateStatus as $info)
<?php if ($info['status'] !== 'success') continue ?>
@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_' . $info['type']) “{{ $info['name'] }}”.

@if (!empty($info['messages']))
  Update messages:

  {{{ implode("\n  ", array_map('htmlentities', $info['messages']) ) }}}
@endif
@endforeach
@endif

@if($hasFailed)
The following extensions have failed to update:
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

@foreach($updateStatus as $info)
<?php if ($info['status'] === 'success') continue ?>
@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_' . $info['type']) “{{ $info['name'] }}”.

@if ($info['status'] === 'exception')
  An application or network error occurred.
@elseif ($info['status'] === 'invalid_json')
  The site's server returned a response we do not understand.
@elseif ($info['status'] === 'error')
  Your Joomla! site encountered an error trying to install the updated version.
@endif

@if (!empty($info['messages']))
  Update messages:
<br />
  {{{ implode("\n  ", array_map('htmlentities', $info['messages']) ) }}}
@endif
@endforeach
@endif
