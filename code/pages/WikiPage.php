<?php
/**

Copyright (c) 2009, SilverStripe Australia PTY LTD - www.silverstripe.com.au
All rights reserved.

Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the 
      documentation and/or other materials provided with the distribution.
    * Neither the name of SilverStripe nor the names of its contributors may be used to endorse or promote products derived from this software 
      without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE 
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE 
LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE 
GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, 
STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY 
OF SUCH DAMAGE.
 
*/

/**
 * A page type that provides wiki like editing functionality. The  
 * initial goals are
 * 
 * - automatically create child pages based on [[page name]] style syntax
 * - be able to edit content via a wysiwyg mechanism
 * - add links by picking pages from the tree selection control
 * - add images picking images from the tree selection control
 * 
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 *
 */
class WikiPage extends Page
{
	
	public static $db = array(
		'EditorType' => "Enum('Inherit,HTML,Wiki,Plain', 'Inherit')",
		// Who was the last editor of the page?
		'WikiLastEditor' => 'Varchar(64)',
		'WikiLockExpiry' => 'SS_Datetime',
	);
	
	/**
	 * lock pages for 1 minute at a time by default
	 *
	 * This value is in seconds
	 *
	 * @var int
	 */
	public static $lock_time = 60;

		
	/**
	 * Set this to true in your mysite/_config.php file to force publishing
	 * as soon as you hit save. Removes the potentially awkward step of
	 * save/done/publish making pages seem to 'disappear'. Consider the 
	 * situation where a user creates a new page link (while editing a page in stage),
	 * then publishes the edited page, which takes them to the newly published
	 * page; the page they created hasn't been published, so there's now a 
	 * broken link. 
	 * 
	 *  By setting this to true, you more closely mirror the functionality of
	 *  other wikis that have save -> live 
	 * 
	 * @var boolean
	 */
	public static $auto_publish = true;
	
	/**
	 * Whether or not to allow public users to see the 'edit' button. If not
	 * set, the user must manually know to hit the 'edit' URL, or the assumption
	 * is that there is a separate module managing the login of users on the
	 * frontend of the website. 
	 * 
	 * @var boolean
	 */
	public static $show_edit_button = true;

	/**
	 * Before writing, convert any page links to appropriate 
	 * new, non-published, pages
	 * 
	 * @see sapphire/core/model/SiteTree#onBeforeWrite()
	 */
	protected function onBeforeWrite()
	{
		parent::onBeforeWrite();
		
		$newPages = $this->parseNewPagesFrom($this->Content);
		foreach ($newPages as $pageTitle) {
			$trimmedTitle = trim($pageTitle);
			
			// need to trim the title before doing the search
			$page = DataObject::get_one('WikiPage', "SiteTree.Title='".Convert::raw2sql($trimmedTitle)."'");

			if (!$page) {
				// it's a new page, so create that
				$page = new WikiPage();
				$page->Title = $trimmedTitle;
				$page->MenuTitle = $trimmedTitle;
				$page->ParentID = $this->ID;
				$page->write();
				
				// publish if we're on autopublish
				if (WikiPage::$auto_publish) {
					$page->doPublish();
				}
			}

			$replacement = '<a href="[sitetree_link id='.$page->ID.']">'.$pageTitle.'</a>';
			$this->Content = str_replace('[['.$pageTitle.']]', $replacement, $this->Content);
		}

		// set a lock expiry in the past if there's not one already set
		if (!$this->WikiLockExpiry) {
			$this->WikiLockExpiry = date('Y-m-d H:i:s');
		}

		// Make sure to set the last editor to the current user
		if (Member::currentUser()) {
			$this->WikiLastEditor = Member::currentUser()->Email;
		}
	}

	/**
	 * Returns whether or not the current user can edit this page
	 *
	 * If the
	 */
	public function canEdit($member=null)
	{
		$can = parent::canEdit($member);

		if (!$can) {
			// see if they can via the wiki permission explicitly
			$can = Permission::check(EDIT_WIKI);
		}

		return $can;
	}

	/**
	 * Get the CMS fields
	 * @see sapphire/core/model/SiteTree#getCMSFields()
	 */
	public function getCMSFields()
	{
		$fields = parent::getCMSFields();

		$fields->addFieldToTab('Root.Behaviour', new OptionsetField('EditorType', _t('WikiPage.EDITORTYPE', 'Editor Type'), array('Inherit'=>'Inherit','HTML'=>'HTML','Wiki'=>'Wiki','Plain'=>'Plain')));
		return $fields;
	}

