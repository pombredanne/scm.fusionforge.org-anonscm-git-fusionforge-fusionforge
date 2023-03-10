<?php
/**
 * Copyright 2010-2011, Roland Mas
 * Copyright (C) 2011 Alain Peyrat - Alcatel-Lucent
 * Copyright 2013-2014,2019, Franck Villaume - TrivialDev
 * Copyright (C) 2015  Inria (Sylvain Beucler)
 *
 * This file is part of FusionForge.
 *
 * FusionForge is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published
 * by the Free Software Foundation; either version 2 of the License,
 * or (at your option) any later version.
 *
 * FusionForge is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

require_once dirname(dirname(__FILE__)).'/SeleniumForge.php';

class RBAC extends FForge_SeleniumTestCase
{
	public $fixture = 'projecta';

	function testAnonymousProjectReadAccess()
	{
		$this->loadAndCacheFixture();
		$this->switchUser(FORGE_ADMIN_USERNAME);
		$this->gotoProject('ProjectA');

		$this->clickAndWait("link=Admin");
		$this->waitForPageToLoad();
		$this->assertTrue($this->isTextPresent("Project Information"));
		$this->clickAndWait("link=Users and permissions");
		$this->waitForPageToLoad();
		$this->assertTrue($this->isTextPresent("Current Project Members"));
		$this->clickAndWait("//tr/td/form/div[contains(.,'Anonymous')]/../../../td/form/div/input[contains(@value,'Unlink Role')]");
		$this->waitForPageToLoad();
		$this->assertTrue($this->isTextPresent("Role unlinked successfully"));

		$this->createUser ('staffmember') ;
		$this->logout();
		$this->assertFalse($this->isTextPresent("ProjectA"));

		$this->open( ROOT . '/projects/projecta') ;
		$this->waitForPageToLoad();
		$this->assertTrue($this->isLoginRequired());
		$this->triggeredLogin('staffmember');
		$this->assertTrue($this->isTextPresent("Project Information"));
	}

	function testGlobalRolesAndPermissions()
	{
		$this->loadAndCacheFixture();
		$this->switchUser(FORGE_ADMIN_USERNAME);

		$this->clickAndWait("link=Site Admin");
		$this->waitForPageToLoad();

		// Create "Project approvers" role
		$this->type ("//form[contains(@action,'globalroleedit.php')]//input[@name='role_name']", "Project approvers") ;
		$this->clickAndWait("//form[contains(@action,'globalroleedit.php')]//input[@value='Create Role']") ;
		$this->waitForPageToLoad();

		// Grant it permissions
		$this->select($this->byXPath("//select[@name='data[approve_projects][-1]']"))->selectOptionByLabel("Approve projects");
		$this->select($this->byXPath("//select[@name='data[approve_news][-1]']"))->selectOptionByLabel("Approve news");
		$this->clickAndWait("//input[@value='Submit']") ;
		$this->waitForPageToLoad();

		// Check permissions were saved
		$this->clickAndWait("link=Site Admin");
		$this->waitForPageToLoad();
		$this->select($this->byXPath("//form[contains(@action,'globalroleedit.php')]//select[@name='role_id']"))->selectOptionByLabel("Project approvers") ;
		$this->clickAndWait("//form[contains(@action,'globalroleedit.php')]//input[@value='Edit Role']") ;
		$this->waitForPageToLoad();

		$selectedOptions = $this->select($this->byXPath("//select[@name='data[approve_projects][-1]']"))->selectedLabels();
		$this->assertEquals(array("Approve projects"), $selectedOptions);
		$this->assertNotEquals(array("No Access"), $selectedOptions);
		$selectedOptions = $this->select($this->byXPath("//select[@name='data[approve_news][-1]']"))->selectedLabels();
		$this->assertEquals(array("Approve news"), $selectedOptions);

		// Whoops, we don't actually want the news moderation bit, unset it
		$this->select($this->byXPath("//select[@name='data[approve_news][-1]']"))->selectOptionByLabel("No Access");
		$this->clickAndWait("//input[@value='Submit']") ;
		$this->waitForPageToLoad();
		$selectedOptions = $this->select($this->byXPath("//select[@name='data[approve_projects][-1]']"))->selectedLabels();
		$this->assertEquals(array("Approve projects"), $selectedOptions);
		$selectedOptions = $this->select($this->byXPath("//select[@name='data[approve_news][-1]']"))->selectedLabels();
		$this->assertEquals(array("No Access"), $selectedOptions);

		// Create users for "Project approvers" and "News moderators" roles
		$this->createUser ("projapp") ;
		$this->createUser ("newsmod") ;

		// Add them to their respective roles, check they're here
		$this->clickAndWait("link=Site Admin");
		$this->waitForPageToLoad();
		$this->select($this->byXPath("//form[contains(@action,'globalroleedit.php')]//select[@name='role_id']"))->selectOptionByLabel("Project approvers");
		$this->clickAndWait("//form[contains(@action,'globalroleedit.php')]//input[@value='Edit Role']") ;
		$this->waitForPageToLoad();
		$this->type ("//form[contains(@action,'globalroleedit.php')]//input[@name='form_unix_name']", "projapp") ;
		$this->clickAndWait("//input[@value='Add User']") ;
		$this->waitForPageToLoad();
		$this->assertTrue($this->isTextPresent("projapp Lastname"));

		$this->clickAndWait("link=Site Admin");
		$this->waitForPageToLoad();
		$this->select($this->byXPath("//form[contains(@action,'globalroleedit.php')]//select[@name='role_id']"))->selectOptionByLabel("News moderators") ;
		$this->clickAndWait("//form[contains(@action,'globalroleedit.php')]//input[@value='Edit Role']") ;
		$this->waitForPageToLoad();
		$this->type ("//form[contains(@action,'globalroleedit.php')]//input[@name='form_unix_name']", "newsmod") ;
		$this->clickAndWait("//input[@value='Add User']") ;
		$this->waitForPageToLoad();
		$this->assertTrue($this->isTextPresent("newsmod Lastname"));

		// Add a wrong user to the role, then remove it
		$this->type ("//form[contains(@action,'globalroleedit.php')]//input[@name='form_unix_name']", "projapp") ;
		$this->clickAndWait("//input[@value='Add User']") ;
		$this->waitForPageToLoad();
		$this->assertTrue($this->isTextPresent("projapp Lastname"));
		$this->assertTrue($this->isTextPresent("newsmod Lastname"));
		$this->clickAndWait("//a[contains(@href,'/users/projapp')]/../../td/input[@type='checkbox']") ;
		$this->clickAndWait("//input[@name='reallyremove']") ;
		$this->clickAndWait("//input[@name='dormusers']") ;
		$this->waitForPageToLoad();
		$this->assertFalse($this->isTextPresent("projapp Lastname"));
		$this->assertTrue($this->isTextPresent("newsmod Lastname"));

		// Register unprivileged user
		$this->createUser ("toto") ;

		// Temporarily grant project approval rights to user
		// (For cases where project_registration_restricted=true)
		$this->clickAndWait("link=Site Admin");
		$this->waitForPageToLoad();
		$this->select($this->byXPath("//form[contains(@action,'globalroleedit.php')]//select[@name='role_id']"))->selectOptionByLabel("Project approvers");
		$this->clickAndWait("//form[contains(@action,'globalroleedit.php')]//input[@value='Edit Role']") ;
		$this->waitForPageToLoad();
		$this->type ("//form[contains(@action,'globalroleedit.php')]//input[@name='form_unix_name']", "toto") ;
		$this->clickAndWait("//input[@value='Add User']") ;
		$this->waitForPageToLoad();
		$this->assertTrue($this->isTextPresent("toto Lastname"));

		// Register project
		$this->registerProject ("TotoProject", "toto") ;

		// Revoke project approval rights
		$this->clickAndWait("link=Site Admin");
		$this->waitForPageToLoad();
		$this->select($this->byXPath("//form[contains(@action,'globalroleedit.php')]//select[@name='role_id']"))->selectOptionByLabel("Project approvers");
		$this->clickAndWait("//form[contains(@action,'globalroleedit.php')]//input[@value='Edit Role']") ;
		$this->waitForPageToLoad();
		$this->clickAndWait("//a[contains(@href,'/users/toto')]/../../td/input[@type='checkbox']") ;
		$this->clickAndWait("//input[@name='reallyremove']") ;
		$this->clickAndWait("//input[@name='dormusers']") ;
		$this->waitForPageToLoad();
		$this->assertFalse($this->isTextPresent("toto Lastname"));

		// Try approving it as two users without the right to do so
		$this->switchUser ("toto") ;
		$this->open( ROOT . '/admin/approve-pending.php') ;
		$this->waitForPageToLoad();
		$this->assertTrue ($this->isPermissionDenied()) ;
		$this->switchUser ("newsmod") ;
		$this->open( ROOT . '/admin/approve-pending.php') ;
		$this->waitForPageToLoad();
		$this->assertTrue ($this->isPermissionDenied()) ;

		// Submit a news in the project
		$this->switchUser ("toto") ;
		$this->gotoProject ("TotoProject") ;
		$this->clickAndWait("link=News");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Submit") ;
		$this->waitForPageToLoad();
		$this->type("summary", "First TotoNews");
		$this->type("details", "This is a simple news for Toto's project.");
		$this->clickAndWait("submit");
		$this->waitForPageToLoad();

		// Try to push it to front page with user toto
		$this->open( ROOT . '/admin/pending-news.php') ;
		$this->waitForPageToLoad();
		$this->assertTrue ($this->isPermissionDenied()) ;

		// Try to push it to front page with user projapp
		$this->switchUser ("projapp") ;
		$this->open( ROOT . '/admin/pending-news.php') ;
		$this->waitForPageToLoad();
		$this->assertTrue ($this->isPermissionDenied()) ;

		// Push it to front page with user newsmod
		$this->switchUser ("newsmod") ;
		$this->open( ROOT . '/admin/pending-news.php') ;
		$this->waitForPageToLoad();
		$this->assertTrue ($this->isTextPresent("These items need to be approved")) ;
		$this->assertTrue ($this->isTextPresent("First TotoNews")) ;
		$this->clickAndWait("//a[contains(.,'First TotoNews')]") ;
		$this->waitForPageToLoad();
		$this->clickAndWait("//input[@type='radio' and @value='1']") ;
		$this->clickAndWait("submit") ;
		$this->waitForPageToLoad();
		$this->assertTrue ($this->isTextPresent("These items were approved this past week")) ;
		$this->open( ROOT ) ;
		$this->waitForPageToLoad();
		$this->assertTrue ($this->isTextPresent("First TotoNews")) ;

		// Non-regression test for #265
		$this->logout();
		$this->open( ROOT ) ;
		$this->waitForPageToLoad();
		$this->assertTrue ($this->isTextPresent("First TotoNews")) ;
		$this->clickAndWait("link=First TotoNews") ;
		$this->waitForPageToLoad();
		$this->assertFalse ($this->isPermissionDenied()) ;

		// Non-regression test for Adacore ticket K802-005
		// (Deletion of global roles)
		$this->switchUser(FORGE_ADMIN_USERNAME);
		$this->clickAndWait("link=Site Admin");
		$this->waitForPageToLoad();
		$this->type ("//form[contains(@action,'globalroleedit.php')]//input[@name='role_name']", "Temporary role") ;
		$this->clickAndWait("//form[contains(@action,'globalroleedit.php')]//input[@value='Create Role']") ;
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Site Admin");
		$this->waitForPageToLoad();
		$this->assertTrue($this->isElementPresent("//option[.='Temporary role']"));
		$this->select($this->byXPath("//form[contains(@action,'globalroleedit.php')]//select[@name='role_id']"))->selectOptionByLabel("Temporary role");
		$this->clickAndWait("//form[contains(@action,'globalroleedit.php')]//input[@value='Edit Role']") ;
		$this->waitForPageToLoad();
		$this->type ("//form[contains(@action,'globalroleedit.php')]//input[@name='form_unix_name']", "toto") ;
		$this->clickAndWait("//input[@value='Add User']") ;
		$this->waitForPageToLoad();
		$this->assertTrue($this->isTextPresent("toto Lastname"));
		$this->clickAndWait("//input[@type='checkbox' and @name='sure']") ;
		$this->clickAndWait("//input[@value='Delete role']") ;
		$this->waitForPageToLoad();
		$this->assertTrue($this->isTextPresent("Cannot remove a non empty role"));
		$this->clickAndWait("link=Site Admin");
		$this->waitForPageToLoad();
		$this->select($this->byXPath("//form[contains(@action,'globalroleedit.php')]//select[@name='role_id']"))->selectOptionByLabel("Temporary role");
		$this->clickAndWait("//form[contains(@action,'globalroleedit.php')]//input[@value='Edit Role']") ;
		$this->waitForPageToLoad();
		$this->clickAndWait("//a[contains(@href,'/users/toto')]/../../td/input[@type='checkbox']") ;
		$this->clickAndWait("//input[@name='reallyremove']") ;
		$this->clickAndWait("//input[@name='dormusers']") ;
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Site Admin");
		$this->waitForPageToLoad();
		$this->assertTrue($this->isElementPresent("//option[.='Temporary role']"));
		$this->select($this->byXPath("//form[contains(@action,'globalroleedit.php')]//select[@name='role_id']"))->selectOptionByLabel("Temporary role");
		$this->clickAndWait("//form[contains(@action,'globalroleedit.php')]//input[@value='Edit Role']") ;
		$this->waitForPageToLoad();
		$this->clickAndWait("//input[@type='checkbox' and @name='sure']") ;
		$this->clickAndWait("//input[@value='Delete role']") ;
		$this->waitForPageToLoad();
		$this->assertFalse($this->isElementPresent("//option[.='Temporary role']"));
	}

	function testProjectRolesAndPermissions()
	{
		$this->loadAndCacheFixture();

		$this->createUser ("bigboss") ;
		$this->createUser ("guru") ;
		$this->createUser ("docmaster") ;
		$this->createUser ("trainee") ;

		// Create "Project moderators" role
		$this->clickAndWait("link=Site Admin");
		$this->waitForPageToLoad();
		$this->type ("//form[contains(@action,'globalroleedit.php')]//input[@name='role_name']", "Project moderators") ;
		$this->clickAndWait("//form[contains(@action,'globalroleedit.php')]//input[@value='Create Role']") ;
		$this->waitForPageToLoad();

		// Grant it permissions
		$this->select($this->byXPath("//select[@name='data[approve_projects][-1]']"))->selectOptionByLabel("Approve projects");
		$this->clickAndWait("//input[@value='Submit']") ;
		$this->waitForPageToLoad();

		// Add bigboss
		$this->type ("//form[contains(@action,'globalroleedit.php')]//input[@name='form_unix_name']", "bigboss") ;
		$this->clickAndWait("//input[@value='Add User']") ;
		$this->waitForPageToLoad();
		$this->assertTrue($this->isTextPresent("bigboss Lastname"));

		// Create "Documentation masters" role
		$this->clickAndWait("link=Site Admin");
		$this->waitForPageToLoad();
		$this->type ("//form[contains(@action,'globalroleedit.php')]//input[@name='role_name']", "Documentation masters") ;
		$this->clickAndWait("//form[contains(@action,'globalroleedit.php')]//input[@value='Create Role']") ;
		$this->waitForPageToLoad();

		// Make it shared
		$this->clickAndWait("//input[@type='checkbox' and @name='public']") ;
		$this->clickAndWait("//input[@value='Submit']") ;
		$this->waitForPageToLoad();

		// Add docmaster
		$this->type ("//form[contains(@action,'globalroleedit.php')]//input[@name='form_unix_name']", "docmaster") ;
		$this->clickAndWait("//input[@value='Add User']") ;
		$this->waitForPageToLoad();
		$this->assertTrue($this->isTextPresent("docmaster Lastname"));

		// Register projects
		$this->switchUser ("bigboss") ;
		$this->registerProject ("MetaProject", "bigboss") ;
		$this->registerProject ("SubProject", "bigboss") ;

		// Create roles
		$this->gotoProject ("MetaProject") ;
		$this->clickAndWait("link=Admin");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Users and permissions");
		$this->waitForPageToLoad();
		$this->type ("//form[contains(@action,'roleedit.php')]/..//input[@name='role_name']", "Senior Developer") ;
		$this->clickAndWait("//input[@value='Create Role']") ;
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Users and permissions");
		$this->waitForPageToLoad();
		$this->type ("//form[contains(@action,'roleedit.php')]/..//input[@name='role_name']", "Junior Developer") ;
		$this->clickAndWait("//input[@value='Create Role']") ;
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Users and permissions");
		$this->waitForPageToLoad();
		$this->type ("//form[contains(@action,'roleedit.php')]/..//input[@name='role_name']", "Doc Writer") ;
		$this->clickAndWait("//input[@value='Create Role']") ;
		$this->waitForPageToLoad();

		// Add users
		$this->gotoProject ("MetaProject") ;
		$this->clickAndWait("link=Admin");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Users and permissions");
		$this->waitForPageToLoad();
		$this->type ("//form[contains(@action,'users.php')]//input[@name='form_unix_name' and @type='text']", "guru") ;
		$this->select($this->byXPath("//input[@value='Add Member']/../fieldset/select[@name='role_id']"))->selectOptionByLabel("Senior Developer");
		$this->clickAndWait("//input[@value='Add Member']") ;
		$this->waitForPageToLoad();
		$this->assertTrue($this->isTextPresent("guru Lastname"));
		$this->assertTrue($this->isElementPresent("//tr/td/a[.='guru Lastname']/../../td/div[contains(.,'Senior Developer')]")) ;

		$this->type ("//form[contains(@action,'users.php')]//input[@name='form_unix_name' and @type='text']", "trainee") ;
		$this->select($this->byXPath("//input[@value='Add Member']/../fieldset/select[@name='role_id']"))->selectOptionByLabel("Junior Developer");
		$this->clickAndWait("//input[@value='Add Member']") ;
		$this->waitForPageToLoad();
		$this->assertTrue($this->isTextPresent("trainee Lastname"));
		$this->assertTrue($this->isElementPresent("//tr/td/a[.='trainee Lastname']/../../td/div[contains(.,'Junior Developer')]")) ;

		$this->type ("//form[contains(@action,'users.php')]//input[@name='form_unix_name' and @type='text']", "docmaster") ;
		$this->select($this->byXPath("//input[@value='Add Member']/../fieldset/select[@name='role_id']"))->selectOptionByLabel("Doc Writer");
		$this->clickAndWait("//input[@value='Add Member']") ;
		$this->waitForPageToLoad();
		$this->assertTrue($this->isTextPresent("docmaster Lastname"));
		$this->assertTrue($this->isElementPresent("//tr/td/a[.='docmaster Lastname']/../../td/div[contains(.,'Doc Writer')]")) ;

		$this->type ("//form[contains(@action,'users.php')]//input[@name='form_unix_name' and @type='text']", "bigboss") ;
		$this->select($this->byXPath("//input[@value='Add Member']/../fieldset/select[@name='role_id']"))->selectOptionByLabel("Senior Developer");
		$this->clickAndWait("//input[@value='Add Member']") ;
		$this->waitForPageToLoad();
		$this->assertTrue($this->isTextPresent("bigboss Lastname"));
		$this->assertTrue($this->isElementPresent("//tr/td/div[contains(.,'Senior Developer')]/..//input[@value='Remove']/../input[@name='username' and @value='bigboss']")) ;

		// Oops, bigboss doesn't need the extra role after all
		$this->clickAndWait("//tr/td/div[contains(.,'Senior Developer')]/../div/form/input[@name='username' and @value='bigboss']/../input[@value='Remove']") ;
		$this->waitForPageToLoad();
		$this->assertFalse($this->isElementPresent("//tr/td/div[contains(.,'Senior Developer')]/../div/form/input[@value='Remove']/../input[@name='username' and @value='bigboss']")) ;

		// Remove/re-add a user
		$this->clickAndWait("//tr/td/div[contains(.,'Junior Developer')]/../div/form/input[@name='username' and @value='trainee']/../input[@value='Remove']") ;
		$this->waitForPageToLoad();
		$this->assertFalse($this->isTextPresent("trainee Lastname"));

		$this->type ("//form[contains(@action,'users.php')]//input[@name='form_unix_name' and @type='text']", "trainee") ;
		$this->select($this->byXPath("//input[@value='Add Member']/../fieldset/select[@name='role_id']"))->selectOptionByLabel("Junior Developer");
		$this->clickAndWait("//input[@value='Add Member']") ;
		$this->waitForPageToLoad();
		$this->assertTrue($this->isTextPresent("trainee Lastname"));
		$this->assertTrue($this->isElementPresent("//tr/td/a[.='trainee Lastname']/../../td/div[contains(.,'Junior Developer')]")) ;

		// Edit permissions of the JD role
		$this->gotoProject ("MetaProject") ;
		$this->clickAndWait("link=Admin");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Users and permissions");
		$this->waitForPageToLoad();

		$this->clickAndWait("//td/form/div[contains(.,'Junior Developer')]/../div/input[@value='Edit Permissions']") ;
		$this->waitForPageToLoad();

		$this->select($this->byXPath("//select[contains(@name,'data[frs_admin]')]"))->selectOptionByLabel("FRS access");
		$this->select($this->byXPath("//select[contains(@name,'data[docman]')]"))->selectOptionByLabel("Read only");
		$this->clickAndWait("//input[@value='Submit']") ;
		$this->waitForPageToLoad();
		$selectedOptions = $this->select($this->byXPath("//select[contains(@name,'data[docman]')]"))->selectedLabels();
		$this->assertEquals(array("Read only"), $selectedOptions);
		$selectedOptions = $this->select($this->byXPath("//select[contains(@name,'data[frs_admin]')]"))->selectedLabels();
		$this->assertEquals(array("FRS access"), $selectedOptions);
		$this->select($this->byXPath("//select[contains(@name,'data[new_frs]')]"))->selectOptionByLabel("Read only");
		$this->clickAndWait("//input[@value='Submit']") ;
		$this->waitForPageToLoad();
		$selectedOptions = $this->select($this->byXPath("//select[contains(@name,'data[new_frs]')]"))->selectedLabels();
		$this->assertEquals(array("Read only"), $selectedOptions);

		// Check that SD is technician on trackers but DM isn't
		$this->clickAndWait("link=Tracker");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Bugs");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Submit New");
		$this->waitForPageToLoad();
		$this->assertTrue($this->isElementPresent("//select[@name='assigned_to']")) ;
		$this->assertTrue($this->isElementPresent("//select[@name='assigned_to']/option[.='guru Lastname']")) ;
		$this->assertFalse($this->isElementPresent("//select[@name='assigned_to']/option[.='docmaster Lastname']")) ;

		// Check that SD is a manager on trackers but JD isn't
		$this->switchUser('guru');
		$this->gotoProject ("MetaProject") ;
		$this->clickAndWait("link=Tracker");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Bugs");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Submit New");
		$this->waitForPageToLoad();
		$this->assertTrue($this->isElementPresent("//select[@name='assigned_to']")) ;

		$this->switchUser('trainee');
		$this->gotoProject ("MetaProject") ;
		$this->clickAndWait("link=Tracker");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Bugs");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Submit New");
		$this->waitForPageToLoad();
		$this->assertFalse($this->isElementPresent("//select[@name='assigned_to']")) ;

		// Also check that guru isn't a manager on SubProject yet
		$this->switchUser('guru');
		$this->gotoProject("SubProject");
		$this->clickAndWait("link=Tracker");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Bugs");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Submit New");
		$this->waitForPageToLoad();
		$this->assertFalse($this->isElementPresent("//select[@name='assigned_to']")) ;

		// Mark SD role as shared
		$this->switchUser('bigboss');
		$this->gotoProject("MetaProject");
		$this->clickAndWait("link=Admin");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Users and permissions");
		$this->waitForPageToLoad();
		$this->clickAndWait("//td/form/div[contains(.,'Senior Developer')]/../div/input[@value='Edit Permissions']") ;
		$this->waitForPageToLoad();
		$this->clickAndWait("//input[@type='checkbox' and @name='public']") ;
		$this->clickAndWait("//input[@value='Submit']") ;
		$this->waitForPageToLoad();

		// Link MetaProject/SD role into SubProject
		$this->gotoProject ("SubProject") ;
		$this->clickAndWait("link=Admin");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Users and permissions");
		$this->waitForPageToLoad();

		$this->assertTrue($this->isElementPresent("//input[@value='Link external role']/../../div/fieldset/select/option[.='Senior Developer (in project MetaProject)']")) ;
		$this->select($this->byXPath("//input[@value='Link external role']/../../div/fieldset/select"))->selectOptionByLabel("Senior Developer (in project MetaProject)") ;
		$this->clickAndWait("//input[@value='Link external role']") ;
		$this->waitForPageToLoad();
		$this->assertTrue($this->isElementPresent("//td/form/div[contains(.,'Senior Developer (in project MetaProject)')]/../div/input[contains(@value,'Unlink Role')]"));

		// Grant it tracker manager permissions
		$this->clickAndWait("//td/form/div[contains(.,'Senior Developer (in project MetaProject)')]/../div/input[@value='Edit Permissions']") ;
		$this->waitForPageToLoad();
		$this->select($this->byXPath("//select[contains(@name,'data[tracker]')]"))->selectOptionByLabel("Manager");
		$this->clickAndWait("//input[@value='Submit']") ;
		$this->waitForPageToLoad();

		// Check that guru now has manager permissions on SubProject
		$this->switchUser('guru');
		$this->gotoProject("SubProject");
		$this->clickAndWait("link=Tracker");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Bugs");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Submit New");
		$this->waitForPageToLoad();
		$this->assertTrue($this->isElementPresent("//select[@name='assigned_to']")) ;

		// Link global "Documentation masters" role into SubProject
		$this->switchUser("bigboss") ;
		$this->gotoProject("SubProject");
		$this->clickAndWait("link=Admin");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Users and permissions");
		$this->waitForPageToLoad();

		$this->assertTrue($this->isElementPresent("//input[@value='Link external role']/../../div/fieldset/select/option[.='Documentation masters (global role)']")) ;
		$this->assertFalse($this->isElementPresent("//input[@value='Link external role']/../../div/fieldset/select/option[.='Project moderators (global role)']")) ;
		$this->select($this->byXPath("//input[@value='Link external role']/../../div/fieldset/select"))->selectOptionByLabel("Documentation masters (global role)") ;
		$this->clickAndWait("//input[@value='Link external role']") ;
		$this->waitForPageToLoad();
		$this->assertTrue($this->isElementPresent("//td/form/div[contains(.,'Documentation masters (global role)')]/../div/input[contains(@value,'Unlink Role')]"));

		// Check that a project admin (not forge admin) can create a new role
		$this->gotoProject ("SubProject") ;
		$this->clickAndWait("link=Admin");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Users and permissions");
		$this->waitForPageToLoad();
		$this->type ("//form[contains(@action,'users.php')]//input[@name='form_unix_name' and @type='text']", "guru") ;
		$this->select($this->byXPath("//input[@value='Add Member']/../fieldset/select[@name='role_id']"))->selectOptionByLabel("Admin");
		$this->clickAndWait("//input[@value='Add Member']") ;
		$this->waitForPageToLoad();
		$this->assertTrue($this->isTextPresent("guru Lastname"));
		$this->assertTrue($this->isElementPresent("//tr/td/a[.='guru Lastname']/../../td/div[contains(.,'Admin')]")) ;

		$this->switchUser('guru');
		$this->gotoProject ("SubProject") ;
		$this->clickAndWait("link=Admin");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Users and permissions");
		$this->waitForPageToLoad();
		$this->type ("//form[contains(@action,'roleedit.php')]/..//input[@name='role_name']", "Role created by guru") ;
		$this->clickAndWait("//input[@value='Create Role']") ;
		$this->waitForPageToLoad();
		$this->assertFalse ($this->isPermissionDenied()) ;
		$this->clickAndWait("link=Users and permissions");
		$this->waitForPageToLoad();
		$this->assertTrue($this->isElementPresent("//td/form/div[contains(.,'Role created by guru')]/../div/input[@value='Edit Permissions']")) ;

		// Non-regression test for Adacore ticket K802-005
		// (Deletion of project-wide roles)
		$this->switchUser(FORGE_ADMIN_USERNAME);
		$this->gotoProject ("MetaProject") ;
		$this->clickAndWait("link=Admin");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Users and permissions");
		$this->waitForPageToLoad();
		$this->type ("//form[contains(@action,'roleedit.php')]/..//input[@name='role_name']", "Temporary role") ;
		$this->clickAndWait("//input[@value='Create Role']") ;
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Users and permissions");
		$this->waitForPageToLoad();
		$this->assertTrue($this->isTextPresent("Temporary role"));
		$this->type ("//form[contains(@action,'users.php')]//input[@name='form_unix_name' and @type='text']", "trainee") ;
		$this->select($this->byXPath("//input[@value='Add Member']/../fieldset/select[@name='role_id']"))->selectOptionByLabel("Temporary role");
		$this->clickAndWait("//input[@value='Add Member']") ;
		$this->waitForPageToLoad();
		$this->clickAndWait("//td/form/div[contains(.,'Temporary role')]/../../form/div/input[@value='Delete role']") ;
		$this->waitForPageToLoad();
		$this->clickAndWait("//input[@type='checkbox' and @name='sure']") ;
		$this->clickAndWait("//input[@value='Submit']") ;
		$this->waitForPageToLoad();
		$this->assertTrue($this->isTextPresent("Cannot remove a non empty role"));
		$this->clickAndWait("//tr/td/div[contains(.,'Temporary role')]/../div/form/input[@name='username' and @value='trainee']/../input[@value='Remove']") ;
		$this->waitForPageToLoad();
		$this->clickAndWait("//td/form/div[contains(.,'Temporary role')]/../../form/div/input[@value='Delete role']") ;
		$this->waitForPageToLoad();
		$this->clickAndWait("//input[@type='checkbox' and @name='sure']") ;
		$this->clickAndWait("//input[@value='Submit']") ;
		$this->waitForPageToLoad();
		$this->assertFalse($this->isTextPresent("Temporary role"));

		// Non-regression test
		$this->clickAndWait("link=Site Admin");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Display Full Project List/Edit Projects");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=SubProject");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Permanently Delete Project");
		$this->waitForPageToLoad();
		$this->clickAndWait("sure");
		$this->clickAndWait("reallysure");
		$this->clickAndWait("reallyreallysure");
		$this->clickAndWait("submit");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Home");
		$this->waitForPageToLoad();
		$this->assertFalse($this->isTextPresent("SubProject"));

		// Make sure permissions are saved for news-related forums
		$this->switchUser(FORGE_ADMIN_USERNAME);
		$this->gotoProject ("MetaProject") ;

		$this->clickAndWait("link=News");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Submit");
		$this->waitForPageToLoad();
		$this->type("summary", "First news");
		$this->type("details", "This is a simple news.");
		$this->clickAndWait("submit");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=News");
		$this->waitForPageToLoad();
		$this->assertTrue($this->isTextPresent("First news"));
		$this->clickAndWait("link=First news");
		$this->waitForPageToLoad();
		$this->assertTrue($this->isTextPresent("First news"));
		$this->assertTrue($this->isTextPresent("This is a simple news."));

		$this->clickAndWait("link=Admin");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Users and permissions");
		$this->waitForPageToLoad();
		$this->clickAndWait("//td/form/div[contains(.,'Anonymous')]/../div/input[@value='Edit Permissions']") ;
		$this->waitForPageToLoad();

		$this->select($this->byXPath("//tr/td[contains(.,'first-news')]/../td/fieldset/select"))->selectOptionByLabel("Read only");
		$this->clickAndWait("//input[@value='Submit']") ;
		$this->waitForPageToLoad();
		$selectedOptions = $this->select($this->byXPath("//tr/td[contains(.,'first-news')]/../td/fieldset/select"))->selectedLabels();
		$this->assertEquals(array("Read only"), $selectedOptions);

		$this->select($this->byXPath("//tr/td[contains(.,'first-news')]/../td/fieldset/select"))->selectOptionByLabel("Moderated post");
		$this->clickAndWait("//input[@value='Submit']") ;
		$this->waitForPageToLoad();
		$selectedOptions = $this->select($this->byXPath("//tr/td[contains(.,'first-news')]/../td/fieldset/select"))->selectedLabels();
		$this->assertEquals(array("Moderated post"), $selectedOptions);

		$this->select($this->byXPath("//tr/td[contains(.,'first-news')]/../td/fieldset/select"))->selectOptionByLabel("Unmoderated post");
		$this->clickAndWait("//input[@value='Submit']") ;
		$this->waitForPageToLoad();
		$selectedOptions = $this->select($this->byXPath("//tr/td[contains(.,'first-news')]/../td/fieldset/select"))->selectedLabels();
		$this->assertEquals(array("Unmoderated post"), $selectedOptions);
	}
}
