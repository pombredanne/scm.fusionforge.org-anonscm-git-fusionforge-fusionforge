<?php
 /**
 * Copyright (c) STMicroelectronics, 2007. All Rights Reserved.
 *
 * Originally written by Mohamed CHAARI, 2007
 *
 * This file is a part of codendi.
 *
 * codendi is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * codendi is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * $Id$
 */

header("content-type: application/x-javascript");
require_once 'env.inc.php';
require_once 'pre.php';

global $HTML;

?>

function addHeader(cc,file,header_type)
{
  var ni = document.getElementById('mail_header');
  var numi = document.getElementById('header_val');
  var num = (document.getElementById('header_val').value -1)+ 2;
  numi.value = num;
  var divIdName = "mail_header_"+num+"_div";
  var newdiv = document.createElement('div');

  newdiv.setAttribute("id",divIdName);
  if (header_type == 1) {
	newdiv.innerHTML += "<table><tr><td width='65' align='right'><b><em><?php echo _('Cc:') ?> </em></b></td><td align=center width=350><input name='ccs["
					+num
					+"]' type='text' value='"
					+cc
					+"'size=41></td><td align=center><a href=\"javascript:;\" onclick=\"removeHeader(\'"
					+divIdName
					+"\')\"><?php echo $HTML->getDeletePic('', ''); ?></a></td></tr></table>";
  } else {
	newdiv.innerHTML += "<table><tr><td width='65' align='right'><b><em><?php echo _('Attach:') ?> </em></b></td><td align=center width=350><input name='files["
					+num
					+"]'' type='file' value='"
					+file
					+"'size=30></td><td align=center><a href=\"javascript:;\" onclick=\"removeHeader(\'"
					+divIdName
					+"\')\"><?php echo $HTML->getDeletePic('', ''); ?></a></td></tr></table>";
  }
  ni.appendChild(newdiv);
}

function removeHeader(divNum)
{
  var d = document.getElementById('mail_header');
  var olddiv = document.getElementById(divNum);
  d.removeChild(olddiv);
}

<?php
// Waiting for a better script, this comment just to make test
// BlankAfterClosingPhpTag happy...