	/**
	 * Separated into a separate method for testing
	 * 
	 * @param String $content
	 * @return array
	 */
	public function parseNewPagesFrom($content)
	{
		$pages = array();
		if (preg_match_all('/\[\[([\w\s_.-]+)\]\]/', $content, $matches)) {
			// exit(print_r($matches));
			foreach ($matches[1] as $pageTitle) {
				$pages[] = $pageTitle;
			}
		}

		return $pages;
	}

	/**
	 * Return the editor type to use for this item. Will interrogate
	 * parents if needbe
	 * 
	 * @return String
	 */
	public function getActualEditorType()
	{
		if ($this->EditorType && $this->EditorType != 'Inherit') {
			return $this->EditorType;
		}

		$parent = $this->getParent();
		$editorType = 'HTML';
		while ($parent != null && $parent instanceof WikiPage) {
			if ($parent->EditorType && $parent->EditorType != 'Inherit') {
				return $parent->EditorType;
			}
			$parent = $parent->getParent();
		}

		return 'HTML';
	}
	
	/**
	 * Retrieves the page's content, passed through any necessary parsing
	 * eg Wiki based content
	 * 
	 * @return String
	 */
	public function ParsedContent()
	{
		$type = $this->getActualEditorType();
		$content = $this->Content;
		switch ($type) {
			case 'Wiki': {
				$parser = &new WikiParser();
				$parser->emphasis = array();
				$parser->preformat = false;
				// need to change [] urls before parsing the text otherwise
				// the wiki parser breaks... 
				$content = preg_replace('/\[sitetree_link id=(\d+)\]/', '|sitetree_link id=\\1|', $content);
				$content = $parser->parse($content,'');
				$content = preg_replace('/\|sitetree_link id=(\d+)\|/', '[sitetree_link id=\\1]', $content);
				break;
			}
			default: {
				break;
			}
		}
		return $content;
	}

	/**
	 * Get the root of the wiki that this wiki page exists in
	 *
	 * @return WikiPage
	 */
	public function getWikiRoot()
	{
		$current = $this;
		$parent = $current->Parent();
		while ($parent instanceof WikiPage) {
			$current = $parent;
			$parent = $current->Parent();
		}
		return $current;
	}

	/**
	 * Lock the page for the current user
	 *
	 * @param Member $member
	 *			The user to lock the page for
	 */
	public function lock($member = null)
	{
		if (!$member) {
			$member = Member::currentUser();
		}

		// set the updated lock expiry based on now + lock timeout
		$this->WikiLastEditor = $member->Email;
		$this->WikiLockExpiry = date('Y-m-d H:i:s', time() + WikiPage::$lock_time);

		// save it with us as the editor
		$this->write();
	}
}


class WikiPage_Controller extends Page_Controller implements PermissionProvider
{

	
	static $allowed_actions = array(
		'linkselector',
		'edit',
		'StatusForm',
		'save',
		'done',
		'publish',
		'startediting',
		'EditForm',
		'LinkSelectForm',
		'objectdetails',
		'CreatePageForm',
		'delete',
		'addpage',
		'updatelock',
	);

	public function init()
	{
	    parent::init();
	    Requirements::javascript(THIRDPARTY_DIR.'/jquery/jquery.js');
	    Requirements::javascript('simplewiki/javascript/simplewiki.js');
		Requirements::css('simplewiki/css/simplewiki.css');
	}

	/**
	 * Define some permissions used for editing wiki pages
	 *
	 * @return array
	 */
	public function providePermissions()
	{
		return array(
			EDIT_WIKI => array (
				'name' =>  _t('WikiPage.PERM_EDIT', 'Edit Wiki Pages'),
				'category' => _t('WikiPage.WIKI_CATEGORY', 'Wiki'),
				'sort' => -100,
				'help' => _t('WikiPage.PERM_EDIT_HELP', 'Allows users to edit wiki pages')
			),
			MANAGE_WIKI_PAGES => array (
				'name' =>   _t('WikiPage.MANAGE_PAGES', 'Manage Wiki pages'),
				'category' => _t('WikiPage.WIKI_CATEGORY', 'Wiki'),
				'sort' => -100,
				'help' => _t('WikiPage.CREATE_PAGES_HELP', 'Display controls that allow users to create and delete aribtrary pages from the Wiki editing UI')
			),
		);
	}

	/**
	 * The form we're editing with
	 * 
	 * @var Form
	 */
	protected $form;

