<?php
/**
 * FusionForge globalsearch plugin
 *
 * Copyright 1999-2001, VA Linux Systems, Inc.
 * Copyright 2003-2004, GForge, LLC
 * Copyright 2007-2009, Roland Mas
 * Copyright 2016, Franck Villaume - TrivialDev
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

/**
 *      globalsearch_admin_table_add() - present a form for adding a record to the specified table
 */
function globalsearch_admin_table_add() {
        global $HTML;
	echo _('Create a new associated forge below');
	echo $HTML->openForm(array('name' => 'add', 'action' => '/plugins/globalsearch/edit_assoc_sites.php?function=postadd', 'method' => 'post'));
	echo '<table>';
	echo '<tr><td><strong>'._('Title').'</strong></td><td><input type="text" name="title" required="required" /></td></tr>';
	echo '<tr><td><strong>'._('Link').'</strong></td><td><input type="text" name="link" required="required" /></td></tr>';
	echo '<tr><td><strong>'._('Software only').'</strong></td><td><input type="checkbox" checked name="onlysw" value="t"/></td></tr>';
	echo '<tr><td><strong>'._('Enabled').'</strong></td><td><input type="checkbox" checked name="enabled" value="t"/></td></tr>';
	echo '<tr><td><strong>'._('Rank').'</strong></td><td><input type="text" name="rank" /></td></tr>';
	echo '</table><input type="submit" value="'._('Submit new associated forge').'" />
                        <input type="reset" value="'._('Cancel').'" />';
	echo $HTML->closeForm();
}

/**
 *      globalsearch_admin_table_postadd() - update the database based on a submitted change
 */
function globalsearch_admin_table_postadd () {
	$new_title     = getStringFromRequest ('title');
	$new_link      = getStringFromRequest ('link');
	$new_onlysw    = getStringFromRequest ('onlysw');
	$new_enabled   = getStringFromRequest ('enabled');
	$new_rank      = getIntFromRequest ('rank', 1);
	if ($new_onlysw != 't' and $new_onlysw != 'f') {
		$new_onlysw = 'f' ;
	}
	if ($new_enabled != 't' and $new_enabled != 'f') {
		$new_enabled = 'f' ;
	}

        if (db_query_params ('INSERT INTO plugin_globalsearch_assoc_site (title, link, onlysw, enabled, rank)
				VALUES ($1, $2, $3, $4, $5)',
			     array ($new_title,
				    $new_link,
				    $new_onlysw,
				    $new_enabled,
				    $new_rank))) {
		echo _('Associated Forge successfully added.');
        } else {
                echo db_error();
        }
}

/**
 *      globalsearch_admin_table_confirmdelete() - present a form to confirm requested record deletion
 *
 *      @param $id - the id of the record to act on
 */
function globalsearch_admin_table_confirmdelete ($id) {
        global $HTML;

        $result = db_query_params ('SELECT * FROM plugin_globalsearch_assoc_site WHERE assoc_site_id=$1',
				   array($id));
        if ($result and db_numrows($result) == 1) {
		$title     =  db_result ($result, 0, 'title');
		$link      =  db_result ($result, 0, 'link');
		$onlysw    =  db_result ($result, 0, 'onlysw');
		$enabled   =  db_result ($result, 0, 'enabled');
		$rank      =  db_result ($result, 0, 'rank');

                echo _('Are you sure you want to delete this associated forge?') ;
		echo '<table>';
		echo '<tr><td><strong>'._('Title').'</strong></td><td>'.$title.'</td></tr>';
		echo '<tr><td><strong>'._('Link').'</strong></td><td>'.$link.'</td></tr>';
		echo '<tr><td><strong>'._('Software only').'</strong></td><td>'.(($onlysw == 't')?_('Yes'):_('No')) .'</td></tr>';
		echo '<tr><td><strong>'._('Enabled').'</strong></td><td>'.(($enabled == 't')?_('Yes'):_('No')) .'</td></tr>';
		echo '<tr><td><strong>'._('Rank').'</strong></td><td>'.$rank.'</td></tr>';
		echo '</table>' ;
		echo $HTML->openForm(array('name' => 'delete', 'action' => '/plugins/globalsearch/edit_assoc_sites.php?function=delete&id='.$id, 'method' => 'post'));
		echo '<input type="submit" value="'._('Delete').'" />';
		echo util_make_link('/plugins/globalsearch/edit_assoc_sites.php', '<input type="button" value="'._('Cancel').'" />');
		echo $HTML->closeForm();
        } else {
                echo db_error();
        }
}

/**
 *      globalsearch_admin_table_delete() - delete a record from the database after confirmation
 *
 *      @param $id - the id of the record to act on
 */
function globalsearch_admin_table_delete ($id) {
        if (db_query_params ('DELETE FROM plugin_globalsearch_assoc_site WHERE assoc_site_id=$1',
			     array($id))) {
		echo _('Associated Forge successfully deleted.');
        } else {
                echo db_error();
        }
}

/**
 *      globalsearch_admin_table_edit() - present a form for editing a record in the specified table
 *
 *      @param $id - the id of the record to act on
 */
