#! /bin/sh
# Install FusionForge from source

# Authors :
#  Roland Mas
#  Olivier BERGER <olivier.berger@it-sudparis.eu>
#  Sylvain Beucler

#set -x
set -e
export DEBIAN_FRONTEND=noninteractive

# fusionforge-plugin-scmbzr depends on loggerhead (>= 1.19~bzr477~),
# but wheezy only has 1.19~bzr461-1, so we need to manually "Backport"
# a more recent dependency
if grep ^7 /etc/debian_version >/dev/null && ! dpkg-query -s loggerhead >/dev/null 2>&1 ; then
    # install loggerhead with its dependencies
    # we need gdebi to make sure dependencies are installed too (simple dpkg -i won't)
    apt-get -y install gdebi-core wget
    wget -c http://snapshot.debian.org/archive/debian/20121107T152130Z/pool/main/l/loggerhead/loggerhead_1.19%7Ebzr477-1_all.deb
    gdebi --non-interactive loggerhead_1.19~bzr477-1_all.deb
fi

# Install locales-all which is a Recommends and not a Depends
if ! dpkg -l locales-all | grep -q ^ii ; then
    apt-get -y install locales-all
fi

# Install FusionForge packages
apt-get update
apt-get install -y make gettext confget php5-cli php5-pgsql php-htmlpurifier \
    apache2 postgresql \
    subversion python-subversion \
    mediawiki \
    python-moinmoin libapache2-mod-wsgi python-psycopg2
# TODO: replace python-subversion with non-bundled viewvc

cd /usr/src/fusionforge/src/
make
make install-base install-shell
make install-plugin-scmsvn install-plugin-blocks \
    install-plugin-mediawiki install-plugin-moinmoin \
    install-plugin-online_help
# adapt .ini configuration in /etc/fusionforge/config.ini.d/
make post-install-base post-install-plugin-scmsvn post-install-plugin-blocks \
    post-install-plugin-mediawiki post-install-plugin-moinmoin \
    post-install-plugin-online_help

# Dump clean DB
if [ ! -e /root/dump ]; then $(dirname $0)/../../func/db_reload.sh --backup; fi
