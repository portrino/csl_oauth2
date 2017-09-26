<?php
namespace Causal\CslOauth2\Aspect;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Axel Boeswetter <boeswetter@portrino.de>, portrino GmbH
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use Causal\CslOauth2\Controller\ServerController;
use TYPO3\CMS\Core\Database\DatabaseConnection;

/**
 * Class StaticInfoTablesAspect
 *
 * @package Causal\CslOauth2\Aspect
 */
class StaticInfoTablesAspect
{

    /**
     * @var DatabaseConnection
     */
    protected $db;

    public function __construct()
    {
        $this->db = $GLOBALS['TYPO3_DB'];
    }

    /**
     * @param array $responseParams
     * @param ServerController $pObj
     */
    public function convertStaticInfoTablesUidToIso2Code(&$responseParams, &$pObj)
    {
        if (is_array($responseParams)
            && array_key_exists('country', $responseParams)
            && is_numeric($responseParams['country'])
        ) {
            $iso2 = current($this->db->exec_SELECTgetSingleRow(
                'cn_iso_2',
                'static_countries',
                'uid = ' . (int)$responseParams['country']
            ));
            if (!empty($iso2) && strlen($iso2) === 2) {
                $responseParams['country'] = $iso2;
            }
        }
    }
}
