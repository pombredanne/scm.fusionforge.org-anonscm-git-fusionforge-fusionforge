<?php
/**
 * Base layout class.
 *
 * Copyright 1999-2001 (c) VA Linux Systems
 * Copyright 2010 - Alain Peyrat
 * Copyright 2010-2011, Franck Villaume - Capgemini
 * Copyright 2010-2012, Alain Peyrat - Alcatel-Lucent
 * Copyright © 2011 Thorsten Glaser – tarent GmbH
 * Copyright 2011 - Marc-Etienne Vargenau, Alcatel-Lucent
 * Copyright 2012-2019,2021, Franck Villaume - TrivialDev
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

/**
 * Extends the basic Error class to add HTML functions
 * for displaying all site dependent HTML, while allowing
 * extendibility/overriding by themes via the Theme class.
 *
 * Make sure browser.php is included _before_ you create an instance
 * of this object.
 */

require_once $gfcommon.'include/constants.php';
require_once $gfcommon.'include/FusionForge.class.php';
require_once $gfcommon.'include/Navigation.class.php';
require_once $gfcommon.'include/html.php';

abstract class Layout extends FFError {

	/**
	 * The default main page content
	 * @var	string	$rootindex
	 */
	var $rootindex = 'index_std.php';

	/**
	 * The base directory of the theme in the servers file system
	 * @var	string	$themedir
	 */
	var $themedir;

	/**
	 * The base url of the theme
	 * @var	string	$themeurl
	 */
	var $themeurl;

	/**
	 * The base directory of the image files in the servers file system
	 * @var	string	$imgdir
	 */
	var $imgdir;

	/**
	 * The base url of the image files
	 * @var	string	$imgbaseurl
	 */
	var $imgbaseurl;

	/**
	 * The base directory of the js files in the servers file system
	 * @var	string	$jsdir
	 */
	var $jsdir;

	/**
	 * The base url of the js files
	 * @var	string	$jsbaseurl
	 */
	var $jsbaseurl;

	/**
	 * The navigation object that provides the basic links. Should
	 * not be modified.
	 */
	var $navigation;

	var $js = array();
	var $js_min = array();
	var $javascripts = array();
	var $css = array();
	var $css_min = array();
	var $stylesheets = array();
	var $buttons = array();

	function __construct() {
		parent::__construct();

		$this->navigation = new Navigation();

		// determine rootindex
		if (file_exists(forge_get_config('custom_path') . '/index_std.php')) {
			$this->rootindex = forge_get_config('custom_path') . '/index_std.php';
		} else {
			$this->rootindex = $GLOBALS['gfwww'].'index_std.php';
		}

		// determine theme{dir,url}
		$this->themedir = forge_get_config('themes_root') . '/' . forge_get_config('default_theme') . '/';
		if (!file_exists ($this->themedir)) {
			html_error_top(_("Cannot find theme directory!"));
			return;
		}
		$this->themeurl = util_make_uri('themes/' . forge_get_config('default_theme') . '/');

		// determine {css,img,js}{url,dir}
		if (file_exists ($this->themedir . 'images/')) {
			$this->imgdir = $this->themedir . 'images/';
			$this->imgbaseurl = $this->themeurl . 'images/';
		} else {
			$this->imgdir = $this->themedir;
			$this->imgbaseurl = $this->themeurl;
		}

		if (file_exists ($this->themedir . 'js/')) {
			$this->jsdir = $this->themedir . 'js/';
			$this->jsbaseurl = $this->themeurl . 'js/';
		} else {
			$this->jsdir = $this->themedir;
			$this->jsbaseurl = $this->themeurl;
		}

		$this->addStylesheet('/themes/css/fusionforge.css');

	}

	/**
	 * Build the list of required Javascript files.
	 *
	 * If js file is found, then a timestamp is automatically added to ensure
	 * that file is cached only if not changed.
	 *
	 * @param string $js path to the JS file
	 */
	function addJavascript($js) {
		// If a minified version of the javascript is available, then use it.
		if (isset($this->js_min[$js])) {
			$js = $this->js_min[$js];
		}
		if ($js && !isset($this->js[$js])) {
			$this->js[$js] = true;
			$filename = $GLOBALS['fusionforge_basedir'].'/www'.$js;
			if (file_exists($filename)) {
				$js .= '?'.date ("U", filemtime($filename));
			} else {
				$filename = str_replace('/scripts/', $GLOBALS['fusionforge_basedir'].'/vendor/', $js);
				if (file_exists($filename)) {
					$js .= '?'.date ("U", filemtime($filename));
				}
			}
			$this->javascripts[] = $js;
		}
	}

	function addStylesheet($css, $media='') {
		if (isset($this->css_min[$css])) {
			$css = $this->css_min[$css];
		}
		if (!isset($this->css[$css])) {
			$this->css[$css] = true;
			$filename = $GLOBALS['fusionforge_basedir'].'/www'.$css;
			if (file_exists($filename)) {
				$css .= '?'.date ("U", filemtime($filename));
			} else {
				$filename = str_replace('/scripts/', $GLOBALS['fusionforge_basedir'].'/vendor/', $css);
				if (file_exists($filename)) {
					$css .= '?'.date ("U", filemtime($filename));
				}
			}
			$this->stylesheets[] = array('css' => $css, 'media' => $media);
		}
	}

	/**
	 * getJavascripts - include javascript in html page. check to load only once the file
	 */
	function getJavascripts() {
		$code = '';
		foreach ($this->javascripts as $js) {
			$code .= html_e('script', array('type' => 'text/javascript', 'src' => util_make_uri($js)), '', false);
		}
		$this->javascripts = array();
		return $code;
	}

	/**
	 * getStylesheets - include stylesheet in html page. check to load only once the file
	 */
	function getStylesheets() {
		$code = '';
		foreach ($this->stylesheets as $c) {
			if ($c['media']) {
				$code .= html_e('link', array('rel' => 'stylesheet', 'type' => 'text/css', 'href' => util_make_uri($c['css']), 'media' => $c['media']));
			} else {
				$code .= html_e('link', array('rel' => 'stylesheet', 'type' => 'text/css', 'href' => util_make_uri($c['css'])));
			}
		}
		$this->stylesheets = array();
		return $code;
	}

