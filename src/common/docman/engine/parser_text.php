#!/usr/bin/php
<?php
/**
 * FusionForge Documentation Manager Search Engine
 *
 * Copyright 2005, Fabio Bertagnin
 * Copyright 2009-2011, Franck Villaume - Capgemini
 * Copyright 2012, Franck Villaume - TrivialDev
 * http://fusionforge.org
 *
 * This file is part of FusionForge. FusionForge is free software;
 * you can redistribute it and/or modify it under the terms of the
 * GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the Licence, or (at your option)
 * any later version.
 *
 * FusionForge is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with FusionForge; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

require dirname(__FILE__).'/../../include/env.inc.php';

require_once $gfcommon.'docman/engine/parser_text.inc.php';

if ($argc != 2) {
	echo 'Usage : parser_text.php <filename>'."\n";
	exit(1);
}

$fichin = $argv[1];
if (!is_file($fichin)) {
	exit(1);
}
echo parser_text($fichin);

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
