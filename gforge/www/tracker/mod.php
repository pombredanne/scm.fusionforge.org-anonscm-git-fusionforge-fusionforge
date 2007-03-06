<?php
/**
  * SourceForge Generic Tracker facility
  *
  * SourceForge: Breaking Down the Barriers to Open Source Development
  * Copyright 1999-2001 (c) VA Linux Systems
  * http://sourceforge.net
  *
  * @version   $Id$
  */


$ath->header(array ('title'=>$Language->getText('tracker_mod','title').': '.$ah->getID(). ' - ' . $ah->getSummary(),'atid'=>$ath->getID() ));

echo notepad_func();

?>
	<h3>[#<?php echo $ah->getID(); ?>] <?php echo $ah->getSummary(); ?></h3>

	<form action="<?php echo getStringFromServer('PHP_SELF'); ?>?group_id=<?php echo $group_id; ?>&amp;atid=<?php echo $ath->getID(); ?>"  enctype="multipart/form-data" method="post">
	<input type="hidden" name="form_key" value="<?php echo form_generate_key(); ?>"/>
	<input type="hidden" name="func" value="postmod"/>
	<input type="hidden" name="artifact_id" value="<?php echo $ah->getID(); ?>"/>

	<table width="80%">
<?php
if (session_loggedin()) {
?>
		<tr>
			<td><?php
				if ($ah->isMonitoring()) {
					$img="xmail16w.png";
					$key="monitorstop";
				} else {
					$img="mail16w.png";
					$key="monitor";
				}
				echo '
				<a href="index.php?group_id='.$group_id.'&amp;artifact_id='.$ah->getID().'&amp;atid='.$ath->getID().'&amp;func=monitor"><strong>'.
					html_image('ic/'.$img.'','20','20',array()).' '.$Language->getText('tracker_monitor',$key).'</strong></a>';
				?>
			</td>
			<td><?php
				if ($group->usesPM()) {
					echo '
				<a href="'.getStringFromServer('PHP_SELF').'?func=taskmgr&amp;group_id='.$group_id.'&amp;atid='.$atid.'&amp;aid='.$aid.'">'.
					html_image('ic/taskman20w.png','20','20',array()).'<strong>'.$Language->getText('tracker_mod','build_task_relation').'</strong></a>';
				}
				?>
			</td>
			<td>
				<a href="<?php echo getStringFromServer('PHP_SELF')."?func=deleteartifact&amp;aid=$aid&amp;group_id=$group_id&amp;atid=$atid"; ?>"><strong><?php echo html_image('ic/trash.png','16','16',array()) . $Language->getText('tracker_artifact','delete_text'); ?></strong></a>
			</td>
			<td>
				<input type="submit" name="submit" value="<?php echo $Language->getText('tracker_artifact','save') ?>" />
			</td>
		</tr>
</table>
<br />
<?php } ?>
<table border="0" width="80%">
	<tr>
		<td>
			<strong><?php echo $Language->getText('tracker','submitted_by') ?>:</strong><br />
			<?php echo $ah->getSubmittedRealName();
			if($ah->getSubmittedBy() != 100) {
				$submittedUnixName = $ah->getSubmittedUnixName();
				?>
				(<tt><a href="<?php echo $GLOBALS['sys_urlprefix']; ?>/users/<?php echo $submittedUnixName; ?>"><?php echo $submittedUnixName; ?></a></tt>)
			<?php } ?>
		</td>
		<td><strong><?php echo $Language->getText('tracker_mod','date_submitted') ?>:</strong><br />
		<?php
		echo date($sys_datefmt, $ah->getOpenDate() );

		$close_date = $ah->getCloseDate();
		if ($ah->getStatusID()==2 && $close_date > 1) {
			echo '<br /><strong>'.$Language->getText('tracker_mod','date_closed').':</strong><br />'
				.date($sys_datefmt, $close_date);
		}
		?>
		</td>
	</tr>

	<tr>
		<td><strong><?php echo $Language->getText('tracker_mod','data_type') ?>: <a href="javascript:help_window('<?php echo $GLOBALS['sys_urlprefix']; ?>/help/tracker.php?helpname=data_type')"><strong>(?)</strong></a></strong><br />
		<?php

//
//  kinda messy - but works for now
//  need to get list of data types this person can admin
//
	$perm =& $group->getPermission(session_get_user());
	if ($perm->isArtifactAdmin()) {
		$alevel=' >= 0';	
	} else {
		$alevel=' > 1';	
	}
	$sql="SELECT agl.group_artifact_id,agl.name 
		FROM artifact_group_list agl,artifact_perm ap
		WHERE agl.group_artifact_id=ap.group_artifact_id 
		AND ap.user_id='". user_getid() ."' 
		AND ap.perm_level $alevel
		AND agl.group_id='$group_id'";
	$res=db_query($sql);

	echo html_build_select_box ($res,'new_artifact_type_id',$ath->getID(),false);

		?>
		</td>
		<td>
		</td>
	</tr>

	<?php
		$ath->renderExtraFields($ah->getExtraFieldData(),true);
	?>

	<tr>
		<td><strong><?php echo $Language->getText('tracker','assigned_to')?>: <a href="javascript:help_window('<?php echo $GLOBALS['sys_urlprefix']; ?>/help/tracker.php?helpname=assignee')"><strong>(?)</strong></a></strong><br />
		<?php
		echo $ath->technicianBox('assigned_to', $ah->getAssignedTo() );
		echo '&nbsp;<a href="'.$GLOBALS['sys_urlprefix'].'/tracker/admin/?group_id='.$group_id.'&amp;atid='. $ath->getID() .'&amp;update_users=1">('.$Language->getText('tracker','admin').')</a>';
		?>
		</td><td>
		<strong><?php echo $Language->getText('tracker','priority') ?>: <a href="javascript:help_window('<?php echo $GLOBALS['sys_urlprefix']; ?>/help/tracker.php?helpname=priority')"><strong>(?)</strong></a></strong><br />
		<?php
		/*
			Priority of this request
		*/
		build_priority_select_box('priority',$ah->getPriority());
		?>
		</td>
	</tr>

	<tr>
		<td>
		<?php if (!$ath->usesCustomStatuses()) { ?>
		<strong><?php echo $Language->getText('tracker','status') ?>: <a href="javascript:help_window('<?php echo $GLOBALS['sys_urlprefix']; ?>/help/tracker.php?helpname=status')"><strong>(?)</strong></a></strong><br />
		<?php

		echo $ath->statusBox ('status_id', $ah->getStatusID() );
		}
		?>
		</td>
		<td>
		</td>
	</tr>

	<tr>
		<td><strong><?php echo $Language->getText('tracker','summary')?>: <a href="javascript:help_window('<?php echo $GLOBALS['sys_urlprefix']; ?>/help/tracker.php?helpname=summary')"><strong>(?)</strong></a></strong><br />
		<input type="text" name="summary" size="70" value="<?php
			echo $ah->getSummary(); 
			?>" maxlength="255" />
		</td>
		<td>
		</td>
	</tr>
	<tr><td colspan="2">
		<?php echo $ah->showDetails(); ?>
	</td></tr>
</table>
<br />
<br />
<script type="text/javascript" src="/tabber/tabber.js"></script>
<div id="tabber" class="tabber">
<div class="tabbertab" title="<?php echo $Language->getText('trackertab','followups'); ?>">
<table border="0" width="80%">
	<tr><td colspan="2">
		<br /><strong><?php echo $Language->getText('tracker_mod','canned_response') ?>: <a href="javascript:help_window('<?php echo $GLOBALS['sys_urlprefix']; ?>/help/tracker.php?helpname=canned_response')"><strong>(?)</strong></a></strong><br />
		<?php
		echo $ath->cannedResponseBox('canned_response');
		echo '&nbsp;<a href="'.$GLOBALS['sys_urlprefix'].'/tracker/admin/?group_id='.$group_id.'&amp;atid='. $ath->getID() .'&amp;add_canned=1">('.$Language->getText('tracker','admin').')</a>';
		?>
		<p>
		<strong><?php echo $Language->getText('tracker_mod','attach_comment') ?>:<?php echo notepad_button('document.forms[1].details') ?><a href="javascript:help_window('<?php echo $GLOBALS['sys_urlprefix']; ?>/help/tracker.php?helpname=comment')"><strong>(?)</strong></a></strong><br />
		<textarea name="details" rows="7" cols="60"></textarea></p>
		<h3><?php echo $Language->getText('tracker','followups') ?>:</h3>
		<?php
			echo $ah->showMessages(); 
		?>
	</td></tr>
</table>
</div>
<?php
$tabcnt=0;
if ($group->usesPM()) {
?>
<div class="tabbertab" title="<?php echo $Language->getText('trackertab','relatedtasks'); ?>">
		<h3><?php echo $Language->getText('tracker','related_tasks'); ?>:</h3>
<table border="0" width="80%">
		<?php
		$tabcnt++;
		$result = $ah->getRelatedTasks();
		$taskcount = db_numrows($ah->relatedtasks);
		if ($taskcount > 0) {
			echo '<tr><td colspan="2">';
			$titles[] = $Language->getText('pm','task_id');
			$titles[] = $Language->getText('pm','summary');
			$titles[] = $Language->getText('pm','start_date');
			$titles[] = $Language->getText('pm','end_date');
			echo $GLOBALS['HTML']->listTableTop($titles,'',$tabcnt);
			for ($i = 0; $i < $taskcount; $i++) {
				$taskinfo  = db_fetch_array($ah->relatedtasks, $i);
				$taskid    = $taskinfo['project_task_id'];
				$projectid = $taskinfo['group_project_id'];
				$groupid   = $taskinfo['group_id'];
				$summary   = util_unconvert_htmlspecialchars($taskinfo['summary']);
				$startdate = date($sys_datefmt, $taskinfo['start_date']);
				$enddate   = date($sys_datefmt, $taskinfo['end_date']);
				echo '<tr '. $GLOBALS['HTML']->boxGetAltRowStyle($i) .'>
					<td>'.$taskid.'</td>
						<td><a href="'.$GLOBALS['sys_urlprefix'].'/pm/task.php?func=detailtask&amp;project_task_id='.$taskid.
						'&amp;group_id='.$groupid.'&amp;group_project_id='.$projectid.'">'.$summary.'</a></td>
						<td>'.$startdate.'</td>
						<td>'.$enddate.'</td>
				</tr>';
			}
			echo $GLOBALS['HTML']->listTableBottom();
		} else {
			echo '<tr><td colspan="3">'.$Language->getText('tracker','no_related_tasks').'</td></tr>';
		}
      ?>
</table>
</div>
<?php } ?>
<div class="tabbertab" title="<?php echo $Language->getText('trackertab','attachments'); ?>">
		<h3><?php echo $Language->getText('tracker_mod','existing_files') ?>:</h3>
<table border="0" width="80%">
	<tr><td colspan="2">
        <strong><?php echo $Language->getText('tracker','file_upload') ?>:</strong><br />
        <input type="file" name="input_file[]" size="30" /><br />
        <input type="file" name="input_file[]" size="30" /><br />
        <input type="file" name="input_file[]" size="30" /><br />
        <input type="file" name="input_file[]" size="30" /><br />
        <input type="file" name="input_file[]" size="30" /><br />
		<?php
		//
		//	print a list of files attached to this Artifact
		//
		$file_list =& $ah->getFiles();
		
		$count=count($file_list);
		$tabcnt++;
		$title_arr=array();
		$title_arr[]=$Language->getText('tracker_mod','delete');
		$title_arr[]=$Language->getText('tracker_mod','name');
		$title_arr[]=$Language->getText('tracker_mod','download');
		echo $GLOBALS['HTML']->listTableTop ($title_arr,'',$tabcnt);

		if ($count > 0) {

			for ($i=0; $i<$count; $i++) {
				echo '
				<tr '. $GLOBALS['HTML']->boxGetAltRowStyle($i) .'>
				<td><input type="CHECKBOX" name="delete_file[]" value="'. $file_list[$i]->getID() .'">'.$Language->getText('tracker_mod','delete').' </td>'.
				'<td>'. htmlspecialchars($file_list[$i]->getName()) .'</td>
				<td><a href="'.$GLOBALS['sys_urlprefix'].'/tracker/download.php/'.$group_id.'/'. $ath->getID().'/'. $ah->getID() .'/'.$file_list[$i]->getID().'/'.$file_list[$i]->getName() .'">'.$Language->getText('tracker_mod','download').'</a></td>
				</tr>';
			}

		} else {
			echo '<tr '.$GLOBALS['HTML']->boxGetAltRowStyle(0).'><td colspan="4">'.$Language->getText('tracker_mod','no_files').'</td></tr>';
		}

		echo $GLOBALS['HTML']->listTableBottom();
		?>
	</td></tr>
</table>
</div>
<div class="tabbertab" title="<?php echo $Language->getText('trackertab','commits'); ?>">
<table border="0" width="80%">
	<?php
		$hookParams['artifact_id']=$aid;
		plugin_hook("artifact_extra_detail",$hookParams);
	?>
</table>
</div>
<div class="tabbertab" title="<?php echo $Language->getText('trackertab','changes'); ?>">
<table border="0" width="80%">
	<tr><td colspan="2">
		<h3><?php echo $Language->getText('tracker_mod','changelog') ?>:</h3>
		<?php 
			echo $ah->showHistory(); 
		?>
	</td></tr>
</table>
</div>
</div>
		</form>

<?php

$ath->footer(array());

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:

?>