	/**
	 * Action handler for editing this wiki page
	 * 
	 * Creates a form that's used for editing the page's content, 
	 * as well as adding in a couple of additional toolbar actions
	 * for adding a simple link and a simple image
	 */
	public function edit()
	{
		HtmlEditorField::include_js();
		Requirements::javascript('simplewiki/javascript/sslinks/editor_plugin_src.js');

		$existing = $this->getEditingLocks($this->owner, true);
		// oops, we've somehow got here even though we shouldn't have
		if ($existing && $existing['user'] != Member::currentUser()->Email) {
			Director::redirect($this->owner->Link());
			return;
		}

		if(!$this->owner->canEdit()) {
			return Security::permissionFailure($this);
		}

		$this->form = $this->EditForm();

		// check who's editing and whether or not we should bail out
		return $this->renderWith(array('WikiPage', 'Page'));
	}

	/**
	 * Creates the form used for editing the page's content
	 * 
	 * @return Form
	 */
	public function EditForm()
	{
		$record = DataObject::get_by_id('WikiPage', $this->owner->ID);
		$editorType = $record->getActualEditorType();
		$editorField = null;
		switch ($editorType) {
			case 'Wiki': {
				$editorField = new MarkItUpField('Content', '', 'wiki', 30, 20, $record->Content);
				break;
			}
			case 'Plain': {
				$editorField = new TextareaField('Content', '', 30, 20, $record->Content);
			}
			case 'HTML': 
			default: {
				$editorField = new HtmlEditorField('Content', '', 30, 20, $record->Content);
				break;
			}			
		}

		$fields = new FieldSet(
			$editorField,
			new HiddenField('LockUpdate', '', $this->owner->Link('updatelock')),
			new HiddenField('LockLength', '', WikiPage::$lock_time - 10)
		);

		$actions = null;
		if (!WikiPage::$auto_publish) {
			$actions = new FieldSet(
				new FormAction('save', _t('WikiPage.SAVE','Save')),
				new FormAction('done', _t('WikiPage.DONE','Done (Draft)')),
				new FormAction('publish', _t('WikiPage.PUBLISH','Publish'))
			);
		} else {
			$actions = new FieldSet(
				new FormAction('save', _t('WikiPage.SAVE','Save')),
				new FormAction('publish', _t('WikiPage.FINISHED','Finished'))
			);
		}

		if (Permission::check(MANAGE_WIKI_PAGES)) {
			$actions->push(new FormAction('addpage_t', _t('WikiPage.ADD_PAGE', 'New Page')));
			$actions->push(new FormAction('delete', _t('WikiPage.DELETE_PAGE', 'Delete Page')));
		}

		return new Form($this, "EditForm", $fields, $actions);
	}

	/**
	 * Returns the form used to create new pages. If the current form is not set
	 * (ie the user is NOT currently editing), then we just return null.
	 *
	 * @return Form
	 */
	public function CreatePageForm()
	{
		$createOptions = array(
			'child' => 'As a child of the selected page',
			'sibling' => 'As a sibling of the selected page',
		);

		$pageTree = new TreeDropdownField('CreateContext', _t('WikiPage.CREATE_CONTEXT', 'Select an existing page'), 'WikiPage');
		$pageTree->setValue($this->ID);
		$pageTree->setTreeBaseID($this->data()->getWikiRoot()->ID);
		$fields = new FieldSet(
		    new TextField('NewPageName', _t('WikiPage.NEW_PAGE_NAME', 'New Page Name')),
		    $pageTree,
			new OptionsetField('CreateType', _t('WikiPage.CREATE_OPTIONS', 'and create the new page '), $createOptions, 'child')
		);

		$actions = new FieldSet(new FormAction('addpage', _t('WikiPage.ADD_PAGE', 'Create')));

		return new Form($this, 'CreatePageForm', $fields, $actions);
	}

	/**
	 * Deletes the current page and returns the user to the parent
	 * of the now deleted page.
	 *
	 */
	public function delete()
	{
		$page = $this->owner;
		/* @var $page Page */
		if ($page) {
			$parent = $page->Parent();
			$ID = $page->ID;

			$page->deleteFromStage('Live');

			// only fully delete if we're autopublishing stuff.. a bit counter
			// intuitive, but works pretty well
			if (WikiPage::$auto_publish) {
				$page->ID = $ID;
				$page->deleteFromStage('Stage');
			}

			Director::redirect($parent->Link());
			return;
		} 

		throw new Exception("Invalid request");
	}

