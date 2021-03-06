<?php
/**
 * +----------------------------------------------------------------------+
 * | (c) Copyright IBM Corporation 2007.                                  |
 * | All Rights Reserved.                                                 |
 * +----------------------------------------------------------------------+
 * |                                                                      |
 * | Licensed under the Apache License, Version 2.0 (the "License"); you  |
 * | may not use this file except in compliance with the License. You may |
 * | obtain a copy of the License at                                      |
 * | http://www.apache.org/licenses/LICENSE-2.0                           |
 * |                                                                      |
 * | Unless required by applicable law or agreed to in writing, software  |
 * | distributed under the License is distributed on an "AS IS" BASIS,    |
 * | WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or      |
 * | implied. See the License for the specific language governing         |
 * | permissions and limitations under the License.                       |
 * +----------------------------------------------------------------------+
 * | Author: Matthew Peters                                               |
 * +----------------------------------------------------------------------+
 * $Id: SCA_HttpHeaderCatcher.php 234864 2007-05-03 18:23:57Z mfp $
 *
 * PHP Version 5
 *
 * @category SCA_SDO
 * @package  SCA_SDO
 * @author   Matthew Peters <mfp@php.net>
 * @license  Apache http://www.apache.org/licenses/LICENSE-2.0
 * @link     http://www.osoa.org/display/PHP/
 */

/**
 * This class exists so that we can unit test code with calls that want to emit
 * htpp headers. Unit tests that want to check that certain headers have been emitted
 * create an instance of ths class and pass it to SCA::setHttpHeaderCatcher
 * That will override the normal behaviour of SCA::sendHttpHeader so that this
 * class's catchHeader method will be called instead
 * Later the unit test can call atLeastOneHeaderContains to check whether any of the
 * headers contained a given string
 *
 * @category SCA_SDO
 * @package  SCA_SDO
 * @author   Matthew Peters <mfp@php.net>
 * @license  Apache http://www.apache.org/licenses/LICENSE-2.0
 * @link     http://www.osoa.org/display/PHP/
 */
class SCA_HttpHeaderCatcher
{
    public $headers = null;

    /**
     * Catch a specific HTTP Header
     *
     * @param string $header Header
     *
     * @return null
     */
    public function catchHeader($header) // NB cannot call this method catch as reserved name
    {
        $this->headers[] = $header;
    }

    /**
     * Search headers
     *
     * @param string $needle Header
     *
     * @return bool
     */
    public function atLeastOneHeaderContains($needle)
    {
        if ($this->headers === null) {
            return false;
        }

        foreach ($this->headers as $header) {
            if (strstr($header, $needle)) {
                return true;
            }
        }

        return false;
    }

}
