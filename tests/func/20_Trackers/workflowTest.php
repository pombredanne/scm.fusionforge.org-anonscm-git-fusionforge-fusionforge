<?php
/**
 * Copyright (C) 2008 Alain Peyrat <aljeux@free.fr>
 * Copyright (C) 2009 - 2010 Alain Peyrat, Alcatel-Lucent
 * Copyright (C) 2015  Inria (Sylvain Beucler)
 * Copyright 2019, Franck Villaume - TrivialDev
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

/**
 * Standard Alcatel-Lucent disclaimer for contributing to open source
 *
 * "The test suite ("Contribution") has not been tested and/or
 * validated for release as or in products, combinations with products or
 * other commercial use. Any use of the Contribution is entirely made at
 * the user's own responsibility and the user can not rely on any features,
 * functionalities or performances Alcatel-Lucent has attributed to the
 * Contribution.
 *
 * THE CONTRIBUTION BY ALCATEL-LUCENT IS PROVIDED AS IS, WITHOUT WARRANTY
 * OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, COMPLIANCE,
 * NON-INTERFERENCE AND/OR INTERWORKING WITH THE SOFTWARE TO WHICH THE
 * CONTRIBUTION HAS BEEN MADE, TITLE AND NON-INFRINGEMENT. IN NO EVENT SHALL
 * ALCATEL-LUCENT BE LIABLE FOR ANY DAMAGES OR OTHER LIABLITY, WHETHER IN
 * CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
 * CONTRIBUTION OR THE USE OR OTHER DEALINGS IN THE CONTRIBUTION, WHETHER
 * TOGETHER WITH THE SOFTWARE TO WHICH THE CONTRIBUTION RELATES OR ON A STAND
 * ALONE BASIS."
 */

require_once dirname(dirname(__FILE__)).'/SeleniumForge.php';

class CreateTrackerWorkflow extends FForge_SeleniumTestCase
{
	public $fixture = 'projecta';

	function testWorkflow()
	{
		// Testing extra-fields
		$this->loadAndCacheFixture();
		$this->switchUser(FORGE_ADMIN_USERNAME);
		$this->gotoProject('ProjectA');

		$this->clickAndWait("link=Tracker");
		$this->clickAndWait("link=Bugs");
		$this->clickAndWait("//a[contains(@href, '".ROOT. "/tracker/admin/')]");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Manage Custom Fields");
		$this->type("name", "MyStatus");
		$this->type("alias", "mystatus");
		$this->clickAndWait("//input[@name='field_type' and @value=7]");
		$this->clickAndWait("post_changes");
		$this->waitForPageToLoad();
		$this->clickAndWait("//tr[@id='field-mystatus']/td[9]/a[1]");
		$this->waitForPageToLoad();
		$this->type("name", "New");
		$this->clickAndWait("post_changes");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Manage Custom Fields");
		$this->waitForPageToLoad();
		$this->clickAndWait("//tr[@id='field-mystatus']/td[9]/a[1]");
		$this->waitForPageToLoad();
		$this->type("name", "Analyse");
		$this->select($this->byName("status_id"))->selectOptionByLabel("Open");
		$this->clickAndWait("post_changes");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Manage Workflow");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Manage Custom Fields");
		$this->waitForPageToLoad();
		$this->clickAndWait("//tr[@id='field-mystatus']/td[9]/a[1]");
		$this->waitForPageToLoad();
		$this->type("name", "Candidate");
		$this->clickAndWait("post_changes");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Manage Custom Fields");
		$this->waitForPageToLoad();
		$this->clickAndWait("//tr[@id='field-mystatus']/td[9]/a[1]");
		$this->waitForPageToLoad();
		$this->type("name", "Open");
		$this->clickAndWait("post_changes");
		$this->type("name", "Resolved");
		$this->clickAndWait("post_changes");
		$this->type("name", "Validated");
		$this->clickAndWait("post_changes");
		$this->type("name", "Verified");
		$this->select($this->byName("status_id"))->selectOptionByLabel("Closed");
		$this->clickAndWait("post_changes");
		$this->type("name", "Duplicated");
		$this->clickAndWait("post_changes");
		$this->type("name", "Postponed");
		$this->clickAndWait("post_changes");
		$this->type("name", "Closed");
		$this->select($this->byName("status_id"))->selectOptionByLabel("Closed");
		$this->clickAndWait("post_changes");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Manage Workflow");
		$this->waitForPageToLoad();