	function addButtons($link, $text, $options = array()) {
		$this->buttons[] = array_merge(array('link' => $link, 'text' => $text), $options);
	}

	function getButtons() {
		$code = '';
		if ($this->buttons) {
			$code .= html_ao('p', array('class' => 'buttonsbar'));
			foreach ($this->buttons as $b) {
				$text = $b['text'];
				$link = $b['link'];
				if (isset($b['icon'])) {
					$text = $b['icon'].' '.$text;
					unset($b['icon']);
				}
				unset($b['text'], $b['link'], $b['icon']);
				$code .= html_e('span', array('class' => 'buttons'), util_make_link($link, $text, $b));
			}
			$code .= html_ac(html_ap() -1);
			$this->buttons = array();
		}
		return $code;
	}

	/**
	 * header() - generates the complete header of page by calling
	 * headerStart() and bodyHeader().
	 *
	 * @param	array	$params		Header parameters array
	 */
	function header($params) {
		$this->headerStart($params);
		echo html_ao('body');
		$this->bodyHeader($params);
	}

	/**
	 * headerStart() - generates the header code for all themes up to the
	 * closing </head>.
	 * Override any of the methods headerHTMLDeclaration(), headerTitle(),
	 * headerFavIcon(), headerRSS(), headerSearch(), headerCSS(), or
	 * headerJS() to adapt your theme.
	 *
	 * @param	array	$params		Header parameters array
	 */
	function headerStart($params) {
		$this->headerHTMLDeclaration();
		echo html_ao('head');
		echo html_e('meta', array('http-equiv' => 'Content-Type', 'content' => 'text/html; charset=utf-8'));
		echo html_e('meta', array('http-equiv' => 'X-UA-Compatible', 'content' => 'IE=edge'));
		if (isset($params['meta-description'])) {
			echo html_e('meta', array('name' => 'description', 'content' => $params['meta-description']));
		}
		if (isset($params['meta-keywords'])) {
			echo html_e('meta', array('name' => 'keywords', 'content' => $params['meta-keywords']));
		}
		plugin_hook('htmlhead');
		$this->headerTitle($params);
		$this->headerFavIcon();
		$this->headerRSS();
		$this->headerSearch();
		echo '<script type="text/javascript">//<![CDATA[' .
		"\n\tvar sys_url_base = " . minijson_encode(util_make_url("/"), false) . ";\n" .
		"//]]></script>\n";
		$this->headerJS();
		$this->headerCSS();
		$this->headerForgepluckerMeta();
		$this->headerLinkedDataAutodiscovery();
		echo html_ac(html_ap() -1);
	}

	/**
	 * headerHTMLDeclaration() - generates the HTML declaration, i.e. the
	 * HTML 5 doctype definition, and the opening <html>.
	 *
	 */
	function headerHTMLDeclaration() {
		print "<!DOCTYPE html>\n";
		echo '<html xml:lang="' . _('en') . '" lang="' . _('en') .  '" ' . ">\n";
	}

	/**
	 * headerTitle() - creates the <title> header
	 *
	 * @param	array	$params		Header parameters array
	 */
	function headerTitle($params) {
		echo $this->navigation->getTitle($params);
	}

	/**
	 * headerFavIcon() - creates the favicon <link> headers.
	 *
	 */
	function headerFavIcon() {
		echo $this->navigation->getFavIcon();
	}

	/**
	 * headerRSS() - creates the RSS <link> headers.
	 *
	 */
	function headerRSS() {
		echo $this->navigation->getRSS();
	}

	/**
	 * headerSearch() - creates the search <link> header.
	 *
	 */
	function headerSearch() {
		echo html_e('link', array('rel' => "search", 'title' => forge_get_config('forge_name'),
					'href' => util_make_uri('/export/search_plugin.php'),
					'type' => 'application/opensearchdescription+xml'));
	}

	/**
	 * Create the CSS headers for all cssfiles in $cssfiles and
	 * calls the plugin cssfile hook.
	 */
	function headerCSS() {
		plugin_hook ('cssfile',$this);
		echo $this->getStylesheets();
	}

	/**
	 * headerJS() - creates the JS headers and calls the plugin javascript hook
	 * @todo generalize this
	 */
	function headerJS() {
		echo html_e('script', array('type' => 'text/javascript', 'src' => util_make_uri('/js/common.js')), '', false);
		echo '	<script type="text/javascript">/* <![CDATA[ */';
		plugin_hook ("javascript");
		echo '
			/* ]]> */</script>';
		plugin_hook ("javascript_file");
		echo $this->getJavascripts();

		// invoke the 'javascript' hook for custom javascript addition
		$params = array('return' => false);
		plugin_hook("javascript",$params);
		$javascript = $params['return'];
		if($javascript) {
			echo '<script type="text/javascript">';
			echo $javascript;
			echo '
			</script>';
		}
	}

	/**
	 * headerLinkedDataAutodiscovery() - creates the link+alternate links to alternate
	 *		representations for Linked Data autodiscovery
	 */
	function headerLinkedDataAutodiscovery() {

		// retrieve the script's prefix
		$script_name = getStringFromServer('SCRIPT_NAME');
		$end = strpos($script_name,'/',1);
		if($end) {
			$script_name = substr($script_name,0,$end);
		}

		// Only activated for /projects, /users or /softwaremap for the moment
		if ($script_name == '/projects' || $script_name == '/users' || $script_name == '/softwaremap') {

			$php_self = getStringFromServer('PHP_SELF');

			// invoke the 'alt_representations' hook to add potential 'alternate' links (useful for Linked Data)
			// cf. http://www.w3.org/TR/cooluris/#linking
			$params = array('script_name' => $script_name,
							'php_self' => $php_self,
							'return' => array());

			plugin_hook_by_reference('alt_representations', $params);

			foreach($params['return'] as $link) {
				echo "                        $link"."\n";
			}
		}
	}

	function headerForgepluckerMeta() {
		/*-
		 * Forge-Identification Meta Header, Version 1.0
		 * cf. http://home.gna.org/forgeplucker/forge-identification.html
		 */
		$ff = FusionForge::getInstance();
		echo html_e('meta', array('name' => 'Forge-Identification', 'content' => $ff->software_name.':'.$ff->software_version));
	}

	abstract function bodyHeader($params);

