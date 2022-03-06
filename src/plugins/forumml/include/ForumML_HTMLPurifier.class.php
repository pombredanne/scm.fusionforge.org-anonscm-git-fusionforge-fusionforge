<?php
/**
 * Copyright (c) STMicroelectronics, 2007. All Rights Reserved.
 * Copyright 2022, Franck Villaume - TrivialDev
 *
 * Originally written by Mohamed CHAARI, 2007.
 *
 * This file is a part of codendi.
 *
 * codendi is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * codendi is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

class ForumML_TextSanitizer extends TextSanitizer {

	/**
	* Perform HTML purification depending of level purification required and create links.
	*/
	function purifyml($html, $level=0, $groupId=0) {
		return util_make_links($thi->purify($html));
	}
}
