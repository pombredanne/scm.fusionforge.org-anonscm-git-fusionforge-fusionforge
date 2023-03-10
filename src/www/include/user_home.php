<?php
/**
 * Developer Info Page
 *
 * Copyright 1999-2001 (c) VA Linux Systems
 * Copyright 2010, FusionForge Team
 * Copyright (C) 2011 Alain Peyrat - Alcatel-Lucent
 * Copyright 2012, Franck Villaume - TrivialDev
 * Copyright © 2012
 *	Thorsten Glaser <t.glaser@tarent.de>
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

/**
 * Assumes $user object for displayed user is present
 */

require_once $gfcommon.'include/user_profile.php';
require_once $gfwww.'include/vote_function.php';

echo $HTML->boxTop(_('Personal Information'), _('Personal Information'));
echo html_ao('div', array('about' => '', 'typeof' => 'sioc:UserAccount'));
echo user_personal_information($user);

if (forge_get_config('use_ratings')) {
	echo $HTML->boxMiddle(_('Peer Rating'), _('Peer Rating'));
	echo '<table class="my-layout-table" id="user-profile-rating">';
	if ($user->usesRatings()) {
		vote_show_user_rating($user->getID());
	} else {
		echo '<tr><td colspan="2">';
		echo _('User chose not to participate in peer rating');
		echo '</td></tr>';
	}
	echo '</table><!-- id="user-profile-rating" -->';
}

if (forge_get_config('use_diary')) {
	echo $HTML->boxMiddle(_('Diary and Notes'), _('Diary and Notes'));

	/* Get their diary information */

	$res = db_query_params('SELECT count(*) from user_diary WHERE user_id=$1 AND is_public=1',
				array($user->getID()));
	echo _('Diary/Note entries')._(': ').db_result($res, 0, 0).'
		<p>';
		//.'<span rel="foaf:weblog">'
	echo util_make_link('/developer/diary.php?diary_user='.$user->getID(), htmlentities(_('View Diary and Notes')));
	//.'</span>'.
	echo '</p>
	<p>';
	echo util_make_link('/developer/monitor.php?diary_user='.$user->getID(),
					html_image("ic/check.png", 15, 13) ._('Monitor this Diary')
		) ;
	echo '</p>';
	$hookparams['user_id'] = $user->getID();
	plugin_hook("user_personal_links", $hookparams);
}


echo $HTML->boxMiddle(_('Project Information'), _('Project Information'));

$projects = $user->getGroups();
sortProjectList ($projects) ;
$roles = RBACEngine::getInstance()->getAvailableRolesForUser($user);
sortRoleList($roles);

// see if there were any groups
echo '<div>'."\n";
if (count ($projects) < 1) {
	?>
	<p><?php echo _('This user is not a member of any project.') ?></p>
	<?php
} else { // endif no groups
	echo html_e('p', array(), _('This user is a member of the following projects')._(':'));

	foreach ($projects as $p) {
		if (!forge_check_perm('project_read', $p->getID())) {
			continue;
		}

		$project_link = util_make_link_g ($p->getUnixName(),$p->getID(),$p->getPublicName());
		$project_uri = util_make_url_g($p->getUnixName());
		// sioc:UserGroups for all members of a project are named after /projects/A_PROJECT/members/
		$usergroup_uri = $project_uri .'members/';

		print '<div rel="sioc:member_of">'."\n"
			.'<div about="'. $usergroup_uri .'" typeof="sioc:UserGroup">'."\n"
			.'<div rel="sioc:usergroup_of">'."\n"
			.'<div about="'. $project_uri .'" typeof="sioc:Space">';
		$role_names = array () ;
		$sioc_has_function_close = "";
		foreach ($roles as $r) {
			if ($r instanceof RoleExplicit
			&& $r->getHomeProject() != NULL
			&& $r->getHomeProject()->getID() == $p->getID()) {
				$role_names[] = $r->getName() ;
				print '<div property="sioc:has_function" content= "'.$r->getName().'">';
				$sioc_has_function_close .= "</div>";
			}
		}

		print ('<br />' . $project_link .' ('.htmlspecialchars (implode (', ', $role_names)).')');
		print "\n";

		if (forge_check_perm_for_user ($user, 'project_admin', $p->getID())) {
			echo html_e('div', array('rev' => 'doap:maintainer', 'resource' => '#me'));
		}
		else {
			echo html_e('div', array('rev' => 'doap:developer', 'resource' => '#me'));
		}

		echo $sioc_has_function_close."\n";  // sioc:has_function
		echo "</div>\n";  // sioc:Space .../projects/A_PROJECT/
		echo "</div>\n"; // sioc:usergroup_of
		echo "</div>\n";  // sioc:UserGroup .../projects/A_PROJECT/members
		echo "</div>\n"; // sioc:member_of
	}
} // end if groups
echo "</div>\n"; // prefixes

echo html_ac(html_ap() -1);

$me = session_get_user();
if (forge_get_config('use_ratings')) {
	if ($user->usesRatings() && (!$me || $me->usesRatings())) {

		print "<p>";
		print _('If you are familiar with this user, please take a moment to rate him/her on the following criteria. Keep in mind, that your rating will be visible to the user and others.');
		print "</p>";

		print "<p>";
		printf(_('The %s Peer Rating system is based on concepts from <a href="http://www.advogato.com/">Advogato.</a> The system has been re-implemented and expanded in a few ways.'), forge_get_config ('forge_name'));
		print "</p>";
	?>

		<div class="align-center">
			<?php echo vote_show_user_rate_box($user->getID(), $me ? $me->getID() : 0); ?>
		</div>

	<?php
		print "<p>";
		print _('The Peer rating box shows all rating averages (and response levels) for each individual criteria. Due to the math and processing required to do otherwise, these numbers incorporate responses from both “trusted” and “non-trusted” users.');
		print "</p>";

		print "<ul>";
		print "<li>";
		printf(_('The “Sitewide Rank” field shows the user\'s rank compared to all ranked %s users.'), forge_get_config('forge_name'));
		print "</li>";

		print "<li>";
		print _('The “Aggregate Score” shows an average, weighted overall score, based on trusted-responses only.');
		print "</li>";

		print "<li>";
		print _('The “Personal Importance” field shows the weight that users ratings of other developers will be given (between 1 and 1.5) -- higher rated user\'s responses are given more weight.');
		print "</li>";
		print "</ul>";

		print "<p>";
		print "<em>";
		printf(_('If you would like to opt-out from peer rating system (this will affect your ability to both rate and be rated), refer to <a href="%s">your account maintenance page</a>. If you choose not to participate, your ratings of other users will be permanently deleted and the “Peer Rating” box will disappear from your user page.'),
				util_make_url("/account"));
		print "</em>";
		print "</p>";

	} elseif ($me && !$me->usesRatings()) {
		print "<p>";
		print "<em>";
		printf(_('You opted-out from peer rating system, otherwise you would have a chance to rate the user. Refer to <a href="%s">your account maintenance page</a> for more information.'),
				util_make_url("/account"));
		print "</em>";
		print "</p>";
	}
}
echo $HTML->boxBottom();

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
