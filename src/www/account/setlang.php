<?php
/**
 * Set default language for not-logged-on sessions (via cookie)
 *
 * Copyright 1999-2001 (c) VA Linux Systems
 *
 * This file is part of FusionForge. FusionForge is free software;
 * you can redistribute it and/or modify it under the terms of the
 * GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the Licence, or (at your option)
 * any later version.
 *
 * FusionForge is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with FusionForge; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

//TODO: this file is NOT USED ? Option: using it to set the language interface. We need to offer the choice at the not-logged user somewhere...
require_once '../env.inc.php';
require_once $gfcommon.'include/pre.php';

$language_id=getIntFromRequest('language_id');
setcookie('cookie_language_id',$language_id,(time()+2592000),'/','',0);
$cookie_language_id = $language_id;

$HTML->header(array('title'=>"Change Language"));

?>

<h2>Language Updated</h2>
<p>
Your language preference has been saved in a cookie and will be
remembered next time you visit the site.
</p>

<?php

$HTML->footer();
