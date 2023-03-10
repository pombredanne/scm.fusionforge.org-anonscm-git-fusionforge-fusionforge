<?php
/**
 * Artifact Expression
 *
 * Copyright 2017 Stéphane-Eymeric Bredthauer - TrivialDev
 * http://fusionforge.org/
 *
 * This file is part of FusionForge. FusionForge is free software;
 * you can redistribute it and/or modify it under the terms of the
 * GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the Licence, or (at your option)
 * any later version.
 *
 * FusionForge is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with FusionForge; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

require_once $gfcommon.'include/FFError.class.php';
require_once $gfwww.'include/expression.php';

class ArtifactExpression extends FFError {

	private $expression;
	private $arithmeticOperators = array (
						array('=','Assignment','a = b'),
						array('+','Addition','a + b'),
						array('-','Subtraction','a - b'),
						array('-','Unary minus','-a'),
						array('*','Multiplication','a * b'),
						array('/','Division','a / b'),
						array('%','Modulo (integer remainder)','a % b'),
						array('^','Power','a ^ b')
					);
	private $comparisonOperators = array (
						array('==','Equal to','a == b'),
						array('!=','Not equal to','a != b'),
						array('>','Greater than','a > b'),
						array('<','Less than','a < b'),
						array('>=','Greater than or equal to','a >= b'),
						array('<=','Less than or equal to','a <= b'),
						array('=~','Regex match','a =~ regex')
					);
	private $logicalOperators = array(
						array('!','Logical negation (NOT)','!a'),
						array('&&','Logical AND','a && b'),
						array('||','Logical OR','a || b')
					);

	private $otherOperators = array(
						array('?:','Conditional operator','a ? b : c')
					);

	private $functionsDescription = array();

	public function __construct() {
		$this->functionsDescription['in_array'] = _('Checks if a value exists in an (json) array');
		$this->functionsDescription['datetime_add'] = _('Adds to a date/time a duration (duration in ISO 8601 Format)');
		$this->functionsDescription['datetime_sub'] = _('Subtracts to a date/time a duration (duration in ISO 8601 Format)');
		$this->functionsDescription['intval'] = _('Get the integer value of a variable');
		$this->expression = new Expression;
		$this->expression->suppress_errors = true;
		$this->expression->fb = array();
		$this->expression->functions ['in_array'] = 'expr_in_array';
		$this->expression->functions ['datetime_add'] = 'expr_datetime_add';
		$this->expression->functions ['datetime_sub'] = 'expr_datetime_sub';
		$this->expression->functions ['intval'] = 'expr_intval';
	}

	public function evaluate($expression) {
		$return = null;
		$this->clearError();
		$lines = preg_split('/;\s*\R/',$expression);
		foreach ($lines as $line) {
			$line = preg_replace('/\R|\s/',' ', $line);
			if (!preg_match('/^\s*#.*/',$line)) {
				$return = $this->expression->evaluate($line);
				if ($this->expression->last_error) {
					$this->setError($this->expression->last_error);
				}
			}
		}
		return $return;
	}

	public function getVariables() {
		return $this->expression->vars();
	}

	public function getFunctions () {
		$builtInFunctions = $this->expression->fb;
		$customFunctions = array_keys($this->expression->functions);
		return array_merge($builtInFunctions, $customFunctions);
	}

	public function getUserDefineFunctions () {
		return array_keys($this->expression->f);
	}

	public function getOperators() {
		return array(
				array(_('Arithmetic operators'), $this->arithmeticOperators),
				array(_('Comparison operators'), $this->comparisonOperators),
				array(_('Logical operators'), $this->logicalOperators),
				array(_('Other operators'), $this->otherOperators)
			);
	}

	public function getFunctionDescription($function) {
		return $this->functionsDescription[$function];
	}

	public function setConstant($name, $value) {
		$this->clearError();
		if (is_integer($value)) {
			$expression = $name.'='.$value;
		} else {
			$expression = $name.'='.json_encode($value);
		}
		$this->expression->evaluate($expression);
		$this->expression->vb[] = $name;
	}
}

function expr_in_array($value, $jsonArray) {
	$array = json_decode($jsonArray, true);
	return in_array($value, $array);
}

function expr_datetime_add($datetime, $interval) {
	if (!trim($datetime)) {
		return '';
	}
	$dateTimeObj = DateTime::createFromFormat(_('Y-m-d H:i'), $datetime);
	if (!$dateTimeObj) {
		return '';
	}
	$intervalObj = new DateInterval($interval);
	if (!$intervalObj) {
		return '';
	}
	$dateTimeObj->add($intervalObj);
	return $dateTimeObj->format(_('Y-m-d H:i'));
}

function expr_datetime_sub($datetime, $interval) {
	if (!trim($datetime)) {
		return '';
	}
	$dateTimeObj = DateTime::createFromFormat(_('Y-m-d H:i'), $datetime);
	if (!$dateTimeObj) {
		return '';
	}
	$intervalObj = new DateInterval($interval);
	if (!$intervalObj) {
		return '';
	}
	$dateTimeObj->sub($intervalObj);
	return $dateTimeObj->format(_('Y-m-d H:i'));
}

function expr_intval($value) {
	return intval($value);
}
