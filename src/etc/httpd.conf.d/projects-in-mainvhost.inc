# This is used instead of *.forgename vhosts only

# Project home pages are in a virtual /www/<group> location
AliasMatch ^/www/([^/]*)/(.*) {core/groupdir_prefix}/$1/htdocs/$2
# disabled for CVE-2014-6275
# Only enable it if you know what you are doing, by default all scripts run as Apache
#ScriptAliasMatch ^/([^/]*)/cgi-bin/(.*) {core/groupdir_prefix}/$1/cgi-bin/$2
<Directory {core/groupdir_prefix}>
  Options Indexes FollowSymlinks
  AllowOverride All
  
  Include {core/config_path}/httpd.conf.d/auth-projects.inc
</Directory>