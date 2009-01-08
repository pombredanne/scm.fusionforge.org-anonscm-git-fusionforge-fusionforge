<?php
/**
 *	SCM object
 *
 *	Provides an base class for an SCM plugin
 *
 * This file is copyright (c) Roland Mas <lolando@debian.org>, 2004
 *
 * $Id: SCM.class.php 6506 2008-05-27 20:56:57Z aljeux $
 *
 * This file is part of GForge.
 *
 * GForge is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * GForge is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GForge; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

require_once $gfcommon.'include/scm.php';

class SCM extends Plugin {
	/**
	 * SCM() - constructor
	 *
	 */
	function SCM () {
		$this->Plugin() ;
	}

	function register () {
		global $scm_list ;

		$scm_list[] = $this->name ;
	}
}

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:

?>
