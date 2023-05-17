<div class="alert alert-info">
    @lang('PANOPTICON_MAILTEMPLATES_LBL_CSS_HEAD')
</div>

<form action="@route('index.php?view=mailtemplates&task=savecss')" class="my-4"
      method="post" name="adminForm" id="adminForm">

	<?= \Akeeba\Panopticon\Library\Editor\ACE::editor('css', $this->css) ?>
    <input type="hidden" name="task" value="">
    <input type="hidden" name="@token" value="1">
</form>