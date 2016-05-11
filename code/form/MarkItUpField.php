<?php
/**
 * A text field that wraps around the markitup JS plugin from
 * 
 * http://markitup.jaysalvat.com/
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 *
 */
class MarkItUpField extends TextareaField
{
	protected $markupType = 'wiki';
    
    protected $includeCallback;
	
	/**
	 * Includes the JavaScript neccesary for this field to work using the {@link Requirements} system.
	 */
	public static function include_js($type, $callback = null) {
		Requirements::javascript(THIRDPARTY_DIR.'/jquery/jquery.js');
		Requirements::javascript(THIRDPARTY_DIR.'/jquery-ui/jquery-ui.js');
		Requirements::javascript(THIRDPARTY_DIR .'/jquery-livequery/jquery.livequery.js');
		
		Requirements::javascript(THIRDPARTY_DIR .'/jquery-form/jquery.form.js');
		
		Requirements::javascript('simplewiki/javascript/markitup/jquery.markitup.js');

        if ($callback) {
            $callback($type);
        } else {
            Requirements::css('simplewiki/javascript/markitup/skins/markitup/style.css', 'all');
            switch ($type) {
                case 'wiki': {
                    Requirements::javascript('simplewiki/javascript/markitup/sets/wiki/set.js');
                    Requirements::css('simplewiki/javascript/markitup/sets/wiki/style.css');
                    break;
                }
                case 'markdown': {
                    Requirements::javascript('simplewiki/javascript/markitup/sets/markdown/set.js');
                    Requirements::css('simplewiki/javascript/markitup/sets/markdown/style.css');
                    break;
                }
                default: {
                }
            }
        }
	}
	
	
	/**
	 * @see TextareaField::__construct()
	 */
	public function __construct($name, $title = null, $type = 'wiki', $rows = 30, $cols = 20, $value = '', $form = null) {
		parent::__construct($name, $title, $rows, $cols, $value, $form);
		$this->markupType = $type;
	}

    public function setIncludeCallback($callback) {
        $this->includeCallback = $callback;
        return $this;
    }
    
    /**
	 * @return string
	 */
	public function Value() {
		return str_replace('&amp;#13;', '', htmlentities($this->value, ENT_COMPAT, 'UTF-8'));
	}
    
	/**
	 * @return string
	 */
	function Field($properties = array()) {
        self::include_js($this->markupType, $this->includeCallback);
        
		$settings = ucfirst($this->markupType);
		// add JS
		Requirements::customScript('jQuery().ready(function () { jQuery("#'.$this->id().'").markItUp(my'.$settings.'Settings)});');

        return parent::Field($properties);
	}
}