		$this->clickAndWait("//tr[@id='configuring-1']//td[3]/input");
		$this->clickAndWait("//tr[@id='configuring-1']//td[4]/input");
		$this->clickAndWait("//tr[@id='configuring-1']//td[5]/input");
		$this->clickAndWait("//tr[@id='configuring-1']//td[6]/input");
		$this->clickAndWait("//tr[@id='configuring-1']//td[7]/input");
		$this->clickAndWait("//tr[@id='configuring-1']//td[8]/input");
		$this->clickAndWait("//tr[@id='configuring-1']//td[9]/input");
		$this->clickAndWait("//tr[@id='configuring-1']//td[10]/input");

		$this->clickAndWait("//tr[@id='configuring-2']//td[1]/input");
		$this->clickAndWait("//tr[@id='configuring-2']//td[4]/input");
		$this->clickAndWait("//tr[@id='configuring-2']//td[5]/input");
		$this->clickAndWait("//tr[@id='configuring-2']//td[6]/input");
		$this->clickAndWait("//tr[@id='configuring-2']//td[7]/input");
		$this->clickAndWait("//tr[@id='configuring-2']//td[8]/input");

		$this->clickAndWait("//tr[@id='configuring-3']//td[1]/input");
		$this->clickAndWait("//tr[@id='configuring-3']//td[2]/input");
		$this->clickAndWait("//tr[@id='configuring-3']//td[5]/input");
		$this->clickAndWait("//tr[@id='configuring-3']//td[6]/input");
		$this->clickAndWait("//tr[@id='configuring-3']//td[7]/input");
		$this->clickAndWait("//tr[@id='configuring-3']//td[8]/input");

		$this->clickAndWait("//tr[@id='configuring-4']//td[1]/input");
		$this->clickAndWait("//tr[@id='configuring-4']//td[3]/input");
		$this->clickAndWait("//tr[@id='configuring-4']//td[6]/input");
		$this->clickAndWait("//tr[@id='configuring-4']//td[7]/input");
		$this->clickAndWait("//tr[@id='configuring-4']//td[8]/input");
		$this->clickAndWait("//tr[@id='configuring-4']//td[9]/input");
		$this->clickAndWait("//tr[@id='configuring-4']//td[10]/input");

		$this->clickAndWait("//tr[@id='configuring-5']//td[1]/input");
		$this->clickAndWait("//tr[@id='configuring-5']//td[3]/input");
		$this->clickAndWait("//tr[@id='configuring-5']//td[4]/input");
		$this->clickAndWait("//tr[@id='configuring-5']//td[7]/input");
		$this->clickAndWait("//tr[@id='configuring-5']//td[8]/input");
		$this->clickAndWait("//tr[@id='configuring-5']//td[9]/input");
		$this->clickAndWait("//tr[@id='configuring-5']//td[10]/input");

		$this->clickAndWait("//tr[@id='configuring-6']//td[1]/input");
		$this->clickAndWait("//tr[@id='configuring-6']//td[3]/input");
		$this->clickAndWait("//tr[@id='configuring-6']//td[4]/input");
		$this->clickAndWait("//tr[@id='configuring-6']//td[5]/input");
		$this->clickAndWait("//tr[@id='configuring-6']//td[8]/input");
		$this->clickAndWait("//tr[@id='configuring-6']//td[9]/input");
		$this->clickAndWait("//tr[@id='configuring-6']//td[10]/input");

