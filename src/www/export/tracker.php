<?php
/**
 * FusionForge Tracker XML export
 *
 * Copyright 1999-2001, Darrell Brogdon - VALinux
 * Copyright 2017, Franck Villaume - TrivialDev
 * http://fusionforge.org
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

require_once '../env.inc.php';
require_once $gfcommon.'include/pre.php';
require_once $gfcommon.'tracker/Artifact.class.php';
require_once $gfcommon.'tracker/Artifacts.class.php';
require_once $gfcommon.'tracker/ArtifactFile.class.php';
require_once $gfcommon.'tracker/ArtifactType.class.php';
require_once $gfcommon.'tracker/ArtifactCanned.class.php';

$sysdebug_enable = false;

function beginDocument() {
	header("Content-Type: text/plain");
	echo '<tracker version="1.0" xmlns:xsi="http://www.w3.org/2000/10/XMLSchema-instance" xsi:noNamespaceSchemaLocation="http://'.forge_get_config('web_host').'/export/tracker.xsd">'."\n";
}

function displayError($errorMessage) {
	echo '<error>'.$errorMessage.'</error>'."\n";
}

function endDocument() {
	echo '</tracker>';
	exit();
}

function endOnError($errorMessage) {
	displayError($errorMessage);
	endDocument();
}

$group_id = getIntFromRequest('group_id');
$atid = getIntFromRequest('atid');
$offset = getIntFromRequest('offset');

if ($group_id && $atid) {
	//
	//	get the Project object
	//
	$group = group_get_object($group_id);

	beginDocument();
	if (!$group || !is_object($group)) {
		endOnError('Could not get the Project object');
	} elseif ($group->isError()) {
		endOnError($group->getErrorMessage());
	}

	//
	//  Add checks to see if they have perms to view this
	//
	if (!forge_check_perm('tracker', $atid, 'read')) {
		endOnError('Permission Denied');
		$errors = true;
	}
	//
	//	Create the ArtifactType object
	//
	$ath = new ArtifactType($group,$atid);
	if (!$ath || !is_object($ath)) {
		endOnError('ArtifactType could not be created');
	} elseif ($ath->isError()) {
		endOnError($ath->getErrorMessage());
	}

	//
	// Create the Artifacts object
	//
	$artifacts = new Artifacts($ath);
	if (!$artifacts || !is_object($ath)) {
		endOnError('Artifacts could not be created');
	}
	if ($artifacts->isError()) {
		endOnError($artifacts->getErrorMessage());
	}

	//
	// Loop through each artifact object and show the results
	//
	if (!$alist = $artifacts->getArtifacts($offset)) {
		displayError($artifacts->getErrorMessage());
		$errors = true;
	}

	if ($errors) {
		endDocument();
	}

	for ($i=0; $i<count($alist); $i++) {
?>
		<artifact id="<?php echo $alist[$i]->getID(); ?>">
			<submitted_by><?php echo $alist[$i]->getSubmittedUnixName(); ?></submitted_by>
			<submitted_date><?php echo date( _('Y-m-d H:i'), $alist[$i]->getOpenDate() ); ?></submitted_date>
			<artifact_type id="<?php echo $ath->getID(); ?>"><?php echo $ath->getID(); ?></artifact_type>
			<assigned_to><?php echo $alist[$i]->getAssignedRealName(); ?></assigned_to>
			<priority id="<?php echo $alist[$i]->getPriority(); ?>"><?php echo $alist[$i]->getPriority(); ?></priority>
			<status><?php echo $alist[$i]->getStatusName(); ?></status>
			<summary><?php echo $alist[$i]->getSummary(); ?></summary>
			<detail><?php echo $alist[$i]->getDetails(); ?></detail>
<?php
		$result = $alist[$i]->getMessages();
		$rows = db_numrows($result);
		if ($rows > 0) {
?>
			<follow_ups>
<?php
			for ($x=0; $x<$rows; $x++) {
?>
				<item>
					<date><?php echo db_result($result, $x, 'adddate'); ?></date>
					<sender><?php echo db_result($result, $x, 'user_name'); ?></sender>
					<text><?php echo db_result($result, $x, 'body'); ?></text>
				</item>
<?php
			}
?>
		</follow_ups>
<?php
	}

		$file_list =& $alist[$i]->getFiles();
		$count=count($file_list);
		if ($count > 0) {
?>
			<existingfiles>
<?php
			for ($x=0; $x<$count; $x++) {
?>
				<file>
					<id><?php echo $file_list[$x]->getID(); ?></id>
					<name><?php echo $file_list[$x]->getName(); ?></name>
					<description><?php echo $file_list[$x]->getDescription(); ?></description>
					<filesize><?php echo $file_list[$x]->getSize(); ?></filesize>
					<filetype><?php echo $file_list[$x]->getType(); ?></filetype>
					<adddate><?php echo $file_list[$x]->getDate(); ?></adddate>
					<submitted_by><?php echo $file_list[$x]->getSubmittedBy(); ?></submitted_by>
				</file>
<?php
			}
?>
			</existingfiles>
<?php
		}

		$result = $alist[$i]->getHistory();
		$rows = db_numrows($result);

		if ($rows > 0) {
?>
			<change_log>
<?php
			for ($x=0; $x<$rows; $x++) {
?>
				<item>
					<field><?php echo db_result($result, $x, 'field_name'); ?></field>
					<old_value><?php echo db_result($result, $x, 'old_value'); ?></old_value>
					<date><?php echo db_result($result, $x, 'entrydate'); ?></date>
					<by><?php echo db_result($result, $x, 'user_name'); ?></by>
				</item>
<?php
			}
?>
			</change_log>
<?php
		}
?>
		</artifact>
<?php
	}
	echo '</tracker>';
} else {
	beginDocument();
	endOnError('Project ID or Artifact ID Not Set');
}

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
