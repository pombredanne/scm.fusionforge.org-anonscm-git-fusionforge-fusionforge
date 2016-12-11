<?php
/**
 * Comment Tracker Content Widget Class
 *
 * Copyright 2016, Franck Villaume - TrivialDev
 * http://fusionforge.org
 *
 * This file is a part of Fusionforge.
 *
 * Fusionforge is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Fusionforge is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Fusionforge. If not, see <http://www.gnu.org/licenses/>.
 */

require_once 'Widget.class.php';

class Widget_TrackerComment extends Widget {

	var $content;

	function __construct() {
		$request =& HTTPRequest::instance();
		$owner_id   = (int)substr($request->get('owner'), 1);
		parent::__construct('trackercomment', $owner_id, WidgetLayoutManager::OWNER_TYPE_TRACKER);
		$this->content['title'] = _('Follow-up tabs');
	}

	function getTitle() {
		return $this->content['title'];
	}

	function getDescription() {
		return _('Default widget where comments, attachments, ... tabs are stored & displayed.');
	}

	function isAvailable() {
		return isset($this->content['title']);
	}

	function getContent() {
		global $ath;
		global $ah;
		global $group_id;
		global $group;
		global $aid;
		global $atid;
		global $HTML;
		global $func;
		$return = '';
		if ($func == 'detail') {
			$count = $ah->hasMessages();
			$nb = $count? ' ('.$count.')' : '';
			$file_list = $ah->getFiles();
			$count=count($file_list);
		}
		$nbf = (isset($count) && $count)? ' ('.$count.')' : '';
		$elementsLi = array();
		if ($func == 'detail') {
			$elementsLi[] = array('content' => util_make_link('#tabber-comments', _('Comments').$nb, false, true));
			if ($group->usesPM()) {
				$elementsLi[] = array('content' => util_make_link('#tabber-tasks', _('Related Tasks'), false, true));
			}
		}
		$elementsLi[] = array('content' => util_make_link('#tabber-attachments', _('Attachments').$nbf, false, true));
		$pluginfound = false;
		if ($func == 'detail') {
			$pm = plugin_manager_get_object();
			$pluginsListeners = $pm->GetHookListeners('artifact_extra_detail');

			foreach ($pluginsListeners as $pluginsListener) {
				if ($group->usesPlugin($pluginsListener)) {
					$pluginfound = true;
					break;
				}
			}
			if ($pluginfound) {
				$elementsLi[] = array('content' => util_make_link('#tabber-commits', _('Commits'), false, true));
			}
			$elementsLi[] = array('content' => util_make_link('#tabber-changes', _('Changes'), false, true));
			if ($ah->hasRelations()) {
				$elementsLi[] = array('content' => util_make_link('#tabber-relations', _('Relations'), false, true));
			}
			if (forge_get_config('use_object_associations')) {
				$tabTitle = _('Associations');
				if ($ah->getAssociationCounter()) {
					$tabTitle .= ' ('.$ah->getAssociationCounter().')';
				}
				$elementsLi[] = array('content' => util_make_link('#tabber-associations', $tabTitle, false, true));
			}
		}
		$tabberContent = '';
		if ($func == 'detail') {
			$divContent = '';
			if (forge_check_perm('tracker', $atid, 'tech')) {
				$divContent .= html_e('strong', array(), _('Use Canned Response')._(':')).html_e('br').
						$ath->cannedResponseBox('canned_response').' '.util_make_link('/tracker/admin/?group_id='.$group_id.'&atid='.$ath->getID().'&add_canned=1', '('._('Admin').')').html_e('br').
						'<script type="text/javascript">//<![CDATA[
							jQuery("#tracker-canned_response").change(function() {
								jQuery.ajax({
									type: "POST",
									url: "index.php",
									data: "rtype=ajax&function=get_canned_response&group_id='.$group_id.'&canned_response_id="+jQuery("#tracker-canned_response").val(),
									success: function(rep){
										// the following line is not the best but works with IE6
										jQuery("#tracker-canned_response option").each(function() {jQuery(this).attr("selected", "selected"); return false;});
										if (jQuery("#tracker-comment").val()) {
											rep = "\n" + rep
										}
										jQuery("#tracker-comment").val(jQuery("#tracker-comment").val() + rep);
									}
								});
							});
						//]]></script>';
			}
			if (forge_check_perm('tracker', $atid, 'submit')) {
				$divContent .= html_e('strong', array(), _('Post Comment')._(':')).html_e('br').
						html_e('textarea', array('id' => 'tracker-comment', 'name' => 'details', 'rows' => 7, 'style' => 'width: 100%', 'title' => util_html_secure(html_get_tooltip_description('comment'))), '', false);
			}
			$tabberContent .= html_e('div', array('id' => 'tabber-comments', 'class' => 'tabbertab'), $divContent.$ah->showMessages());
			if ($group->usesPM()) {
				$tabberContent .= html_e('div', array('id' => 'tabber-tasks', 'class' => 'tabbertab'),
							$ath->renderRelatedTasks($group, $ah).
							util_make_link('/tracker/?func=taskmgr&group_id='.$group_id.'&atid='.$atid.'&aid='.$aid, html_image('ic/taskman20w.png', 20, 20).'<strong>'._('Build Task Relation').'</strong>'));
			}
		}
		$attachmentContent = '';
		if (forge_check_perm('tracker', $atid, 'submit')) {
			$attachmentContent .=  html_e('strong', array(), _('Attach Files')._(':')).' ('._('max upload size')._(': ').human_readable_bytes(util_get_maxuploadfilesize()).')'.html_e('br');
			for ($i = 0; $i < 5; $i++) {
				$attachmentContent .= html_e('input', array('type' => 'file', 'name' => 'input_file'.$i, 'size' => 30)).html_e('br');
			}
		}
		if ($func == 'detail') {
			$attachmentContent .= $ath->renderFiles($group_id, $ah);
		}

		$tabberContent .= html_e('div', array('id' => 'tabber-attachments', 'class' => 'tabbertab'),
						$attachmentContent, false);
		if ($pluginfound) {
			//TODO: implement it!!!
			$tabberContent .= html_e('div', array('id' => 'tabber-commits', 'class' => 'tabbertab'),
							'', false);
		}
		if ($func == 'detail') {
			$tabberContent .= html_e('div', array('id' => 'tabber-changes', 'class' => 'tabbertab'),
						$ah->showHistory());
			$tabberContent .= $ah->showRelations();
			if (forge_get_config('use_object_associations')) {
				$associationContent = $ah->showAssociations('/tracker/?func=removeassoc&aid='.$ah->getID().'&group_id='.$group_id.'&atid='.$ath->getID());
				if (forge_check_perm('tracker', $atid, 'tech')) {
					$associationContent .= $ah->showAddAssociations();
				}
				$tabberContent .= html_e('div', array('id' => 'tabber-associations', 'class' => 'tabbertab'), $associationContent);
			}
		}
		$return .= html_e('div', array('id' => 'tabber'), $HTML->html_list($elementsLi).$tabberContent);

		if (forge_check_perm('tracker', $atid, 'submit')) {
			$return .= html_e('p', array('class' => 'middleRight'), html_e('input', array('type' => 'submit', 'name' => 'submit', 'value' => _('Save Changes'))));
		}
		return $return;
	}

	function canBeRemove() {
		return false;
	}

	function canBeMinize() {
		return false;
	}

	function getCategory() {
		return _('Trackers');
	}
}