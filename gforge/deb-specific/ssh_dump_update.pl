#!/usr/bin/perl
#
# $Id$
#
# ssh_dump.pl - Script to suck data outta the database to be processed by ssh_create.pl
#
use DBI;

require("/usr/lib/gforge/lib/include.pl");  # Include all the predefined functions

my $verbose=0;
my $ssh_array = ();

&db_connect;

$dbh->{AutoCommit} = 0;

# Dump the Table information
$query = "SELECT user_name,unix_uid,authorized_keys FROM users WHERE authorized_keys != '' AND status !='D'";
$c = $dbh->prepare($query);
$c->execute();
while(my ($username, $unix_uid, $ssh_key) = $c->fetchrow()) {
	$new_list = "$username:$unix_uid:$ssh_key\n";
	push @ssh_array, $new_list;
}

my ($username, $ssh_keys, $ssh_dir);

if($verbose){print("\n\n	Processing Users\n\n")};
while ($ln = pop(@ssh_array)) {
	($username, $uid, $ssh_key) = split(":", $ln);

	$ssh_key =~ s/\#\#\#/\n/g;
	$username =~ tr/[A-Z]/[a-z]/;
	$uid += $uid_add;

	push @user_authorized_keys, $ssh_key . "\n";

	$ssh_dir = "$homedir_prefix$username/.ssh";

	if (! -d $ssh_dir) {
		mkdir $ssh_dir, 0755;
		system("chown $uid:$uid $ssh_dir");
	}

	if($verbose){print("Writing authorized_keys for $username: ")};

	write_array_file("$ssh_dir/authorized_keys", @user_authorized_keys);
	system("chown $uid:$uid $homedir_prefix$username");
	system("chown $uid:$uid $ssh_dir");
	system("chmod 0644 $ssh_dir/authorized_keys");
	system("chown $uid:$uid $ssh_dir/authorized_keys");

	if($verbose){print ("Done\n")};

	undef @user_authorized_keys;
}
undef @ssh_array;

### Phase 2: remove the files when needed

# Dump the Table information
$query = "SELECT user_name,unix_uid FROM users WHERE authorized_keys = '' OR authorized_keys IS NULL OR status = 'D'";
$c = $dbh->prepare($query);
$c->execute();
while(my ($username, $unix_uid, $ssh_key) = $c->fetchrow()) {
	$new_list = "$username:$unix_uid\n";
	push @ssh_array, $new_list;
}

if($verbose){print("\n\n	Processing Users\n\n")};
while ($ln = pop(@ssh_array)) {
	($username, $uid) = split(":", $ln);

	$username =~ tr/[A-Z]/[a-z]/;
	$uid += $uid_add;

	$ssh_dir = "$homedir_prefix$username/.ssh";

	if (-d $ssh_dir) {
	    if($verbose){print("Resetting authorized_keys for $username: ")};
	    
	    unlink("$ssh_dir/authorized_keys");
	    system("chown $uid:$uid $homedir_prefix$username");
	    system("chown $uid:$uid $ssh_dir");

	    if($verbose){print ("Done\n")};
	}
}
