<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

use Akeeba\Panopticon\Factory;
use Awf\Pagination\Pagination;
use Awf\Pagination\PaginationObject;

/**
 * Method to create an active pagination link to the item
 *
 * @param   PaginationObject  $item  The object with which to make an active link.
 *
 * @return  string  HTML link
 */
function _akeeba_pagination_item_active(PaginationObject $item): string
{
	return <<< HTML
<a class="page-link" href="{$item->link}">{$item->text}</a>
HTML;
}

/**
 * Method to create an inactive pagination string
 *
 * @param   PaginationObject  $item  The item to be processed
 *
 * @return  string
 */
function _akeeba_pagination_item_inactive(PaginationObject $item)
{
	return <<< HTML
<a class="page-link">{$item->text}</a>
HTML;
}

/**
 * Create the html for a list footer
 *
 * @param   array  $list  Pagination list data structure.
 *
 * @return  string  HTML for a list start, previous, next,end
 * @see https://getbootstrap.com/docs/5.3/components/pagination/
 */
function _akeeba_pagination_list_render($list, Pagination $pagination)
{
	// Start the navigation.
	$html = <<< HTML
<nav aria-label="">
	<ul class="pagination">

HTML;

	// First Page
	if ($pagination->pagesStart > 1)
	{
		$link = _akeeba_pagination_preprocess_arrows($list['start']['data']);

		$class = ($list['start']['active'] ? '' : ' disabled');
		$html  .= <<< HTML
		<li class="page-item{$class}">$link</li>

HTML;
	}

	$class = $list['previous']['active'] ? '' : ' disabled"';
	$link = _akeeba_pagination_preprocess_arrows($list['previous']['data']);
	$html  .= <<< HTML
		<li class="page-item{$class}">$link</li>

HTML;

	foreach ($list['pages'] as $page)
	{
		$class = $page['active'] ? '' : ' disabled"';
		$html  .= <<< HTML
		<li class="page-item{$class}">{$page['data']}</li>

HTML;
	}

	$class = $list['next']['active'] ? '' : ' disabled"';
	$link = _akeeba_pagination_preprocess_arrows($list['next']['data']);
	$html  .= <<< HTML
		<li class="page-item{$class}">$link</li>

HTML;

	if ($pagination->pagesStop < $pagination->pagesTotal)
	{
		$class = $list['end']['active'] ? '' : ' disabled"';
		$link = _akeeba_pagination_preprocess_arrows($list['end']['data']);
		$html  .= <<< HTML
		<li class="page-item{$class}">$link</li>

HTML;
	}

	$html .= <<< HTML
	</ul>
</nav>

HTML;

	return $html;
}

/**
 * Replace arrows with icons
 *
 * AWF generates pagination arrows using double and single, left and right angled quotes. In FEF-based software we
 * prefer using elements from our icon font to render a more polished GUI.
 *
 * @param   string  $text  The source text with the angled quotes
 *
 * @return string The text after the replacements have run
 */
function _akeeba_pagination_preprocess_arrows($text)
{
	$lang = Factory::getContainer()->language;

	$replacements = [
		'&laquo;'  => $lang->text('PANOPTICON_LBL_LIST_START'),
		'&lsaquo;' => $lang->text('PANOPTICON_LBL_LIST_PREV'),
		'&raquo;'  => $lang->text('PANOPTICON_LBL_LIST_NEXT'),
		'&rsaquo;' => $lang->text('PANOPTICON_LBL_LIST_END'),
	];

	foreach ($replacements as $icon => $label)
	{
		if (str_contains($text, $icon))
		{
			$text = str_replace('<a class', sprintf(
				'<a aria-label="%s" class',
				$label
			), $text);

			$text = str_replace($icon, sprintf(
				'<span aria-hidden="true">%s</span>',
				$icon
			), $text);
		}

	}

	return $text;
}

/**
 * Create the HTML for a list footer
 *
 * @param   array  $list  Pagination list data structure.
 *
 * @return  string  HTML for a list footer
 */
function _akeeba_pagination_list_footer($list)
{
	$textNum = Factory::getContainer()->language->text('AWF_COMMON_LBL_DISPLAY_NUM');

	return <<< HTML
<div class="d-flex flex-column flex-md-row justify-content-center align-items-center align-items-md-baseline gap-2 my-2">
	{$list['pageslinks']}
	<div class="text-muted">
		{$list['pagescounter']}
	</div>
	<div class="ms-md-5">
		<span class="visually-hidden">
			<label id="limit-lbl" for="limit">$textNum</label>
		</span>
		{$list['limitfield']}
	</div>
	<input type="hidden" name="limitstart" value="{$list['limitstart']}">
</div>
HTML;
}
