<?php
if (!defined('PHPUnit_MAIN_METHOD')) {
	define('PHPUnit_MAIN_METHOD', 'AllTests::main');
}

if (!@include_once 'PHPUnit/Autoload.php') {
	include_once 'PHPUnit/Framework.php';
	require_once 'PHPUnit/TextUI/TestRunner.php';
}

class AllTests
{
	public static function main()
	{
		PHPUnit_TextUI_TestRunner::run(self::suite());
	}

	public static function suite()
	{
		$suite = new PHPUnit_Framework_TestSuite('PHPUnit');

		// Unit tests
		$suite->addTestFiles(glob("unit/*/*Test.php"));

		// Code tests
		$suite->addTestFiles(glob("code/*/*Test.php"));

		// Building packages and documentation tests
		$suite->addTestFiles(glob("build/*/*Test.php"));

		return $suite;
	}
}