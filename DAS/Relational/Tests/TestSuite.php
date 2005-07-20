<?php
/* 
+----------------------------------------------------------------------+
| (c) Copyright IBM Corporation 2005.                                  |
| All Rights Reserved.                                                 |
+----------------------------------------------------------------------+
|                                                                      |
| Licensed under the Apache License, Version 2.0 (the "License"); you  |
| may not use this file except in compliance with the License. You may |
| obtain a copy of the License at                                      |
| http://www.apache.org/licenses/LICENSE-2.0                           |
|                                                                      |
| Unless required by applicable law or agreed to in writing, software  |
| distributed under the License is distributed on an "AS IS" BASIS,    |
| WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or      |
| implied. See the License for the specific language governing         |
| permissions and limitations under the License.                       |
+----------------------------------------------------------------------+
| Author: Matthew Peters                                               |
+----------------------------------------------------------------------+

*/

/***********************************************************************************************************
*
* SDORDASTestSuite.php contains all the SDORDAS tests
*
* Two ways to run it:
*    Command line with phpunit --testdox-text SDORDAS.txt SDORDASTestSuite
*    Under ZDE with ZDERunner.php
*
************************************************************************************************************/

require_once "PHPUnit2/Framework/TestSuite.php";
require_once "PHPUnit2/TextUI/TestRunner.php";

require_once 'TestRelational.php';
require_once "TestTable.php";
require_once "TestForeignKey.php";
require_once "TestDatabaseModel.php";
require_once "TestContainmentReference.php";
require_once "TestReferencesModel.php";
require_once "TestObjectModel.php";
require_once 'TestInsertAction.php';
require_once 'TestPlan.php';


class SDO_DAS_Relational_TestSuite {
	public static function suite() {
		$suite = new PHPUnit2_Framework_TestSuite();
		$suite->addTest(new PHPUnit2_Framework_TestSuite("TestRelational"));
		$suite->addTest(new PHPUnit2_Framework_TestSuite("TestTable"));
		$suite->addTest(new PHPUnit2_Framework_TestSuite("TestForeignKey"));
		$suite->addTest(new PHPUnit2_Framework_TestSuite("TestDatabaseModel"));
		$suite->addTest(new PHPUnit2_Framework_TestSuite("TestContainmentReference"));
		$suite->addTest(new PHPUnit2_Framework_TestSuite("TestReferencesModel"));
		$suite->addTest(new PHPUnit2_Framework_TestSuite("TestObjectModel"));
		$suite->addTest(new PHPUnit2_Framework_TestSuite("TestInsertAction"));
		$suite->addTest(new PHPUnit2_Framework_TestSuite("TestPlan"));
		return $suite;
	}

}

//		throw new PHPUnit2_Framework_IncompleteTestError();


?>