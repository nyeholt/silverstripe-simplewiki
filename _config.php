<?php

define('SIMPLEWIKI_DIR', dirname(__FILE__));

Director::addRules(100, array(
	'ssimplewiki/$Action' => 'WikiPage_Controller'
));

HtmlEditorConfig::get('default')->enablePlugins('sslinks');
HtmlEditorConfig::get('default')->insertButtonsBefore('advcode', 'ss_simplelink', 'unlink', 'ss_simpleimage');

// PERMISSION CONSTANTS
// To use these permissions, you MUST grant your wiki editor group the ability
// to  View draft content as well as the edit wiki pages. 
define('EDIT_WIKI', 'EDIT_WIKI');
define('MANAGE_WIKI_PAGES', 'MANAGE_WIKI_PAGES');

// Registration of wiki formatters
WikiPage::register_formatter(new MarkdownFormatter());
WikiPage::register_formatter(new HTMLFormatter());
WikiPage::register_formatter(new WikiFormatter());
WikiPage::register_formatter(new PlainFormatter());

// Example configuration options below
/*
WikiPage::$show_edit_button = true; // | false - whether public users get an edit link when viewing a wikipage
WikiPage::$auto_publish = true; // | false - whether pages are automatically published when saved/created
*/


/**
 * A simple helper function to deal with DB quoting. 
 */
if (!defined('SSAU_QUOTE_CHAR')) {

	define('SSAU_QUOTE_CHAR', defined('DB::USE_ANSI_SQL') ? '"' : '');

	/**
	 * Quote up a filter of the form
	 *
	 * array ("ParentID =" => 1)
	 *
	 *
	 *
	 * @param unknown_type $filter
	 * @return unknown_type
	 */
	function db_quote($filter = array(), $join = " AND ")
	{
		$string = '';
		$sep = '';

		foreach ($filter as $field => $value) {
			// first break the field up into its two components
			list($field, $operator) = explode(' ', trim($field));
			if (is_array($value)) {
				// quote each individual one into a string
				$ins = '';
				$insep = '';
				foreach ($value as $v) {
					$ins .= $insep . Convert::raw2sql($v);
					$insep = ',';
				}
				$value = '('.$ins.')';
			} else {
				$value = "'" . Convert::raw2sql($value) . "'";
			}
			
			if (strpos($field, '.')) {
				list($tb, $fl) = explode('.', $field);
				$string .= $sep . SSAU_QUOTE_CHAR . $tb . SSAU_QUOTE_CHAR . '.' . SSAU_QUOTE_CHAR . $fl . SSAU_QUOTE_CHAR . " $operator " . $value;
			} else {
				$string .= $sep . SSAU_QUOTE_CHAR . $field . SSAU_QUOTE_CHAR . " $operator " . $value;
			}
			$sep = $join;
		}

		return $string;
	}
}

?>