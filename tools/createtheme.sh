#!/bin/sh

usage() {
	echo Usage: $0 PluginName
}

echo "Theme template creator"
if [ "$#" != "1" ] 
then
	usage
else
	fullname=$1
	minus=`echo $1 | tr '[A-Z]' '[a-z]'`
	themedir=gforge-theme-$minus
	echo "Creating $1 theme"
	echo "Creating directory $themedir"
	if [ ! -d $themedir ]
	then
		mkdir $themedir
		mkdir $themedir/debian
		mkdir $themedir/www
		mkdir $themedir/www/themes
		mkdir $themedir/www/themes/$minus

echo Creating $themedir/debian/dirs
cat > $themedir/debian/dirs <<FIN
/usr/share/gforge
FIN

echo Creating $themedir/debian/README.Debian
cat > $themedir/debian/README.Debian <<FIN
GForge Theme Package for Debian
===============================

This is a package installing a theme in the main gforge package.
See more documentation in the main package.
The purpose is essentially to customize your gforge installation.
FIN

echo Creating $themedir/debian/changelog
cat > $themedir/debian/changelog <<FIN
$themedir (3rc2-1) unstable; urgency=low

  * [Christian] $fullname theme first release

 -- Christian Bayle <bayle@debian.org>  Tue, 13 May 2003 23:31:59 +0200
FIN

echo Creating $themedir/debian/control
cat > $themedir/debian/control <<FIN
Source: $themedir
Section: devel
Priority: optional
Maintainer: Christian Bayle <bayle@debian.org>
Build-Depends-Indep: debhelper (>= 4.0), sharutils, docbook-to-man
Standards-Version: 3.5.8

Package: $themedir
Architecture: all
Depends: debconf (>= 0.5.00), gforge-common, gforge-web-apache | gforge-web, gforge-db-postgresql | gforge-db
Description: Collaborative development tool - theme package
 GForge provides many tools to help collaboration in a
 development project, such as bug-tracking, task management,
 mailing-lists, CVS repository, forums, support request helper, web
 page / FTP hosting, release management, etc.  All these services are
 integrated into one web site and managed via a nice web interface.
 .
 This meta-package installs a gforge theme
FIN

echo Creating $themedir/debian/copyright
cat > $themedir/debian/copyright <<FIN
This package was first debianised on Wed, 22 Nov 2000 22:06:35 +0100
by Roland Mas <lolando@debian.org>.  Work has been constant since
then, and the package evolved a great deal.  It began to work, for a
start.

Copyright info for the GForge software:
+----
| The original sources were downloaded from http://www.sourceforge.net/
| 
| Authors: The Sourceforge crew at VA Linux.  They are many, they
| change as time goes by, and they are listed on the Sourceforge
| website.  Let them be thanked for their work.
| 
| Copyright:
| 
| This software is copyright (c) 1999-2000 by VA Linux.
| 
| You are free to distribute this software under the terms of the GNU
| General Public License.
+----

The packaging and installing scripts (in the debian/ and deb-specific/
directories amongst others) are copyright (c) 2000-2002 by Christian
Bayle <bayle@aist.enst.fr> and Roland Mas <lolando@debian.org>.  You
are free to use and redistribute them under the terms of the GNU
General Public License.

The themes are copyright (c) 1999-2000 by VA Linux.  The Savannah
themes are modified versions, and are copyright (c) 2000-2001 by the
Free Software Foundation.  They all are free software, and you can
redistribute them and/or modify them under the terms of the GNU
General Public License.

On Debian systems, the complete text of the GNU General Public License
can be found in the /usr/share/common-licenses directory.
FIN

echo Creating $themedir/debian/postinst
cat > $themedir/debian/postinst <<FIN
#! /bin/sh
# postinst script for gforge
#

set -e
# set -x				# Be verbose, be very verbose.

