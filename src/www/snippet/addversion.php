<?php
/**
 * Code Snippets Repository
 *
 * Copyright 1999-2001 (c) VA Linux Systems
 * Copyright 2014,2016, Franck Villaume - TrivialDev
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

global $HTML;

if (session_loggedin()) {
	$type = getStringFromRequest('type');
	$id = getIntFromRequest('id');

	if ($type=='snippet') {
		/*
			See if the snippet exists first
		*/
		$result=db_query_params ('SELECT * FROM snippet AS s INNER JOIN snippet_version AS sv ON (s.snippet_id = sv.snippet_id) WHERE s.snippet_id=$1 ORDER BY snippet_version_id DESC LIMIT 1',
			array($id));
		if (!$result || db_numrows($result) < 1) {
			exit_error(_('Error: snippet does not exist'));
		}

		/*
			handle inserting a new version of a snippet
		*/
		if (getStringFromRequest('post_changes')) {
			if (!form_key_is_valid(getStringFromRequest('form_key'))) {
				exit_form_double_submit();
			}

			$snippet_id = getIntFromRequest('snippet_id');
			$changes = getStringFromRequest('changes');
			$version = getStringFromRequest('version');
			$code = getStringFromRequest('code');

			/*
				Create a new snippet entry, then create a new snippet version entry
			*/
			if ($changes && $version && $code) {

				/*
					create the snippet version
				*/
				$result = db_query_params ('INSERT INTO snippet_version (snippet_id,changes,version,submitted_by,post_date,code) VALUES ($1,$2,$3,$4,$5,$6)',
							   array ($snippet_id,
								  htmlspecialchars($changes),
								  htmlspecialchars($version),
								  user_getid(),
								  time(),
								  htmlspecialchars($code)));
				if (!$result) {
					$error_msg .= _('Error doing snippet version insert').' '.db_error();
				} else {
					form_release_key(getStringFromRequest("form_key"));
					$feedback .= _('Snippet Version Added Successfully.');
					session_redirect('snippet/detail.php?type=snippet&id='.$id);
				}
			} else {
				exit_error(_('Error')._(': ')._('Go back and fill in all the information'));
			}

		}
		html_use_ace();
		snippet_header(array('title'=>_('New snippet version')));


		$last_version=db_result($result,0,'version');
		$last_code=db_result($result,0,'code');

		?>
		<p><?php echo _('If you have modified a version of a snippet and you feel it is significant enough to share with others, please do so.'); ?></p>
		<?php echo $HTML->openForm(array('action' => getStringFromServer('PHP_SELF'), 'method' => 'post')); ?>
		<input type="hidden" name="form_key" value="<?php echo form_generate_key(); ?>">
		<input type="hidden" name="post_changes" value="y" />
		<input type="hidden" name="type" value="snippet" />
		<input type="hidden" name="snippet_id" value="<?php echo $id; ?>" />
		<input type="hidden" name="id" value="<?php echo $id; ?>" />

		<table>
		<tr><td colspan="2"><strong><?php echo _('Last version')._(':'); ?></strong><br />
			<?php echo $last_version ?>
		</td></tr>
		<tr><td colspan="2"><label for="version"><strong><?php echo _('Version')._(':'). utils_requiredField(); ?></strong></label><br />
			<input id="version" type="text" name="version" size="10" maxlength="15" required="required" />
		</td></tr>
		<tr><td colspan="2"><label for="changes"><strong><?php echo _('Changes')._(':'). utils_requiredField(); ?></strong></label><br />
			<textarea id="changes" name="changes" rows="5" cols="45" required="required"></textarea>
		</td></tr>
		<tr><td colspan="2">
			<?php echo $HTML->html_input('code', '', _('Paste the Code Here').utils_requiredField()._(': '), 'hidden', array('value'=>$last_code)); ?>
			<div id='editor'><?php echo $last_code; ?></div>
		</td></tr>
		<tr><td colspan="2" class="align-center">
			<strong><?php echo _('Make sure all info is complete and accurate'); ?></strong>
			<br />
			<input type="submit" name="submit" value="<?php echo _('Submit'); ?>" />
		</td></tr>
		</table>
		<?php
		echo $HTML->closeForm();

		$jsvar = "var mode = '".$SCRIPT_LANGUAGE_ACE[db_result($result,0,'language')]."';\n";
		$javascript = <<<'EOS'
	var editor = ace.edit("editor");
	editor.setOptions({
		minLines: 20,
		maxLines: 40,
		mode: "ace/mode/"+mode,
		autoScrollEditorIntoView: true
	});
	editor.session.setMode({
		path:"ace/mode/"+mode,
		inline:true
	});
	editor.getSession().on('change', function () {
		$('input#code').val(editor.getSession().getValue());
	});
