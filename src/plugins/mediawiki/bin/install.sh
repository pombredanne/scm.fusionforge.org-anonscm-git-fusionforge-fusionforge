#!/bin/bash -e
# MediaWiki post-install

source_path=$(forge_get_config source_path)
data_path=$(forge_get_config data_path)
plugindir=$(forge_get_config plugins_path)/mediawiki

mediawikidir=$(find /usr/share -type d -name 'mediawiki' | grep -E -v 'doc|extensions|resources|vendor|selinux')
# Debian: /usr/share/mediawiki/
# CentOS7: /usr/share/mediawiki/
# OpenSUSE Leap 15: /usr/share/php/mediawiki/

upgrade_mediawikis () {
	# Upgrade Mediawiki database schemas
	$(forge_get_config binary_path)/list-projects-using-plugin.php mediawiki | while read i ; do
		$(forge_get_config plugins_path)/mediawiki/bin/mw-wrapper.php $i update.php --quick
	done
}

case "$1" in
	configure)
		# Default value for mediawiki.ini:src_path:
		ln -nfs $mediawikidir                      $plugindir/src_path

		# Symlinks for integration in FusionForge web frontend
		ln -nfs $mediawikidir/api.php              $plugindir/www/
		ln -nfs $mediawikidir/extensions           $plugindir/www/
		ln -nfs $mediawikidir/img_auth.php         $plugindir/www/
		ln -nfs $mediawikidir/includes             $plugindir/www/
		ln -nfs $mediawikidir/index.php            $plugindir/www/
		ln -nfs $mediawikidir/languages            $plugindir/www/
		ln -nfs $mediawikidir/load.php             $plugindir/www/
		ln -nfs $mediawikidir/maintenance          $plugindir/www/
		ln -nfs $mediawikidir/opensearch_desc.php  $plugindir/www/
		ln -nfs $mediawikidir/profileinfo.php      $plugindir/www/
		ln -nfs $mediawikidir/thumb.php            $plugindir/www/
		if [ -d $mediawikidir/vendor ]; then
			ln -nfs $mediawikidir/vendor       $plugindir/www/
		fi

		ln -nfs $mediawikidir/skins $plugindir/www/
		# specific workaround for debian CamelCase
		if [ -e $mediawikidir/skins/MonoBook ];then
			ln -nfs $mediawikidir/skins/MonoBook $mediawikidir/skins/monobook
		fi
		ln -nfs $mediawikidir/skins/monobook/headbg.jpg $source_path/www/themes/css/mw-headbg.jpg

		ln -nfs $mediawikidir $data_path/plugins/mediawiki/master
		rm -f $mediawikidir/skins/FusionForge.php //remove the old autoload mechanism
		ln -nfs $plugindir/mediawiki-skin $mediawikidir/skins/
		ln -nfs $plugindir/MonoBookFusionForge125 $mediawikidir/skins/
		ln -nfs $plugindir/MonoBookFusionForge125/wiki.png $plugindir/www/
	;;
	triggered)
		case $2 in
			/usr/share/mediawiki*) upgrade_mediawikis ;;
		esac
	;;
	remove)
		find $plugindir/www/ -type l -print0 | xargs -r0 rm
		rm -f $source_path/www/themes/css/mw-headbg.jpg
		rm -f $data_path/plugins/mediawiki/master
		rm -f $mediawikidir/skins/FusionForge.php //remove the old autoload mechanism
		rm -f $mediawikidir/skins/mediawiki-skin
		rm -f $mediawikidir/skins/MonoBookFusionForge125
	;;
	*)
		echo "Usage: $0 {configure|triggered|remove}"
		exit 1
esac
