<?php
/**
 * Copyright (c) Xerox Corporation, Codendi Team, 2001-2009. All rights reserved
 * Copyright (C) 2011 Alain Peyrat - Alcatel-Lucent
 * Copyright 2013-2018,2021-2022, Franck Villaume - TrivialDev
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

require_once $gfcommon.'widget/WidgetLayout.class.php';
require_once $gfcommon.'widget/Widget.class.php';
require_once $gfcommon.'include/preplugins.php';
require_once $gfcommon.'include/utils.php';

/**
 * WidgetLayoutManager
 *
 * Manage layouts for users, groups and homepage
 */
class WidgetLayoutManager {

	/**
	 * define constants for type of widget page
	 */
	const OWNER_TYPE_USER     = 'u';
	const OWNER_TYPE_GROUP    = 'g';
	const OWNER_TYPE_HOME     = 'h';
	const OWNER_TYPE_TRACKER  = 't';
	const OWNER_TYPE_USERHOME = 'p';

	/**
	 * displayLayout
	 *
	 * Display the default layout for the "owner". It may be the home page, the project summary page, the project tracker artifact view page, or /my/ page.
	 *
	 * @param	int	$owner_id
	 * @param	string	$owner_type
	 */
	function displayLayout($owner_id, $owner_type) {
		$sql = "SELECT * from owner_layouts where owner_id=$1 and owner_type=$2";
		$res = db_query_params($sql, array($owner_id, $owner_type));
		if($res && db_numrows($res)<1) {
			if($owner_type == self::OWNER_TYPE_USER) {
				$this->createDefaultLayoutForUser($owner_id);
				$this->displayLayout($owner_id, $owner_type);
			} elseif ($owner_type == self::OWNER_TYPE_GROUP) {
				$this->createDefaultLayoutForProject($owner_id, 1);
				$this->displayLayout($owner_id, $owner_type);
			} elseif ($owner_type == self::OWNER_TYPE_HOME) {
				$this->createDefaultLayoutForForge($owner_id);
				$this->displayLayout($owner_id, $owner_type);
			} elseif ($owner_type == self::OWNER_TYPE_TRACKER) {
				$this->createDefaultLayoutForTracker($owner_id);
				$this->displayLayout($owner_id, $owner_type);
			} elseif ($owner_type == self::OWNER_TYPE_USERHOME) {
				$this->createDefaultLayoutForUserHome($owner_id);
				$this->displayLayout($owner_id, $owner_type);
			}
		} else {
			$sql = "SELECT l.*
				FROM layouts AS l INNER JOIN owner_layouts AS o ON(l.id = o.layout_id)
				WHERE o.owner_type = $1
				AND o.owner_id = $2
				AND o.is_default = 1
				";
			$req = db_query_params($sql, array($owner_type, $owner_id));
			if ($data = db_fetch_array($req)) {
				$readonly = !$this->_currentUserCanUpdateLayout($owner_id, $owner_type);
				$layout = new WidgetLayout($data['id'], $data['name'], $data['description'], $data['scope']);
				$sql = 'SELECT * FROM layouts_rows WHERE layout_id = $1 ORDER BY rank';
				$req_rows = db_query_params($sql, array($layout->id));
				while ($data = db_fetch_array($req_rows)) {
					$row = new WidgetLayout_Row($data['id'], $data['rank']);
					$sql = 'SELECT * FROM layouts_rows_columns WHERE layout_row_id = $1';
					$req_cols = db_query_params($sql, array($row->id));
					while ($data = db_fetch_array($req_cols)) {
						$col = new WidgetLayout_Row_Column($data['id'], $data['width']);
						$sql = "SELECT * FROM layouts_contents WHERE owner_type = $1  AND owner_id = $2 AND column_id = $3 ORDER BY rank";
						$req_content = db_query_params($sql, array($owner_type, $owner_id, $col->id));
						while ($data = db_fetch_array($req_content)) {
							$c = Widget::getInstance($data['name'], $owner_id);
							if ($c && $c->isAvailable()) {
								$c->loadContent($data['content_id']);
								$col->add($c, $data['is_minimized'], $data['display_preferences']);
							}
							unset($c);
						}
						$row->add($col);
						unset($col);
					}
					$layout->add($row);
					unset($row);
				}
				$layout->display($readonly, $owner_id, $owner_type);
			}
		}
	}

	/**
	 * _currentUserCanUpdateLayout
	 *
	 * @param	int	$owner_id
	 * @param	string	$owner_type
	 * @return	bool	true if the user can update the layout (add/remove widget, collapse, set preferences, ...)
	 */
	function _currentUserCanUpdateLayout($owner_id, $owner_type) {
		$modify = false;
		switch ($owner_type) {
			case self::OWNER_TYPE_USER:
			case self::OWNER_TYPE_USERHOME:
				if (user_getid() == $owner_id) { //Current user can only update its own /my/ page
					$modify = true;
				}
				break;
			case self::OWNER_TYPE_GROUP:
				if (forge_check_perm('project_admin', $owner_id)) { //Only project admin
					$modify = true;
				}
				break;
			case self::OWNER_TYPE_HOME:
				if (forge_check_global_perm('forge_admin')) { //Only site admin
					$modify = true;
				}
				break;
			case self::OWNER_TYPE_TRACKER:
				if (forge_check_global_perm('tracker_admin', $owner_id)) { //Only tracker admin
					$modify = true;
				}
				break;
			default:
				break;
		}
		return $modify;
	}