# summary of how this script can be called:
#        * <postinst> 'configure' <most-recently-configured-version>
#        * <old-postinst> 'abort-upgrade' <new version>
#        * <conflictor's-postinst> 'abort-remove' 'in-favour' <package>
#          <new-version>
#        * <deconfigured's-postinst> 'abort-deconfigure' 'in-favour'
#          <failed-install-package> <version> 'removing'
#          <conflicting-package> <version>
# for details, see /usr/share/doc/packaging-manual/
#
# quoting from the policy:
#     Any necessary prompting should almost always be confined to the
#     post-installation script, and should be protected with a conditional
#     so that unnecessary prompting doesn't happen if a package's
#     installation fails and the 'postinst' is called with 'abort-upgrade',
#     'abort-remove' or 'abort-deconfigure'.

. /usr/share/debconf/confmodule

case "\$1" in
    configure)
        # Add the theme
	/usr/share/gforge/bin/register-theme "$minus" "$fullname"
    ;;

    abort-upgrade|abort-remove|abort-deconfigure)
    ;;

    *)
        echo "postinst called with unknown argument \'\$1'" >&2
        exit 0
    ;;
esac

# dh_installdeb will replace this with shell code automatically
# generated by other debhelper scripts.

#DEBHELPER#

exit 0
FIN

echo Creating $themedir/debian/rules
cat > $themedir/debian/rules <<FIN
#!/usr/bin/make -f
# debian/rules that uses debhelper.
# GNU copyright 1997 to 1999 by Joey Hess (sample file)
# Copyright 2000 to 2002 by Roland Mas and Christian Bayle for the GForge package

# Uncomment this to turn on verbose mode.
#export DH_VERBOSE=1

# This is the debhelper compatability version to use.
export DH_COMPAT=4

configure: configure-stamp
configure-stamp:
	dh_testdir

	touch configure-stamp

build: configure-stamp build-stamp
build-stamp:
	dh_testdir
	touch build-stamp

clean:
	dh_testdir
	dh_testroot
	rm -f build-stamp configure-stamp
	dh_clean

install: build
	dh_testdir
	dh_testroot
	dh_clean -k
	dh_installdirs

	# $themedir
	cp -r www \$(CURDIR)/debian/$themedir/usr/share/gforge/
	find \$(CURDIR)/debian/$themedir/usr/share/gforge/ -name CVS -type d | xargs rm -rf
	find \$(CURDIR)/debian/$themedir/usr/share/gforge/www -type d -exec chmod 0755 {} \;
	find \$(CURDIR)/debian/$themedir/usr/share/gforge/www -type f -exec chmod 0644 {} \;
	mkdir -p \$(CURDIR)/debian/$themedir/usr/share/gforge/bin

binary-indep: build install
	dh_testdir
	dh_testroot
	dh_installdebconf	
	dh_installdocs
	#dh_installexamples
	#dh_installmenu
	#dh_installemacsen
	#dh_installpam
	#dh_installinit
	dh_installcron
	dh_installman
	#dh_installinfo
	#dh_undocumented
	dh_installchangelogs
	#dh_link
	dh_strip
	dh_compress
	#dh_fixperms
	#dh_makeshlibs
	dh_installdeb
	#dh_perl
	#dh_shlibdeps
	dh_gencontrol
	dh_md5sums
	dh_builddeb

binary-arch: build install
	# (No architecture-dependent files for GForge, doing nothing here)

binary: binary-indep binary-arch
.PHONY: build clean binary-indep binary-arch binary install configure
FIN

echo Creating $themedir/debian/prerm
cat > $themedir/debian/prerm <<FIN
#! /bin/sh
# prerm script for gforge-theme
#
# see: dh_installdeb(1)

set -e

# summary of how this script can be called:
#        * <prerm> 'remove'
#        * <old-prerm> 'upgrade' <new-version>
#        * <new-prerm> 'failed-upgrade' <old-version>
#        * <conflictor's-prerm> 'remove' 'in-favour' <package> <new-version>
#        * <deconfigured's-prerm> 'deconfigure' 'in-favour'
#          <package-being-installed> <version> 'removing'
#          <conflicting-package> <version>
# for details, see http://www.debian.org/doc/debian-policy/ or
# the debian-policy package