	abstract function footer();

	function getRootIndex() {
		return $this->rootindex;
	}

	/**
	 * boxTop() - Top HTML box.
	 *
	 * @param	string	$title	Box title
	 * @param   string  $id
	 * @return	string	the html code
	 */
	abstract function boxTop($title, $id = '');

	/**
	 * boxMiddle() - Middle HTML box.
	 *
	 * @param	string	$title	Box title
	 * @param   string  $id
	 * @return	string	The html code
	 */
	abstract function boxMiddle($title, $id = '');

	/**
	 * boxBottom() - Bottom HTML box.
	 *
	 * @return	string	the html code
	 */
	abstract function boxBottom();

	/**
	 * listTableTop() - Takes an array of titles and builds the first row of a new table.
	 *
	 * @param	array	$titleArray		The array of titles
	 * @param	array	$linksArray		The array of title links
	 * @param	string	$class			The css classes to add (optional)
	 * @param	string	$id			The id of the table (needed by sortable for example)
	 * @param	array	$thClassArray		specific class for th cell
	 * @param	array	$thTitleArray		specific title for th cell
	 * @param	array	$thOtherAttrsArray	optional other html attributes for the th
	 * @param	string	$theadClass		optional thead tr css class. default is tableheading
	 * @return	string	the html code
	 */
	function listTableTop($titleArray = array(), $linksArray = array(), $class = '', $id = '', $thClassArray = array(), $thTitleArray = array(), $thOtherAttrsArray = array(), $theadClass = 'tableheading') {
		$attrs = array();
		if ($class) {
			$attrs['class'] = $class;
		} else {
			$attrs['class'] = 'full listing';
		}
		if ($id) {
			$attrs['id'] = $id;
		}
		$return = html_ao('table', $attrs);

		if (!empty($titleArray)) {
			$ap = html_ap();
			$return .= html_ao('thead');
			$return .= html_ao('tr', array('class' => $theadClass));

			for ($i = 0; $i < count($titleArray); $i++) {
				$thAttrs = array();
				if ($thOtherAttrsArray && isset($thOtherAttrsArray[$i])) {
					$thAttrs = $thOtherAttrsArray[$i];
				}
				if ($thClassArray && isset($thClassArray[$i])) {
					$thAttrs['class'] = $thClassArray[$i];
				}
				if ($thTitleArray && isset($thTitleArray[$i])) {
					$thAttrs['title'] = $thTitleArray[$i];
				}
				$cell = $titleArray[$i];
				if ($linksArray && !empty($linksArray[$i])) {
					$cell = util_make_link($linksArray[$i], $titleArray[$i]);
				}
				$return .= html_e('th', $thAttrs, $cell, false);
			}
			$return .= html_ac($ap);
		}
		$return .= html_ao('tbody');
		return $return;
	}

	function listTableBottom() {
		return html_ac(html_ap() -2);
	}

	function outerTabs($params) {
		$menu = $this->navigation->getSiteMenu();
		echo $this->tabGenerator($menu['urls'], $menu['titles'], $menu['tooltips'], false, $menu['selected'], '');
	}

	/**
	 * Prints out the quicknav menu, contained here in case we
	 * want to allow it to be overridden.
	 */
	function quickNav() {
		if (!session_loggedin()) {
			return '';
		} else {
			// get all projects that the user belongs to
			$groups = session_get_user()->getGroups();

			if (count($groups) < 1) {
				return '';
			} else {
				$result = html_ao('div', array('id' => 'quicknavdiv'));
				$result .= html_ao('select', array('name' => 'quicknav', 'id' => 'quicknav', 'onchange' => 'if (this.value) window.location.href=this.value'));
				$result .= html_e('option', array('value' => ''), _('Quick Jump To...'), false);
				if (!forge_get_config('use_quicknav_default') && session_get_user()->getPreference('quicknav_mode')) {
					$groups = session_get_user()->getActivityLogGroups();
				}
				sortProjectList($groups);
				foreach ($groups as $g) {
					$group_id = $g->getID();
					$menu = $this->navigation->getProjectMenu($group_id);
					$result .= html_e('option', array('value' => $menu['starturl']), $menu['name'], true);
					for ($j = 0; $j < count($menu['urls']); $j++) {
						$result .= html_e('option', array('value' => $menu['urls'][$j]), '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'.$menu['titles'][$j], true);
						if (@$menu['adminurls'][$j]) {
							$result .= html_e('option', array('value' => $menu['adminurls'][$j]), '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'._('Admin'), true);
						}
					}
				}
				$result .= html_ac(html_ap() - 2);
			}
			return $result;
		}
	}

	/**
	 * projectTabs() - Prints out the project tabs, contained here in case
	 * we want to allow it to be overriden.
	 *
	 * @param	string	$toptab		Is the tab currently selected
	 * @param	string	$group_id	Is the group we should look up get title info
	 */
	function projectTabs($toptab, $group_id) {
		// get group info using the common result set
		$menu = $this->navigation->getProjectMenu($group_id, $toptab);
		echo $this->tabGenerator($menu['urls'], $menu['titles'], $menu['tooltips'], true, $menu['selected'], 'white');
	}

	abstract function tabGenerator($tabs_dirs, $tabs_titles, $tabs_tooltips, $nested=false, $selected=false, $sel_tab_bgcolor='white', $total_width='100%');

	function searchBox() {
		return $this->navigation->getSearchBox();
	}

	/**
	 * beginSubMenu() - Opening a submenu.
	 *
	 * @return	string	Html to start a submenu.
	 */
	function beginSubMenu() {
		return '<p><strong>';
	}

	/**
	 * endSubMenu() - Closing a submenu.
	 *
	 * @return	string	Html to end a submenu.
	 */
	function endSubMenu() {
		return '</strong></p>';
	}

	/**
	 * printSubMenu() - Takes two array of titles and links and builds the contents of a menu.
	 *
	 * @param	array	$title_arr	The array of titles.
	 * @param	array	$links_arr	The array of title links.
	 * @param	array	$attr_arr	The array of string for title attributes.
	 * @return	string	Html to build a submenu.
	 */
	function printSubMenu($title_arr, $links_arr, $attr_arr) {
		$count=count($title_arr);
		$count--;

		$return = '';
		for ($i=0; $i<$count; $i++) {
			$return .= util_make_link($links_arr[$i],$title_arr[$i],$attr_arr[$i]). $this->subMenuSeparator();
		}
		$return .= util_make_link($links_arr[$i],$title_arr[$i],$attr_arr[$i]);
		return $return;
	}

