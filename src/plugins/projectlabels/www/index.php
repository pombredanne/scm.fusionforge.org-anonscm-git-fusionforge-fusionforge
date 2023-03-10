<?php
/**
 * Project labels plugin
 *
 * Roland Mas <lolando@debian.org>
 * Copyright 2016, Franck Villaume - TrivialDev
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

require_once '../../../www/env.inc.php';
require_once $gfcommon.'include/pre.php';
require_once $gfwww.'admin/admin_utils.php';

global $HTML;

site_admin_header(array('title'=>_('Project labels')));

$func = getStringFromRequest ('func') ;

if ($func == 'addlabel') {
	$label_name = addslashes (getStringFromRequest ('label_name')) ;
	$label_text = getStringFromRequest ('label_text') ;
	$res = db_query_params ('INSERT INTO plugin_projectlabels_labels (label_name, label_text)
                         VALUES($1,$2)',
			array($label_name,
				$label_text));

	if (!$res || db_affected_rows($res) < 1) {
		printf (_('Cannot insert new label: %s'),
			db_error()) ;
	} else {
		echo _('Project label added.');
	}

}
if ($func == 'delete') {
	db_begin () ;
	$label_id = getIntFromRequest ('label_id', 0) ;
	$res = db_query_params ('DELETE FROM plugin_projectlabels_group_labels WHERE label_id=$1',
			array($label_id));

	if (!$res) {
		printf (_('Cannot delete label: %s'),
			db_error()) ;
		db_rollback () ;
	} else {
		$res = db_query_params ('DELETE FROM plugin_projectlabels_labels WHERE label_id=$1',
			array($label_id));

		if (!$res) {
			printf (_('Cannot delete label: %s'),
				db_error()) ;
			db_rollback () ;
		} else {
			echo _('Project label deleted.');
			db_commit () ;
		}
	}
}

if ($func == 'addlabeltoproject') {
	$label_id = getIntFromRequest ('label_id', 0) ;
	$group_uname = addslashes (getStringFromRequest ('group_uname')) ;
	$g = group_get_object_by_name ($group_uname) ;

	if ($g && !$g->isError()) {

		$res = db_query_params ('INSERT INTO plugin_projectlabels_group_labels (label_id, group_id) VALUES ($1, $2)',
					array ($label_id,
					       $g->getID()));

		if (!$res || db_affected_rows($res) < 1) {
			printf (_('Cannot add label onto project: %s'),
				db_error()) ;
		} else {
			echo _('The label has been added to the project.');
		}
	} else {
		echo _('No such project.') ;
	}

}
if ($func == 'removelabelfromproject') {
	$label_id = getIntFromRequest ('label_id', 0) ;
	$res = db_query_params ('DELETE FROM plugin_projectlabels_group_labels WHERE label_id = $1 AND group_id = $2',
			array($label_id,
				$group_id));

	if (!$res) {
		printf (_('Cannot remove label: %s'),
			db_error()) ;
	} else {
		echo _('The label has been removed from the project.') ;
	}

}
if ($func == 'editlabel') {
	$label_id = getIntFromRequest ('label_id', 0) ;
	$label_name = addslashes (getStringFromRequest ('label_name')) ;
	$label_text = getStringFromRequest ('label_text') ;
	$res = db_query_params ('UPDATE plugin_projectlabels_labels SET label_name = $1, label_text = $2
		         WHERE label_id=$3',
			array($label_name,
				$label_text,
				$label_id));
	if (!$res || db_affected_rows($res) < 1) {
		printf (_('Cannot modify label: %s'),
			db_error()) ;
	} else {
		echo _('Label has been saved.') ;
	}
}
if ($func == 'edit') {
	$label_id = getIntFromRequest ('label_id', 0) ;
	$res = db_query_params ('SELECT label_id, label_name, label_text FROM plugin_projectlabels_labels
		         WHERE label_id=$1',
			array($label_id));
	$row = db_fetch_array($res) ;

	echo $HTML->openForm(array('name' => 'edit_label', 'action' => '/plugins/projectlabels/', 'method' => 'post'));
	?>
<input type="hidden" name="func" value="editlabel" />
<input type="hidden" name="label_id" value="<?php echo $label_id ?>" />
<?php echo utils_requiredField(); ?>
<?php echo _('Label name')._(': '); ?><br/>
<input type="text" required="required" size="15" maxlength="32" name="label_name" value="<?php echo stripslashes ($row['label_name']) ; ?>"/> <br/>
<?php echo _('Displayed text (or HTML) for the label')._(': ') ; ?><br/>
<textarea tabindex='1' accesskey="," name="label_text" rows='5' cols='80'><?php echo $row['label_text'] ; ?></textarea><br/>
<?php echo _('This label currently looks like this')._(': ').$row['label_text'] ; ?>
<input type="submit" value="<?php echo _('Save this label') ?>" />
<?php
	echo $HTML->closeForm();
}
?>

<p>
<?php

$res = db_query_params ('SELECT label_id, label_name, label_text FROM plugin_projectlabels_labels
		 ORDER BY label_name ASC',
			array());

if (db_numrows($res) >= 1) {
	echo "<h2>"._('Manage labels')."</h2>" ;
	echo _('You can edit the labels that you have already created.') . "<br />" ;

	while ($row = db_fetch_array($res)) {
		echo "<h3>".stripslashes ($row['label_name'])."</h3>" ;
		echo "<br />" . _('This label currently looks like this')._(': ');
		echo $row['label_text'] . "<br />" ;

		$res2 = db_query_params ('SELECT groups.unix_group_name, groups.group_name, groups.group_id FROM groups, plugin_projectlabels_group_labels
                 WHERE plugin_projectlabels_group_labels.group_id = groups.group_id
                 AND plugin_projectlabels_group_labels.label_id=$1
		 ORDER BY groups.unix_group_name ASC',
					 array ($row['label_id']));
		if (db_numrows($res2) >= 1) {
			echo ngettext ('This label is used on the following group:',
				       'This label is used on the following groups:',
				       db_numrows ($res2)) ;

			echo "<br />";
			while ($row2 = db_fetch_array($res2)) {
				printf ('%1$s (%2$s)',
					$row2['group_name'],
					util_make_link ('/projects/'.$row2['unix_group_name'],
							$row2['unix_group_name'])) ;
				echo util_make_link ('/plugins/projectlabels/?func=removelabelfromproject&label_id='.$row['label_id']."&group_id=".$row2['group_id'],
						     _('[Remove this label]')) .  "<br />" ;
			}
		} else {
			echo _('This label is not used on any group.')."<br />" ;
		}
		echo $HTML->openForm(array('name' => 'addlabeltoproject', 'method' => 'post', 'action' => '/plugins/projectlabels/'));
		echo _('Project Unix Name')._(': '); ?>
<input type=text name=group_uname>
<input type="hidden" name="func" value="addlabeltoproject">
<input type="submit" value="<?php echo _('Add label to project') ?>">
<input type="hidden" value="<?php echo $row['label_id'] ;?>" name=label_id>
<?php
		echo $HTML->closeForm();
		echo util_make_link ('/plugins/projectlabels/?func=edit&label_id='.$row['label_id'],
			      _('[Edit this label]')) ;
		echo util_make_link ('/plugins/projectlabels/?func=delete&label_id='.$row['label_id'],
			      _('[Delete this label]')) ;
	}
}
?>
</p>

		<p><?php

echo "<h2>"._('Add new labels')."</h2>" ;
echo _('You can create new labels with the form below.') ?></p>
		<?php echo $HTML->openForm(array('name' => 'new_label', 'action' => '/plugins/projectlabels/', 'method' => 'post')); ?>
<p>
<input type="hidden" name="func" value="addlabel" />
<?php echo utils_requiredField(); ?>
		  <?php echo _('Label name')._(': ') ; ?><br/>
<input type="text" required="required" size="15" maxlength="32" name="label_name" value="<?php echo _('potm') ; ?>"/> <br/>
		  <?php echo _('Displayed text (or HTML) for the label')._(': ') ; ?><br/>
<textarea tabindex='1' accesskey="," name="label_text" rows='5'
		  cols='80'><p><?php echo html_e('strong', array(), _('Project of the month!')) ; ?></p>
</textarea><br/>
<input type="submit" value="<?php echo _('Add label') ?>" />
</p>

<?php
echo $HTML->closeForm();
site_admin_footer();

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
