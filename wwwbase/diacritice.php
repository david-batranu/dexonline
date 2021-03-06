<?php

require_once '../phplib/Core.php';
require_once '../phplib/AppLog.php';
require_once '../phplib/MemoryManagement.php';


DB::init();

$logFile = Config::get('crawler.diacritics_log');


class DiacriticsFixer {


	private static $a = ['defaultForm' => 'a', 'curvedForm' => 'ă', 'circumflexForm' => 'â'];
	private static $i = ['defaultForm' => 'i', 'curvedForm' => null, 'circumflexForm' => 'î'];
	private static $s = ['defaultForm' => 's', 'curvedForm' => 'ș', 'circumflexForm' => null];
	private static $t = ['defaultForm' => 't', 'curvedForm' => 'ț', 'circumflexForm' => null];

	private $resultText;
	private $lastOffset;
	private $hiddenText;
	private $selectCount;


	protected $currOffset;
	protected $text;
	protected $fileEndOffset;

	protected static $diacritics;
	protected static $nonLowerDiacritics;
	protected static $nonUpperDiacritics;
	protected static $paddingNumber;
	protected static $paddingChar;
	/*
	 * initialises instance variables
	 */
	function __construct() {
		crawlerLog("INSIDE " . __FILE__ . ' - ' . __CLASS__ . '::' . __FUNCTION__ . '() - ' . 'line '.__LINE__ );

		self::$diacritics = Config::get("crawler.diacritics");
		self::$nonLowerDiacritics = Config::get("crawler.non_lower_diacritics");
		self::$nonUpperDiacritics = mb_strtoupper(self::$nonLowerDiacritics);
		self::$paddingNumber = Config::get('crawler.diacritics_padding_length');
		self::$paddingChar = Config::get('crawler.padding_char');
		$this->selectCount = 0;
 	}

	/* returneaza urmatorul index in fisier care contine
	 * un caracter din lista [a,i,s,t]
	 */
	function getNextOffset() {
		crawlerLog("INSIDE " . __FILE__ . ' - ' . __CLASS__ . '::' . __FUNCTION__ . '() - ' . 'line '.__LINE__ );
		
		while($this->currOffset <= $this->textEndOffset) {
			//daca urmatorul offset e a,i,s,t sau ă,â,î,ș,ț
			crawlerLog(Str::getCharAt($this->text, $this->currOffset) . ' - offset ' .$this->currOffset);

			if (self::isPossibleDiacritic(Str::getCharAt($this->text, $this->currOffset))) {
				return $this->currOffset ++;
			}
			$this->currOffset ++;
		}
		return null;
	}

	static function isSeparator($ch) {
		crawlerLog("INSIDE " . __FILE__ . ' - ' . __CLASS__ . '::' . __FUNCTION__ . '() - ' . 'line '.__LINE__ );
		return !(ctype_alpha(Str::unicodeToLatin($ch)) || $ch == '-');
	}


	function processText($text) {
		crawlerLog("INSIDE " . __FILE__ . ' - ' . __CLASS__ . '::' . __FUNCTION__ . '() - ' . 'line '.__LINE__ );


		$this->currOffset = 0;
		$this->lastOffset = 0;

		$this->resultText = '';
		$this->hiddenText = '';
		$this->text = $text;

		$this->textEndOffset = mb_strlen($text) - 1;
		$offset = 0;
		while(($offset = $this->getNextOffset()) !== null ) {
			
			$this->leftAndRightPadding($offset);
		}

		//copiem de la ultimul posibil diacritic pana la final
		$lastChunk = mb_substr($this->text, $this->lastOffset, $this->textEndOffset - $this->lastOffset + 1);
		$this->resultText .= $lastChunk;
		$this->hiddenText .= $lastChunk;
	}


	function fix($text) {
		crawlerLog("INSIDE " . __FILE__ . ' - ' . __CLASS__ . '::' . __FUNCTION__ . '() - ' . 'line '.__LINE__ );
		if (mb_strlen($text) > Config::get('crawler.diacritics_buffer_limit')) {
			return "Dimensiune text prea mare, limita este de " .
			Config::get('crawler.diacritics_buffer_limit') . ' de caractere';
		}
		$this->processText($text);
		return $this->text2Html($this->resultText);
	}

	static function toLower($content) {
		crawlerLog("INSIDE " . __FILE__ . ' - ' . __CLASS__ . '::' . __FUNCTION__ . '() - ' . 'line '.__LINE__ );
		return mb_strtolower($content);
	}


	static function isPossibleDiacritic($ch) {
		crawlerLog("INSIDE " . __FILE__ . ' - ' . __CLASS__ . '::' . __FUNCTION__ . '() - ' . 'line '.__LINE__ );
		return strstr(self::$nonLowerDiacritics, $ch) ||
			strstr(self::$nonUpperDiacritics, $ch);
	}