	/**
	 * subMenuSeparator() - returns the separator used between submenus
	 *
	 * @return	string	Html to build a submenu separator.
	 */
	function subMenuSeparator() {
		return '';
	}

	/**
	 * subMenu() - Takes two array of titles and links and build a menu.
	 *
	 * @param	array	$title_arr	The array of titles.
	 * @param	array	$links_arr	The array of title links.
	 * @param	array	$attr_arr	The array of string for title attributes.
	 * @return	string	Html to build a submenu.
	 */
	function subMenu($title_arr, $links_arr, $attr_arr = array()) {
		$return  = $this->beginSubMenu();
		$return .= $this->printSubMenu($title_arr, $links_arr, $attr_arr);
		$return .= $this->endSubMenu();
		return $return;
	}

	/**
	 * multiTableRow() - create a multilevel row in a table
	 *
	 * @param	array	$row_attrs	the row attributes
	 * @param	array	$cell_data	the array of cell data, each element is an array,
	 *					the first item must be the text,
	 *					the subsequent items are attributes (dont include the bgcolor for the title here, that will be handled by $istitle)
	 * @param	bool	$istitle	is this row part of the title ?
	 * @return	string	the html code
	 */
	function multiTableRow($row_attrs, $cell_data, $istitle = false) {
		$ap = html_ap();
		if ($istitle) {
			(isset($row_attrs['class'])) ? $row_attrs['class'] .= ' align-center multiTableRowTitle' : $row_attrs['class'] = 'align-center multiTableRowTitle';
			$row_attrs['class'] .= '';
		}
		$return = html_ao('tr', $row_attrs);
		$type = $istitle ? 'th' : 'td';
		for ($c = 0; $c < count($cell_data); $c++) {
			$locAp = html_ap();
			$cellAttrs = array();
			foreach (array_slice($cell_data[$c],1) as $k => $v) {
				$cellAttrs[$k] = $v;
			}
			$return .= html_ao($type, $cellAttrs);
			if ($istitle) {
				$return .= html_ao('span', array('class' => 'multiTableRowTitle'));
			}
			$return .= $cell_data[$c][0];
			if ($istitle) {
				$return .= html_ac(html_ap() -1);
			}
			$return .= html_ac($locAp);
		}
		$return .= html_ac($ap);
		return $return;
	}

	/**
	 * feedback() - returns the htmlized feedback string when an action is performed.
	 *
	 * @param	string	$feedback	feedback string
	 * @param	array	$attr		html attributes
	 * @return	string	htmlized feedback
	 */
	function feedback($feedback, $attr = array()) {
		if (!$feedback) {
			return '';
		} else {
			return html_e('p', array_merge(array('class' => 'feedback'), $attr), strip_tags($feedback, '<br>'), true);
		}
	}
	/**
	 * warning_msg() - returns the htmlized warning string when an action is performed.
	 *
	 * @param	string	$msg	msg string
	 * @param	array	$attr	html attributes
	 * @return	string	htmlized warning
	 */
	function warning_msg($msg, $attr = array()) {
		if (!$msg) {
			return '';
		} else {
			return html_e('p', array_merge(array('class' => 'warning_msg'), $attr), strip_tags($msg, '<br>'), true);
		}
	}

	/**
	 * error_msg() - returns the htmlized error string when an action is performed.
	 *
	 * @param	string	$msg	msg string
	 * @param	array	$attr	html attributes
	 * @return	string	htmlized error
	 */
	function error_msg($msg, $attr = array()) {
		if (!$msg) {
			return '';
		} else {
			return html_e('p', array_merge(array('class' => 'error'), $attr), strip_tags($msg, '<br>'), true);
		}
	}

	/**
	 * information() - returns the htmlized information string.
	 *
	 * @param	string	$msg	msg string
	 * @return	string	htmlized information
	 */
	function information($msg) {
		if (!$msg) {
			return '';
		} else {
			return html_e('p', array('class' => 'information'), strip_tags($msg, '<br>'), true);
		}
	}

	function confirmBox($msg, $params, $buttons, $image = '*none*') {
		if ($image == '*none*') {
			$image = html_image('ic/stop.png', 48, 48);
		}

		foreach ($params as $b => $v) {
			$prms[] = '<input type="hidden" name="'.$b.'" value="'.$v.'" />'."\n";
		}
		$prm = join('	 	', $prms);

		foreach ($buttons as $b => $v) {
			$btns[] = '<input type="submit" name="'.$b.'" value="'.$v.'" />'."\n";
		}
		$btn = join('	 	&nbsp;&nbsp;&nbsp;'."\n	 	", $btns);

		return '
			<div id="infobox" style="margin-top: 15%; margin-left: 15%; margin-right: 15%; text-align: center;">
			<table align="center">
			<tr>
			<td>'.$image.'</td>
			<td>'.$msg.'<br/></td>
			</tr>
			<tr>
			<td colspan="2" align="center">
			<br />'.$this->openForm(array('action' => getStringFromServer('PHP_SELF'), 'method' => 'get'))
			.$prm.'
			'.$btn.
			$this->closeForm().'
			</td>
			</tr>
			</table>
			</div>
			';
	}

	function jQueryUIconfirmBox($id = 'dialog-confirm', $title = 'Confirm your action', $message = 'Do you confirm your action?') {
		return html_e('div', array('id' => $id, 'title' => $title, 'class' => 'hide'),
				html_e('p', array(), html_e('span', array('class' => 'ui-icon ui-icon-alert', 'style' => 'float:left; margin:0 7px 20px 0;'), '', false).$message));
	}