	/**
	 * Creates an entirely new page as a child of the current page, or
	 * 'after' a selected page.
	 */
	public function addpage($args)
	{
		if (!Permission::check(MANAGE_WIKI_PAGES)) {
			return Security::permissionFailure($this);
		}

		$pageName = trim($args['NewPageName']);
		$createType = $args['CreateType'] ? $args['CreateType'] : 'child';
		if (!strlen($pageName)) {
			throw new Exception("Invalid page name");
		}

		$createContext = $this->owner;

		if ($args['CreateContext']) {
			$createContext = DataObject::get_by_id('WikiPage', $args['CreateContext']);
		}

		if (!$createContext instanceof WikiPage) {
			throw new Exception("You must select an existing wiki page.");
		}

		// now see whether to add the new page above, below or as a child
		$page = new WikiPage();
		$page->Title = $pageName;
		$page->MenuTitle = $pageName;

		switch ($createType) {
			case 'sibling': {
				$page->ParentID = $createContext->ParentID;
				break;
			}
			case 'child': 
			default: {
				$page->ParentID = $createContext->ID;
				break;
			}
		}

		$page->write();

		// publish if we're on autopublish
		if (WikiPage::$auto_publish) {
			$page->doPublish();
		}

		Director::redirect($page->Link('edit').'?stage=Stage');
	}

	/**
	 * 
	 * @param WikiPage $page
	 * @param array $data
	 * @return WikiPage
	 */
	protected function savePage($page, $form = null, $stage = 'Stage')
	{
		// save stuff then reuse the edit action
		if ($form) {
			$form->saveInto($page);
		}
		$page->Status = ($page->Status == "New page" || $page->Status == "Saved (new)") ? "Saved (new)" : "Saved (update)"; 
		$page->write($stage);
	}

	/**
	 * Save the submitted data
	 * 
	 * @return 
	 */
	public function save($data, $form)
	{
		if(!$this->owner->canEdit()) {
			return Security::permissionFailure($this);
		}

		$existing = $this->getEditingLocks($this->owner, true);
		// oops, we've somehow got here even though we shouldn't have
		if ($existing && $existing['user'] != Member::currentUser()->Email) {
			return "Someone somehow locked it while you were gone, this shouldn't happen like this :(";
		}

		$this->savePage($this->owner, $form);
		if (WikiPage::$auto_publish) {
			// do publish
			$this->owner->doPublish();
		}

		Director::redirect($this->owner->Link('edit').'?stage=Stage');
	}

	/**
	 * Complete editing and publish the data
	 * 
	 * @param mixed $data
	 * @param Form $form
	 */
	public function done($data, $form)
	{
		if(!$this->owner->canEdit()) {
			return Security::permissionFailure($this);
		}
		// save stuff then reuse the edit action
		$this->savePage($this->owner, $form);

		Director::redirect($this->owner->Link().'?stage=Stage');
	}
	
	/**
	 * Complete editing and publish the data
	 * 
	 * @param mixed $data
	 * @param Form $form
	 */
	public function publish($data, $form)
	{
		if(!$this->owner->canEdit()) {
			return Security::permissionFailure($this);
		}
		// save stuff then reuse the edit action
		$this->savePage($this->owner, $form);
		$this->owner->doPublish();

		// Make sure we're on the live content now
		Versioned::reading_stage('Live');
		
		// and go 
		Director::redirect($this->owner->Link().'?stage=Live');
	}

	/**
	 * We only want to output content if we're not in edit mode
	 * at all
	 * 
	 * @return String
	 */
	public function Content()
	{
		if ($this->form) {
			return '';
		}
		
		return $this->owner->ParsedContent(); //XML_val('Content');
	}

	/**
	 * Return the form to the user if it exists, otherwise some information
	 * about who is currently editing
	 * 
	 * @return Form
	 */
	public function Form()
	{
		// The editing form hasn't been put in place by the 'edit' action
		// so lets just show the status form
		$append = '';
		if (!$this->form) {
			if (WikiPage::$show_edit_button || $this->owner->canEdit()) {
				// create the information form 
				$this->form = $this->StatusForm();
			}
		} else {
			// if we have got an editing form, then we'll add a New Page
			// form if we have permissions to do so
			if (Permission::check(MANAGE_WIKI_PAGES)) {
				$append = $this->CreatePageForm()->forTemplate();
			}
		}

		return $this->form->forTemplate() . $append;
	}

	/**
	 * Gets the status form that is used by users to trigger the editing mode
	 * if they have the relevant access to it. 
	 * 
	 * @return Form
	 */
	public function StatusForm()
	{
		$existing = $this->getEditingLocks($this->owner);

		if ($existing && $existing['user'] != Member::currentUser()->Email) {
			$fields = new FieldSet(
				new ReadonlyField('ExistingEditor', '', _t('WikiPage.EXISTINGEDITOR', 'This page is currently locked for editing by '.$existing['user']. ' until ' . $existing['expires']))
			);
			$actions = new FieldSet();
		} else {
			$fields = new FieldSet();
			$actions = new FieldSet(
				new FormAction('startediting', _t('WikiPage.STARTEDIT','Edit Page'))
			);
		}

		return new Form($this, 'StatusForm', $fields, $actions);
	}

