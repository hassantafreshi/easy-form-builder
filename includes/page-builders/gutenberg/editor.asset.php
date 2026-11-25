<?php
/**
 * QA1-007: the dependencies and version of editor.js, read by register_block_type() for block.json's editorScript
 * ("file:./editor.js"). WordPress registers the script with the block and, because block.json names the text domain,
 * loads its translations (wp_set_script_translations()).
 */
if (!defined('ABSPATH')) {
	exit;
}
return array(
	'dependencies' => array('wp-blocks', 'wp-i18n', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-api-fetch'),
	'version'      => defined('EMSFB_PLUGIN_VERSION') ? EMSFB_PLUGIN_VERSION : false,
);