	function html_input($name, $id = '', $label = '', $type = 'text', $value = '', $extra_params_input = '', $extra_params_div = array()) {
		if (!$id) {
			$id = $name;
		}
		$htmllabel = '';
		if ($label) {
			$htmllabel .= html_e('label', array('for' => $id), $label, true);
		}
		$attrs = array('id' => $id, 'type' => $type);
		//if input is a submit then name is not present
		if ($name) {
			$attrs['name'] = $name;
		}
		if ($value) {
			$attrs['value'] = $value;
		}
		if (is_array($extra_params_input)) {
			foreach ($extra_params_input as $key => $extra_params_value) {
				$attrs[$key] = $extra_params_value;
			}
		}
		$attrs_div = array('class' => 'field-holder');
		$attrs_div = array_merge($attrs_div, $extra_params_div);
		return html_e('div', $attrs_div, $htmllabel.html_e('input', $attrs));
	}

	function html_checkbox($name, $value, $id = '', $label = '', $checked = '', $extra_params = array()) {
		if (!$id) {
			$id = $name;
		}
		$attrs = array('name' => $name, 'id' => $id, 'type' => 'checkbox', 'value' => $value);
		if ($checked) {
			$attrs['checked'] = 'checked';
		}
		if (is_array($extra_params)) {
			foreach ($extra_params as $key => $extra_params_value) {
				$attrs[$key] = $extra_params_value;
			}
		}
		$htmllabel = '';
		if ($label) {
			$htmllabel .= html_e('label', array('for' => $id), $label, true);
		}
		return html_e('div', array('class' => 'field-holder'), $htmllabel.html_e('input', $attrs));
	}

	function html_text_input_img_submit($name, $img_src, $id = '', $label = '', $value = '', $img_title = '', $img_alt = '', $extra_params = array(), $img_extra_params = '') {
		if (!$id) {
			$id = $name;
		}
		if (!$img_title) {
			$img_title = $name;
		}
		if (!$img_alt) {
			$img_alt = $img_title;
		}
		$return = '<div class="field-holder">
			';
		if ($label) {
			$return .= html_e('label', array('for' => $id), $label);
		}
		$return .= '<input id="' . $id . '" type="text" name="' . $name . '"';
		if ($value) {
			$return .= ' value="' . $value . '"';
		}
		if (is_array($extra_params)) {
			foreach ($extra_params as $key => $extra_params_value) {
				$return .= $key . '="' . $extra_params_value . '" ';
			}
		}
		$return .= '/>
			<input type="image" id="' . $id . '_submit" src="' . $this->imgbaseurl . $img_src . '" alt="' . util_html_secure($img_alt) . '" title="' . util_html_secure($img_title) . '"';
		if (is_array($img_extra_params)) {
			foreach ($img_extra_params as $key => $img_extra_params_value) {
				$return .= $key . '="' . $img_extra_params_value . '" ';
			}
		}
		$return .= '/>
			</div>';
		return $return;
	}

	function html_select($vals, $name, $label = '', $id = '', $checked_val = '', $text_is_value = false, $extra_params = '') {
		if (!$id) {
			$id = $name;
		}
		$htmllabel = '';
		if ($label) {
			$htmllabel .= html_e('label', array('for' => $id), $label);
		}
		$attrs = array();
		if (is_array($extra_params)) {
			foreach ($extra_params as $key => $extra_params_value) {
				$attrs[$key] = $extra_params_value;
			}
		}
		return html_e('div', array('class' => 'field-holder'), $htmllabel.html_build_select_box_from_array($vals, $name, $checked_val, $text_is_value, $attrs));
	}

	function html_textarea($name, $id = '', $label = '', $value = '',  $extra_params = '') {
		if (!$id) {
			$id = $name;
		}
		$return = html_ao('div', array('class' => 'field-holder'));
		if ($label) {
			$return .= html_e('label', array('for' => $id), $label);
		}
		$attrs = array('id' => $id, 'name' => $name);
		if (is_array($extra_params)) {
			foreach ($extra_params as $key => $extra_params_value) {
				$attrs[$key] = $extra_params_value;
			}
		}
		$return .= html_e('textarea', $attrs, $value, false);
		$return .= html_ac(html_ap() -1);
		return $return;
	}

	function getNextPic($title = '', $alt = '', $otherAttr = array()) {
		return $this->getPicto('ic/t.png', $title, $alt, 20, 20, $otherAttr);
	}

	function getPrevPic($title = '', $alt = '', $otherAttr = array()) {
		return $this->getPicto('ic/t2.png', $title, $alt, 20, 20, $otherAttr);
	}

	function getMonitorPic($title = '', $alt = '', $otherAttr = array()) {
		return $this->getPicto('ic/mail16w.png', $title, $alt, 20, 20, $otherAttr);
	}

	function getStartMonitoringPic($title = '', $alt = '', $otherAttr = array()) {
		return $this->getPicto('ic/startmonitor.png', $title, $alt, 20, 20, $otherAttr);
	}

	function getStopMonitoringPic($title = '', $alt = '', $otherAttr = array()) {
		return $this->getPicto('ic/stopmonitor.png', $title, $alt, 20, 20, $otherAttr);
	}

	function getReleaseNotesPic($title = '', $alt = '', $otherAttr = array()) {
		return $this->getPicto('ic/manual16c.png', $title, $alt, 20, 20, $otherAttr);
	}

	function getDownloadPic($title = '', $alt = '', $otherAttr = array()) {
		return $this->getPicto('ic/download.png', $title, $alt, 20, 20, $otherAttr);
	}

	function getHomePic($title = '', $alt = '', $otherAttr = array()) {
		return $this->getPicto('ic/home16b.png', $title, $alt, 20, 20, $otherAttr);
	}

	function getFollowPic($title = '', $alt = '', $otherAttr = array()) {
		return $this->getPicto('ic/tracker20g.png', $title, $alt, 20, 20, $otherAttr);
	}

	function getForumPic($title = '', $alt = '', $otherAttr = array()) {
		return $this->getPicto('ic/forum20g.png', $title, $alt, 20, 20, $otherAttr);
	}

	function getDocmanPic($title = '', $alt = '', $otherAttr = array()) {
		return $this->getPicto('ic/docman16b.png', $title, $alt, 20, 20, $otherAttr);
	}

	function getMailPic($title = '', $alt = '', $otherAttr = array()) {
		return $this->getPicto('ic/mail16b.png', $title, $alt, 20, 20, $otherAttr);
	}

	function getMailNotifyPic($title = '', $alt = '', $otherAttr = array()) {
		return $this->getPicto('ic/mail-send.png', $title, $alt, 20, 20, $otherAttr);
	}

