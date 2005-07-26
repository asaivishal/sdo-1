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


/***********************************************************************
 * Enter your own database password in the second define and then remove the 
 * echos and exit to leave just the two DEFINEs.
 **********************************************************************/
echo "*************************************************************************************************\n";
echo "\n";
echo "You need to supply a database user and password in SDO/DAS/Relational/Scenarios/company_metadata.inc.php\n";
echo "\n";
echo "*************************************************************************************************\n";
exit;
define('DATABASE_USER','root');
define ('DATABASE_PASSWORD','YOUR_DATABASE_PASSWORD_HERE');


/*****************************************************************
* METADATA DEFINING THE DATABASE
* The three tables might be defined like this to MySQL:
* create table company (
*   id integer auto_increment,
*   name char(20),
*   employee_of_the_month integer,
*   primary key(id)
* );
* create table department (
*   id integer auto_increment,
*   name char(20),
*   location char(10),
*   number integer(3),
*   co_id integer,
*   primary key(id)
* );
* create table employee (
*   id integer auto_increment,
*   name char(20),
*   SN char(4),
*   manager tinyint(1),
*   dept_id integer,
*   primary key(id)
* );
******************************************************************/
$company_table = array (
	'name' => 'company',
	'columns' => array('id', 'name',  'employee_of_the_month'),
	'PK' => 'id',
	'FK' => array (
		'from' => 'employee_of_the_month',
		'to' => 'employee',
		),
	);
$department_table = array (
	'name' => 'department', 
	'columns' => array('id', 'name', 'location' , 'number', 'co_id'),
	'PK' => 'id',
	'FK' => array (
		'from' => 'co_id',
		'to' => 'company',
		)
	);
$employee_table = array (
	'name' => 'employee',
	'columns' => array('id', 'name', 'SN', 'manager', 'dept_id'),
	'PK' => 'id',
	'FK' => array (
		'from' => 'dept_id',
		'to' => 'department',
		)
	);
$database_metadata = array($company_table, $department_table, $employee_table);

/*******************************************************************
* METADATA DEFINING SDO CONTAINMENT REFERENCES
*******************************************************************/
$department_reference = array( 'parent' => 'company', 'child' => 'department');
$employee_reference = array( 'parent' => 'department', 'child' => 'employee');

$SDO_reference_metadata = array($department_reference, $employee_reference);


?>
