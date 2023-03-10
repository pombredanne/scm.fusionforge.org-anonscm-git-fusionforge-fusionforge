<?php
/**
 * FusionForge ViewCVS PHP wrapper.
 *
 * Portion of this file is inspired from the ViewCVS wrapper
 * contained in CodeX.
 * Copyright (c) Xerox Corporation, CodeX / CodeX Team, 2001,2002. All Rights Reserved.
 * Copyright (C) 2011 Alain Peyrat - Alcatel-Lucent
 * http://codex.xerox.com
 *
 * Copyright 2010, Franck Villaume - Capgemini
 * Copyright 2012,2014, Franck Villaume - TrivialDev
 * Copyright (C) 2014  Inria (Sylvain Beucler)
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
require_once $gfwww.'scm/include/scm_utils.php';
require_once $gfwww.'scm/include/viewvc_utils.php';

if (!forge_get_config('use_scm')) {
	exit_disabled();
}

// Get the project name from query
$projectName = "";
if (getStringFromGet('root') && strpos(getStringFromGet('root'), ';') === false) {
	$projectName = getStringFromGet('root');
} elseif ($_SERVER['PATH_INFO']) {
	$arr = explode('/', $_SERVER['PATH_INFO']);
	$projectName = $arr[1];
} else {
	$queryString = getStringFromServer('QUERY_STRING');
	if(preg_match_all('/[;]?([^\?;=]+)=([^;]+)/', $queryString, $matches, PREG_SET_ORDER)) {
		for($i = 0, $size = sizeof($matches); $i < $size; $i++) {
			$query[$matches[$i][1]] = urldecode($matches[$i][2]);
		}
		$projectName = $query['root'];
	}
}
// Remove eventual leading /root/ or root/
$projectName = preg_replace('%^..[^/]*/%','', $projectName);
if (!$projectName) {
	exit_no_group();
}

// Check permissions
$Group = group_get_object_by_name($projectName);
if (!$Group || !is_object($Group)) {
	$svnplugin = plugin_get_object('scmsvn');
	$group_id = $svnplugin->getGroupIdFromSecondReponame($projectName);
	//this may be a SVN second repo. Let's check for it.
	$Group = group_get_object($group_id);
}
if (!$Group || !is_object($Group)) {
	exit_no_group();
} elseif ( $Group->isError()) {
	exit_error($Group->getErrorMessage(),'summary');
}
if (!$Group->usesSCM()) {
	exit_disabled();
}

// check if the scm_box is located in another server
$scm_box = $Group->getSCMBox();
//$external_scm = (gethostbyname(forge_get_config('web_host')) != gethostbyname($scm_box));
//$external_scm = !forge_get_config('scm_single_host');
$external_scm = 1;
$redirect = 0;

if (!forge_check_perm('scm', $Group->getID(), 'read')) {
	exit_permission_denied('scm');
}

$unix_name = $Group->getUnixName();
$u = session_get_user();
if ($external_scm && !$Group->usesPlugin('scmcvs')) {
	if ($Group->enableAnonSCM()) {
		$server_script = '/anonscm/viewvc';
	} else {
		$server_script = '/authscm/'.$u->getUnixName().'/viewvc';
	}
	// pass the parameters passed to this script to the remote script in the same fashion
	$protocol = forge_get_config('use_ssl', 'scmsvn')? 'https://' : 'http://';
	$port = util_url_port(forge_get_config('use_ssl', 'scmsvn'));
	$pathinfo = (isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '/');
	$pathinfo = preg_replace('/ /', '%20', $pathinfo);
	$script_url = $protocol . $Group->getSCMBox(). $port . $server_script
		. $pathinfo . '?' . $_SERVER["QUERY_STRING"];
	if ($redirect) {
		header("Location: $script_url");
		exit(0);
	} else {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_URL, $script_url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, forge_get_config('use_ssl_verification'));
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, forge_get_config('use_ssl_verification'));
		curl_setopt($ch, CURLOPT_COOKIE, @$_SERVER['HTTP_COOKIE']);  // for session validation
		curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);  // for session validation
		curl_setopt($ch, CURLOPT_HTTPHEADER,
					array('Accept-Language: '.$_SERVER['HTTP_ACCEPT_LANGUAGE'],  // for i18n
						  'X-Forwarded-For: '.$_SERVER['REMOTE_ADDR']));  // for session validation
		$content = curl_exec($ch);
		if ($content === false) {
			exit_error("Error fetching $script_url : " . curl_error($ch), 'summary');
		}
		curl_close($ch);
	}
} else {
	// Call to ViewCVS CGI locally (see viewcvs_utils.php)

	// see what type of plugin this project if using
	if ($Group->usesPlugin('scmcvs')) {
		$repos_type = 'cvs';
	} elseif ($Group->usesPlugin('scmsvn')) {
		$repos_type = 'svn';
	}

	// HACK : getSiteMenu in Navigation.class.php use GLOBAL['group_id']
	// to fix missing projet name tab
	$group_id = $Group->getID();

	$content = viewcvs_execute($unix_name, $repos_type);
}

// Set content type header from the value set by ViewCVS
// No other headers are generated by ViewCVS because in generate_etags
// is set to 0 in the ViewCVS config file
$exploded_content = explode("\r\n\r\n", $content);
if (count($exploded_content) > 1) {
	list($headers, $body) = explode("\r\n\r\n", $content);
	$headers = explode("\r\n", $headers);
	$content_type = '';
	$charset = '';
	if ($external_scm) {
		// Strip "HTTP/1.1 200 OK" initial status line
		array_shift($headers);
	}
	foreach ($headers as $header) {
		if (preg_match('/^Content-Type:\s*(([^;]*)(\s*;\s*charset=(.*))?)/i', $header, $matches)) {
			$content_type = $matches[2];
			if (isset($matches[4])) {
				$charset = $matches[4];
			}
			// we'll validate content-type or transcode body below
		} elseif (preg_match('/^Transfer-Encoding: chunked/', $header)) {
			// curl already de-chuncked the body
		} else {
			header($header);
		}
	}
} else {
	$body = $content;
}

if (!isset($_GET['view'])) {
	$_GET['view'] = 'none';
}

// echo "script_url=$script_url<br />";
switch ($_GET['view']) {
	case 'tar':
	case 'co':
	case 'patch':
		$sysdebug_enable = false;
		// Force content-type for any text/* or */javascript, to avoid XSS
		if (!empty($content_type)) {
			if (preg_match('/text\/.*/', $content_type) || preg_match('/.*\/javascript/', $content_type)) {
					$content_type = 'text/plain';
			}
			header("Content-Type: $content_type"
				   . (!empty($charset) ? ";charset=$charset" : ''));
		}
		echo $body;
		break;
	default:
		// If we output html and we found the mbstring extension, we
		// should try to encode the output of ViewCVS in UTF-8
		if (!empty($charset) && $charset != 'UTF-8' && extension_loaded('mbstring')) {
			$body = mb_convert_encoding($body, 'UTF-8', $charset);
		}
		scm_header(array('title'=>_("SCM Repository"),
						 'group'=>$Group->getID(),
						 'inframe'=>1));
		echo $body;
		scm_footer(array('inframe'=>1));
		break;
}
