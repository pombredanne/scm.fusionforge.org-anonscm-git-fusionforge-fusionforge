<?php
/**
 * Reporting System
 *
 * Copyright 2004 (c) GForge LLC
 *
 * @version   $Id$
 * @author Tim Perdue tim@gforge.org
 * @date 2003-03-16
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

require_once('../env.inc.php');
require_once $gfwww.'include/pre.php';
require_once $gfcommon.'reporting/report_utils.php';
require_once $gfcommon.'reporting/Report.class.php';

$report=new Report();
if ($report->isError()) {
	exit_error($report->getErrorMessage());
}

if (!$start) {
	$z =& $report->getMonthStartArr();
	$start = $z[count($z)-1];
}

session_require( array('group'=>$sys_stats_group) );

echo report_header(_('Tool Pie Graphs'));

$datatype = getStringFromRequest('datatype');
$start = getStringFromRequest('start');
$end = getStringFromRequest('end');

if (!isset($datatype)) {
	$datatype=1;
}

?>
<h3><?php echo _('Tool Pie Graphs'); ?></h3>
<p>
<form action="<?php echo getStringFromServer('PHP_SELF'); ?>" method="get">
<table><tr>
<td><strong><?php echo _('Trackers'); ?>:</strong><br /><?php echo report_tracker_box('datatype',$datatype); ?></td>
<td><strong><?php echo _('Start'); ?>:</strong><br /><?php echo report_months_box($report, 'start', $start); ?></td>
<td><strong><?php echo _('End'); ?>:</strong><br /><?php echo report_months_box($report, 'end', $end); ?></td>
<td><input type="submit" name="submit" value="<?php echo _('Refresh'); ?>"></td>
</tr></table>
</form>
<p>
<img src="toolspie_graph.php?<?php echo "datatype=$datatype&start=$start&end=$end"; ?>" width="640" height="480">
<p>
<?php

echo report_footer();

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:

?>
