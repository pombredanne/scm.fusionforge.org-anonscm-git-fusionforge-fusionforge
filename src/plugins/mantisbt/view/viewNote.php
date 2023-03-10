<?php
/**
 * MantisBT plugin
 *
 * Copyright 2010-2011, Franck Villaume - Capgemini
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

global $mantisbt;
global $mantisbtConf;
global $username;
global $password;
global $group_id;
global $idBug;
global $editable;
global $HTML;

//$msg is coming from previous soap error
if (empty($msg)) {
	if (!isset($defect)) {
		/* do not recreate $clientSOAP object if already created by other pages */
		if (!isset($clientSOAP)) {
			$clientSOAP = new SoapClient($mantisbtConf['url']."/api/soap/mantisconnect.php?wsdl", array('trace'=>true, 'exceptions'=>true));
		}
		$defect = $clientSOAP->__soapCall('mc_issue_get', array("username" => $username, "password" => $password, "issue_id" => $idBug));
	}

	echo '<h2>'._('Notes').'</h2>';

	if (isset($defect->notes)){
		echo    '<table>';
		foreach ($defect->notes as $key => $note){
			echo	'<tr>';
			echo		'<td width="10%">';
			echo			'('.sprintf($format,$note->id).')';
			echo			'<br/>';
			echo		$note->reporter->name;
			echo			'<br/>';
			// TODO
			//date_default_timezone_set("UTC");
			echo			date("Y-m-d G:i",strtotime($note->date_submitted));
			echo		'</td>';
			if ($editable) {
				echo		'<td width="9%">';
				echo			'<input type=button name="upNote" value="'._('Modify').'" onclick="window.location.href=\'?type='.$type.'&group_id='.$group_id.'&pluginname='.$mantisbt->name.'&idBug='.$defect->id.'&idNote='.$note->id.'&view=editNote\'">';
				echo			'<input type=button name="delNote" value="'._('Delete').'" onclick="window.location.href=\'?type='.$type.'&group_id='.$group_id.'&pluginname='.$mantisbt->name.'&idBug='.$defect->id.'&idNote='.$note->id.'&action=deleteNote&view=viewIssue\'">';
				echo		"</td>";
			}
			echo		'<td>';
			echo		'<textarea disabled name="description" style="width:99%; background-color:white; color:black; border: none;" row="3">'.htmlspecialchars($note->text, ENT_QUOTES).'</textarea>';
			echo		"</td>";
			echo	'</tr>';
		}
		echo "</table>";
	} else {
		echo $HTML->warning_msg(_('No notes for this ticket'));
	}
?>
<script type="text/javascript">
    jQuery(document).ready(function() {
        jQuery("#expandable_note").hide();
    });

</script>
<?php
	if ($editable) {
?>
<p class="notice_title" onclick='jQuery("#expandable_note").slideToggle(300)'><?php echo _('Add note') ?></p>
<div id='expandable_note' class="notice_content">
<?php
		include 'addOrEditNote.php';
	}
?>
</div>
<br/>
<?php
}
