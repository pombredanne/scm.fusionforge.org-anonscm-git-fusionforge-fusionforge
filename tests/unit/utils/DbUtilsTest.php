<?php
/**
 * Copyright FusionForge Team
 *
 * This file is part of FusionForge.
 *
 * FusionForge is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published
 * by the Free Software Foundation; either version 2 of the License,
 * or (at your option) any later version.
 *
 * FusionForge is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

@include_once '/usr/local/share/php/vendor/autoload.php';
require_once dirname(__FILE__) . '/../../../src/common/include/database-pgsql.php' ;

/**
 * Simple tests for the utils library.
 *
 * @package   Tests
 * @author    Alain Peyrat <aljeux@free.fr>
 * @copyright 2009 Alain Peyrat. All rights reserved.
 * @license   GPL License
 */
class DbUtilsTest extends PHPUnit\Framework\TestCase {
	/**
	 * test the validate_hostname function.
	 */
	public function testConstructQPA() {
		$qpa = db_construct_qpa() ;
		$this->assertEquals('', $qpa[0]) ;

		$qpa = db_construct_qpa($qpa, 'SELECT foo FROM bar') ;
		$this->assertEquals('SELECT foo FROM bar', $qpa[0]) ;
		$this->assertEquals(0, count($qpa[1])) ;
		$this->assertEquals(0, $qpa[2]) ;

		$qpa = db_construct_qpa($qpa, ' WHERE name = $1', array ('nrst')) ;
		$this->assertEquals('SELECT foo FROM bar WHERE name = $1', $qpa[0]) ;

		$qpa = db_construct_qpa($qpa, ' AND mail = $1 AND addr LIKE $2', array ('auie@foobar',
									'bépo')) ;
		$this->assertEquals('SELECT foo FROM bar WHERE name = $1 AND mail = $2 AND addr LIKE $3', $qpa[0]) ;

		$qpa = db_construct_qpa($qpa, ' AND quux = $1', array ('jldv')) ;
		$this->assertEquals('SELECT foo FROM bar WHERE name = $1 AND mail = $2 AND addr LIKE $3 AND quux = $4', $qpa[0]) ;
		$this->assertEquals(array ('nrst', 'auie@foobar', 'bépo', 'jldv'), $qpa[1]) ;
		$this->assertEquals(4, $qpa[2]) ;

		$qpa = db_construct_qpa($qpa, ' AND long1 = $1 AND long2 = $2 AND long3 = $3 AND long4 = $4 AND long5 = $5 AND long6 = $6 AND long7 = $7 AND long8 = $8 AND long9 = $9 AND long10 = $10 AND long11 = $11 AND long12 = $12', array (1,2,3,4,5,6,7,8,9,10,11,12)) ;
		$this->assertEquals('SELECT foo FROM bar WHERE name = $1 AND mail = $2 AND addr LIKE $3 AND quux = $4 AND long1 = $5 AND long2 = $6 AND long3 = $7 AND long4 = $8 AND long5 = $9 AND long6 = $10 AND long7 = $11 AND long8 = $12 AND long9 = $13 AND long10 = $14 AND long11 = $15 AND long12 = $16', $qpa[0]) ;

		$this->assertEquals(array ('nrst', 'auie@foobar', 'bépo', 'jldv', 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12), $qpa[1]) ;
		$this->assertEquals(16, $qpa[2]) ;
	}
}

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