	function leftAndRightPadding($offset) {
		crawlerLog("INSIDE " . __FILE__ . ' - ' . __CLASS__ . '::' . __FUNCTION__ . '() - ' . 'line '.__LINE__ );
		$before = '';
		$middle = Str::getCharAt($this->text, $offset);
		$after = '';
		$infOffset = $offset - 1;
		$supOffset = $offset + 1;
		$infPadding = false;
		$supPadding = false;
		

		for ($i = 0; $i < self::$paddingNumber; $i++) {
			
			if ($infOffset < 0) {
				//$before = self::$paddingChar . $before;
				$before = $before . self::$paddingChar;
			}
			else {
				if (!$infPadding) {
					$infCh = Str::getCharAt($this->text, $infOffset);
					$infPadding = self::isSeparator($infCh);
				}
				if ($infPadding) {
					//$before = self::$paddingChar . $before;
					$before = $before . self::$paddingChar;
				}
				else {
					//$before = $infCh . $before;
					$before = $before . $infCh;
					$infOffset --;
				}
			}	

			if ($supOffset > $this->textEndOffset) {
				$after = $after . self::$paddingChar;
			}
			else {
				if (!$supPadding) {
					$supCh = Str::getCharAt($this->text, $supOffset);
					$supPadding = self::isSeparator($supCh);
				}
				if ($supPadding) {
					$after = $after . self::$paddingChar;
				}
				else {
					$after = $after . $supCh;
					$supOffset ++;
				}
			}
		}


		crawlerLog("IN TEXT " . $before .'|' . $middle . '|' . $after);

		$tableObj = Diacritics::entryExists($before, $middle, $after);
		if ($tableObj != null) {
			crawlerLog("Entry Exists");
			$ch = $this->getAllCharForms($tableObj, $middle);
			$textSubstr = mb_substr($this->text, $this->lastOffset, $offset - $this->lastOffset);
			$this->resultText .= $textSubstr;

			$this->hiddenText .= $textSubstr;

			$this->resultText .= $ch;

			if (mb_strlen($ch) == 1) {
				$this->hiddenText .= $ch;
			}
			else {
				$this->hiddenText .= "@@".($this->selectCount - 1)."@@";
			}


		}
		else {
			$textSubstr = mb_substr($this->text, $this->lastOffset, $offset - $this->lastOffset + 1);
			$this->resultText .= $textSubstr;

			$this->hiddenText .= $textSubstr;
		}

		$this->lastOffset = $this->currOffset;
	}

	function getAllCharForms($tableObj, $middle) {
		crawlerLog("INSIDE " . __FILE__ . ' - ' . __CLASS__ . '::' . __FUNCTION__ . '() - ' . 'line '.__LINE__ );
		$ch = $tableObj->middle;
		//$ch = self::$a['circumflexForm'];

		$sortedSet = self::getCharOccurenceArray($tableObj);

		$charArray = $this->getCharArray($ch);

		crawlerLog("ARRAY ". print_r($sortedSet, true));

		//$key = key($sortedSet);//array_search($charArray[0], $charArray);
		//crawlerLog("WTF " . $key);
		//$ch = $charArray[$key];

		if (self::hasMoreVariants($sortedSet)) {
			$ch = $this->dropDownSelect($sortedSet, $charArray, $middle);
		}
		else {
			$ch  = self::getToUpperOrToLower($charArray[key($sortedSet)], $middle);
		}
		return $ch;
	}

	private function dropDownSelect($forms, $charArray, $middle) {

		$buffer = '<select name="'.$this->selectCount++.'">';

		foreach($forms as $form => $value) {

			if ($value > 0) {
				$ch = self::getToUpperOrToLower($charArray[$form], $middle);
				$buffer .= "<option value=\"".$ch."\">".$ch."</option>";
			}
		}

		$buffer .= '</select>';
		return $buffer;
	}

	static function hasMoreVariants($forms) {

		$i = 0;

		foreach($forms as $form => $value) {

			if ($value > 0) {

				$i++;
			}
		}

		return ($i > 1);
	}

	static function getToUpperOrToLower($val, $middle) {

		if ($middle == mb_strtolower($middle)) {
			return $val;
		}
		else {
			return mb_strtoupper($val);
		}
	}

	private function getCharArray($ch) {

		return self::$$ch;
	}

	private static function getCharOccurenceArray($tableObj) {

		$array = [
			'defaultForm' => $tableObj->defaultForm,
			'curvedForm' => $tableObj->curvedForm,
			'circumflexForm' => $tableObj->circumflexForm
			];
		//sort array desc
		arsort($array);
		return $array;
	}

	function getHiddenText() {

		return $this->hiddenText;
	}

	function text2Html($content) {

		//new line to <br> si tab to space(&nbsp;)
		return preg_replace('/[\t]/', '&nbsp;&nbsp;&nbsp;&nbsp;', nl2br($content));
	}

	function replaceDiacritics() {

		if (isset($_POST['hiddenText'])) {
			if ($_POST['hiddenText'] == '')
			return '';
			else {
				$search = [];
				$replace = [];

				$buffer = $_POST['hiddenText'];
				foreach($_POST as $key => $value) {

					if (is_numeric($key)) {
						$search[] = '/@@'.$key.'@@/i';
						$replace[] = $value;
					}
				}
				return preg_replace($search, $replace, $buffer);
			}
		}
		else {
			return '';
		}
	}

}


if (strstr( $_SERVER['SCRIPT_NAME'], 'diacritice.php')) {
	$obj = new DiacriticsFixer();

	if (isset($_POST['text']) && $_POST['text'] != '') {

		SmartyWrap::assign('textarea', '<div id="textInput">'.$obj->fix($_POST['text']).'</div>');
		SmartyWrap::assign('hiddenText', '<input type="hidden" name="hiddenText" value="'.$obj->getHiddenText().'">');
	}
	else {

		SmartyWrap::assign('textarea', '<textarea name="text" id="textInput" placeholder="introduceți textul aici">'.$obj->replaceDiacritics().'</textarea>');
		SmartyWrap::assign('hiddenText', '<input type="hidden" name="hiddenText" value="">');
	}


	SmartyWrap::display('diacritics_fix/diacritics_fix.tpl');
}