		$this->clickAndWait("//tr[@id='configuring-7']//td[1]/input");
		$this->clickAndWait("//tr[@id='configuring-7']//td[2]/input");
		$this->clickAndWait("//tr[@id='configuring-7']//td[3]/input");
		$this->clickAndWait("//tr[@id='configuring-7']//td[4]/input");
		$this->clickAndWait("//tr[@id='configuring-7']//td[5]/input");
		$this->clickAndWait("//tr[@id='configuring-7']//td[6]/input");
		$this->clickAndWait("//tr[@id='configuring-7']//td[8]/input");
		$this->clickAndWait("//tr[@id='configuring-7']//td[9]/input");
		$this->clickAndWait("//tr[@id='configuring-7']//td[10]/input");

		$this->clickAndWait("//tr[@id='configuring-8']//td[1]/input");
		$this->clickAndWait("//tr[@id='configuring-8']//td[2]/input");
		$this->clickAndWait("//tr[@id='configuring-8']//td[3]/input");
		$this->clickAndWait("//tr[@id='configuring-8']//td[4]/input");
		$this->clickAndWait("//tr[@id='configuring-8']//td[5]/input");
		$this->clickAndWait("//tr[@id='configuring-8']//td[6]/input");
		$this->clickAndWait("//tr[@id='configuring-8']//td[7]/input");
		$this->clickAndWait("//tr[@id='configuring-8']//td[9]/input");
		$this->clickAndWait("//tr[@id='configuring-8']//td[10]/input");

		$this->clickAndWait("//tr[@id='configuring-9']//td[1]/input");
		$this->clickAndWait("//tr[@id='configuring-9']//td[3]/input");
		$this->clickAndWait("//tr[@id='configuring-9']//td[4]/input");
		$this->clickAndWait("//tr[@id='configuring-9']//td[5]/input");
		$this->clickAndWait("//tr[@id='configuring-9']//td[6]/input");
		$this->clickAndWait("//tr[@id='configuring-9']//td[7]/input");
		$this->clickAndWait("//tr[@id='configuring-9']//td[8]/input");
		$this->clickAndWait("//tr[@id='configuring-9']//td[10]/input");

		$this->clickAndWait("//tr[@id='configuring-10']//td[1]/input");
		$this->clickAndWait("//tr[@id='configuring-10']//td[2]/input");
		$this->clickAndWait("//tr[@id='configuring-10']//td[3]/input");
		$this->clickAndWait("//tr[@id='configuring-10']//td[4]/input");
		$this->clickAndWait("//tr[@id='configuring-10']//td[5]/input");
		$this->clickAndWait("//tr[@id='configuring-10']//td[6]/input");
		$this->clickAndWait("//tr[@id='configuring-10']//td[7]/input");
		$this->clickAndWait("//tr[@id='configuring-10']//td[8]/input");
		$this->clickAndWait("//tr[@id='configuring-10']//td[9]/input");

		$this->clickAndWait("post_changes");
		$this->waitForPageToLoad();
		$this->assertTrue($this->isTextPresent("Workflow saved"));

		// Ensure that it is not possible to configure the workflow without initial state.
		$this->clickAndWait("//tr[@id='initval']//td[1]/input");
		$this->clickAndWait("//tr[@id='initval']//td[2]/input");
		$this->clickAndWait("//tr[@id='initval']//td[3]/input");
		$this->clickAndWait("//tr[@id='initval']//td[4]/input");
		$this->clickAndWait("//tr[@id='initval']//td[5]/input");
		$this->clickAndWait("//tr[@id='initval']//td[6]/input");
		$this->clickAndWait("//tr[@id='initval']//td[7]/input");
		$this->clickAndWait("//tr[@id='initval']//td[8]/input");
		$this->clickAndWait("//tr[@id='initval']//td[9]/input");
		$this->clickAndWait("//tr[@id='initval']//td[10]/input");
		$this->clickAndWait("post_changes");
		$this->waitForPageToLoad();
		$this->assertTrue($this->isTextPresent("Error: Initial values not saved"));
		$this->assertTrue($this->isTextPresent("Workflow saved"));
		// unset postponned
		$this->clickAndWait("//tr[@id='initval']//td[9]/input");
		$this->clickAndWait("post_changes");
		$this->waitForPageToLoad();
		$this->assertTrue($this->isTextPresent("Workflow saved"));
	}
}

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
