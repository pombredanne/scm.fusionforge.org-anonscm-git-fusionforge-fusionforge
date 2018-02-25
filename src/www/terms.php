<?php
/**
 * FusionForge
 *
 * Copyright 1999-2001, VA Linux Systems
 * Copyright 2002-2004, GForge, LLC
 * Copyright 2009, Roland Mas
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

require_once 'env.inc.php';
require_once $gfcommon.'include/pre.php';
require_once $gfwww.'news/news_utils.php';
require_once $gfcommon.'forum/Forum.class.php';
require_once $gfwww.'include/features_boxes.php';

$HTML->header(array('title'=> sprintf (_('%s Terms of Use'),
					forge_get_config ('forge_name'))));
?>
<p>
<?php
	if ( file_exists(forge_get_config('custom_path') . '/terms.php') ) {
		include forge_get_config('custom_path') . '/terms.php';
	} else {
		printf (_('These are the terms and conditions under which you are allowed to use the %s service. They are empty by default, but the administrator(s) of the service can use this page to publish their local requirements if needed.'),
			forge_get_config ('forge_name')) ;
	}
?>
</p>

<?php

$HTML->footer();

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