	function createDefaultLayoutForUserHome($owner_id) {
		db_begin();
		$success = true;
		$sql = "INSERT INTO owner_layouts(layout_id, is_default, owner_id, owner_type) VALUES (1, 1, $1, $2)";
		if (db_query_params($sql, array($owner_id, self::OWNER_TYPE_USERHOME))) {

			$sql = "INSERT INTO layouts_contents(owner_id, owner_type, layout_id, column_id, name, rank) VALUES ";

			$args[] = "($1, $2, 1, 1, 'uhpersonalinformation', 0)";
			$args[] = "($1, $2, 1, 1, 'uhprojectinformation', 1)";
			$args[] = "($1, $2, 1, 1, 'uhpeerratings', 2)";
			$args[] = "($1, $2, 1, 2, 'uhactivity', 0)";

			foreach($args as $a) {
				if (!db_query_params($sql.$a, array($owner_id, self::OWNER_TYPE_USERHOME))) {
					$success = false;
					break;
				}
			}

			/*  $em =& EventManager::instance();
			    $widgets = array();
			    $em->processEvent('default_widgets_for_new_owner', array('widgets' => &$widgets, 'owner_type' => self::OWNER_TYPE_USER));
			    foreach($widgets as $widget) {
			    $sql .= ",($13, $14, 1, $15, $16, $17)";
			    }*/
		} else {
			$success = false;
		}
		if (!$success) {
			$success = db_error();
			db_rollback();
			exit_error(_('DB Error')._(': ').$success, 'widgets');
		}
		db_commit();
	}
	/**
	 * createDefaultLayoutForUser
	 *
	 * Create the first layout for the user and add some initial widgets:
	 * - MyArtifacts
	 * - MyProjects
	 * - MyBookmarks
	 * - MySurveys
	 * - MyMonitoredFP
	 * - MyMonitoredForums
	 * - and widgets of plugins if they want to listen to the event default_widgets_for_new_owner
	 *
	 * @param	int	$owner_id The id of the newly created user
	 */
	function createDefaultLayoutForUser($owner_id) {
		db_begin();
		$success = true;
		$sql = "INSERT INTO owner_layouts(layout_id, is_default, owner_id, owner_type) VALUES (1, 1, $1, $2)";
		if (db_query_params($sql, array($owner_id, self::OWNER_TYPE_USER))) {

			$sql = "INSERT INTO layouts_contents(owner_id, owner_type, layout_id, column_id, name, rank) VALUES ";

			$args[] = "($1, $2, 1, 1, 'myprojects', 0)";
			$args[] = "($1, $2, 1, 1, 'mybookmarks', 1)";
			$args[] = "($1, $2, 1, 1, 'mymonitoredforums', 2)";
			$args[] = "($1, $2, 1, 1, 'mysurveys', 4)";
			$args[] = "($1, $2, 1, 2, 'myartifacts', 0)";
			$args[] = "($1, $2, 1, 2, 'mymonitoredfp', 1)";

			foreach($args as $a) {
				if (!db_query_params($sql.$a, array($owner_id, self::OWNER_TYPE_USER))) {
					$success = false;
					break;
				}
			}

			/*  $em =& EventManager::instance();
			    $widgets = array();
			    $em->processEvent('default_widgets_for_new_owner', array('widgets' => &$widgets, 'owner_type' => self::OWNER_TYPE_USER));
			    foreach($widgets as $widget) {
			    $sql .= ",($13, $14, 1, $15, $16, $17)";
			    }*/
		} else {
			$success = false;
		}
		if (!$success) {
			$success = db_error();
			db_rollback();
			exit_error(_('DB Error')._(': ').$success, 'widgets');
		}
		db_commit();
	}

	function createDefaultLayoutForForge($owner_id) {
		db_begin();
		$success = true;
		$sql = "INSERT INTO owner_layouts (owner_id, owner_type, layout_id, is_default) values ($1, $2, $3, $4)";
		if (db_query_params($sql, array($owner_id, self::OWNER_TYPE_HOME, 1, 1))) {

			$sql = "INSERT INTO layouts_contents(owner_id, owner_type, layout_id, column_id, name, rank) VALUES ";

			$args[] = "($1, $2, 1, 2, 'hometagcloud', 0)";
			$args[] = "($1, $2, 1, 2, 'homestats', 1)";
			$args[] = "($1, $2, 1, 2, 'homeversion', 2)";
			$args[] = "($1, $2, 1, 1, 'homewelcome', 0)";
			$args[] = "($1, $2, 1, 1, 'homelatestnews', 1)";

			foreach($args as $a) {
				if (!db_query_params($sql.$a, array(0, self::OWNER_TYPE_HOME))) {
					$success = false;
					break;
				}
			}
		} else {
			$success = false;
		}
		if (!$success) {
			$success = db_error();
			db_rollback();
			exit_error(_('DB Error')._(': ').$success, 'widgets');
		}
		db_commit();
	}