	function getPmPic($title = '', $alt = '', $otherAttr = array()) {
		return $this->getPicto('ic/taskman20g.png', $title, $alt, 20, 20, $otherAttr);
	}

	function getSurveyPic($title = '', $alt = '', $otherAttr = array()) {
		return $this->getPicto('ic/survey16b.png', $title, $alt, 20, 20, $otherAttr);
	}

	function getScmPic($title = '', $alt = '', $otherAttr = array()) {
		return $this->getPicto('ic/cvs16b.png', $title, $alt, 20, 20, $otherAttr);
	}

	function getFtpPic($title = '', $alt = '', $otherAttr = array()) {
		return $this->getPicto('ic/ftp16b.png', $title, $alt, 20, 20, $otherAttr);
	}

	function getPackagePic($title = '', $alt = '', $otherAttr = array()) {
		return $this->getPicto('ic/package.png', $title, $alt, 20, 20, $otherAttr);
	}

	function getDeletePic($title = '', $alt = '', $otherAttr = array()) {
		return $this->getPicto('ic/delete.png', $title, $alt, 20, 20, $otherAttr);
	}

	function getRemovePic($title = '', $alt = '', $otherAttr = array()) {
		return $this->getPicto('ic/remove.png', $title, $alt, 20, 20, $otherAttr);
	}

	function getConfigurePic($title = '', $alt = '', $otherAttr = array()) {
		return $this->getPicto('ic/configure.png', $title, $alt, 20, 20, $otherAttr);
	}

	function getZipPic($title = '', $alt = '', $otherAttr = array()) {
		return $this->getPicto('ic/file_type_archive.png', $title, $alt, 20, 20, $otherAttr);
	}

	function getAddDirPic($title = '', $alt = '', $otherAttr = array()) {
		return $this->getPicto('ic/directory-add.png', $title, $alt, 20, 20, $otherAttr);
	}

	function getEditFilePic($title = '', $alt = '', $otherAttr = array()) {
		return $this->getPicto('ic/edit-file.png', $title, $alt, 20, 20, $otherAttr);
	}

	function getEditFieldPic($title = '', $alt = '', $otherAttr = array()) {
		return $this->getPicto('ic/forum_edit.png', $title, $alt, 20, 20, $otherAttr);
	}

	function getNewPic($title = '', $alt = '', $otherAttr = array()) {
		return $this->getPicto('ic/add.png', $title, $alt, 20, 20, $otherAttr);
	}

	function getAddPic($title = '', $alt = '', $otherAttr = array()) {
		return $this->getPicto('ic/add-16.png', $title, $alt, 16, 16, $otherAttr);
	}

	function getMinusPic($title = '', $alt = '', $otherAttr = array()) {
		return $this->getPicto('ic/minus-16.png', $title, $alt, 16, 16, $otherAttr);
	}

	function getFolderPic($title = '', $alt = '', $otherAttr = array()) {
		return $this->getPicto('ic/folder.png', $title, $alt, 20, 20, $otherAttr);
	}

	function getOpenFolderPic($title = '', $alt = '', $otherAttr = array()) {
		return $this->getPicto('ic/ofolder.png', $title, $alt, 20, 20, $otherAttr);
	}

	function getOpenTicketPic($title = '', $alt = '', $otherAttr = array()) {
		return $this->getPicto('ic/ticket-open.png', $title, $alt, 20, 20, $otherAttr);
	}

	function getClosedTicketPic($title = '', $alt = '', $otherAttr = array()) {
		return $this->getPicto('ic/ticket-closed.png', $title, $alt, 20, 20, $otherAttr);
	}

	function getErrorPic($title = '', $alt = '', $otherAttr = array()) {
		return $this->getPicto('ic/stop.png', $title, $alt, 20, 20, $otherAttr);
	}

	function getTagPic($title = '', $alt = '', $otherAttr = array()) {
		return $this->getPicto('ic/tag.png', $title, $alt, 20, 20, $otherAttr);
	}

	function getNewsPic($title = '', $alt = '', $otherAttr = array()) {
		return $this->getPicto('ic/write16w.png', $title, $alt, 20, 20, $otherAttr);
	}

	function getPointerUp($title = '', $alt = '', $otherAttr = array()) {
		return $this->getPicto('ic/pointer_up.png', $title, $alt, 20, 20, $otherAttr);
	}

	function getPointerDown($title = '', $alt = '', $otherAttr = array()) {
		return $this->getPicto('ic/pointer_down.png', $title, $alt, 20, 20, $otherAttr);
	}

	function getFileTxtPic($title = '', $alt = '', $otherAttr = array()) {
		return $this->getPicto('ic/file-txt.png', $title, $alt, 20, 20, $otherAttr);
	}

	function getMessagePic($title = '', $alt = '', $otherAttr = array()) {
		return $this->getPicto('ic/msg.png', $title, $alt, 20, 20, $otherAttr);
	}

	function getPicto($url, $title, $alt, $width = 20, $height = 20, $otherAttr = array()) {
		if ($title != '') {
			$otherAttr['title'] = $title;
		}
		if (!$alt) {
			$otherAttr['alt'] = $title;
		} else {
			$otherAttr['alt'] = $alt;
		}
		return html_image($url, $width, $height, $otherAttr);
	}

	/**
	 * toSlug() - protect a string to be used as a link or an anchor
	 *
	 * @param   string	$string  the string used as a link or an anchor
	 * @param   string	$space   the character used as a replacement for a space
	 * @return  string	a protected string with only alphanumeric characters
	 */
	function toSlug($string, $space = "-") {
		if (function_exists('iconv')) {
			$string = @iconv('UTF-8', 'ASCII//TRANSLIT', $string);
		}
		$string = preg_replace("/[^a-zA-Z0-9_:. -]/", "-", $string);
		$string = strtolower($string);
		$string = str_replace(" ", $space, $string);
		if (!preg_match("/^[a-zA-Z:_]/", $string)) {
			/* some chars aren't allowed at the begin */
			$string = "_" . $string;
		}
		return $string;
	}

