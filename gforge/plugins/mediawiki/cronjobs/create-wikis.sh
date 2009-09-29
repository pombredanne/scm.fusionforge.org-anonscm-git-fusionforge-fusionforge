#! /bin/sh

tmp3=$(mktemp)
perl -e'require "/etc/gforge/local.pl"; print "*:*:$sys_dbname:$sys_dbuser:$sys_dbpasswd\n"' > $tmp3

projects=$(echo "SELECT g.unix_group_name from groups g, group_plugin gp, plugins p where g.group_id = gp.group_id and gp.plugin_id = p.plugin_id and p.plugin_name = 'mediawiki' ;" \
    | PGPASSFILE=/tmp/tmp.wOirlBjmDn /usr/bin/psql -U gforge gforge \
    | tail -n +3 \
    | grep '^ ')

wdprefix=/var/lib/gforge/plugins/mediawiki/wikidata

for project in $projects ; do
    if [ ! -d $wdprefix/$project/images ] ; then
	mkdir -p $wdprefix/$project/images
	chown www-data $wdprefix/$project/images
	touch $wdprefix/$project/LocalSettings.php
	filteredprojects="$filteredprojects $project"
    fi
done

projects=$filteredprojects

for project in $projects ; do
    schema=$(echo plugin_mediawiki_$project | sed s/-/_/g)

    tmp1=$(mktemp)
    tmp2=$(mktemp)

    if su -s /bin/sh postgres -c "/usr/bin/psql gforge" 1> $tmp1 2> $tmp2 <<-EOF \
        && [ "$(tail -n +2 $tmp1 | head -1)" = 'CREATE SCHEMA' ] ;
SET LC_MESSAGES = 'C' ;
CREATE SCHEMA $schema ;
ALTER SCHEMA $schema OWNER TO gforge;
EOF
    then
        rm -f $tmp1 $tmp2
    else
        echo "CREATE SCHEMA's STDOUT:"
        cat $tmp1
        echo "CREATE SCHEMA's STDERR:"
        cat $tmp2
        rm -f $tmp1 $tmp2 $tmp3
	exit 1
    fi

    tmp1=$(mktemp)
    tmp2=$(mktemp)

    if PGPASSFILE=$tmp3 /usr/bin/psql -U gforge gforge 1> $tmp1 2> $tmp2 <<-EOF \
        && true || [ "$(tail -1 $tmp1)" = 'COMMIT' ] ;
SET search_path = "$schema" ;
\i /usr/share/mediawiki/maintenance/postgres/tables.sql
CREATE TEXT SEARCH CONFIGURATION $schema.default ( COPY = pg_catalog.english );
COMMIT ;
EOF
    then
        rm -f $tmp1 $tmp2
    else
        echo "Database creation STDOUT:"
        cat $tmp1
        echo "Database creation STDERR:"
        cat $tmp2
        rm -f $tmp1 $tmp2 $tmp3
        exit 1
    fi

done

rm -f $tmp3