case "\$1" in
    remove|deconfigure)
	/usr/share/gforge/bin/unregister-theme $minus
        ;;
    upgrade|failed-upgrade)
        ;;
    *)
        echo "prerm called with unknown argument \'\$1'" >&2
        exit 1
    ;;
esac

# dh_installdeb will replace this with shell code automatically
# generated by other debhelper scripts.

#DEBHELPER#

exit 0


FIN

echo Creating $themedir/www/themes/$minus/Theme.class
cat > $themedir/www/themes/$minus/Theme.class <<FIN
<?php   
/**
 * Base theme class.
 *
 * SourceForge: Breaking Down the Barriers to Open Source Development
 * Copyright 1999-2001 (c) VA Linux Systems
 * http://sourceforge.net
 */

class Theme extends Layout {

	/**
	 * Theme() - Constructor
	 */
	function Theme() {
		// Parent constructor
		\$this->Layout();

		// The root location for images
		\$this->imgroot = '/themes/gforge/images/';

		// The content background color
		// sky blue
		\$this->COLOR_CONTENT_BACK= '#BBDDFF';

		// The background color
		\$this->COLOR_BACK= '#FFFFFF';

		// The HTML box title color
		\$this->COLOR_HTMLBOX_TITLE = '#C70036';

		// The HTML box background color
		\$this->COLOR_HTMLBOX_BACK = '#C79FA9';

		// Font Face Constants
		// The content font
		\$this->FONT_CONTENT = 'Helvetica';
		// The HTML box title font
		\$this->FONT_HTMLBOX_TITLE = 'Helvetica';
		// The HTML box title font color
		\$this->FONTCOLOR_HTMLBOX_TITLE = '#C6BCBF';
		// The content font color
		\$this->FONTCOLOR_CONTENT = '#000000';
		//The smaller font size
		\$this->FONTSIZE_SMALLER='x-small';
		//The smallest font size
		\$this->FONTSIZE_SMALLEST='xx-small';
		//The HTML box title font size
		\$this->FONTSIZE_HTMLBOX_TITLE = 'small';

	}

