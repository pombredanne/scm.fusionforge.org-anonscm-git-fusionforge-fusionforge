#! /bin/sh
# Configure Subversion

set -e

if [ $(id -u) != 0 ] ; then
    echo "You must be root to run this"
fi

scmsvn_serve_root=$(forge_get_config serve_root scmsvn)

case "$1" in
    configure)
        echo "Modifying inetd for Subversion server"
	if [ -x /usr/sbin/update-inetd ]; then
	    update-inetd --remove svn || true
            update-inetd --add  "svn stream tcp nowait.400 scm-gforge /usr/bin/svnserve svnserve -i -r $scmsvn_serve_root"
	else
	    echo "TODO: xinetd support"
	fi

	# Work-around memory leak in mod_dav_svn
	for conf in /etc/apache2/apache2.conf /etc/httpd/conf/httpd.conf \
	    /etc/apache2/server-tuning.conf; do
	    if [ -e $conf ] && type augtool >/dev/null 2>&1; then
		val=$(augtool "print /files$conf/IfModule[arg='mpm_worker_module' or arg='worker.c']/directive[.='MaxRequestsPerChild']/arg" | sed 's/^.*= "\(.*\)"/\1/')
		if [ "$val" = "0" ]; then
		    augtool --autosave "set /files$conf/IfModule[arg='mpm_worker_module' or arg='worker.c']/directive[.='MaxRequestsPerChild']/arg 5000"
		fi
	    fi
	done
	;;

    remove)
	update-inetd --remove svn || true
	;;

    *)
	echo "Usage: $0 {configure|remove}"
	exit 1
esac
