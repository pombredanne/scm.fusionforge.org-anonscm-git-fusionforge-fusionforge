<?php
/**
 * Copyright (c) STMicroelectronics, 2007. All Rights Reserved.
 *
 * Originally written by Manuel VACELET, 2007.
 * Copyright 2019, Franck Villaume - TrivialDev
 *
 * This file is a part of Fusionforge.
 *
 * Fusionforge is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Fusionforge is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Codendi. If not, see <http://www.gnu.org/licenses/>.
 */

require_once 'common/valid/Valid.class.php';

/**
 * Check that value is a decimal integer greater or equal to zero.
 * @package Codendi
 */
class Valid_UInt extends Valid {
	function validate($value) {
		$nri = new Rule_Int();
		$nrgoe = new Rule_GreaterOrEqual(0);
		$this->addRule($nri);
		$this->addRule($nrgoe);
		return parent::validate($value);
	}
}

/**
 * Check that group_id variable is valid
 */
class Valid_GroupId extends Valid {
	function __construct() {
		parent::__construct('group_id');
		//$this->setErrorMessage(_("Error: No group_id was chosen."));
	}

	function validate($value) {
		$this->addRule(new Rule_Int());
		$this->addRule(new Rule_GreaterThan(0));
		return parent::validate($value);
	}
}

/**
 * Check that 'pv' parameter is set to an acceptable value.
 */
class Valid_Pv extends Valid {
	function __construct() {
		parent::__construct('pv');
	}

	function validate($value) {
		$this->addRule(new Rule_WhiteList(array(0,1,2)));
		return parent::validate($value);
	}
}

/**
 * Check that value is a string (should always be true).
 */
class Valid_Text extends Valid {
	function validate($value) {
		$rs = new Rule_String();
		$this->addRule($rs);
		return parent::validate($value);
	}
}

/**
 * Check that value is a string with neither carrige return nor null char.
 */
class Valid_String extends Valid_Text {
	function validate($value) {
		$rnc = new Rule_NoCr();
		$this->addRule($rnc);
		return parent::validate($value);
	}
}

/**
 * Wrapper for 'WhiteList' rule
 */
class Valid_WhiteList extends Valid {
	function __construct($key, $whitelist) {
		parent::__construct($key);
		$rwl = new Rule_WhiteList($whitelist);
		$this->addRule($rwl);
	}
}

/**
 * Check that value match Codendi user short name format.
 *
 * This rule doesn't check that user actually exists.
 */
class Valid_UserNameFormat extends Valid_String {
	function validate($value) {
		$this->addRule(new Rule_UserNameFormat());
		return parent::validate($value);
	}
}


/**
 * Check that submitted value is a simple string and a valid Codendi email.
 */
class Valid_Email extends Valid_String {
	var $separator;

	function __construct($key=null, $separator=null) {
		if(is_string($separator)) {
			$this->separator = $separator;
		} else {
			$this->separator = null;
		}
		parent::__construct($key);
	}

	function validate($value) {
		$this->addRule(new Rule_Email($this->separator));
		return parent::validate($value);
	}
}

/**
 * Check uploaded file validity.
 */
class Valid_File extends Valid {

	/**
	 * Is uploaded file empty or not.
	 *
	 * @param	array	$file	One entry of $_FILES
	 * @return	bool
	 */
	function isEmptyValue($file) {
		if(!is_array($file)) {
			return false;
		} elseif (parent::isEmptyValue($file['name'])) {
			return false;
		}
		return true;
	}

	/**
	 * Check rules on given file.
	 *
	 * @param	array	$files	$_FILES superarray.
	 * @param	string	$index
	 * @return bool
	 */
	function validate($files, $index='') {
		if(is_array($files) && isset($files[$index])) {
			$this->addRule(new Rule_File());
			return parent::validate($files[$index]);
		} elseif($this->isRequired) {
			return false;
		}
		return true;
	}
}


class ValidFactory {
	/**
	 * If $validator is an instance of a Validator, do nothing and returns it
	 * If $validator is a string and a validator exists (Valid_String for 'string', Valid_UInt for 'uint', ...) then creates an instance and returns it
	 * Else returns null
	 */
	/* public static */
	function getInstance($validator, $key = null) {
		if (is_a($validator, 'Valid')) {
			return $validator;
		} elseif(is_string($validator) && class_exists('Valid_'.$validator)) {
			$validator_classname = 'Valid_'.$validator;
			return new $validator_classname($key);
		}
		return null;
	}
}
