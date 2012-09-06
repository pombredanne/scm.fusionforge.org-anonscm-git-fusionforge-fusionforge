<?php
/*
 * Copyright (C) 2009 Olivier Berger, Institut TELECOM
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

require_once 'PHPUnit/Framework/TestCase.php';

class SoapChecksProcess extends PHPUnit_Framework_TestCase
{
  // Check that the SOAP API server corresponds to a hostname that
  // resolves otherwise, phpunit fails with error code 255 on
  // SoapClient instanciation with WSDL ... strange
  // This assumes that upon failure, the hostname is returned instead
  // of the IP.
  function testHostnameResolves()
  {

    $ip = gethostbyname(HOST);
    $this->assertNotEquals(HOST, $ip);

  }

  // This checks that the login will be able to proceed as the test
  // environment was configured the right way
  function testExistingUserPasswordConfigured()
  {

    $this->assertNotEquals('xxxxx', FORGE_ADMIN_PASSWORD);

  }

  // This checks that the WSDL URL looks fine
  function testWSDLUrl()
  {
    $this->assertRegExp('/^http.?:\/\//', WSDL_URL);
  }

}
