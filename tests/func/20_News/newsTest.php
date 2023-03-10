<?php
/**
 * Copyright (C) 2008 Alain Peyrat <aljeux@free.fr>
 * Copyright (C) 2009 Alain Peyrat, Alcatel-Lucent
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

class CreateNews extends FForge_SeleniumTestCase
{
	public $fixture = 'projecta';

	function testMonitorProjectNews()
	{
		$this->loadAndCacheFixture();
		$this->switchUser(FORGE_ADMIN_USERNAME);
		$this->gotoProject('ProjectA');

		// Create a simple news.
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

		// Create a second news.
		$this->clickAndWait("link=News");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Submit");
		$this->waitForPageToLoad();
		$this->type("summary", "Second news");
		$this->type("details", "This is another text");
		$this->clickAndWait("submit");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=News");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Second news");
		$this->waitForPageToLoad();
		$this->assertTrue($this->isTextPresent("Second news"));
		$this->assertTrue($this->isTextPresent("This is another text"));

		// Check that news are visible in the activity
		$this->clickAndWait("link=Activity");
		$this->waitForPageToLoad();
		$this->assertTextPresent("First news");
		$this->assertTextPresent("Second news");

		// Check modification of a news.
		$this->clickAndWait("link=News");
		$this->clickAndWait("//a[contains(@href, '" . ROOT . "/news/admin/?group_id=7')]");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Second news");
		$this->type("details", "This is another text (corrected)");
		$this->clickAndWait("submit");
		$this->clickAndWait("link=Second news");
		$this->clickAndWait("link=News");
		$this->clickAndWait("link=Second news");
		$this->assertTextPresent("This is another text (corrected)");
		$this->clickAndWait("link=News");
		$this->clickAndWait("link=Submit");
		$this->type("summary", "Test3");
		$this->type("details", "Special ' chars \"");
		$this->clickAndWait("submit");
		$this->clickAndWait("link=News");
		$this->clickAndWait("link=Test3");
		$this->assertTextPresent("Special ' chars \"");
		$this->clickAndWait("link=News");
		$this->clickAndWait("//a[contains(@href, '". ROOT . "/news/admin/?group_id=7')]");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Test3");
		$this->clickAndWait("//form[@id='newsadminform']//input[@name='status' and @value=4]");
		$this->clickAndWait("submit");
	}

	/**
	 * Test multilines news formated in HTML.
	 */
	function testAcBug4100()
	{
		$this->loadAndCacheFixture();
		$this->switchUser(FORGE_ADMIN_USERNAME);
		$this->gotoProject('ProjectA');

		// Create a simple news.
		$this->clickAndWait("link=News");
		$this->clickAndWait("link=Submit");
		$this->type("summary", "Multi line news");
		$this->type("details", "<p>line1</p><p>line2</p><p>line3</p><br />hello<p>line5</p>\n");
		$this->clickAndWait("submit");
		$this->clickAndWait("link=News");
		$this->assertTextPresent("Multi line news");
		$this->assertTextPresent("line1");
		$this->assertTextPresent("line2");
		$this->assertTextPresent("line3");
		$this->assertTextPresent("hello");
		// $this->assertFalse($this->isTextPresent("line5"));
		$this->clickAndWait("link=Multi line news");
		$this->assertTextPresent("Multi line news");
		$this->assertTextPresent("line1");
		$this->assertTextPresent("line2");
		$this->assertTextPresent("line3");
		$this->assertTextPresent("hello");
		$this->assertTextPresent("line5");
	}

	/**
	 * Test multiple post of the news (reload or back+resubmit).
	 */
	function testPreventMultiplePost()
	{
		// Create a simple news.
		$this->loadAndCacheFixture();
		$this->switchUser(FORGE_ADMIN_USERNAME);
		$this->gotoProject('ProjectA');

		$this->clickAndWait("link=News");
		$this->clickAndWait("link=Submit");
		$this->type("summary", "My ABC news");
		$this->type("details", "hello DEF with a long detail.\n");
		$this->clickAndWait("submit");
		$this->assertTextPresent("News Added.");
		$this->goBack();
		sleep(1);
		$this->clickAndWait("submit");
		$this->assertTextPresent("Please avoid double-clicking");
	}

}

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