	function widget(&$widget, $layout_id, $readonly, $column_id, $is_minimized, $display_preferences, $owner_id, $owner_type) {
		$element_id = 'widget_'. $widget->id .'-'. $widget->getInstanceId();
		echo html_ao('div', array('class' => 'widget', 'id' => $element_id));
		echo html_ao('div', array('class' => 'widget_titlebar '. ($readonly?'':'widget_titlebar_handle')));
		echo html_e('div', array('class' => 'widget_titlebar_title'), $widget->getTitle(), false);
		if (!$readonly) {
			if ($widget->canBeRemove()) {
				echo html_e('div', array('class' => 'widget_titlebar_close'),
					util_make_link('/widgets/updatelayout.php?owner='.$owner_type.$owner_id.'&action=widget&name%5B'.$widget->id.'%5D%5Bremove%5D='.$widget->getInstanceId().'&column_id='.$column_id.'&layout_id='.$layout_id, $this->getPicto('ic/close.png', _('Remove block'), _('Remove block')), array('onclick' => 'return confirm('."'"._('Do you really want to remove this block?')."'".');')));
			}
			if ($widget->canBeMinize()) {
				if ($is_minimized) {
					echo html_e('div', array('class' => 'widget_titlebar_maximize'),
						util_make_link('/widgets/updatelayout.php?owner='.$owner_type.$owner_id.'&action=maximize&name%5B'.$widget->id.'%5D='.$widget->getInstanceId().'&column_id='.$column_id.'&layout_id='.$layout_id, $this->getPicto($this->_getTogglePlusForWidgets(), _('Maximize'), _('Maximize'))));
				} else {
					echo html_e('div', array('class' => 'widget_titlebar_minimize'),
						util_make_link('/widgets/updatelayout.php?owner='.$owner_type.$owner_id.'&action=minimize&name%5B'.$widget->id.'%5D='.$widget->getInstanceId().'&column_id='.$column_id.'&layout_id='.$layout_id, $this->getPicto($this->_getToggleMinusForWidgets(), _('Minimize'), _('Minimize'))));
				}
			}
			if (strlen($widget->hasPreferences())) {
				$url = '/widgets/updatelayout.php?owner='.$owner_type.$owner_id.'&action=preferences&name%5B'.$widget->id.'%5D='.$widget->getInstanceId().'&layout_id='.$layout_id;
				if ($owner_type == WidgetLayoutManager::OWNER_TYPE_TRACKER) {
					$url .= '&func='.getStringFromRequest('func');
					if (getIntFromRequest('aid')) {
						$url .= '&aid='.getIntFromRequest('aid');
					}
				}
				echo html_e('div', array('class' => 'widget_titlebar_prefs'), util_make_link($url, _('Preferences')));
			}
		}
		if ($widget->hasRss()) {
			echo html_ao('div', array('class' => 'widget_titlebar_rss'));
			$url = $widget->getRssUrl($owner_id, $owner_type);
			if (util_check_url($url)) {
				echo util_make_link($widget->getRssUrl($owner_id, $owner_type), 'rss', array(), true);
			} else {
				echo util_make_link($widget->getRssUrl($owner_id, $owner_type), 'rss');
			}
			echo html_ac(html_ap() -1);
		}
		echo html_ac(html_ap() -1);
		$style = '';
		if ($is_minimized) {
			$style = 'display:none;';
		}
		echo html_ao('div', array('class' => 'widget_content', 'style' => $style));
		if (!$readonly && $display_preferences) {
			echo html_e('div', array('class' => 'widget_preferences'), $widget->getPreferencesForm($layout_id, $owner_id, $owner_type));
		}
		if ($widget->isAjax()) {
			echo html_ao('div', array('id' => $element_id.'-ajax'));
			echo '<noscript><iframe style="width:99%; border:none;" src="'. $widget->getIframeUrl($owner_id, $owner_type) .'"></iframe></noscript>';
			echo html_ac(html_ap() -1);
		} else {
			echo $widget->getContent();
		}
		echo html_ac(html_ap() -1);
		if ($widget->isAjax()) {
			$spinner = '<div style="text-align:center">'.trim($this->getPicto('ic/spinner.gif',_('Spinner'), _('Spinner'), 10, 10));
			echo '<script type="text/javascript">/* <![CDATA[ */'."
				jQuery(document).ready(function() {
						jQuery('#$element_id-ajax').html('".$spinner."');
						jQuery.ajax({url:'". util_make_uri($widget->getAjaxUrl($owner_id, $owner_type)) ."',
							success: function(result){jQuery('#$element_id-ajax').html(result)}
							});
						});
			/* ]]> */</script>";
		}
		echo html_ac(html_ap() -1);
	}

	function _getTogglePlusForWidgets() {
		return 'ic/toggle_plus.png';
	}

	function _getToggleMinusForWidgets() {
		return 'ic/toggle_minus.png';
	}

	/* Get the navigation links for the software map pages (trove,
	 * tag cloud, full project list) according to what's enabled
	 */
	function printSoftwareMapLinks() {
		$subMenuTitle = array();
		$subMenuUrl = array();
		$subMenuAttr = array();

		if (forge_get_config('use_project_tags')) {
			$subMenuTitle[] = _('Tag Cloud');
			$subMenuUrl[] = '/softwaremap/tag_cloud.php';
			$subMenuAttr[] = array('title' => _('Browse per tags defined by the projects.'));
		}

		if (forge_get_config('use_trove')) {
			$subMenuTitle[] = _('Project Tree');
			$subMenuUrl[] = '/softwaremap/trove_list.php';
			$subMenuAttr[] = array('title' => _('Browse by Category'));
		}

		if (forge_get_config('use_project_full_list')) {
			$subMenuTitle[] = _('Project List');
			$subMenuUrl[] = '/softwaremap/full_list.php';
			$subMenuAttr[] = array('title' => _('Complete listing of available projects.'));
		}

		// Allow plugins to add more softwaremap submenu entries
		$hookParams = array();
		$hookParams['TITLES'] = & $subMenuTitle;
		$hookParams['URLS'] = & $subMenuUrl;
		$hookParams['ATTRS'] = & $subMenuAttr;
		plugin_hook("softwaremap_links", $hookParams);

		echo $this->subMenu($subMenuTitle, $subMenuUrl, $subMenuAttr);
	}

	function displayStylesheetElements() {
		/* Codendi/Tuleap compatibility */
	}

