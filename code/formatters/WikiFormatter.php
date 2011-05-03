<?php


/**
 * Wiki style formatter
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 * @license BSD License (http://silverstripe.org/BSD-License)
 */
class WikiFormatter extends SimpleWikiFormatter {

	public function getFormatterName() {
		return "Wiki";
	}

	public function getEditingField(DataObject $wikiPage) {
		return new MarkItUpField('Content', '', 'wiki', 30, 20);
	}

	public function formatContent(DataObject $wikiPage) {
		include_once SIMPLEWIKI_DIR . '/thirdparty/wikiparser-1.0/class_WikiParser.php';
		$content = $wikiPage->Content;
		$parser = &new WikiParser();
		$parser->emphasis = array();
		$parser->preformat = false;
		// need to change [] urls before parsing the text otherwise
		// the wiki parser breaks...
		$content = preg_replace('/\[sitetree_link id=(\d+)\]/', '|sitetree_link id=\\1|', $content);
		$content = $parser->parse($content, '');
		$content = preg_replace('/\|sitetree_link id=(\d+)\|/', '[sitetree_link id=\\1]', $content);

		return $content;
	}

	public function getHelpUrl() {
		return null;
	}

}

?>