<?php
/**
 * Trove Software Map
 *
 * Copyright 1999-2001 (c) VA Linux Systems
 * http://fusionforge.org/
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
require_once $gfwww.'include/trove.php';

require_once $gfcommon.'include/escapingUtils.php';
require_once $gfwww.'/trove/TroveCategory.class.php';
require_once $gfwww.'/trove/TroveCategoryFactory.class.php';
require_once $gfcommon.'include/utils.php';

if (!forge_get_config('use_trove')) {
	exit_disabled();
}

$categoryId = getIntFromGet('form_cat');

// assign default if not defined
if (!$categoryId) {
	$categoryId = forge_get_config('default_trove_cat');
}

$category = new TroveCategory($categoryId);
$HTML->header(array('title'=>_('Trove Map')));
echo '<hr />';

// We check current filtering directives and display them

$filter = getStringFromGet('discrim');
$tgf = new TroveCategoryFactory;

if($filter) {

	// check and clean the array
	$filterArray = explode(',', $filter);
	$cleanArray = array();
	$count = max(6, sizeof($filterArray));
	for ($i = 0; $i < $count; $i++) {
		if(is_numeric($filterArray[$i]) && $filterArray[$i] != 0) {
			$cleanArray[] = (int) $filterArray[$i];
		}
	}
	$filterArray = array_unique($cleanArray);
	if(!empty($filterArray)) {
		$filterCategories = $tgf->getCategories($filterArray);

		echo '<p><span style="color:red;">'._('Limiting View').'</span>';

		for($i=0, $count = sizeof($filterCategories); $i < $count; $i++) {
			$filterCategory =& $filterCategories[$i];
			echo '<br /> &nbsp; &nbsp; &nbsp; '
				.$filterCategory->getFullPath()
				.' <a href="?form_cat='.$category->getID()
				.getFilterUrl($filterArray, $filterCategory->getID()).'">['._('Remove Filter').']'
				.'</a>';
		}

		echo '</p><hr />';
	}

	$category->setFilter($filterArray);
}

// We display the trove

?>
<table class="fullwidth">
	<tr class="top">
		<td class="halfwidth">
		<?php
			// here we print list of root level categories, and use open folder for current
			$rootCategories = $tgf->getRootCategories();
			echo _('Browse By')._(':');
			for($i = 0, $count = sizeof($rootCategories); $i < $count; $i++) {
				$rootCategory =& $rootCategories[$i];

				// print open folder if current, otherwise closed
				// also make anchor if not current

				echo '<br />';
				if (($rootCategory->getID() == $category->getRootParentId())
					|| ($rootCategory->getID() == $category->getID())) {

					echo $HTML->getOpenFolderPic();
					echo '&nbsp; <strong>'.$rootCategory->getLocalizedLabel().'</strong>';
				} else {
					echo '<a href="?form_cat='.$rootCategory->getID().@$discrim_url.'">';
					echo $HTML->getFolderPic();
					echo '&nbsp; '.$rootCategory->getLocalizedLabel();
					echo '</a>';
				}
			}
		?>
		</td>
		<td class="halfwidth">
		<?php
			$currentIndent='';
			$parentCategories =& $category->getParents();
			for ($i=0, $count = sizeof($parentCategories); $i < $count; $i++) {
				echo str_repeat(' &nbsp; ', $i * 2);

				echo $HTML->getOpenFolderPic();
				echo '&nbsp; ';
				if($parentCategories[$i]['id'] != $category->getID()) {
					echo '<a href="?form_cat='.$parentCategories[$i]['id'].$discrim_url.'">';
				} else {
					echo '<strong>';
				}
				echo $parentCategories[$i]['name'];
				if($parentCategories[$i]['id'] != $category->getID()) {
					echo '</a>';
				} else {
					echo '</strong>';
				}
				echo '<br />';
			}

			$childrenCategories =& $category->getChildren();

			$currentIndent .= str_repeat(' &nbsp; ', sizeof($parentCategories) * 2);

			for($i = 0, $count = sizeof($childrenCategories); $i < $count; $i++) {
				$childCategory =& $childrenCategories[$i];

				echo $currentIndent;
				echo '<a href="?form_cat='.$childCategory->getID().@$discrim_url.'">';
				echo $HTML->getFolderPic();
				echo '&nbsp; '.$childCategory->getLocalizedLabel().'</a>';
				echo ' <em>('
					.sprintf(_('%s projects'), $childCategory->getSubProjectsCount())
					.')</em><br />';
			}
		?>
		</td>
	</tr>
</table>
<hr />
<?php

// We display projects

$offset = getIntFromGet('offset');
$page = getIntFromGet('page');

$projectsResult = $category->getProjects($offset);

// store this as a var so it can be printed later as well
$html_limit = '<span style="text-align:center;font-size:smaller">';
$html_limit .= sprintf (ngettext ('<strong>%d</strong> project in result set.',
				  '<strong>%d</strong> projects in result set.',
				  $querytotalcount),
			$querytotalcount) ;

// only display pages stuff if there is more to display
if ($querytotalcount > $TROVE_BROWSELIMIT) {
	$html_limit .= ' ' . sprintf(
					  _('Displaying %d projects per page. Projects sorted by activity ranking.'),
				      $TROVE_BROWSELIMIT)
		. '<br />';

	// display all the numbers
	for ($i=1;$i<=ceil($querytotalcount/$TROVE_BROWSELIMIT);$i++) {
		$html_limit .= ' ';
		$displayed_i = "&lt;$i&gt;" ;
		if ($page == $i) {
			$html_limit .= "<strong>$displayed_i</strong>" ;
		} else {
			$html_limit .= util_make_link("/softwaremap/trove_list.php?form_cat=$form_cat&page=$i",
						      $displayed_i) ;
		}
		$html_limit .= ' ';
	}
}

$html_limit .= '</span>';

print $html_limit."<hr />\n";

?><table class="fullwidth"><?php
while($project = db_fetch_array($projectsResult)) {
	?>
		<tr class="top">
			<td colspan="2"><?php
	echo $count.'. ' ;
	echo util_make_link_g ($project['unix_group_name'],
			       $project['group_id'],
			       '<strong>'.htmlspecialchars($project['group_name']).'</strong>');
			if ($project['short_description']) {
				echo ' - '.htmlspecialchars($project['short_description']);
			}
			?>
			<br />&nbsp;
			</td>
		</tr>
		<tr class="top">
			<td><?php
				// list all trove categories
				//print trove_getcatlisting($row_grp['group_id'],1,0);
			?></td>
			<td class="align-right">
				Activity Percentile: <strong><?php echo number_format($project['percentile'],2) ?></strong>
				<br />Activity Ranking: <strong><?php echo number_format($project['ranking'],2) ?></strong>
				<br />Register Date: <strong><?php echo date($sys_datefmt, $project['register_time']) ?></strong>
			</td>
		</tr>
		<tr>
			<td colspan="2"><hr /></td>
		</tr>
	<?php
} ?>
</table>
<?php
// print bottom navigation if there are more projects to display
if ($querytotalcount > $TROVE_BROWSELIMIT) {
	print $html_limit;
}

$HTML->footer();