	/**
	 * openForm - create the html code to open a form
	 *
	 * @param	array	$args	argument of the form (method, action, ...)
	 * @param	bool	$proto	force https if needed. Useful in case to force https URL page in http page.
	 * @return	string	html code
	 */
	function openForm($args, $proto = false) {
		if (isset($args['action'])) {
			if ($proto && forge_get_config('use_ssl')) {
				$args['action'] = util_make_url($args['action'], 'https');
			} else {
				$args['action'] = util_make_uri($args['action']);
			}
		}
		return html_ao('form', $args);
	}

	/**
	 * closeForm - create the html code to close a form
	 *		must be used after openForm function.
	 *
	 * @return	string	html code
	 */
	function closeForm() {
		return html_ac(html_ap() -1);
	}

	function addRequiredFieldsInfoBox() {
		return html_e('p', array(), sprintf(_('Fields marked with %s are mandatory.'), utils_requiredField()), false);
	}

	/**
	 * html_list - create the html code:
	 *	<ol>
	 *		<li>
	 *	</ol>
	 *	or
	 *	<ul>
	 *		<li>
	 *	</ul>
	 *
	 * @param	array	$elements	array of args to create li elements
	 *					format array['content'] = the content to display in li
	 *						['attrs'] = array of html attrs applied to the li element
	 * @param	array	$attrs		array of attributes of the ol element. Default empty array.
	 * @param	string	$type		type of list : ol or ul. Default is ul.
	 * @return	string
	 */
	function html_list($elements, $attrs = array() , $type = 'ul') {
		$htmlcode = html_ao($type, $attrs);
		foreach ($elements as $element) {
			if (!isset($element['attrs'])) {
				$element['attrs'] = array();
			}
			$htmlcode .= html_e('li', $element['attrs'], $element['content']);
		}
		$htmlcode .= html_ac(html_ap() -1);
		return $htmlcode;
	}

	/**
	 * html_chartid - create the div code to be used with jqplot script
	 *
	 * @param	string	$chart_id		id to identify the div.
	 * @param	string	$figcaption_title	title of the chart
	 * @return	string
	 */
	function html_chartid($chart_id = 'chart0', $figcaption_title = '') {
		$figcaption_htmlcode = '';
		if ($figcaption_title) {
			$figcaption_htmlcode = html_e('figcaption', array(), $figcaption_title, false);
		}
		return html_e('figure', array(), $figcaption_htmlcode.html_e('div', array('id' => $chart_id)));
	}

	/**
	 * paging_top - Display Introduction to paging & form to set the paging preference
	 *
	 * @param	int	$start		start of the list
	 * @param	int	$paging		number of element per page
	 * @param	int	$totalElements	total number of this type of Elements in the forge
	 * @param	int	$maxElements	max number of Elements to display
	 * @param	string	$actionUrl	next / prev Url to click
	 * @param	array	$htmlAttr	html attributes to set.
	 * @return	string
	 */
	function paging_top($start = 0, $paging = 25, $totalElements = 0, $maxElements = 0, $actionUrl = '/', $htmlAttr = array()) {
		$html_content = '';
		$sep = '?';
		if (strpos($actionUrl, '?')) {
			$sep = '&';
		}
		if ($totalElements && session_loggedin()) {
			$html_content .= $this->openForm(array('action' => $actionUrl.$sep.'start='.$start, 'method' => 'post'));
		}
		if ($totalElements) {
			$html_content .= sprintf(_('Displaying results %1$s out of %2$d total.'), ($start + 1).'-'.$maxElements, $totalElements);
			if (session_loggedin()) {
				$html_content .= sprintf(' ' . _('Displaying %s results.'), html_build_select_box_from_array(array('10', '25', '50', '100', '1000'), 'nres', $paging, 1));
				$html_content .= $this->html_input('setpaging', '', '', 'submit', _('Change'), array(), array('style' => 'display: inline-block'));
				$html_content .= $this->closeForm();
			}
		}
		if (strlen($html_content) > 0) {
			return html_e('div', $htmlAttr, $html_content, false);
		}
		return '';
	}

	/**
	 * paging_bottom - Show extra rows for <-- Prev / Next --> at the bottom of the element list
	 *
	 * @param	int	$start		start of the list
	 * @param	int	$paging		number of element per page
	 * @param	int	$totalElements	total number of Elements to display
	 * @param	string	$actionUrl	next / prev Url to click
	 * @return	string
	 */
	function paging_bottom($start = 0, $paging = 25, $totalElements = 0, $actionUrl = '/') {
		$html_content = '';
		$sep = '?';
		if (strpos($actionUrl, '?')) {
			$sep = '&';
		}
		if ($start > 0) {
			$html_content .= util_make_link($actionUrl.$sep.'start='.($start-$paging),'<strong>&larr; '._('previous').'</strong>');
			$html_content .= '&nbsp;&nbsp;';
		}
		$pages = $totalElements / $paging;
		$currentpage = intval($start / $paging);
		if ($pages > 1) {
			$skipped_pages=false;
			for ($j=0; $j<$pages; $j++) {
				if ($pages > 20) {
					if ((($j > 4) && ($j < ($currentpage-5))) || (($j > ($currentpage+5)) && ($j < ($pages-5)))) {
						if (!$skipped_pages) {
							$skipped_pages=true;
							$html_content .= '....&nbsp;';
						}
						continue;
					} else {
						$skipped_pages=false;
					}
				}
				if ($j * $paging == $start) {
					$html_content .= '<strong>'.($j+1).'</strong>&nbsp;&nbsp;';
				} else {
					$html_content .= util_make_link($actionUrl.$sep.'start='.($j*$paging),'<strong>'.($j+1).'</strong>').'&nbsp;&nbsp;';
				}
			}
		}
		if ($totalElements > $start + $paging) {
			$html_content .= util_make_link($actionUrl.$sep.'start='.($start+$paging),'<strong>'._('next').' &rarr;</strong>');
		}
		return $html_content;
	}

	/**
	* show_priority_colors_key - Show the priority colors legend
	*
	* @return	string	html code
	*
	*/
	function show_priority_colors_key() {
		$html = '<p><strong> '._('Priority Colors')._(':').'</strong>';
		for ($i = 1; $i < 6; $i++) {
			$html .= ' <span class="priority'.$i.'">'.$i.'</span>';
		}
		$html .= '</p>';
		return $html;
	}
}

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
