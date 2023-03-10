#! /usr/bin/php
<?php
/**
 * Copyright (C) 2010  Olaf Lenz
 * Copyright 2022, Franck Villaume - TrivialDev
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

/**
 * This script will automatically create mediawiki instances for
 * projects that do not yet have it.
 *
 * It is intended to be started in a cronjob.
 */

# TODO: How to use cronjob history?
# Required config variables:
#   src_path: the directory where the mediawiki sources are installed

require_once dirname(__FILE__) . '/../../../www/env.inc.php';
require_once $gfcommon.'include/pre.php';
require_once $gfcommon.'include/cron_utils.php';

$src_path = forge_get_config('src_path', 'mediawiki');
$master_path = forge_get_config('master_path', 'mediawiki');

$err = '';

# Get all projects that use the mediawiki plugin
$project_res = db_query_params ("SELECT g.unix_group_name from groups g, group_plugin gp, plugins p where g.group_id = gp.group_id and gp.plugin_id = p.plugin_id and p.plugin_name = $1;", array("mediawiki"));
if (!$project_res) {
	$err =  "Error: Database Query Failed: ".db_error();
	cron_debug($err);
	cron_entry('PLUGIN_MEDIAWIKI_CREATE_WIKIS',$err);
	exit;
}

# Loop over all projects that use the plugin
while ( $row = db_fetch_array($project_res) ) {
	$project = $row['unix_group_name'];
	$project_dir = forge_get_config('projects_path', 'mediawiki')
		. "/$project";
	cron_debug("Checking $project...");

	$res = db_query_params("SET search_path=public", array());
	$res = db_query_params('DELETE FROM plugin_mediawiki_interwiki WHERE iw_prefix=$1', array($project));
	$url = util_make_url('/plugins/mediawiki/wiki/' . $project . '/index.php/$1');
	$res = db_query_params('INSERT INTO plugin_mediawiki_interwiki VALUES ($1, $2, 1, 0)',
			       array($project,
				     $url));

	// Create the project directory if necessary
	if (is_dir($project_dir)) {
		cron_debug("  Project dir $project_dir exists, so I assume the project already exists.");
	} else {
		// Create the DB
		$schema = "plugin_mediawiki_$project";
		// Sanitize schema name
		$schema = strtr($schema, "-", "_");

		db_begin();

		cron_debug("  Creating schema $schema.");
		$res = db_query_params("CREATE SCHEMA $schema", array());
		if (!$res) {
			$err =  "Error: Schema Creation Failed: " .
				db_error();
			cron_debug($err);
			db_rollback();
			cron_entry('PLUGIN_MEDIAWIKI_CREATE_WIKIS',$err);
			exit;
		}

		cron_debug("  Creating mediawiki database.");
		$table_file_generated = "$src_path/maintenance/postgres/tables-generated.sql";
		if (file_exists($table_file_generated)) {
			db_query_params("SET search_path=$schema", array());
			$res = db_query_from_file($table_file_generated);
			if (!$res) {
				db_rollback();
				$err =  "Error: Mediawiki Database Creation Failed: " . db_error();
				cron_debug($err);
				db_query_params("SET search_path=public", array());
				cron_entry('PLUGIN_MEDIAWIKI_CREATE_WIKIS',$err);
				exit;
			}
		}

		$table_file = "$src_path/maintenance/postgres/tables.sql";
		if (!file_exists($table_file)) {
			db_rollback();
			$err =  "Error: Couldn't find Mediawiki Database Creation File $table_file!";
			cron_debug($err);
			db_query_params("SET search_path=public", array());
			cron_entry('PLUGIN_MEDIAWIKI_CREATE_WIKIS',$err);
			exit;
		}
		db_query_params("SET search_path=$schema", array());
		$res = db_query_from_file($table_file);
		if (!$res) {
			db_rollback();
			$err =  "Error: Mediawiki Database Creation Failed: " . db_error();
			cron_debug($err);
			db_query_params("SET search_path=public", array());
			cron_entry('PLUGIN_MEDIAWIKI_CREATE_WIKIS',$err);
			exit;
		}

		$table_file_updatekeys = "$src_path/maintenance/postgres/update-keys.sql";
		if (file_exists($table_file_updatekeys)) {
			db_query_params("SET search_path=$schema", array());
			$res = db_query_from_file($table_file_updatekeys);
			if (!$res) {
				db_rollback();
				$err =  "Error: Mediawiki Database Creation Failed: " . db_error();
				cron_debug($err);
				db_query_params("SET search_path=public", array());
				cron_entry('PLUGIN_MEDIAWIKI_CREATE_WIKIS',$err);
				exit;
			}
		}

		$res = db_query_params("CREATE TEXT SEARCH CONFIGURATION $schema.default ( COPY = pg_catalog.english )", array());
		if (!$res) {
			db_rollback();
			$err =  "Error: DB Query Failed: " . db_error();
			cron_debug($err);
			db_query_params("SET search_path=public", array());
			cron_entry('PLUGIN_MEDIAWIKI_CREATE_WIKIS',$err);
			exit;
		}

		if (!db_commit()) {
			db_rollback();
			$err =  "Error: DB Commit Failed: " . db_error();
			cron_debug($err);
			db_query_params("SET search_path=public", array());
			cron_entry('PLUGIN_MEDIAWIKI_CREATE_WIKIS',$err);
			exit;
		}

		cron_debug("  Creating project dir $project_dir.");
		mkdir($project_dir, 0775, true);
		chmod($project_dir, 0775);

		$f = fopen("$project_dir/ProjectSettings.php", "w");
		fwrite($f, '<?php
// Insert your project-local configuration here
');
		fclose($f);
		chmod("$project_dir/ProjectSettings.php", 0775);

		$mwwrapper = forge_get_config('source_path')."/plugins/mediawiki/bin/mw-wrapper.php" ;
		$dumpfile = forge_get_config('config_path')."/plugins/mediawiki/initial-content.xml" ;

		system ("$mwwrapper $project update.php --quick > /dev/null") ;

		if (file_exists ($dumpfile)) {
			system ("$mwwrapper $project importDump.php $dumpfile") ;
			system ("$mwwrapper $project rebuildrecentchanges.php") ;
		}
	}
}

db_query_params("SET search_path=public", array());
cron_entry('PLUGIN_MEDIAWIKI_CREATE_WIKIS', $err);
