<?php

# This file was automatically generated by the MediaWiki installer.
# If you make manual changes, please keep track in case you need to
# recreate them later.
#
# See includes/DefaultSettings.php for all configurable settings
# and their default values, but don't forget to make changes in _this_
# file, not there.

# If you customize your file layout, set $IP to the directory that contains
# the other MediaWiki files. It will be used as a base to locate files.
if( defined( 'MW_INSTALL_PATH' ) ) {
	$IP = MW_INSTALL_PATH;
} else {
	$IP = dirname( __FILE__ );
}

$path = array( $IP, "$IP/includes", "$IP/languages", "/usr/share/gforge", "/etc/gforge/" );
set_include_path( implode( PATH_SEPARATOR, $path ) . PATH_SEPARATOR . get_include_path() );

require_once  "includes/DefaultSettings.php" ;

# If PHP's memory limit is very low, some operations may fail.
ini_set( 'memory_limit', '20M' );

if ( $wgCommandLineMode ) {
	if ( isset( $_SERVER ) && array_key_exists( 'REQUEST_METHOD', $_SERVER ) ) {
		die( "This script must be run from the command line\n" );
	}
} elseif ( empty( $wgNoOutputBuffer ) ) {
	## Compress output if the browser supports it
	if( !ini_get( 'zlib.output_compression' ) ) @ob_start( 'ob_gzhandler' );
}

$wgSitename         = "GForge";

$wgScriptPath	    = "/mediawiki";
$wgScript           = "$wgScriptPath/index.php";
$wgRedirectScript   = "$wgScriptPath/redirect.php";

## For more information on customizing the URLs please see:
## http://meta.wikimedia.org/wiki/Eliminating_index.php_from_the_url
## If using PHP as a CGI module, the ?title= style usually must be used.
$wgArticlePath      = "$wgScript/$1";
# $wgArticlePath      = "$wgScript?title=$1";

$wgStylePath        = "$wgScriptPath/skins";
$wgStyleDirectory   = "$IP/skins";
$wgLogo             = "$wgStylePath/common/images/wiki.png";

$wgUploadPath       = "$wgScriptPath/upload";
$wgUploadDirectory  = "$IP/upload";

$wgEnableEmail = true;
$wgEnableUserEmail = true;

$wgEmergencyContact = "webmaster@gforge.eu";
$wgPasswordSender	= "webmaster@gforge.eu";

## For a detailed description of the following switches see
## http://meta.wikimedia.org/Enotif and http://meta.wikimedia.org/Eauthent
## There are many more options for fine tuning available see
## /includes/DefaultSettings.php
## UPO means: this is also a user preference option
$wgEnotifUserTalk = true; # UPO
$wgEnotifWatchlist = true; # UPO
$wgEmailAuthentication = true;

$wgDBserver         = "localhost";
$wgDBname           = "wikidb";
$wgDBuser           = "wikiuser";
$wgDBpassword       = getenv('sys_gfdbpasswd');
$wgDBprefix         = "";
$wgDBtype           = "mysql";
$wgDBport           = "5432";

# Experimental charset support for MySQL 4.1/5.0.
$wgDBmysql5 = false;

## Shared memory settings
$wgMainCacheType = CACHE_NONE;
$wgMemCachedServers = array();

## To enable image uploads, make sure the 'images' directory
## is writable, then set this to true:
$wgEnableUploads		= true;
$wgUseImageResize		= true;
# $wgUseImageMagick = true;
# $wgImageMagickConvertCommand = "/usr/bin/convert";

## If you want to use image uploads under safe mode,
## create the directories images/archive, images/thumb and
## images/temp, and make them all writable. Then uncomment
## this, if it's not already uncommented:
# $wgHashedUploadDirectory = false;

## If you have the appropriate support software installed
## you can enable inline LaTeX equations:
$wgUseTeX	         = false;
$wgMathPath         = "{$wgUploadPath}/math";
$wgMathDirectory    = "{$wgUploadDirectory}/math";
$wgTmpDirectory     = "{$wgUploadDirectory}/tmp";

$wgLocalInterwiki   = $wgSitename;

$wgLanguageCode = "fr";

$wgProxyKey = "4bf858500e0459a409627b549125510f6ba406818b5adf60d2c608c78fab0b00";

## Default skin: you can change the default skin. Use the internal symbolic
## names, ie 'standard', 'nostalgia', 'cologneblue', 'monobook':
## $wgDefaultSkin = 'monobook';
$wgDefaultSkin = 'gforge';
$wgSkipSkins = array('standard','nostalgia','cologneblue','monobook','simple','chick','myskin');

## For attaching licensing metadata to pages, and displaying an
## appropriate copyright notice / icon. GNU Free Documentation
## License and Creative Commons licenses are supported so far.
# $wgEnableCreativeCommonsRdf = true;
$wgRightsPage = ""; # Set to the title of a wiki page that describes your license/copyright
$wgRightsUrl = "";
$wgRightsText = "";
$wgRightsIcon = "";
# $wgRightsCode = ""; # Not yet used

$wgDiff3 = "/usr/bin/diff3";

# When you make changes to this configuration file, this will make
# sure that cached pages are cleared.
$configdate = gmdate( 'YmdHis', @filemtime( __FILE__ ) );
$wgCacheEpoch = max( $wgCacheEpoch, $configdate );

# debian specific include:
if (is_file("/etc/mediawiki-extensions/extensions.php")) {
	include( "/etc/mediawiki-extensions/extensions.php" );
}

$wgShowIPinHeader=false;
require_once $gfplugins.'mediawiki/usr/share/mediawiki1.7/includes/GForgeAuthentication.php';
$wgAuth = new GForgeAuthenticationPlugin();
# 'AutoAuthenticate': called to authenticate users on external/environmental means
# $user: writes user object to this parameter
$wgHooks['AutoAuthenticate'][] = array($wgAuth, 'getGForgeUserSession',array());

# Client-side caching:
/** Allow client-side caching of pages */
//$wgCachePages       = false;
/**
 * Set this to current time to invalidate all prior cached pages. Affects both
 * client- and server-side caching.
 * You can get the current date on your server by using the command:
 *   date +%Y%m%d%H%M%S
 */
//$wgCacheEpoch = 'date +%Y%m%d%H%M%S';

?>
