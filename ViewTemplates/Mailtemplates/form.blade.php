<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Mailtemplates\Html $this
 * @var \Akeeba\Panopticon\Model\Mailtemplates     $item
 */
$item = $this->getModel();
?>
<form action="@route('index.php?view=mailtemplates')" method="post"
      name="adminForm" id="adminForm" class="py-3"
>

    <div class="row mb-3">
        <label for="type" class="col-sm-3 col-form-label">
            @lang('PANOPTICON_MAILTEMPLATE_FIELD_TYPE')
        </label>
        <div class="col-sm-9">
            {{ $this->container->html->select->genericList(
            	\Akeeba\Panopticon\Model\Mailtemplates::getMailTypeOptions(),
            	'type',
            	[
		            'class'=>'form-select',
            	    'required' => 'required',
            	],
            	selected: $item->type,
            	idTag: 'type',
            	translate: true
            ) }}
        </div>
    </div>

    <div class="row mb-3">
        <label for="language" class="col-sm-3 col-form-label">
            @lang('PANOPTICON_MAILTEMPLATE_FIELD_LANGUAGE')
        </label>
        <div class="col-sm-9">
            <select name="language" id="language" class="form-select" disabled required>
                <option value="*">@lang('PANOPTICON_MAILTEMPLATES_OPT_LANGUAGE_ALL')</option>
            </select>
        </div>
    </div>

    <div class="row mb-3">
        <label for="subject" class="col-sm-3 col-form-label">
            @lang('PANOPTICON_MAILTEMPLATE_FIELD_SUBJECT')
        </label>
        <div class="col-sm-9">
            <input type="text" name="subject" id="subject" class="form-control"
                   value="{{{ $item->subject }}}" required>
        </div>
    </div>

    <div class="row mb-3">
        <label for="html" class="col-sm-3 col-form-label">
            @lang('PANOPTICON_MAILTEMPLATE_FIELD_HTML')
            <div class="form-text mt-4">
                @lang('PANOPTICON_MAILTEMPLATE_LBL_REMINDER_URLS')
            </div>
        </label>
        <div class="col-sm-9">
			{{ \Akeeba\Panopticon\Library\Editor\TinyMCE::editor(
	            'html',
	            $item->html,
	            [
					'id' => 'html',
					'relative_urls' => true,
					'content_style' => $this->css
                ]
            ) }}
        </div>
    </div>

    <div class="row mb-3">
        <label for="plaintext" class="col-sm-3 col-form-label">
            @lang('PANOPTICON_MAILTEMPLATE_FIELD_PLAINTEXT')
        </label>
        <div class="col-sm-9">
			<?= \Akeeba\Panopticon\Library\Editor\ACE::editor('plaintext', $item->plaintext, 'plaintext', ['id'     => 'plaintext',
			                                                                                               'height' => 'max(30vh, 500px)',
			]) ?>
        </div>
    </div>

    <input type="hidden" name="id" value="{{{ (int) $item->id }}}">
    <input type="hidden" name="task" value="">
    <input type="hidden" name="@token" value="1">

</form>