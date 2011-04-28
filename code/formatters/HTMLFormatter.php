<?php
/**
 * HTML simplewiki formatter
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 * @license BSD License (http://silverstripe.org/BSD-License)
 */
class HTMLFormatter extends SimpleWikiFormatter {

	public function getFormatterName() {
		return "HTML";
	}

	public function getEditingField(DataObject $wikiPage) {
		return new HtmlEditorField('Content', '', 30, 20);
	}

	public function formatContent(DataObject $wikiPage) {
		return $wikiPage->Content;
	}

	public function getHelpUrl() {
		return 'http://tinymce.moxiecode.com/';
	}

}