	/**
	 * Updates the lock timeout for the given object
	 * 
	 * @param <type> $data
	 */
	public function updatelock($data)
	{
		if ($this->owner->ID && $this->owner->canEdit()) {
			$lock = $this->getEditingLocks($this->owner, true);
			$response = new stdClass();
			$response->status = 1;
			if ($lock != null && $lock['user'] != Member::currentUser()->Email) {
				// someone else has stolen it !
				$response->status = 0;
				$response->message = _t('WikiPage.LOCK_STOLEN', "Another user (".$lock['user'].") has forcefully taken this lock");
			}
			return Convert::raw2json($response);
		}
	}
	
	/**
	 * Lock the page for editing
	 * 
	 * @param SiteTree $page
	 * 			The page being edited
	 * @param boolean $doLock 
	 * 			Whether to actually lock the page for ourselves
	 * @return array
	 * 			The names of any existing editors
	 */
	protected function getEditingLocks($page, $doLock=false)
	{
		$currentStage = Versioned::current_stage();

		Versioned::reading_stage('Stage');

		$filter = array(
			'WikiPage.ID =' => $page->ID,
			'WikiLockExpiry > ' => date('Y-m-d H:i:s'),
		);

		$filter = db_quote($filter);

		$user = Member::currentUser();
		$currentLock = DataObject::get_one('WikiPage', $filter);

		$lock = null;

		if ($currentLock && $currentLock->ID) {
			// if there's a current lock in place, lets return that value
			$lock = array(
				'user' => $currentLock->WikiLastEditor,
				'expires' => $currentLock->WikiLockExpiry,
			);
		}

		// If we're trying to take the lock, make sure that a) there's no existing
		// lock or b) we currently hold the lock
		if ($doLock && ($currentLock == null || !$currentLock->ID || $currentLock->WikiLastEditor == $user->Email)) {
			$page->lock();
		}

		Versioned::reading_stage($currentStage);

		return $lock;
	}


	/**
	 * Called to start editing this page
	 * 
	 */
	public function startediting()
	{
		Director::redirect($this->owner->Link('edit').'?stage=Stage');
	}
	
	/**
	 * Show the link selector
	 * 
	 * @return String
	 */
	public function linkselector()
	{
		return $this->renderWith(array('LinkSelectDialog'));
	}
	
	/**
	 * What kind of linking is the link selection form doing
	 * @return unknown_type
	 */
	public function LinkingType()
	{
		return isset($_GET['type']) ? $_GET['type'] : 'href';
	}

	/**
	 * 
	 * @return Form
	 */
	public function LinkSelectForm()
	{
		$type = isset($_GET['type']) ? $_GET['type'] : 'href';

		$fields = new FieldSet(
			new TreeDropdownField('TargetPage', _t('WikiPage.TARGETPAGE', 'Select Page'), 'SiteTree'),
			new TreeDropdownField('TargetFile', _t('WikiPage.TARGETIMAGE', 'Select Image'), 'File')
		);	

		$actions = new FieldSet(
			new FormAction('insert', _t('WikiPage.INSERTLINK','Insert'))
		);

		return new Form($this, 'LinkSelectForm', $fields, $actions);
	}
	
	/**
	 * Retrieves information about a selected image for the frontend
	 * image insertion tool - hacky for now, ideally need to pull through the
	 * backend ImageForm
	 * 
	 * @return string
	 */
	public function objectdetails()
	{
		$response = new stdClass;
		if (isset($_GET['ID'])) {
			$type = null;
			if (isset($_GET['type'])) {
				$type = $_GET['type'] == 'href' ? 'SiteTree' : 'File';	
			} else {
				$type = 'SiteTree';
			}

			$object = DataObject::get_by_id($type, $_GET['ID']);
			
			$response->Title = $object->Title;
			$response->Link = $object->Link();

			if ($object instanceof Image) {
				$response->Name = $object->Name;
				$response->Filename = $object->Filename;
				$response->width = $object->getWidth();
				$response->height = $object->getHeight();
			}

			$response->error = 0;
		} else {
			$response->error = 1;
			$response->message = "Invalid image ID";
		}
		echo json_encode($response);
	}
}

?>