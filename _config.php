<?php

Director::addRules(100, array(
	'ssimplewiki/$Action' => 'WikiPage_Controller'
));

HtmlEditorConfig::get('default')->enablePlugins('sslinks');
HtmlEditorConfig::get('default')->insertButtonsBefore('advcode', 'ss_simplelink', 'unlink', 'ss_simpleimage');

// Example configuration options below
/*
WikiPage::$show_edit_button = true; // | false - whether public users get an edit link when viewing a wikipage
WikiPage::$auto_publish = true; // | false - whether pages are automatically published when saved/created
*/
?>