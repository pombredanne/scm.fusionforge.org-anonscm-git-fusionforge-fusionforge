<?php
/**
 * Code Snippets Repository
 *
 * Copyright 1999-2001 (c) VA Linux Systems
 * Copyright 2016-2017, Franck Villaume - TrivialDev
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
require_once $gfwww.'snippet/snippet_utils.php';

global $HTML, $feedback, $error_msg;

if (session_loggedin()) {
	if (getStringFromRequest('post_changes')) {
		if (!form_key_is_valid(getStringFromRequest('form_key'))) {
			exit_form_double_submit();
		}
		$name = getStringFromRequest('name');
		$description = getStringFromRequest('description');
		$language = getIntFromRequest('language');
		$category = getIntFromRequest('category');
		$changes = getStringFromRequest('changes');
		$version = getStringFromRequest('version');

		/*
			Create a new snippet entry, then create a new snippet version entry
		*/
		if ($name && $description && $language != 0 && $category != 0 && $version) {
			/*
				Create the new package
			*/
			$result = db_query_params('INSERT INTO snippet_package (category,created_by,name,description,language) VALUES ($1,$2,$3,$4,$5)',
						   array($category,
							  user_getid(),
							  htmlspecialchars($name),
							  htmlspecialchars($description),
							  $language));
			if (!$result) {
				//error in database
				form_release_key(getStringFromRequest("form_key"));
				$error_msg .= _('Error doing snippet package insert').' '.db_error();
				snippet_header(array('title'=>_('Submit A New Snippet Package')));
				snippet_footer();
				exit;
			} else {
				$feedback .= _('Snippet Package Added Successfully.');
				$snippet_package_id=db_insertid($result,'snippet_package','snippet_package_id');
				/*
					create the snippet package version
				*/
				$result = db_query_params('INSERT INTO snippet_package_version (snippet_package_id,changes,version,submitted_by,post_date) VALUES ($1,$2,$3,$4,$5)',
							   array($snippet_package_id,
								  htmlspecialchars($changes),
								  htmlspecialchars($version),
								  user_getid(),
								  time()));
				if (!$result) {
					//error in database
					$error_msg .= _('Error doing snippet package version insert');
					snippet_header(array('title'=>_('Submit A New Snippet Package')));
					echo db_error();
					snippet_footer();
					exit;
				} else {
					//so far so good - now add snippets to the package
					$feedback .= _('Snippet Package Version Added Successfully.');

					//id for this snippet_package_version
					$snippet_package_version_id = db_insertid($result,'snippet_package_version','snippet_package_version_id');
					snippet_header(array('title'=>_('Add snippets to package')));

/*
	This raw HTML allows the user to add snippets to the package
*/

					?>

<script type="text/javascript">/* <![CDATA[ */
function show_add_snippet_box() {
	var newWindow = open('','occursDialog','height=500,width=300,scrollbars=yes,resizable=yes');
	newWindow.location=('/snippet/add_snippet_to_package.php?suppress_nav=1&snippet_package_version_id=<?php
			echo $snippet_package_version_id; ?>');
}
/* ]]> */</script>
<body onload="show_add_snippet_box()">

<p>
<span class="important"><?php echo _('IMPORTANT!'); ?></span>
<p>
<?php echo _('If a new window opened, use it to add snippets to your package. If a new window did not open, use the following link to add to your package BEFORE you leave this page.'); ?></p>

<p><?php echo util_make_link ('/snippet/add_snippet_to_package.php?snippet_package_version_id='.$snippet_package_version_id,_('Add snippets to package')); ?></p>

<p>
<?php echo _('<strong>Browse the library</strong> to find the snippets you want to add, then add them using the new window link shown above.'); ?>
<p>

					<?php

					snippet_footer();
					exit;
				}
			}
		} else {
			form_release_key(getStringFromRequest("form_key"));
			exit_error(_('Error')._(': ')._('Go back and fill in all the information'));
		}

	}
	snippet_header(array('title'=>_('Submit A New Snippet Package')));

	?>
	<p>
	<?php echo _('You can group together existing snippets into a package using this interface. Before creating your package, make sure all your snippets are in place and you have made a note of the snippet ID\'s.'); ?>
	</p>
	<ol>
		<li><?php echo _('Create the package using this form.'); ?></li>
		<li><?php echo _('<strong>Then</strong> use the ???Add Snippets to Package??? link to add files to your package.'); ?></li>
	</ol>
	<p><?php echo _('<span class="important">Note:</span> You can submit a new version of an existing package by browsing the library and using the link on the existing package. You should only use this page if you are submitting an entirely new package.'); ?>
	</p>
	<?php
	echo $HTML->openForm(array('action' => getStringFromServer('PHP_SELF'), 'method' => 'post'));
	echo $HTML->html_input('form_key', '', '', 'hidden', form_generate_key());
        echo $HTML->html_input('post_changes', '', '', 'hidden', 'y');
        echo $HTML->html_input('changes', '', '', 'hidden', _('First Posted Version'));
	?>

	<table>

	<tr>
	<td colspan="2">
		<?php echo $HTML->html_input('name', '', _('Title').utils_requiredField()._(': '), 'text', '', array('size' => '45', 'maxlength' => '60', 'required' => 'required')); ?>
	</td>
	</tr>

	<tr>
	<td colspan="2">
		<?php echo $HTML->html_textarea('description', '', _('Description').utils_requiredField()._(': '), '', array('rows' => '5', 'cols' => '45', 'required' => 'required')); ?>
	</td>
	</tr>

	<tr>
	<td>
		<?php echo $HTML->html_select($SCRIPT_LANGUAGE, 'language', _('Language').utils_requiredField()._(': ')); ?>
		<!--<?php echo util_make_link ('/support/?func=addsupport&group_id=1',_('Suggest a Language')); ?>-->
	</td>

	<td>
		<?php echo $HTML->html_select($SCRIPT_CATEGORY, 'category', _('Category').utils_requiredField()._(': ')); ?>
		<!--<?php echo util_make_link ('/support/?func=addsupport&group_id=1',_('Suggest a Category')); ?>-->
	</td>
	</tr>

	<tr>
	<td colspan="2">
		<?php echo $HTML->html_input('version', '', _('Version').utils_requiredField()._(': '), 'text', '', array('size' => '10', 'maxlength' => '15', 'required' => 'required')); ?>
	</td>
	</tr>

	<tr><td colspan="2" class="align-center">
		<strong><?php echo _('Make sure all info is complete and accurate'); ?></strong>
		<br />
		<input type="submit" name="submit" value="<?php echo _('Submit'); ?>" />
	</td></tr>

	</table>
	<?php
	echo $HTML->closeForm();
	snippet_footer();
} else {
	exit_not_logged_in();
}

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
