<?php
/**
 * Role Editing Page
 *
 * Copyright 2010-2011, Roland Mas
 * Copyright (c) 2011 Thorsten Glaser <t.glaser@tarent.de>
 * Copyright 2013-2014,2016, Franck Villaume - TrivialDev
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
require_once $gfwww.'admin/admin_utils.php';
require_once $gfwww.'include/role_utils.php';

global $HTML;

site_admin_header(array('title'=>_('Site Admin')));

$role_id = getIntFromRequest('role_id');
$data = getStringFromRequest('data');

if (getStringFromRequest('add')) {
	$role_name = trim(getStringFromRequest('role_name')) ;
	$role = new Role (NULL) ;
	$role_id=$role->createDefault($role_name) ;
} else {
	$role = RBACEngine::getInstance()->getRoleById($role_id) ;
}

if (!$role || !is_object($role)) {
	exit_error(_('Could Not Get Role'),'admin');
} elseif ($role->isError()) {
	exit_error($role->getErrorMessage(),'admin');
}

$old_data = $role->getGlobalSettings () ;
$new_data = array () ;

if (!is_array ($data)) {
	$data = array () ;
}
foreach ($old_data as $section => $values) {
	if (!array_key_exists ($section, $data)) {
		continue ;
	}
	foreach ($values as $ref_id => $val) {
		if (!array_key_exists ($ref_id, $data[$section])) {
			continue ;
		}
		$new_data[$section][$ref_id] = $data[$section][$ref_id] ;
	}
}
$data = $new_data ;

if (getStringFromRequest('submit')) {
	if ($role instanceof RoleExplicit) {
		$role_name = trim(getStringFromRequest('role_name'));
		$public = getIntFromRequest('public') ? true : false ;
	} else {
		$role_name = $role->getName() ;
		$public = $role->isPublic () ;
	}
	if (!$role_name) {
		$warning_msg .= _('Missing Role Name');
	} else {
		if (!$role_id) {
			$role_id=$role->create($role_name,$data);
			if (!$role_id) {
				$error_msg .= $role->getErrorMessage();
			} else {
				$feedback = _('Successfully Created New Role');
			}
		} else {
			if ($role instanceof RoleExplicit) {
				$role->setPublic($public) ;
			}
			if (!$role->update($role_name,$data)) {
				$error_msg .= $role->getErrorMessage();
			} else {
				$feedback = _('Successfully Updated Role');
			}
		}
	}
}

if (getStringFromRequest('adduser')) {
	if ($role instanceof RoleExplicit) {
		$user_name = getStringFromRequest ('form_unix_name') ;
		$u = user_get_object_by_name ($user_name) ;
		if ($u && $u instanceof FFUser && !$u->isError()) {
			if ($role->addUser ($u)) {
				$feedback .= _('User Added Successfully') ;
			} else {
				$error_msg .= _("Error while adding user to role") ;
			}
		} else {
			$error_msg .= _('Wrong user name.');
		}
	} else {
		$error_msg .= _("Cannot add user to this type of role") ;
	}
}

if (getStringFromRequest('dormusers')) {
	$reallyremove = getStringFromRequest('reallyremove');
	if (!$reallyremove) {
		$error_msg .= _('Error: You did not tick the ???really remove??? box!');
	} elseif ($role instanceof RoleExplicit) {
		$rmlist = getArrayFromRequest('rmusers');
		foreach ($rmlist as $user_id) {
			$u = user_get_object ($user_id) ;
			if ($u && $u instanceof FFUser && !$u->isError()) {
				if ($role->removeUser ($u)) {
					$feedback .= sprintf(
					    _('User %s removed successfully') . "\n",
					    $u->getUnixName());
				} else {
					$error_msg .= sprintf(
					    _("Error while removing user %s from role")  . "\n",
					    $u->getUnixName());
				}
			}
		}
	} else {
		$error_msg .= _("Cannot remove user from this type of role") ;
	}
}

if ($role instanceof RoleExplicit) {
	$users = $role->getUsers();
	if (count ($users) > 0) {
		echo '<p><strong>'._('Current users with this role').'</strong></p>' ;

		echo $HTML->openForm(array('action' => '/admin/globalroleedit.php', 'method' => 'post'));
		echo '<input type="hidden" name="role_id" value="'.$role_id.'" />';
		$titleArr = array(_('User Name'), _('Remove'));
		echo $HTML->listTableTop($titleArr);
		foreach ($users as $user) {
			$cells = array();
			$display = $user->getRealName();
			if (empty($display)) {
				$display = $user->getUnixName();
			}
			$cells[] = array(util_make_link('/users/'.$user->getUnixName(), $display), 'style' => 'white-space:nowrap');
			$cells[][] = '<input type="checkbox" name="rmusers[]" value="'.$user->getID().'" />'._('Remove');
			echo $HTML->multiTableRow(array(), $cells);
		}
		$cells = array();
		$cells[] = array('<input type="checkbox" name="reallyremove" value="1" />'._('Really remove ticked users from role?'), 'colspan' => 2);
		echo $HTML->multiTableRow(array(), $cells);
		$cells = array();
		$cells[] = array('<input type="submit" name="dormusers" value="'._('Remove').'" />', 'colspan' => 2);
		echo $HTML->multiTableRow(array(), $cells);
		echo $HTML->listTableBottom();
		echo $HTML->closeForm();
	} else {
		echo '<p><strong>'._('No users currently have this role').'</strong></p>' ;
	}

	echo $HTML->openForm(array('action' => '/admin/globalroleedit.php', 'method' => 'post')); ?>
	<p><input type="text"
			name="form_unix_name" size="10" value="" />
		<input type="submit" name="adduser"
			value="<?php echo _("Add User") ?>" />
		<input type="hidden" name="role_id" value="<?php echo $role_id; ?>" />
		</p>
<?php
	echo $HTML->closeForm();
}

echo $HTML->openForm(array('action' => '/admin/globalroleedit.php', 'method' => 'post'));
echo '<input type="hidden" name="role_id" value="'.$role_id.'" />' ;

if ($role instanceof RoleExplicit) {
	echo '<p><strong>'._('Role Name').'</strong><br /><input type="text" name="role_name" value="'.$role->getName().'"></p>';
	echo '<input type="checkbox" name="public" value="1"' ;
	if ($role->isPublic()) {
		echo ' checked="checked"' ;
	}
	echo '/> '._('Public role (can be referenced by projects)').'</p>' ;
} else {
	echo '<p><strong>'._('Role Name').'</strong><br />'.$role->getName().'</p>';
}

$titles[]=_('Section');
$titles[]=_('Subsection');
$titles[]=_('Setting');

setup_rbac_strings () ;

echo $HTML->listTableTop($titles);

//
//	Get the keys for this role and iterate to build page
//
//	Everything is built on the multi-dimensional arrays in the Role object
//

$keys = array_keys($role->getGlobalSettings ()) ;
$keys2 = array () ;
foreach ($keys as $key) {
	if (in_array ($key, $role->global_settings)) {
		$keys2[] = $key ;
	}
}
$keys = $keys2 ;

for ($i=0; $i<count($keys); $i++) {
	$cells = array();
	$cells[] = array('<strong>'.$rbac_edit_section_names[$keys[$i]].'</strong>', 'colspan' => 2);
	$cells[][] = html_build_select_box_from_assoc($role->getRoleVals($keys[$i]), "data[".$keys[$i]."][-1]", $role->getVal($keys[$i],-1), false, false );
	echo $HTML->multiTableRow(array(), $cells);
}
echo $HTML->listTableBottom();

echo '<p><input type="submit" name="submit" value="'._('Submit').'" /></p>';
echo $HTML->closeForm();

echo $HTML->openForm(array('action' => '/admin/globalroledelete.php', 'method' => 'post'));
echo '<input type="hidden" name="role_id" value="'.$role_id.'" />';

echo '<p><strong>'._('Delete role').'</strong></p>';
echo '<p><input type="checkbox" name="sure" value="1"/> '._("Really delete this role?");
echo '<input type="submit" name="submit" value="'._('Delete role').'" /></p>';
echo $HTML->closeForm();

site_admin_footer();

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