function globalsearch_admin_table_edit ($id) {
        global $HTML;

        $result = db_query_params ('SELECT * FROM plugin_globalsearch_assoc_site WHERE assoc_site_id=$1',
				   array($id));
        if ($result and db_numrows($result) == 1) {
		$old_title     =  db_result ($result, 0, 'title');
		$old_link      =  db_result ($result, 0, 'link');
		$old_onlysw    =  db_result ($result, 0, 'onlysw');
		$old_enabled   =  db_result ($result, 0, 'enabled');
		$old_rank      =  db_result ($result, 0, 'rank');

		echo _('Modify the associated forge below');
		echo $HTML->openForm(array('name' => 'edit', 'action' => '/plugins/globalsearch/edit_assoc_sites.php?function=postedit&id='.$id, 'method' => 'post'));
		echo '<table>';

		echo '<tr><td><strong>'._('Title').'</strong></td><td><input type="text" name="title" value="'.$old_title.'"/></td></tr>';
		echo '<tr><td><strong>'._('Link').'</strong></td><td><input type="text" name="link" value="'.$old_link.'"/></td></tr>';
		echo '<tr><td><strong>'._('Software only').'</strong></td><td><input type="checkbox" '.(($old_onlysw == 't')?'checked':'') .' name="onlysw" value="t"/></td></tr>';
		echo '<tr><td><strong>'._('Enabled').'</strong></td><td><input type="checkbox" '.(($old_enabled == 't')?'checked':'') .' name="enabled" value="t"/></td></tr>';
		echo '<tr><td><strong>'._('Rank').'</strong></td><td><input type="text" name="rank" value="'.$old_rank.'"/></td></tr>';

		echo '</table><input type="submit" value="'._('Submit Changes').'" />';
		echo $HTML->closeForm();
		echo util_make_link('/plugins/globalsearch/edit_assoc_sites.php', '<input type="button" value="'._('Cancel').'" />');
        } else {
                echo db_error();
        }
}

/**
 *      globalsearch_admin_table_postedit() - update the database to reflect submitted modifications to a record
 *
 *      @param $id - the id of the record to act on
 */
function globalsearch_admin_table_postedit ($id) {
	$new_title     = getStringFromRequest ('title');
	$new_link      = getStringFromRequest ('link');
	$new_onlysw    = getStringFromRequest ('onlysw');
	$new_enabled   = getStringFromRequest ('enabled');
	$new_rank      = getIntFromRequest ('rank', 999);
	if ($new_onlysw != 't' and $new_onlysw != 'f') {
		$new_onlysw = 'f' ;
	}
	if ($new_enabled != 't' and $new_enabled != 'f') {
		$new_enabled = 'f' ;
	}

        if (db_query_params ('UPDATE plugin_globalsearch_assoc_site SET title=$1, link=$2, onlysw=$3, enabled=$4, rank=$5 WHERE assoc_site_id=$6',
			     array ($new_title,
				    $new_link,
				    $new_onlysw,
				    $new_enabled,
				    $new_rank,
				    $id))) {
		echo _('Associated Forge successfully modified.');
        } else {
                echo db_error();
        }
}

/**
 *      globalsearch_admin_table_show() - display the specified table, sorted by the primary key, with links to add, edit, and delete
 */
function globalsearch_admin_table_show () {
	global $HTML;

	$result = db_query_params ('SELECT * FROM plugin_globalsearch_assoc_site ORDER BY assoc_site_id',
				   array());
	if ($result) {
		$rows = db_numrows($result);

		$cell_data=array();
		$cell_data[]=array(ngettext('Associated Forge','Associated Forges',$rows).' '.util_make_link(getStringFromServer('PHP_SELF').'?function=add', '['._('add new').']'),
			'colspan' => 8);

		echo '<table border="0" width="100%">';
		echo $HTML->multiTableRow(array(),$cell_data, TRUE);

		echo '<tr>';
		echo '<td style="width:5%;"></td>';
		echo '<td><strong>'._('Forge ID').'</strong></td>';
		echo '<td><strong>'._('Title').'</strong></td>';
		echo '<td><strong>'._('Link').'</strong></td>';
		echo '<td><strong>'._('Software only').'</strong></td>';
		echo '<td><strong>'._('Enabled').'</strong></td>';
		echo '<td><strong>'._('Status').'</strong></td>';
		echo '<td><strong>'._('Rank').'</strong></td>';
                echo '</tr>';

                for ($j = 0; $j < $rows; $j++) {
                        echo '<tr>';

                        $id = db_result($result,$j,0);
                        echo '<td>'.util_make_link(getStringFromServer('PHP_SELF').'?function=edit&amp;id='.$id, '['._('edit').']');
                        echo util_make_link(getStringFromServer('PHP_SELF').'?function=confirmdelete&amp;id='.$id, '['._('delete').']').'</td>';

			echo '<td><strong>'.db_result ($result, $j, 'assoc_site_id').'</strong></td>';
			echo '<td><strong>'.db_result ($result, $j, 'title').'</strong></td>';
			echo '<td><strong>'.db_result ($result, $j, 'link').'</strong></td>';
			echo '<td><strong>'.((db_result ($result, $j, 'onlysw') == 't')?_('Yes'):_('No')).'</strong></td>';
			echo '<td><strong>'.((db_result ($result, $j, 'enabled') == 't')?_('Yes'):_('No')).'</strong></td>';
			echo '<td><strong>'.globalsearch_status_name (db_result ($result, $j, 'status_id')).'</strong></td>';
			echo '<td><strong>'.db_result ($result, $j, 'rank').'</strong></td>';
			echo '</tr>';
                }
                echo '</table>';
        } else {
                echo db_error();
        }
}

function globalsearch_status_name ($status_id) {
	switch ($status_id) {
		case 1: return _('New'); break;
		case 2: return _('OK'); break;
		case 3: return _('Error fetching data'); break;
		case 4: return _('Error parsing data'); break;
		default: return _('Unknown status ID');
	}
}

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
