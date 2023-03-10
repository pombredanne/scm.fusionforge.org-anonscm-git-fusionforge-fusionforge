#!/bin/bash

#
# This file is part of FusionForge. FusionForge is free software;
# you can redistribute it and/or modify it under the terms of the
# GNU General Public License as published by the Free Software
# Foundation; either version 2 of the Licence, or (at your option)
# any later version.
#
# FusionForge is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License along
# with FusionForge; if not, write to the Free Software Foundation, Inc.,
# 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.

# First, make sure the distro is up-to-date
if [ -e /etc/debian_version ]; then
	aptitude update
	aptitude -y dist-upgrade
elif [[ ! -z `cat /etc/os-release | grep "SUSE"` ]]; then
	zypper update -y
else
	yum upgrade
fi

set -e

# Then update the checked-out sources of FusionForge
cd /usr/src/fusionforge/
git pull
