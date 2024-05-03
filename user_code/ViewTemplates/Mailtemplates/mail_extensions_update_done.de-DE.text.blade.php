<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
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
Die Erweitwerungs-Updates für {{{ $site->name }}} sind fehlgeschlagen.
@else
Das Erweiterungs-Update für {{{ $site->name }}} ist fehlgeschlagen.
@endif
@elseif ($hasFailed)
Einige Erweiterungs-Updates für {{{ $site->name }}} sind fehlgeschlagen.
@else
@if($moreThanOne)
Die Erweiterungs-Updates für {{{ $site->name }}} wurden erfolgreich durchgeführt.
@else
Das Erweiterungs-Update für {{{ $site->name }}} wurde erfolgreich durchgeführt.
@endif
@endif

===========================================================================


@if($hasSuccess)
Die folgenden Erweiterungen wurden erfolgreich aktualisiert:
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

@foreach($updateStatus as $info)
<?php if ($info['status'] !== 'success') continue ?>
<?php
    $messages = array_map(function($item) {
	    $item    = is_object($item) ? (array) $item : $item;
	    $message = is_array($item) ? ($item['message'] ?? '') : $item;
	    $message = is_string($message) ? $message : '';
		$message = strip_tags($message);

		$type = is_array($item) ? ($item['type'] ?? 'info') : $item['type'];
		$type = is_string($type) ? $type : 'info';

		return sprintf('[%s] %s', strtoupper($type), strip_tags($message));
    }, $info['messages']);
?>

@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_' . $info['type']) “{{{ strip_tags($info['name']) }}}”.

@if (!empty($info['messages']))
  Update-Hinweise:

  {{ implode("\n  ", $messages ) }}
@endif
@endforeach
@endif

@if($hasFailed)
Die folgenden Erweiterungen konnten nicht aktualisiert werden:
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

@foreach($updateStatus as $info)
<?php if ($info['status'] === 'success') continue ?>
<?php
$messages = array_map(function($item) {
	$item    = is_object($item) ? (array) $item : $item;
    $message = is_array($item) ? ($item['message'] ?? '') : $item;
    $message = is_string($message) ? $message : '';
    $message = strip_tags($message);

    $type = is_array($item) ? ($item['type'] ?? 'info') : $item['type'];
    $type = is_string($type) ? $type : 'info';

    return sprintf('[%s] %s', strtoupper($type), strip_tags($message));
}, $info['messages']);
?>
@lang('PANOPTICON_SYSCONFIG_LBL_EXTTYPE_' . $info['type']) “{{{ strip_tags($info['name']) }}}”.

@if ($info['status'] === 'exception')
  Ein Anwendungs- oder Netzwerkfehler ist aufgetreten.
@elseif ($info['status'] === 'invalid_json')
  Der Site-Server antwortete mit einer unverständlichen Antwort.
@elseif ($info['status'] === 'error')
  Die Joomla! Site meldete einen Fehler beim Versuch, die aktualisierte Version zu installieren.
@endif

@if (!empty($info['messages']))
  Update-Hinweise:

  {{ implode("\n  ", $messages ) }}
@endif
@endforeach
@endif
