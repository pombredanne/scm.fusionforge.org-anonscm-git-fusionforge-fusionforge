<?php
/**
 * GForge language management
 *
 * Portions Copyright 1999-2001 (c) VA Linux Systems
 * The rest Copyright 2002-2004 (c) GForge Team
 * http://gforge.org/
 *
 * @version   $Id$
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

require_once('common/include/escapingUtils.php');

$lang = getStringFromRequest('lang');

$unit        = 'item';
$table       = 'tmp_lang';
$primary_key = 'seq';
if ( $sys_database_type == "mysql" ) {
	$whereclause = " WHERE concat(language_id,pagename,category) IN (SELECT concat(language_id,pagename,category) FROM (SELECT count(*) AS cnt,language_id,pagename,category  FROM tmp_lang WHERE pagename!='#' GROUP BY language_id,pagename,category) AS cntdouble where cnt>1 AND language_id='".$lang."') AND pagename!='' ORDER BY language_id,pagename,category,seq ";
} else {
	$whereclause = " WHERE language_id||pagename||category IN (SELECT language_id||pagename||category FROM (SELECT count(*) AS cnt,language_id,pagename,category  FROM tmp_lang WHERE pagename!='#' GROUP BY language_id,pagename,category) AS cntdouble where cnt>1 AND language_id='".$lang."') AND pagename!='' ORDER BY language_id,pagename,category,seq ";
}
$columns     = "seq, tmpid, pagename, category, tstring";
$edit        = 'yes';

include_once('admintabfiles.php');

?>
