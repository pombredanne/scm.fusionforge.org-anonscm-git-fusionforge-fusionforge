<?php
/**
 * Copyright 2016, Franck Villaume - TrivialDev
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

class folderTest extends FForge_SeleniumTestCase {
	public $fixture = 'projecta';

	function testUpdateFolderName() {
		$this->loadAndCacheFixture();
		$this->switchUser(FORGE_ADMIN_USERNAME);

		$this->gotoProject('ProjectA');
		$this->clickAndWait("link=Docs");
		$this->clickAndWait("id=addItemDocmanMenu");
		// ugly hack until we fix behavior in docman when no folders exist. We need to click twice on the link
		$this->clickAndWait("id=addItemDocmanMenu");
		$this->clickAndWait("jquery#tabs-new-folder");
		$this->type("groupname", "renamedirectory");
		$this->clickAndWait("id=submitaddsubgroup");
		$this->clickAndWait("id=listFileDocmanMenu");
		$this->clickAndWait("link=renamedirectory");
		$this->clickAndWait("//a[@id='docman-editdirectory']/img");
		$this->type("groupname", "renamedirectory2");
		$this->clickAndWait("submit");
		$this->assertTextPresent("Documents folder renamedirectory2 updated successfully");
	}
}
