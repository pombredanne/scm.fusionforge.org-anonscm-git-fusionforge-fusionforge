#!/bin/sh
. tests/scripts/common-functions
. tests/scripts/common-vm

get_config

set -x

export FORGE_HOME=/usr/share/gforge
export HOST=$1
export FILTER="RPMCentosTests.php"

case $HOST in
    centos5.local)
	VM=centos5
	;;
    centos6.local)
	VM=centos6
	;;
    *)
	VM=centos6
	;;
esac	

prepare_workspace

$(dirname $0)/destroy_vm $HOST
$(dirname $0)/start_vm $HOST

# BUILD FUSIONFORGE REPO
echo "Build FUSIONFORGE REPO in $BUILDRESULT"
make -f Makefile.rh BUILDRESULT=$BUILDRESULT RPM_TMP=$RPM_TMP fusionforge dist

# TRANSFER FUSIONFORGE REPO
rsync -a --delete $BUILDRESULT/ root@$HOST:/root/fusionforge_repo/

# SETUP FUSIONFORGE REPO
echo "Installing FUSIONFORGE REPO"
ssh root@$HOST "cat > /etc/yum.repos.d/FusionForge.repo" <<-EOF
[FusionForge]
name = Red Hat Enterprise \$releasever - fusionforge.org
baseurl = file:///root/fusionforge_repo/noarch/
enabled = 1
protect = 0
gpgcheck = 0
EOF

setup_dag_repo $@
case $VM in
    centos5|centos6)
	setup_epel_repo $@
	ssh root@$HOST "yum -y --enablerepo=epel install cronolog"
	ssh root@$HOST "yum -y --enablerepo=epel install php-phpunit-PHPUnit-Selenium"
	;;
esac

ssh root@$HOST "yum install -y rsync"

setup_redhat_3rdparty_repo
case $VM in
    centos5)
	ssh root@$HOST "yum -y --enablerepo=epel install epel-release"
	ssh root@$HOST "rpm -i http://rpms.famillecollet.com/enterprise/remi-release-5.rpm"
	ssh root@$HOST "yum -y --enablerepo=remi install php-phpunit-PHPUnit"
	;;
esac

sleep 5
ssh root@$HOST "FFORGE_DB=$DB_NAME FFORGE_USER=gforge FFORGE_ADMIN_USER=$FORGE_ADMIN_USERNAME FFORGE_ADMIN_PASSWORD=$FORGE_ADMIN_PASSWORD export FFORGE_DB FFORGE_USER FFORGE_ADMIN_USER FFORGE_ADMIN_PASSWORD; yum install -y --skip-broken fusionforge fusionforge-plugin-scmsvn fusionforge-plugin-online_help fusionforge-plugin-extratabs fusionforge-plugin-authldap fusionforge-plugin-scmgit fusionforge-plugin-blocks"

config_path=$(ssh root@$HOST forge_get_config config_path)

ssh root@$HOST "(echo [core];echo use_ssl=no) > $config_path/config.ini.d/zzz-buildbot.ini"
ssh root@$HOST "(echo [moinmoin];echo use_frame=no) >> $config_path/config.ini.d/zzz-buildbot.ini"
ssh root@$HOST "(echo [mediawiki];echo unbreak_frames=yes) >> $config_path/config.ini.d/zzz-buildbot.ini"
ssh root@$HOST "su - postgres -c \"pg_dumpall\" > /root/dump"
# Install a fake sendmail to catch all outgoing emails.
ssh root@$HOST "perl -spi -e s#/usr/sbin/sendmail#$FORGE_HOME/tests/scripts/catch_mail.php# $config_path/config.ini.d/defaults.ini"

echo "Stop cron daemon"
ssh root@$HOST "service crond stop" || true

# Install selenium
ssh root@$HOST "yum -y install selenium"

# Install selenium tests
ssh root@$HOST "[ -d $FORGE_HOME ] || mkdir -p $FORGE_HOME"
rsync -a --delete tests/ root@$HOST:$FORGE_HOME/tests/

# Transfer hudson config
ssh root@$HOST "cat > $FORGE_HOME/tests/config/phpunit" <<-EOF
HUDSON_URL=$HUDSON_URL
JOB_NAME=$JOB_NAME
EOF

# Run tests
retcode=0
echo "Run phpunit test on $HOST in $FORGE_HOME"
ssh root@$HOST "$FORGE_HOME/tests/func/vncxstartsuite.sh $FILTER"
retcode=$?
rsync -av root@$HOST:/var/log/ $WORKSPACE/reports/
scp root@$HOST:/tmp/gforge-*.log $WORKSPACE/reports/

$(dirname $0)/stop_vm $HOST

exit $retcode