	function createDefaultLayoutForTracker($owner_id, $template_id = 0, $newEFIds = array()) {
		db_begin();
		$success = true;
		$notemplate = true;
		$res = db_query_params('SELECT content_id FROM layouts_contents WHERE content_id != $1 AND owner_type = $2 AND owner_id = $3', array(0, 't', $owner_id));
		if ($res && db_numrows($res) > 0) {
			$contentIdArr = util_result_column_to_array($res);
			foreach ($contentIdArr as $contentId) {
				db_query_params('DELETE FROM artifact_display_widget_field WHERE id = $1', array($contentId));
				db_query_params('DELETE FROM artifact_display_widget WHERE id = $1 AND owner_id = $2', array($contentId, $owner_id));
			}
		}
		db_query_params('DELETE FROM layouts_contents WHERE owner_id = $1 AND owner_type = $2', array($owner_id, 't'));
		db_query_params('DELETE FROM owner_layouts WHERE owner_id = $1 AND owner_type = $2', array($owner_id, 't'));
		if ($template_id) {
			$res = db_query_params('SELECT layout_id FROM owner_layouts WHERE owner_type = $1 AND owner_id = $2', array(self::OWNER_TYPE_TRACKER, $template_id));
			if ($res && db_numrows($res) == 1) {
				$res = db_query_params('INSERT INTO owner_layouts(layout_id, is_default, owner_id, owner_type)
						SELECT layout_id, is_default, $1, owner_type
						FROM owner_layouts
						WHERE owner_type = $2
						AND owner_id = $3', array($owner_id, self::OWNER_TYPE_TRACKER, $template_id));
				$notemplate = false;
			}
		}
		if ($notemplate) {
			$res = db_query_params('INSERT INTO owner_layouts (owner_id, owner_type, layout_id, is_default) values ($1, $2, $3, $4)', array($owner_id, self::OWNER_TYPE_TRACKER, 1, 1));
		}
		if ($res) {
			if ($notemplate) {
				$sql = "INSERT INTO layouts_contents (owner_id, owner_type, layout_id, column_id, name, rank) VALUES ";

				$args[] = "($1, $2, 1, 1, 'trackersummary', 1)";
				$args[] = "($1, $2, 1, 1, 'trackermain', 2)";
				$args[] = "($1, $2, 1, 1, 'trackerdefaultactions', 3)";
				$args[] = "($1, $2, 1, 2, 'trackergeneral', 1)";
				$args[] = "($1, $2, 1, 2, 'trackercomment', 2)";

				foreach($args as $a) {
					if (!db_query_params($sql.$a,array($owner_id, self::OWNER_TYPE_TRACKER))) {
						$success = false;
						break;
					}
				}

				// owner_id is an atid
				$at = artifactType_get_object($owner_id);
				$extrafields = $at->getExtraFields(array());
				if (count($extrafields) > 0) {
					$res = db_query_params('INSERT INTO artifact_display_widget (owner_id, title) VALUES ($1, $2)', array($owner_id, _('Default ExtraField 2-columns Widget')));
					$content_id = db_insertid($res, 'artifact_display_widget', 'id');
					$row_id = 1;
					$column_id = 1;
					foreach ($extrafields as $key => $extrafield) {
						$column_id = ($key % 2) + 1; // 1 or 2
						if ($column_id == 2) {
							$row_id++;
						}
						db_query_params('INSERT INTO artifact_display_widget_field (id, field_id, column_id, row_id, width, section) VALUES ($1, $2, $3, $4, $5, $6)', array($content_id, $extrafield['extra_field_id'], $column_id, $row_id, 50, ''));
					}
					db_query_params('INSERT INTO layouts_contents (owner_id, owner_type, layout_id, column_id, name, rank, content_id) VALUES ($1, $2, 1, 2, $3, 3, $4)',
							array($owner_id, self::OWNER_TYPE_TRACKER, 'trackercontent', $content_id));
				}
			} else {
				$sql = "SELECT layout_id, column_id, name, rank, is_minimized, is_removed, display_preferences, content_id
					FROM layouts_contents
					WHERE owner_type = $1
					AND owner_id = $2
					";
				if ($req = db_query_params($sql,array(self::OWNER_TYPE_TRACKER, $template_id))) {
					while ($data = db_fetch_array($req)) {
						$content_id = 0;
						if ($data['name'] == 'trackercontent') {
							$res = db_query_params('SELECT title FROM artifact_display_widget WHERE owner_id = $1 AND id = $2', array($template_id, $data['content_id']));
							if ($res && db_numrows($res) > 0) {
								$arr = db_fetch_array($res);
								db_query_params('INSERT INTO artifact_display_widget (owner_id, title) VALUES ($1, $2)', array($owner_id, $arr['title']));
								$content_id = db_insertid($res, 'artifact_display_widget', 'id');
								$res2 = db_query_params('SELECT field_id, column_id, row_id, width, section FROM artifact_display_widget_field WHERE id = $1', array($data['content_id']));
								if ($res2 && db_numrows($res2) > 0) {
									while ($arr2 = db_fetch_array($res2)) {
										db_query_params('INSERT INTO artifact_display_widget_field (id, field_id, column_id, row_id, width, section) VALUES ($1, $2, $3, $4, $5, $6)',
												array($content_id, $newEFIds[$arr2['field_id']], $arr2['column_id'], $arr2['row_id'], $arr2['width'], $arr2['section']));
										echo db_error();
									}
								}
							}
						}
						$sql = 'INSERT INTO layouts_contents (owner_id, owner_type, content_id, layout_id, column_id, name, rank, is_minimized, is_removed, display_preferences)
							VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10)';
						db_query_params($sql, array($owner_id, self::OWNER_TYPE_TRACKER, $content_id, $data['layout_id'], $data['column_id'], $data['name'], $data['rank'], $data['is_minimized'], $data['is_removed'], $data['display_preferences']));
						echo db_error();
					}
				}
			}
		} else {
			$success = false;
		}
		if (!$success) {
			$success = db_error();
			db_rollback();
			exit_error(_('DB Error')._(': ').$success, 'widgets');
		}
		db_commit();
	}

