<?php
/**
 * Copyright (C) 2013 Vitaliy Pylypiv <vitaliy.pylypiv@gmail.com>
 *
 * This file is part of FusionForge.
 *
 * FusionForge is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published
 * by the Free Software Foundation; either version 2 of the License,
 * or (at your option) any later version.
 *
 * FusionForge is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

global $group_id, $group, $HTML, $pluginTaskboard, $taskboard;

$taskboard->header(
	array(
		'title' => _('Taskboard for ').$group->getPublicName()._(': ')._('Administration')._(': ')._('Columns configuration'),
		'pagename' => _('Columns configuration'),
		'sectionvals' => array(group_getname($group_id)),
		'group' => $group_id
	)
);

if(count($taskboard->getUsedTrackersIds()) == 0) {
	echo $HTML->warning_msg(_('Choose at least one tracker for using with taskboard.'));
} else {
	if($taskboard->isError()) {
		echo $HTML->error_msg($taskboard->getErrorMessage());
	} else {
		echo '<div id="messages" style="display: none;"></div>';
	}

	$columns = $taskboard->getColumns();
	$tablearr = array(_('Order'), _('Title'), _('Max number of tasks'), _('Assigned resolutions'), _('Drop resolution'));

	echo $HTML->listTableTop($tablearr, false, 'sortable_table_tracker', 'sortable_table_tracker');
	foreach($columns as $column) {
		$downLink = '';
		if($column->getOrder() < count($columns)) {
			$downLink = util_make_link('/plugins/taskboard/admin/?group_id='.$group_id.'&action=down_column&column_id='.$column->getID(), "<img alt='" ._('Down'). "' src='/images/pointer_down.png'>" );
		}
		$cells = array();
		$cells[][] = $column->getOrder().'&nbsp;'.$downLink;
		$cells[][] = '<div style="float: left; border: 1px solid grey; height: 30px; width: 20px; background-color: '.$column->getColumnBackgroundColor().'; margin-right: 10px;"><div style="width: 100%; height: 10px; background-color: '.$column->getTitleBackgroundColor().';"></div></div>'.
					util_make_link('/plugins/taskboard/admin/?group_id='.$group_id.'&view=edit_column&column_id='.$column->getID(),
					$column->getTitle());
		$cells[][] = $column->getMaxTasks();
		$cells[][] = implode(', ', array_values($column->getResolutions()));
		$cells[][] = $column->getResolutionByDefault();
		echo $HTML->multiTableRow(array('valign' => 'middle'), $cells);
	}
	echo $HTML->listTableBottom();

	echo $HTML->openForm(array('action' => '/plugins/taskboard/admin/?group_id='.$group_id.'&action=columns', 'method' => 'post'));
	echo html_e('input', array('type' => 'hidden', 'name' => 'post_changes', 'value' => 'y'));
	echo html_e('h2', array(), _('Add new column').(':'));
	echo $HTML->listTableTop();
	$cells = array();
	$cells[][] = html_e('strong', array(), _('Title').utils_requiredField()._(':'));
	$cells[][] = html_e('input', array('type' => 'text', 'name' => 'column_title', 'required' => 'required'));
	echo $HTML->multiTableRow(array(), $cells);
	$cells = array();
	$cells[][] = html_e('strong', array(), _('Title backgound color')._(':'));
	$cells[][] = $taskboard->colorBgChooser('title_bg_color');
	echo $HTML->multiTableRow(array(), $cells);
	$cells = array();
	$cells[][] = html_e('strong', array(), _('Column Background color')._(':'));
	$cells[][] = $taskboard->colorBgChooser('column_bg_color', 'none');
	echo $HTML->multiTableRow(array(), $cells);
	$cells = array();
	$cells[][] = html_e('strong', array(), _('Maximum tasks number')._(':'));
	$cells[][] = html_e('input', array('type' => 'text', 'name' => 'column_max_tasks'));
	echo $HTML->multiTableRow(array(), $cells);
	$cells = array();
	$cells[][] = html_e('strong', array(), _('Drop resolution by default').utils_requiredField()._(':'));
	$cells[][] = html_build_select_box_from_arrays($taskboard->getUnusedResolutions(), $taskboard->getUnusedResolutions(), 'resolution');
	echo $HTML->multiTableRow(array(), $cells);
	echo $HTML->listTableBottom();
	echo html_e('p', array(), html_e('input', array('type' => 'submit', 'name' => 'post_changes', 'value' => _('Submit'))));
	echo $HTML->closeForm();
	echo $HTML->addRequiredFieldsInfoBox();
}
