#! /usr/bin/php
<?php
/**
 * Implement CVS ACLs based on GForge roles
 *
 * Copyright 2004 GForge, LLC
 *
 * This file is part of FusionForge.
 *
 * FusionForge is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * FusionForge is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

if (((int) $_SERVER['argc']) < 1) {
	print "Usage: ".basename(__FILE__)." /cvsroot/projectname\n";
	exit(1);
}

require_once dirname(__FILE__).'/../../env.inc.php';
require_once $gfcommon. 'include/pre.php';
require_once $gfcommon.'include/utils.php';
require_once $gfconfig.'plugins/scmcvs/config.php';
require_once 'libphp-snoopy/Snoopy.class.php';

// Input cleansing
$env_cvsroot = (string) $_ENV['CVSROOT'];

# Rules
# 1. Must begin with /cvs/ or /cvsroot/
# 2. Then must contain 3 - 25 alphanumeric chars or -
preg_match("/^\/\/?(cvs)(root)*\/\/?([[:alnum:]-]{3,25})$/", $env_cvsroot, $matches);

if (empty($matches)) {
	print "Invalid CVS directory\n";
	exit(1);
}

$projectName = $matches[count($matches)-1];

$userArray=posix_getpwuid ( posix_geteuid ( ) );
$userName= $userArray['name'];

// Our POSTer in Gforge
$snoopy = new Snoopy;

$SubmitUrl=util_make_url('/plugins/scmcvs/acl.php');
$SubmitVars['group'] = $projectName;
$SubmitVars['user'] = $userName;

if ($userName == 'root') {
	exit(0);
} else {

	$snoopy->submit($SubmitUrl,$SubmitVars);
	if (!empty($snoopy->error) || !empty($snoopy->results)) {
		print $snoopy->results."\n";
		exit(1);
	}

}

?>
