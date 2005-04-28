#!/usr/bin/perl
#/**
#  *
#  * stats_cvs.pl - NIGHTLY SCRIPT
#  *
#  * Recurses through the /cvsroot directory tree and parses each projects
#  * '~/CVSROOT/history' file, and create and fill the sql table with 
#  * modified, and added to each project.
#  *
#  * @version   $Id$
#  *
#  */

# For the files
#use strict;
use Time::Local;
use POSIX qw( strftime );

# For the database
use DBI;
require("/usr/lib/gforge/lib/include.pl");
my $cvsroot = "/var/lib/gforge/chroot/cvsroot";
my $verbose = 1;
$|=0 if $verbose;
$|++;

sub drop_tables {
    db_drop_table_if_exists ("deb_cvs_dump") ;
    db_drop_table_if_exists ("deb_cvs_group") ;
    db_drop_table_if_exists ("deb_cvs_group_user") ;
}

sub create_dump_table {
	my ($sql);
	$sql = "CREATE TABLE deb_cvs_dump (
		type char(1),
		year integer NOT NULL,
		month integer NOT NULL,
		day integer NOT NULL,
		time integer NOT NULL,
		cvsuser text,
		cvsgroup text
	)";
	$dbh->do( $sql );
}

sub dump_history {
	my ($year, $month, $day);
	
	print "Running tree at $cvsroot/\n";
	
	chdir( "$cvsroot" ) || die("Unable to make $cvsroot the working directory.\n");
	
	$old_date = time() - 28 * 86400 ;
	foreach $group ( glob("*") ) {
		next if ( ! -d "$group" );
		my ($cvs_co, $cvs_commit, $cvs_add, %usr_commit, %usr_add );
		print "Parsing $group/\n";
	
		open(HISTORY, "< $cvsroot/$group/CVSROOT/history") or print "E::Unable to open history for $group\n";
		while ( <HISTORY> ) {
			my ($time_parsed, $type, $cvstime, $user, $curdir, $module, $rev, $file );
	 
			## Split the cvs history entry into it's 6 fields.
			($cvstime,$user,$curdir,$module,$rev,$file) = split(/\|/, $_, 6 );
	
			## log modified  $type eq "M" 
			## log added  $type eq "A"
			## log others  $type neq "A"  neq "M"
			$type = substr($cvstime, 0, 1);
			$time_parsed = hex( substr($cvstime, 1, 8) );
			if ( ($time_parsed >= $old_date) && ($user ne 'anonymous')
				&& ( ($type eq '0') || ($type eq 'M') || ($type eq 'A') )){
				$year	= strftime("%Y", gmtime( $time_parsed ) );
				$month	= strftime("%m", gmtime( $time_parsed ) );
				$day	= strftime("%d", gmtime( $time_parsed ) );
				$sql = "INSERT INTO deb_cvs_dump 
				(type,year,month,day,time,cvsuser,cvsgroup)
				VALUES ('$type','$year','$month','$day','$time_parsed','$user','$group')";
			
       				# print "$sql\n" if $verbose;
				$dbh->do( $sql );
			}
		}
		close( HISTORY );
	}
}

sub parse_history {
	my ($sql);
# CVS doc says the meaning of the code letters.
#
#Letter          Meaning
#======          =========================================================
#O               Checkout
#T               Tag
#F               Release
#W               Update (no user file, remove from entries file)
#U               Update (file overwrote unmodified user file)
#G               Update (file was merged successfully into modified user file)
#C               Update (file was merged, but conflicts w/ modified user file)
#M               Commit (from modified file)
#A               Commit (an added file)
#R               Commit (the removal of a file)
#E               Export
	$sql = "
	CREATE TABLE deb_cvs_group_user AS
        	SELECT agg.cvsgroup,agg.cvsuser,agg.year,agg.month,agg.day,agg.total AS total,m.modified AS modified,a.added AS added,o.others AS others
        	FROM (
        		SELECT cvsgroup,cvsuser,year,month,day,COUNT(*) AS total
        		FROM deb_cvs_dump
        		GROUP BY year,month,day,cvsgroup,cvsuser
		) agg
		LEFT JOIN (
        	SELECT cvsgroup,cvsuser,year,month,day,COUNT(*) AS modified
        	FROM deb_cvs_dump
		WHERE type='M'
        	GROUP BY year,month,day,cvsgroup,cvsuser
		) m USING (cvsgroup,cvsuser,year,month,day)
		LEFT JOIN (
        	SELECT cvsgroup,cvsuser,year,month,day,COUNT(*) AS added
        	FROM deb_cvs_dump
		WHERE type='A'
        	GROUP BY year,month,day,cvsgroup,cvsuser
		) a USING (cvsgroup,cvsuser,year,month,day)
		LEFT JOIN (
        	SELECT cvsgroup,cvsuser,year,month,day,COUNT(*) AS others
        	FROM deb_cvs_dump
		WHERE type!='A' and type!='M' 
        	GROUP BY year,month,day,cvsgroup,cvsuser
		) o USING (cvsgroup,cvsuser,year,month,day)
	";
	$dbh->do( $sql );
}

sub load_groupids {
	my ($sql,$res) ;
	$sql = "SELECT group_id, unix_group_name from groups";
        $res = $dbh->prepare($sql);
        $res->execute();
        while ( my ($group_id, $group_name) = $res->fetchrow()) {
		# print "$group_name -> $group_id\n" ;
		$gids{$group_name} = $group_id ;
        }
}


sub setup_stats {
	my ($sql,$res,$temp);
	$dbh->do("delete from stats_cvs_group");
	$sql = "SELECT * FROM deb_cvs_group_user order by year, month, day";
	# print "$sql\n" if $verbose;
	$res = $dbh->prepare($sql);
	$res->execute();
	while ( my ($cvsgroup, $cvsuser, $year, $month, $day, $total, $modified, $added, $others) = $res->fetchrow()) {
		# print "$cvsgroup $cvsuser $year $month $day $total=$modified+$added+$others\n";
	}
	print "-----------------------------------------------------\n";
	print "cvsgroup(id):yearmonth:day:modified:added:others\n";
	print "-----------------------------------------------------\n";
	$sql = "SELECT cvsgroup, SUM(modified), SUM(added), SUM(others), year||month AS ym,day FROM deb_cvs_group_user group by cvsgroup,ym,day order by cvsgroup,ym,day" ;
	$res = $dbh->prepare($sql);
	$res->execute();
	while ( my ($cvsgroup, $modified, $added, $others, $ym, $day) = $res->fetchrow()) {
		$modified = 0 unless defined $modified ;
		$added = 0 unless defined $added ;
		$others = 0 unless defined $others ;
		$ym =~ s/^(....)(.)$/$1-0-$2/ ;
		$ym =~ s/-//g ;
		print "$cvsgroup($gids{$cvsgroup}):$ym:$day:$modified:$added:$others\n";
		$dbh->do("INSERT INTO stats_cvs_group (month, day, group_id, checkouts, commits, adds) VALUES ($ym, $day, $gids{$cvsgroup}, $others, $modified, $added)") ;
	}
	print "-----------------------------------------------------\n";
}

#############
# main      #
#############
&db_connect;
&drop_tables;
&create_dump_table;
&load_groupids;
&dump_history;
&parse_history;
&setup_stats;
&drop_tables;