	/**
	 * createLayoutForTrackerFromArray
	 *
	 * Create a specific layout for a new tracker, based on an descriptive array.
	 * The descriptive array is generated by getLayout function.
	 *
	 * @param	int	$owner_id	the id of the newly created tracker
	 * @param	array	$layoutDescArr  the descriptive array.
	 * @return	bool	success
	 */
	function createLayoutForTrackerFromArray($owner_id, $layoutDescArr) {
		if (isset($layoutDescArr['rows']) && is_array($layoutDescArr['rows'])) {
			db_begin();
			$res = db_query_params('SELECT content_id FROM layouts_contents WHERE content_id != $1 AND owner_type = $2 AND owner_id = $3', array(0, 't', $owner_id));
			if ($res && db_numrows($res) > 0) {
				$contentIdArr = util_result_column_to_array($res);
				foreach ($contentIdArr as $contentId) {
					db_query_params('DELETE FROM artifact_display_widget_field WHERE id = $1', array($contentId));
					db_query_params('DELETE FROM artifact_display_widget WHERE id = $1 AND owner_id = $2', array($contentId, $owner_id));
				}
			}
			db_query_params('DELETE FROM layouts_contents WHERE owner_id = $1 AND owner_type = $2', array($owner_id, 't'));
			db_query_params('DELETE FROM owner_layouts WHERE owner_id = $1 AND owner_type = $2', array($owner_id, 't'));
			$sql = "INSERT INTO layouts(name, description, scope) VALUES ('custom', '', 'P')";
			if ($res = db_query_params($sql, array())) {
				if ($new_layout_id = db_insertid($res, 'layouts', 'id')) {
					$res = db_query_params('INSERT INTO owner_layouts (owner_id, owner_type, layout_id, is_default) values ($1, $2, $3, $4)', array($owner_id, self::OWNER_TYPE_TRACKER, $new_layout_id, 1));
					if ($res) {
						foreach ($layoutDescArr['rows'] as $row) {
							$sql = "INSERT INTO layouts_rows(layout_id, rank) VALUES ($1, $2)";
							if ($res = db_query_params($sql, array($new_layout_id, $row['rank']))) {
								if ($row_id = db_insertid($res,'layouts_rows', 'id')) {
									if (isset($row['columns']) && is_array($row['columns'])) {
										foreach ($row['columns'] as $column) {
											$sql = "INSERT INTO layouts_rows_columns(layout_row_id, width) VALUES ($1, $2)";
											db_query_params($sql, array($row_id, $column['width']));
											$column_id = db_insertid($res,'layouts_rows_columns', 'id');
											if (isset($column['contents']) && is_array($column['contents'])) {
												foreach ($column['contents'] as $nkey => $nwidget) {
													$content_id = 0;
													if ($nwidget['content']['id'] == 'trackercontent') {
														$res = db_query_params('INSERT INTO artifact_display_widget (owner_id, title) VALUES ($1, $2)', array($owner_id, $nwidget['content']['trackercontent_title']));
														$content_id = db_insertid($res, 'artifact_display_widget', 'id');
														foreach ($nwidget['content']['layoutExtraFieldIDs'] as $efrkey => $efrow) {
															foreach ($efrow as $efckey => $efcol) {
																$key = key($efcol);
																db_query_params('INSERT INTO artifact_display_widget_field (id, field_id, column_id, row_id, width, section) VALUES ($1, $2, $3, $4, $5, $6)', array($content_id, $key, $efckey, $efrkey, $efcol[$key][0], $efcol[$key][1]));
															}
														}
													}
													$sql = 'INSERT INTO layouts_contents (owner_id, owner_type, content_id, layout_id, column_id, name, rank, is_minimized, is_removed, display_preferences)
															VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10)';
													db_query_params($sql, array($owner_id, self::OWNER_TYPE_TRACKER, $content_id, $new_layout_id, $column_id, $nwidget['content']['id'], $nkey, $nwidget['is_minimized'], 0, $nwidget['display_preferences']));
													echo db_error();
												}
											}
										}
									}
								}
							}
						}
						db_commit();
						return true;
					}
				} else {
					db_rollback();
				}
			} else {
				db_rollback();
			}
		}
		return false;
	}

	/**
	 * createDefaultLayoutForProject
	 *
	 * Create the first layout for a new project, based on its parent template.
	 * Add some widgets based also on its parent configuration and on its service configuration.
	 *
	 * @param	int	$group_id  the id of the newly created project
	 * @param	int	$template_id  the id of the project template
	 */
	function createDefaultLayoutForProject($group_id, $template_id) {
		$project = group_get_object($group_id);
		$sql = "INSERT INTO owner_layouts(layout_id, is_default, owner_id, owner_type)
			SELECT layout_id, is_default, $1, owner_type
			FROM owner_layouts
			WHERE owner_type = $2
			AND owner_id = $3
			";
		if (db_query_params($sql, array($group_id, self::OWNER_TYPE_GROUP, $template_id))) {
			$sql = "SELECT layout_id, column_id, name, rank, is_minimized, is_removed, display_preferences, content_id
				FROM layouts_contents
				WHERE owner_type = $1
				AND owner_id = $2
				";
			if ($req = db_query_params($sql, array(self::OWNER_TYPE_GROUP, $template_id))) {
				while($data = db_fetch_array($req)) {
					$w = Widget::getInstance($data['name']);
					if ($w) {
						$w->setOwner($template_id, self::OWNER_TYPE_GROUP);
						if ($w->canBeUsedByProject($project)) {
							$content_id = $w->cloneContent($w->content_id, $group_id, self::OWNER_TYPE_GROUP);
							$sql = "INSERT INTO layouts_contents(owner_id, owner_type, content_id, layout_id, column_id, name, rank, is_minimized, is_removed, display_preferences)
								VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10);
							";
							db_query_params($sql, array($group_id, self::OWNER_TYPE_GROUP, $content_id, $data['layout_id'], $data['column_id'], $data['name'], $data['rank'], $data['is_minimized'], $data['is_removed'], $data['display_preferences']));
							echo db_error();
						}
					}
				}
			}
		}
		echo db_error();
	}

	/**
	 * createLayoutForProjectFromArray
	 *
	 * Create a specific layout for a new project, based on an descriptive array.
	 * The descriptive array is generated by getLayout function.
	 *
	 * @param	int	$group_id	the id of the newly created project
	 * @param	array	$layoutDescArr  the descriptive array.
	 * @return	bool	success
	 */
	function createLayoutForProjectFromArray($group_id, $layoutDescArr) {
		if (isset($layoutDescArr['rows']) && is_array($layoutDescArr['rows'])) {
			db_query_params('DELETE FROM layouts_contents WHERE owner_id = $1 AND owner_type = $2', array($group_id, self::OWNER_TYPE_GROUP));
			db_query_params('DELETE FROM owner_layouts WHERE owner_id = $1 AND owner_type = $2', array($group_id, self::OWNER_TYPE_GROUP));
			$sql = "INSERT INTO layouts(name, description, scope) VALUES ('custom', '', 'P')";
			if ($res = db_query_params($sql, array())) {
				if ($new_layout_id = db_insertid($res, 'layouts', 'id')) {
					$sql = 'INSERT INTO owner_layouts(layout_id, is_default, owner_id, owner_type) VALUES ($1, $2, $3, $4)';
					if (db_query_params($sql, array($new_layout_id, 1, $group_id, self::OWNER_TYPE_GROUP))) {
						//Create rows & columns
						foreach($layoutDescArr['rows'] as $row) {
							$sql = "INSERT INTO layouts_rows(layout_id, rank) VALUES ($1, $2)";
							if ($res = db_query_params($sql, array($new_layout_id, $row['rank']))) {
								if ($row_id = db_insertid($res,'layouts_rows', 'id')) {
									foreach($row['columns'] as $column) {
										$sql = "INSERT INTO layouts_rows_columns(layout_row_id, width) VALUES ($1, $2)";
										db_query_params($sql, array($row_id, $column['width']));
										$column_id = db_insertid($res,'layouts_rows_columns', 'id');
										foreach ($column['contents'] as $new_widget) {
											$sql = "INSERT INTO layouts_contents (owner_id, owner_type, content_id, layout_id, column_id, name, rank, is_minimized, is_removed, display_preferences)
												VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10);
												";
											db_query_params($sql, array($group_id, self::OWNER_TYPE_GROUP, $new_widget['content']['content_id'], $new_layout_id, $column_id, $new_widget['content']['id'], 0, $new_widget['is_minimized'], 0, $new_widget['display_preferences']));
											echo db_error();
										}
									}
								}
							}
						}
					} else {
						return false;
					}
				}
			}
			return true;
		} else {
			return false;
		}
	}

	/**
	 * displayAvailableWidgets - Display all widgets that the user can add to the layout
	 *
	 * @param	int	$owner_id
	 * @param	string	$owner_type
	 * @param	int	$layout_id
	 */
	function displayAvailableWidgets($owner_id, $owner_type, $layout_id) {
		global $HTML;
		// select already used widgets
		$used_widgets = array();
		$sql = "SELECT *
			FROM layouts_contents
			WHERE owner_type = $1
			AND owner_id = $2
			AND layout_id = $3
			AND content_id = 0 AND column_id <> 0";
		$res = db_query_params($sql, array($owner_type, $owner_id, $layout_id));
		while($data = db_fetch_array($res)) {
			$used_widgets[] = $data['name'];
		}
		// build & display contextual toolbar
		$url = '/widgets/widgets.php?owner='.getStringFromRequest('owner').
			'&layout_id='.getIntFromRequest('layout_id');
		$elementsLi = array();
		$elementsLi[0]['content'] = util_make_link($url, _('Add widgets'));
		$elementsLi[1]['content'] = util_make_link($url.'&update=layout', _('Customize Layout'));
		$update_layout = (getStringFromRequest('update') == 'layout');
		if ($update_layout) {
			// customized selected
			$elementsLi[1]['attrs'] = array('class' => 'current');
			$action = 'layout';
		} else {
			// add selected, or default when first displayed
			$elementsLi[0]['attrs'] = array('class' => 'current');
			$action = 'widget';
		}
		echo $HTML->html_list($elementsLi, array('class' => 'widget_toolbar'));
		echo $HTML->openForm(array('id' => 'builder', 'action' => '/widgets/updatelayout.php?owner='.$owner_type.$owner_id.'&action='.$action.'&layout_id='.$layout_id, 'method' => 'post'));
		if ($update_layout) {
			?>
			<script type='text/javascript'>//<![CDATA[
				var controllerLayoutBuilder;
				jQuery(document).ready(function() {
					controllerLayoutBuilder = new LayoutBuilderController({
						buttonAddRow:		jQuery('.layout-manager-row-add'),
						buttonAddColumn:	jQuery('.layout-manager-column-add'),
						buttonRemoveColumn:	jQuery('.layout-manager-column-remove')
					});
					jQuery('#save').click(function(){
						if (jQuery('#layout_custom').is(':checked')) {
							var form = jQuery('#layout-manager').parents('form').first();
							jQuery('#layout-manager').find('.layout-manager-row').each(function(i, e) {
								jQuery('<input>', {
									type: 'hidden',
									name: 'new_layout[]',
									value: jQuery(e).find('.layout-manager-column input[type=number]').map(function(){ return this.value;}).get().join(',')
									}).appendTo(form);
							});
						}
					});
					jQuery('.layout-manager-chooser').each(function(i, e) {
						jQuery(e).find('input[type=radio]').change(function() {
							jQuery('.layout-manager-chooser').each(function(i, e) {
								jQuery(e).removeClass('layout-manager-chooser_selected');
							});
							jQuery(e).addClass('layout-manager-chooser_selected');
						});
					});
				});
			//]]></script>
			<?php
			$sql = "SELECT * FROM layouts WHERE scope='S' ORDER BY id ";
			$req_layouts = db_query_params($sql, array());
			echo $HTML->listTableTop();
			$is_custom = true;
			while ($data = db_fetch_array($req_layouts)) {
				$checked = $layout_id == $data['id'] ? 'checked="checked"' : '';
				$is_custom = $is_custom && !$checked;
				echo '<tr class="layout-manager-chooser '. ($checked ? 'layout-manager-chooser_selected' : '') .'" ><td>';
				echo '<input type="radio" name="layout_id" value="'. $data['id'] .'" id="layout_'. $data['id'] .'" '. $checked .'/>';
				echo '</td><td>';
				echo html_e('label', array('for' => 'layout_'. $data['id']), html_image('layout/'. strtolower(preg_replace('/(\W+)/', '-', $data['name'])) .'.png'));
				echo '</td><td>';
				echo html_e('label', array('for' => 'layout_'. $data['id']), html_e('strong', array(), $data['name']).html_e('br').$data['description']);
				echo '</td></tr>';
			}
			/* Custom layout are not available yet */
			$checked = $is_custom ? 'checked="checked"' : '';
			echo '<tr class="layout-manager-chooser '. ($checked ? 'layout-manager-chooser_selected' : '') .'"><td>';
			echo '<input type="radio" name="layout_id" value="-1" id="layout_custom" '. $checked .'/>';
			echo '</td><td>';
			echo html_e('label', array('for' => 'layout_custom'), html_image('layout/custom.png', '', '', array('style' => 'vertical-align:top;float:left;')));
			echo '</td><td>';
			echo html_e('label', array('for' => 'layout_custom'), html_e('strong', array(), _('Custom')).html_e('br')._('Define your own layout')._(':'));
			echo '<table id="layout-manager">
				<tr>
				<td>
				<div class="layout-manager-row-add">+</div>';
			$sql = 'SELECT * FROM layouts_rows WHERE layout_id = $1 ORDER BY rank';
			$req_rows = db_query_params($sql,array($layout_id));
			while ($data = db_fetch_array($req_rows)) {
				echo '<table class="layout-manager-row">
					<tr>
					<td class="layout-manager-column-add">+</td>';
				$sql = 'SELECT * FROM layouts_rows_columns WHERE layout_row_id = $1';
				$req_cols = db_query_params($sql,array($data['id']));
				while ($data = db_fetch_array($req_cols)) {
					echo '<td class="layout-manager-column">
						<div class="layout-manager-column-remove">x</div>
						<div class="layout-manager-column-width">
						<input type="number" value="'. $data['width'] .'" size="1" maxlength="3" />%
						</div>
						</td>
						<td class="layout-manager-column-add">+</td>';
				}
				echo '  </tr>
					</table>';
				echo html_e('div', array('class' => 'layout-manager-row-add'), '+');
			}
			echo '    </td>
				</tr>
				</table>';
			echo '</td></tr>';
			echo $HTML->listTableBottom();
			echo html_e('input', array('type' => 'submit', 'id' => 'save', 'value' => _('Submit')));
		} else {
			// display the widget selection form
			$after = '';
			echo '<table>
				<tbody>
				<tr class="top">
				<td>';
			$after .= $this->_displayWidgetsSelectionForm(Widget::getCodendiWidgets($owner_type), $used_widgets, $owner_id);
			echo '</td>
				<td id="widget-content-categ">'. $after .'</td>
				</tr>
				</tbody>
				</table>';
		}
		echo $HTML->closeForm();
	}

	function updateLayout($owner_id, $owner_type, $layout, $custom_layout) {
		$sql = "SELECT l.*
			FROM layouts AS l INNER JOIN owner_layouts AS o ON(l.id = o.layout_id)
			WHERE o.owner_type = $1
			AND o.owner_id = $2
			AND o.is_default = 1
			";
		$req = db_query_params($sql, array($owner_type, $owner_id));
		if ($data = db_fetch_array($req)) {
			if ($this->_currentUserCanUpdateLayout($owner_id, $owner_type)) {
				$old_scope = $data['scope'];
				$old_layout_id = $data['id'];
				$new_layout_id = null;
				if ($layout == '-1' && is_array($custom_layout)) {
					//Create a new layout based on the custom layout structure defined by the user
					$rows = array();
					foreach($custom_layout as $widths) {
						$row = array();
						$cols = explode(',', $widths);
						foreach($cols as $col) {
							if ($width = (int)$col) {
								$row[] = $width;
							}
						}
						if (count($row)) {
							$rows[] = $row;
						}
					}
					//If the structure contains at least one column, create a new layout
					if (count($rows)) {
						$sql = "INSERT INTO layouts(name, description, scope) VALUES ('custom', '', 'P')";
						if ($res = db_query_params($sql, array())) {
							if ($new_layout_id = db_insertid($res, 'layouts', 'id')) {
								//Create rows & columns
								$rank = 0;
								foreach($rows as $cols) {
									$sql = "INSERT INTO layouts_rows(layout_id, rank) VALUES ($1, $2)";
									if ($res = db_query_params($sql, array($new_layout_id, $rank++))) {
										if ($row_id = db_insertid($res,'layouts_rows', 'id')) {
											foreach($cols as $width) {
												$sql = "INSERT INTO layouts_rows_columns(layout_row_id, width) VALUES ($1, $2)";
												db_query_params($sql, array($row_id, $width));
											}
										}
									}
								}
							}
						}
					}
				} else {
					$new_layout_id = $layout;
				}

				if ($new_layout_id) {
					//Retrieve columns of old layout
					$old = $this->_retrieveStructureOfLayout($old_layout_id);

					//Retrieve columns of new layout
					$new = $this->_retrieveStructureOfLayout($new_layout_id);

					// Switch content from old columns to new columns
					$last_new_col_id = null;
					reset($new);
					foreach($old['columns'] as $old_col) {
						if (key($new)) {
							$new_col = current($new['columns']);
							$last_new_col_id = $new_col['id'];
							next($new);
						}
						$sql = "UPDATE layouts_contents
							SET layout_id  = $1, column_id = $2
							WHERE owner_type =$3
							AND owner_id  =$4
							AND layout_id =$5
							AND column_id =$6;";
						db_query_params($sql, array($new_layout_id, $last_new_col_id, $owner_type, $owner_id, $old_layout_id, $old_col['id']));
					}
					$sql = "UPDATE owner_layouts
						SET layout_id  = $1
						WHERE owner_type = $2
						AND owner_id  = $3
						AND layout_id = $4";
					db_query_params($sql, array($new_layout_id, $owner_type, $owner_id, $old_layout_id));

					//If the old layout is custom remove it
					if ($old_scope != 'S') {
						$structure = $this->_retrieveStructureOfLayout($old_layout_id);
						foreach($structure['rows'] as $row) {
							$sql = "DELETE FROM layouts_rows WHERE id = $1";
							db_query_params($sql, array($row['id']));
							$sql = "DELETE FROM layouts_rows_columns WHERE layout_row_id = $1";
							db_query_params($sql, array($row['id']));
						}
						$sql = "DELETE FROM layouts WHERE id = $1";
						db_query_params($sql, array($old_layout_id));
					}

				}
			}
		}
		$this->feedback();
	}

	function _retrieveStructureOfLayout($layout_id) {
		$structure = array('rows' => array(), 'columns' => array());
		$sql = 'SELECT * FROM layouts_rows WHERE layout_id = $1 ORDER BY rank';
		$req_rows = db_query_params($sql,array($layout_id));
		while ($row = db_fetch_array($req_rows)) {
			$structure['rows'][] = $row;
			$sql = 'SELECT * FROM layouts_rows_columns WHERE layout_row_id =$1 ORDER BY id';
			$req_cols = db_query_params($sql,array($row['id']));
			while ($col = db_fetch_array($req_cols)) {
				$structure['columns'][] = $col;
			}
		}
		return $structure;
	}

	/**
	 * _displayWidgetsSelectionForm - displays a widget selection form
	 *
	 * @param	array	$widgets	widgets
	 * @param	array	$used_widgets	used widgets
	 * @param	int	$owner_id
	 * @return	string
	 */
	function _displayWidgetsSelectionForm($widgets, $used_widgets, $owner_id = null) {
		$additionnal_html = '';
		if (count($widgets)) {
			$categs = $this->getCategories($widgets, $owner_id);
			$widget_rows = array();
			if (count($categs)) {
				// display the categories selector in left panel
				foreach($categs as $c => $ws) {
					$widget_rows[$c] = util_make_link('#widget-categ-'.$c, html_e('span', array(), str_replace('_',' ', htmlentities($c, ENT_QUOTES, 'UTF-8'))), array('class' => 'widget-categ-switcher', 'id' => 'widget-categ-switcher-'.$c, 'onClick' => 'jQuery(\'.widget-categ-class-void\').hide();jQuery(\'.widget-categ-switcher\').removeClass(\'selected\');jQuery(\'#widget-categ-'. $c .'\').show();jQuery(\'#widget-categ-switcher-'. $c .'\').addClass(\'selected\')'), true);
				}
				uksort($widget_rows, 'strnatcasecmp');
				echo html_ao('ul', array('id' => 'widget-categories'));
				foreach($widget_rows as $row) {
					echo html_e('li', array(), $row, false);
				}
				echo html_ac(html_ap() - 1);
				foreach($categs as $c => $ws) {
					$widget_rows = array();
					// display widgets of the category
					foreach($ws as $widget_name => $widget) {
						$row = html_e('div', array('class' => 'widget-preview '. $widget->getPreviewCssClass()),
								html_e('h3', array(), $widget->getTitle()).
								html_e('p', array(), $widget->getDescription()).
								$widget->getInstallPreferences());
						$row .= '<div style="text-align:right; border-bottom:1px solid #ddd; padding-bottom:10px; margin-bottom:20px;">';
						if ($widget->isUnique() && in_array($widget_name, $used_widgets)) {
							$row .= html_e('em', array(), _('Already used'));
						} else {
							$row .= html_e('input', array('type' => 'submit', 'name' => 'name['. $widget_name .'][add]', 'value' => _('Add')));
						}
						$row .= '</div>';
						$widget_rows[$widget->getTitle()] = $row;
					}
					uksort($widget_rows, 'strnatcasecmp');
					$additionnal_html .= '<div id="widget-categ-'. $c .'" class="widget-categ-class-void hide" ><h2 class="boxtitle">'. str_replace('_',' ', htmlentities($c, ENT_QUOTES, 'UTF-8')) .'</h2>';
					foreach($widget_rows as $row) {
						$additionnal_html .= $row;
					}
					$additionnal_html .= '</div>';
				}
			}
		}
		return $additionnal_html;
	}

	/**
	 * getCategories - sort the widgets in their different categories
	 *
	 * @param	array	$widgets
	 * @param	int	$owner_id
	 * @return	array	(category => widgets)
	 */
	function getCategories($widgets, $owner_id = null) {
		$categ = array();
		foreach($widgets as $widget_name) {
			if ($widget = Widget::getInstance($widget_name, $owner_id)) {
				if ($widget->isAvailable()) {
					$category = str_replace(' ', '_', $widget->getCategory());
					$cs = explode(',', $category);
					foreach($cs as $c) {
						if ($c = trim($c)) {
							if (!isset($categ[$c])) {
								$categ[$c] = array();
							}
							$categ[$c][$widget_name] = $widget;
						}
					}
				}
			}
		}
		return $categ;
	}

	/**
	 * addWidget
	 *
	 * @param	int	$owner_id
	 * @param	string	$owner_type
	 * @param	int	$layout_id
	 * @param	string	$name
	 * @param	object	$widget
	 */
	function addWidget($owner_id, $owner_type, $layout_id, $name, &$widget) {
		//Search for the right column. (The first used)
		$sql = "SELECT u.column_id AS id
			FROM layouts_contents AS u
			LEFT JOIN (SELECT r.rank AS rank, c.id as id
					FROM layouts_rows AS r INNER JOIN layouts_rows_columns AS c
					ON (c.layout_row_id = r.id)
					WHERE r.layout_id = $1) AS col
			ON (u.column_id = col.id)
			WHERE u.owner_type = $2
			AND u.owner_id = $3
			AND u.layout_id = $4
			AND u.column_id <> 0
			ORDER BY col.rank, col.id";
		$res = db_query_params($sql, array($layout_id, $owner_type, $owner_id, $layout_id));
		echo db_error();
		$column_id = db_result($res, 0, 'id');
		if (!$column_id) {
			$sql = "SELECT r.rank AS rank, c.id as id
				FROM layouts_rows AS r
				INNER JOIN layouts_rows_columns AS c
				ON (c.layout_row_id = r.id)
				WHERE r.layout_id = $1
				ORDER BY rank, id";
			$res = db_query_params($sql,array($layout_id));
			$column_id = db_result($res, 0, 'id');
		}

		//content_id
		if ($widget->isUnique()) {
			//unique widgets do not have content_id
			$content_id = 0;
		} else {
			$content_id = $widget->create();
		}

		//See if it already exists but not used
		$sql = "SELECT column_id FROM layouts_contents
			WHERE owner_type = $1
			AND owner_id = $2
			AND layout_id = $3
			AND name = $4";
		$res = db_query_params($sql, array($owner_type, $owner_id, $layout_id, $name));
		if (db_numrows($res) && !$widget->isUnique() && db_result($res, 0, 'column_id') == 0) {
			//search for rank
			$sql = "SELECT min(rank) - 1 AS rank FROM layouts_contents WHERE owner_type =$1 AND owner_id = $2 AND layout_id = $3 AND column_id = $4 ";
			$res = db_query_params($sql, array($owner_type, $owner_id, $layout_id, $column_id));
			$rank = db_result($res, 0, 'rank');

			//Update
			$sql = "UPDATE layouts_contents
				SET column_id = $1, rank = $2
				WHERE owner_type = $3
				AND owner_id = $4
				AND name = $5
				AND layout_id = $6";
			db_query_params($sql, array($column_id, $rank, $owner_type, $owner_id, $name, $layout_id));
		} else {
			//Insert
			$sql = "INSERT INTO layouts_contents(owner_type, owner_id, layout_id, column_id, name, content_id, rank)
				SELECT R1.owner_type, R1.owner_id, R1.layout_id, R1.column_id, $1, $2, coalesce(R2.rank, 1) - 1
				FROM ( SELECT $3::character varying(1) AS owner_type, $4::integer AS owner_id, $5::integer AS layout_id, $6::integer AS column_id ) AS R1
				LEFT JOIN layouts_contents AS R2 USING ( owner_type, owner_id, layout_id, column_id )
				ORDER BY rank ASC
				LIMIT 1";
			db_query_params($sql, array($name, $content_id, $owner_type, $owner_id, $layout_id, $column_id));
		}
		$this->feedback();
	}

	protected function feedback() {
		global $feedback;
		$feedback .= _('Your dashboard has been updated.');
	}

	/**
	 * removeWidget
	 *
	 * @param	int	$owner_id
	 * @param	string	$owner_type
	 * @param	int	$layout_id
	 * @param	string	$name
	 * @param	int	$instance_id
	 * @param	object	$widget
	 */
	function removeWidget($owner_id, $owner_type, $layout_id, $name, $instance_id, &$widget) {
		$sql = "DELETE FROM layouts_contents WHERE owner_type =$1 AND owner_id = $2 AND layout_id = $3 AND name = $4 AND content_id = $5";
		db_query_params($sql, array($owner_type, $owner_id, $layout_id, $name, $instance_id));
		if (!db_error()) {
			$widget->destroy($instance_id);
		}
	}

	/**
	 * mimizeWidget
	 *
	 * @param	int	$owner_id
	 * @param	string	$owner_type
	 * @param	int	$layout_id
	 * @param	string	$name
	 * @param	int	$instance_id
	 */
	function mimizeWidget($owner_id, $owner_type, $layout_id, $name, $instance_id) {
		$sql = "UPDATE layouts_contents SET is_minimized = 1 WHERE owner_type = $1 AND owner_id = $2 AND layout_id = $3 AND name = $4 AND content_id = $5";
		db_query_params($sql, array($owner_type, $owner_id, $layout_id, $name, $instance_id));
		echo db_error();
	}

	/**
	 * maximizeWidget
	 *
	 * @param	int	$owner_id
	 * @param	string	$owner_type
	 * @param	int	$layout_id
	 * @param	string	$name
	 * @param	int	$instance_id
	 */
	function maximizeWidget($owner_id, $owner_type, $layout_id, $name, $instance_id) {
		$sql = "UPDATE layouts_contents SET is_minimized = 0 WHERE owner_type =$1 AND owner_id =$2 AND layout_id = $3 AND name = $4 AND content_id = $5";
		db_query_params($sql, array($owner_type, $owner_id, $layout_id, $name, $instance_id));
		echo db_error();
	}

	/**
	 * displayWidgetPreferences
	 *
	 * @param	int	$owner_id
	 * @param	string	$owner_type
	 * @param	int	$layout_id
	 * @param	string	$name
	 * @param	int	$instance_id
	 */
	function displayWidgetPreferences($owner_id, $owner_type, $layout_id, $name, $instance_id) {
		$sql = "UPDATE layouts_contents SET display_preferences = 1, is_minimized = 0 WHERE owner_type = $1 AND owner_id = $2 AND layout_id = $3 AND name = $4 AND content_id = $5";
		db_query_params($sql, array($owner_type, $owner_id, $layout_id, $name, $instance_id));
		echo db_error();
	}

	/**
	 * hideWidgetPreferences
	 *
	 * @param	int	$owner_id
	 * @param	string	$owner_type
	 * @param	int	$layout_id
	 * @param	string	$name
	 * @param	int	$instance_id
	 */
	function hideWidgetPreferences($owner_id, $owner_type, $layout_id, $name, $instance_id) {
		$sql = "UPDATE layouts_contents SET display_preferences = 0 WHERE owner_type = $1 AND owner_id = $2 AND layout_id = $3 AND name = $4 AND content_id = $5";
		db_query_params($sql, array($owner_type, $owner_id, $layout_id, $name, $instance_id));
		echo db_error();
	}

	/**
	 * reorderLayout
	 *
	 * @param	int	$owner_id
	 * @param	string	$owner_type
	 * @param	int	$layout_id
	 */
	function reorderLayout($owner_id, $owner_type, $layout_id) {
		$keys = array_keys($_REQUEST);
		foreach($keys as $key) {
			if (preg_match('`widgetlayout_col_\d+`', $key)) {
				$split = explode('_', $key);
				$column_id = (int)$split[count($split)-1];

				$names = array();
				$keyArray = getArrayFromRequest($key);
				foreach($keyArray as $name) {
					list($name, $id) = explode('-', $name);
					$names[] = array($id, $name);
				}

				db_begin();
				//Compute differences
				$originals = array();
				$sql = "SELECT * FROM layouts_contents WHERE owner_type = $1 AND owner_id = $2 AND column_id = $3 ORDER BY rank";
				$res = db_query_params($sql, array($owner_type, $owner_id, $column_id));

				while($data = db_fetch_array($res)) {
					$originals[] = array($data['content_id'], $data['name']);
				}

				//Insert new contents
				$added_names = utils_array_diff_names($names, $originals);
				if (count($added_names)) {
					$_and = '';
					foreach($added_names as $name) {
						if ($_and) {
							$_and .= ' OR ';
						} else {
							$_and .= ' AND (';
						}
						$_and .= " (name = '".$name[1]."' AND content_id = ". $name[0] .") ";
					}
					$_and .= ')';
					//old and new column must be part of the same layout
					$sql = 'UPDATE layouts_contents
						SET column_id = $1
						WHERE owner_type = $2
						AND owner_id = $3' . $_and ."
						AND layout_id = $4";
					db_query_params($sql, array($column_id, $owner_type, $owner_id, $layout_id));
				}

				// we do not need to delete old contents since another request is sent to add the new content to the other column.

				//Update ranks
				$rank = 0;
				foreach($names as $name) {
					$sql = 'UPDATE layouts_contents SET rank = $1 WHERE owner_type =$2 AND owner_id = $3 AND column_id = $4 AND name = $5 AND content_id = $6';
					db_query_params($sql, array($rank++, $owner_type, $owner_id, $column_id, $name[1], $name[0]));
				}
				db_commit();
			}
		}
	}

	function getLayout($owner_id, $owner_type) {
		$layout = null;
		$sql = "SELECT l.*
			FROM layouts AS l INNER JOIN owner_layouts AS o ON(l.id = o.layout_id)
			WHERE o.owner_type = $1
			AND o.owner_id = $2
			AND o.is_default = 1
			";
		$req = db_query_params($sql, array($owner_type, $owner_id));
		if ($data = db_fetch_array($req)) {
			$layout = new WidgetLayout($data['id'], $data['name'], $data['description'], $data['scope']);
			$sql = 'SELECT * FROM layouts_rows WHERE layout_id = $1 ORDER BY rank';
			$req_rows = db_query_params($sql,array($layout->id));
			while ($data = db_fetch_array($req_rows)) {
				$row = new WidgetLayout_Row($data['id'], $data['rank']);
				$sql = 'SELECT * FROM layouts_rows_columns WHERE layout_row_id = $1';
				$req_cols = db_query_params($sql, array($row->id));
				while ($data = db_fetch_array($req_cols)) {
					$col = new WidgetLayout_Row_Column($data['id'], $data['width']);
					$sql = "SELECT * FROM layouts_contents WHERE owner_type = $1  AND owner_id = $2 AND column_id = $3 ORDER BY rank";
					$req_content = db_query_params($sql, array($owner_type, $owner_id, $col->id));
					while ($data = db_fetch_array($req_content)) {
						$c = Widget::getInstance($data['name']);
						if ($c && $c->isAvailable()) {
							$c->loadContent($data['content_id']);
							$col->add($c, $data['is_minimized'], $data['display_preferences']);
						}
						unset($c);
					}
					$row->add($col);
					unset($col);
				}
				foreach ($row->columns as $lcol) {
					unset($lcol->row);
				}
				$layout->add($row);
				unset($row);
				foreach ($layout->rows as $lrow) {
					unset($lrow->layout);
				}
			}
		}
		return (array)$layout;
	}
}