	/**
	 *	header() - "steel theme" top of page
	 *
	 * @param	array	Header parameters array
	 */
	function header(\$params) {

		if (!\$params['title']) {
			\$params['title'] = "GForge";
		} else {
			\$params['title'] = "GForge: " . \$params['title'];
		}
		?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN" "http://www.w3.org/TR/REC-html40/loose.dtd">

<html lang="<?php echo _('en') ?>">
  <head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<TITLE><?php echo \$params['title']; ?></TITLE>
	<SCRIPT language="JavaScript">
	<!--
	function help_window(helpurl) {
		HelpWin = window.open( '<?php echo ((session_issecure()) ? 'https://'.
			\$GLOBALS['sys_default_domain'] : 'http://'.\$GLOBALS['sys_default_domain']); ?>' + helpurl,'HelpWindow','scrollbars=yes,resizable=yes,toolbar=no,height=400,width=400');
	}
	// -->
	<?php plugin_hook ("javascript",false) ; ?>
	</SCRIPT>
<?php
/*



	WARNING - changing this font call can affect
	INTERNATIONALIZATION


*/


		//gets font from Language Object
		\$site_fonts=\$GLOBALS['Language']->getFont();

	?>

<style type="text/css">
	<!--
	OL,UL,P,BODY,TD,TR,TH,FORM { font-family: <?php echo \$site_fonts; ?>; font-size:<?php echo \$this->FONTSIZE; ?>; color: <?php echo \$this->FONTCOLOR_CONTENT ?>; }

	H1 { font-size: x-large; font-family: <?php echo \$site_fonts; ?>; }
	H2 { font-size: large; font-family: <?php echo \$site_fonts; ?>; }
	H3 { font-size: medium; font-family: <?php echo \$site_fonts; ?>; }
	H4 { font-size: small; font-family: <?php echo \$site_fonts; ?>; }
	H5 { font-size: x-small; font-family: <?php echo \$site_fonts; ?>; }
	H6 { font-size: xx-small; font-family: <?php echo \$site_fonts; ?>; }

	PRE,TT { font-family: courier,sans-serif }

	A:link { text-decoration:none }
	A:visited { text-decoration:none }
	A:active { text-decoration:none }
	A:hover { text-decoration:underline; color:#FF0000 }

	.titlebar { color: #000000; text-decoration: none; font-weight: bold; }
	A.tablink { color: #000000; text-decoration: none; font-weight: bold; font-size: <?php echo \$this->FONTSIZE_SMALLER; ?>; }
	A.tablink:visited { color: #000000; text-decoration: none; font-weight: bold; font-size: <?php
		echo \$this->FONTSIZE_SMALLER; ?>; }
	A.tablink:hover { text-decoration: none; color: #000000; font-weight: bold; font-size: <?php
		echo \$this->FONTSIZE_SMALLER; ?>; }
	A.tabsellink { color: #000000; text-decoration: none; font-weight: bold; font-size: <?php
		echo \$this->FONTSIZE_SMALLER; ?>; }
	A.tabsellink:visited { color: #000000; text-decoration: none; font-weight: bold; font-size: <?php
		echo \$this->FONTSIZE_SMALLER; ?>; }
	A.tabsellink:hover { text-decoration: none; color: #000000; font-weight: bold; font-size: <?php
		echo \$this->FONTSIZE_SMALLER; ?>; }
	-->
</style>

</head>

<BODY TOPMARGIN="3" MARGINHEIGHT="3" MARGINWIDTH="3" LEFTMARGIN="3" RIGHTMARGIN="3" bgcolor="<?php echo \$this->COLOR_CONTENT_BACK; ?>">

<table border=0 width="100%" cellspacing="0" cellpadding=0>

	<tr>
		<td><a href="/"><?php echo html_image('logo.png',275,75,array('border'=>'0')); ?></a></td>
		<td><?php echo \$this->searchBox(); ?></td>
		<td align="RIGHT"><?php
			if (session_loggedin()) {
				?>
				<a href="/account/logout.php">Logout</a><br />
				<a href="/account/">My Account</a>
				<?php
			} else {
				?>
				<a href="/account/login.php">Login</a><br />
				<a href="/account/register.php">New Account</a>
				<?php
			}

		?></td>
		<td>&nbsp;&nbsp;</td>
	</tr>

</TABLE>

<table border=0 width="100%" cellspacing="0" cellpadding=0>

	<tr>
		<td>&nbsp;</td>
		<td colspan=3>

<?php echo \$this->outerTabs(\$params); ?>

		</td>
		<td>&nbsp;</td>
	</tr>

	<tr>
		<td align=LEFT width=10><img src="<?php echo \$this->imgroot; ?>tabs/topleft.png" height=9 width=9></td>
		<td width=30><img src="<?php echo \$this->imgroot; ?>clear.png" width=30 height=1></td>
		<td><img src="<?php echo \$this->imgroot; ?>clear.png" width=1 height=1></td>
		<td width=30><img src="<?php echo \$this->imgroot; ?>clear.png" width=30 height=1></td>
		<td align=RIGHT width=10><img src="<?php echo \$this->imgroot; ?>tabs/topright.png" height=9 width=9></td>
	</tr>

	<tr>

		<!-- Outer body row -->

		<td><img src="<?php echo \$this->imgroot; ?>clear.png" width=10 height=1></td>
		<td valign=TOP width="99%" colspan=3>

			<!-- Inner Tabs / Shell -->

			<table border=0 width="100%" cellspacing="0" cellpadding=0>
<?php


if (\$params['group']) {

			?>
			<tr>
				<td>&nbsp;</td>
				<td>
				<?php

				echo \$this->projectTabs(\$params['toptab'],\$params['group']);

				?>
				</td>
				<td>&nbsp;</td>
			</tr>
			<?php

}

?>
			<tr>
				<td align=LEFT bgcolor="<?php echo \$this->COLOR_BACK; ?>" width=10><img src="<?php echo \$this->imgroot; ?>tabs/topleft-inner.png" height=9 width=9></td>
				<td bgcolor="<?php echo \$this->COLOR_BACK; ?>"><img src="<?php echo \$this->imgroot; ?>clear.png" width=1 height=1></td>
				<td align=RIGHT bgcolor="<?php echo \$this->COLOR_BACK; ?>" width=10><img src="<?php echo \$this->imgroot; ?>tabs/topright-inner.png" height=9 width=9></td>
			</tr>

			<tr>
				<td bgcolor="<?php echo \$this->COLOR_BACK; ?>"><img src="<?php echo \$this->imgroot; ?>clear.png" width=10 height=1></td>
				<td valign=TOP width="99%">

	<?php

	}

	function footer(\$params) {

	?>

			<!-- end main body row -->


				</td>
				<td width=10 bgcolor="<?php echo \$this->COLOR_BACK; ?>"><img src="<?php echo \$this->imgroot; ?>clear.png" width=2 height=1></td>
			</tr>
			<tr>
				<td align=LEFT width=10"><img src="<?php echo \$this->imgroot; ?>tabs/bottomleft-inner.png" height=11 width=11></td>
				<td bgcolor="<?php echo \$this->COLOR_BACK; ?>"><img src="<?php echo \$this->imgroot; ?>clear.png" width="1" height=1></td>
				<td align=RIGHT width=10><img src="<?php echo \$this->imgroot; ?>tabs/bottomright-inner.png" height=11 width=11></td>
			</tr>
			</TABLE>

		<!-- end inner body row -->

		</td>
		<td width=10><img src="<?php echo \$this->imgroot; ?>clear.png" width=2 height=1></td>
	</tr>
	<tr>
		<td align=LEFT width=10><img src="<?php echo \$this->imgroot; ?>tabs/bottomleft.png" height=9 width=9></td>
		<td colspan=3><img src="<?php echo \$this->imgroot; ?>clear.png" width="1" height=1></td>
		<td align=RIGHT width=10><img src="<?php echo \$this->imgroot; ?>tabs/bottomright.png" height=9 width=9></td>
	</tr>
</TABLE>
</body>
</html>
<?php

	}


	/**
	 * boxTop() - Top HTML box
	 *
	 * @param   string  Box title
	 * @param   bool	Whether to echo or return the results
	 * @param   string  The box background color
	 */
	function boxTop(\$title) {
		return '
		<table cellspacing="0" cellpadding="1" width="100%" border="0" bgcolor="' .\$this->COLOR_HTMLBOX_TITLE.'">
		<tr><td>
			<table cellspacing="0" cellpadding="2" width="100%" border="0" bgcolor="'. \$this->COLOR_HTMLBOX_BACK.'">
				<tr bgcolor="'.\$this->COLOR_HTMLBOX_TITLE.'" align="center">
					<td colspan=2><SPAN class=titlebar>'.\$title.'</SPAN></td>
				</tr>
				<tr align=left>
					<td colspan=2>';
	}

	/**
	 * boxMiddle() - Middle HTML box
	 *
	 * @param   string  Box title
	 * @param   string  The box background color
	 */
	function boxMiddle(\$title) {
		return '
					</td>
				</tr>
				<tr bgcolor="'.\$this->COLOR_HTMLBOX_TITLE.'" align="center">
					<td colspan=2><SPAN class=titlebar>'.\$title.'</SPAN></td>
				</tr>
				<tr align=left bgcolor="'. \$this->COLOR_HTMLBOX_BACK .'">
					<td colspan=2>';
	}

	/**
	 * boxBottom() - Bottom HTML box
	 *
	 * @param   bool	Whether to echo or return the results
	 */
	function boxBottom() {
		return '
					</td>
				</tr>
			</TABLE>
		</td></tr>
		</TABLE><p>';
	}

}

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:

?>
FIN

	fi
fi
