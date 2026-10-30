<?php

/**
 *
 * Twig Converter. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2017, kasimi, https://kasimi.net
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = [];
}

$lang = array_merge($lang, [
	'TWIGCONVERTER_SELECT' => 'Convert template files',
	'TWIGCONVERTER_SELECT_EXPLAIN' => 'Select an extension or a style to convert its template files to Twig syntax. A zip file will be downloaded and also be saved to the board’s store directory.',
	'TWIGCONVERTER_SELECT_EXT' => 'Select extension or style',
	'TWIGCONVERTER_EXT' => 'Extension: %s',
	'TWIGCONVERTER_STYLE' => 'Style: %s',
	'TWIGCONVERTER_ERROR_NO_TEMPLATE_FILES' => 'The selected directory doesn’t contain any template files.',
	'TWIGCONVERTER_ERROR_WRITEABLE' => 'The destination directory isn’t writeable.',
	'TWIGCONVERTER_ERROR_ZIP' => 'Failed to create zip file. Error code: %d',
]);
