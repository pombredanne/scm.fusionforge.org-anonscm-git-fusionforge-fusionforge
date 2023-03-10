<?php
/**
 * Copyright (C) 2008 Alain Peyrat <aljeux@free.fr>
 * Copyright (C) 2009 Alain Peyrat, Alcatel-Lucent
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

class CreateForum extends FForge_SeleniumTestCase
{
	public $fixture = 'projecta';

	function testSimplePost()
	{
		$this->loadAndCacheFixture();
		$this->switchUser(FORGE_ADMIN_USERNAME);

		// Create the first message (Message1/Text1).
		$this->gotoProject('ProjectA');
		$this->clickAndWait("link=Forums");
		$this->assertFalse($this->isTextPresent("Permission denied."));
		$this->assertTrue($this->isTextPresent("open-discussion"));
		$this->clickAndWait("link=open-discussion");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Start New Thread");
		$this->waitForPageToLoad();
		$this->type("subject", "Message1");
		$this->type("body", "Text1");
		$this->clickAndWait("submit");
		$this->waitForPageToLoad();
		$this->assertTrue($this->isTextPresent("Message Posted Successfully"));
		$this->clickAndWait("link=Forums");
		$this->waitForPageToLoad();
		$this->assertTrue($this->isTextPresent("open-discussion"));
		$this->clickAndWait("link=open-discussion");
		$this->waitForPageToLoad();
		$this->assertTrue($this->isTextPresent("Message1"));
	}

	/**
	 * Simulate a click on the link from a mail.
	 * As the forum is private, the users should be
	 * redirected to the login prompt saying that he has
	 * to login to get access to the message. Once logged,
	 * he should be redirected to the given forum.
	 */
	function testSimpleAccessWhenPrivate()
	{
		$this->loadAndCacheFixture();
		$this->switchUser(FORGE_ADMIN_USERNAME);

		$this->open( ROOT.'/forum/message.php?msg_id=6' );
		$this->waitForPageToLoad();
		$this->assertTrue($this->isTextPresent("Welcome to developers"));

		$this->logout();
		$this->open( ROOT.'/forum/message.php?msg_id=6' );
		$this->waitForPageToLoad();
		$this->assertTrue($this->isLoginRequired());
		$this->triggeredLogin(FORGE_ADMIN_USERNAME);
		$this->assertTrue($this->isTextPresent("Welcome to developers"));
	}

	/**
	 * Simulate a user non logged that will reply
	 * to a message in a forum. He will be redirected
	 * to the login page, then will reply and then
	 * we check that his reply is present in the thread.
	 */
	function testReplyToMessage()
	{
		$this->loadAndCacheFixture();
		$this->logout();

		$this->gotoProject('ProjectA');
		$this->clickAndWait("link=Forums");
		$this->clickAndWait("link=open-discussion");
		$this->clickAndWait("link=Welcome to open-discussion");
		$this->clickAndWait("link=[ Reply ]");
		$this->waitForPageToLoad();
		$this->assertTrue($this->isLoginRequired());
		$this->triggeredLogin(FORGE_ADMIN_USERNAME);
		$this->type("body", "Here is my 19823 reply");
		$this->clickAndWait("submit");
		$this->assertTextPresent("Message Posted Successfully");
		$this->clickAndWait("link=Welcome to open-discussion");
		$this->waitForPageToLoad();
		$this->assertTextPresent("Here is my 19823 reply");

	}

	/**
	 * Verify that it is impossible to use name already used by a mailing list
	 */
	function testEmailAddressNotAlreadyUsed() {
		$this->loadAndCacheFixture();
		$this->switchUser(FORGE_ADMIN_USERNAME);

		$this->gotoProject('ProjectA');
		$this->clickAndWait("link=Mailing Lists");
		$this->waitForPageToLoad();
		$this->clickAndWait("//body//main[@id='maindiv']//a[.='Administration']");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Add Mailing List");
		$this->waitForPageToLoad();
		$this->type("list_name", "toto");
		$this->type("//input[@name='description']", "Toto mailing list");
		$this->clickAndWait("submit");
		$this->waitForPageToLoad();
		$this->assertTrue($this->isTextPresent("List Added"));
		$this->clickAndWait("link=Forums");
		$this->waitForPageToLoad();
		$this->clickAndWait("//body//main[@id='maindiv']//a[.='Administration']");
		$this->waitForPageToLoad();
		$this->clickAndWait("link=Add Forum");
		$this->waitForPageToLoad();
		$this->type("forum_name", "toto");
		$this->type("//input[@name='description']", "Toto forum");
		$this->clickAndWait("submit");
		$this->waitForPageToLoad();
		$this->assertTrue($this->isTextPresent("Error: a mailing list with the same email address already exists"));
	}

	function testHtmlFiltering()
	{
		// Create the first message (Message1/Text1).
		$this->loadAndCacheFixture();
		$this->switchUser(FORGE_ADMIN_USERNAME);

		$this->gotoProject('ProjectA');
		$this->clickAndWait("link=Forums");
		$this->assertFalse($this->isTextPresent("Permission denied."));
		$this->assertTextPresent("open-discussion");
		$this->clickAndWait("link=open-discussion");
		$this->clickAndWait("link=Start New Thread");
		$this->type("subject", "Message1");
		$this->type("body", "Text1 <script>Hacker inside</script> done");
		$this->clickAndWait("submit");
		$this->assertTextPresent("Message Posted Successfully");
		$this->clickAndWait("link=Forums");
		$this->assertTextPresent("open-discussion");
		$this->clickAndWait("link=open-discussion");
		$this->assertTextPresent("Message1");
		$this->assertFalse($this->isTextPresent("Hacker inside"));
		$this->assertFalse($this->isTextPresent("Text1  done"));
	}
}

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
