<?php

/**
 * Plain text simplewiki formatter
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 * @license BSD License (http://silverstripe.org/BSD-License)
 */
class PlainFormatter extends SimpleWikiFormatter
{
	public function getFormatterName() {
		return "Plain";
	}

	public function getEditingField(DataObject $wikiPage) {
		return new TextareaField('Content', '', 30, 20);
	}


	public function formatContent(DataObject $wikiPage) {
		return $wikiPage->Content;
	}

	public function getHelpUrl() {
		return null;
	}
}