EOS;
		echo html_e('script', array( 'type'=>'text/javascript'), '//<![CDATA['."\n".'$(function(){'.$jsvar.$javascript.'});'."\n".'//]]>');

		snippet_footer();

	} elseif ($type=='package') {
		/*
			Handle insertion of a new package version
		*/

		/*
			See if the package exists first
		*/
		$result=db_query_params ('SELECT * FROM snippet_package WHERE snippet_package_id=$1',
					array($id));
		if (!$result || db_numrows($result) < 1) {
			exit_error(_('Error: snippet_package does not exist'));
		}

		if (getStringFromRequest('post_changes')) {
			if (!form_key_is_valid(getStringFromRequest('form_key'))) {
				exit_form_double_submit();
			}

			$snippet_package_id = getIntFromRequest('snippet_package_id');
			$changes = getStringFromRequest('changes');
			$version = getStringFromRequest('version');

			/*
				Create a new snippet entry, then create a new snippet version entry
			*/
			if ($changes && $snippet_package_id) {
				/*
					create the snippet package version
				*/
				$result = db_query_params ('INSERT INTO snippet_package_version (snippet_package_id,changes,version,submitted_by,post_date) VALUES ($1,$2,$3,$4,$5)',
							   array ($snippet_package_id,
								  htmlspecialchars($changes),
								  htmlspecialchars($version),
								  user_getid(),
								  time()));
				if (!$result) {
					//error in database
					$error_msg .= _('Error doing snippet package version insert').' '.db_error();
					snippet_header(array('title'=>_('New snippet package')));
					snippet_footer();
					exit;
				} else {
					//so far so good - now add snippets to the package
					$feedback .= _('Snippet Package Version Added Successfully.');

					//id for this snippet_package_version
					$snippet_package_version_id=
						db_insertid($result,'snippet_package_version','snippet_package_version_id');
					snippet_header(array('title'=>_('Add snippet to package')));

/*
	This raw HTML allows the user to add snippets to the package
*/
					?>

<script type="text/javascript">/* <![CDATA[ */
function show_add_snippet_box() {
	var newWindow = open('','occursDialog','height=500,width=300,scrollbars=yes,resizable=yes');
	newWindow.location=('/snippet/add_snippet_to_package.php?snippet_package_version_id=<?php
			echo $snippet_package_version_id; ?>');
}
/* ]]> */</script>
<body onLoad="show_add_snippet_box()">

<p><span class="important"><?php echo _('IMPORTANT!'); ?></span></p>
<p>
<?php echo _('If a new window opened, use it to add snippets to your package. If a new window did not open, use the following link to add to your package BEFORE you leave this page.'); ?>
</p>
<p>
<?php echo util_make_link('/snippet/add_snippet_to_package.php?snippet_package_version_id='.$snippet_package_version_id, _('Add snippets to package') , array('target'=>'_blank')); ?></p>
<p><?php echo _('<strong>Browse the library</strong> to find the snippets you want to add, then add them using the new window link shown above.'); ?></p>
<p>

					<?php

					snippet_footer();
					exit;
				}

			} else {
				form_release_key(getStringFromRequest("form_key"));
				exit_error(_('Error')._(': ')._('Go back and fill in all the information'));
			}

		}
		snippet_header(array('title'=>_('New snippet version')));

		?>
		</p>
		<p>
		<?php echo _('If you have modified a version of a package and you feel it is significant enough to share with others, please do so.'); ?></p>
		<p>
		<?php echo $HTML->openForm(array('action' => getStringFromServer('PHP_SELF'), 'method' => 'post')); ?>
		<input type="hidden" name="form_key" value="<?php echo form_generate_key(); ?>">
		<input type="hidden" name="post_changes" value="y" />
		<input type="hidden" name="type" value="package" />
		<input type="hidden" name="snippet_package_id" value="<?php echo $id; ?>" />
		<input type="hidden" name="id" value="<?php echo $id; ?>" />

		<table>
		<tr><td colspan="2"><label for="version"><strong><?php echo _('Version')._(':'); ?></strong></label><br />
			<input id="version" type="text" name="version" size="10" maxlength="15" />
		</td></tr>

		<tr><td colspan="2"><label for="changes"><strong><?php echo _('Changes')._(':'); ?></strong></label><br />
			<textarea id="changes" name="changes" rows="5" cols="45"></textarea>
		</td></tr>

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
		exit_error(_('Error: was the URL or form mangled??'));
	}

} else {
	exit_not_logged_in();
}

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
