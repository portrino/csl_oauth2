<?php
namespace Causal\CslOauth2\Utility;

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
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class LoginFrontendUserUtility
 *
 * @package Causal\CslOauth2\Utility
 */
class LoginFrontendUserUtility
{

    /**
     * @var \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController
     */
    protected $typoScriptFrontendController = null;

    /**
     * @var \TYPO3\CMS\Core\Database\DatabaseConnection
     */
    protected $database = null;

    /**
     * @var \TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication
     * @inject
     */
    protected $frontendUserAuthentication = null;

    /**
     * initializeObject
     */
    public function initializeObject()
    {
        $this->database = $GLOBALS['TYPO3_DB'];
        $this->typoScriptFrontendController = $GLOBALS['TSFE'];
    }

    /**
     * Login user
     *
     * @param int $userUid
     *
     * @return void
     */
    public function loginUser($userUid)
    {
        if ((int)$userUid > 0) {
            $userData = $this->fetchUserData((int)$userUid);
            $this->initFrontendUser($userData);

            $this->typoScriptFrontendController->fe_user->createUserSession($userData);
            $this->typoScriptFrontendController->initUserGroups();
            $this->typoScriptFrontendController->setSysPageWhereClause();
        }
    }


    /**
     * Initialize fe_user object
     *
     * @param array $userdata
     *
     * @return void
     */
    protected function initFrontendUser(array $userdata)
    {
        $this->frontendUserAuthentication->lockIP = $GLOBALS['TYPO3_CONF_VARS']['FE']['lockIP'];
        $this->frontendUserAuthentication->checkPid = $GLOBALS['TYPO3_CONF_VARS']['FE']['checkFeUserPid'];
        $this->frontendUserAuthentication->lifetime = (int)$GLOBALS['TYPO3_CONF_VARS']['FE']['lifetime'];

        // List of pid's acceptable
        $this->frontendUserAuthentication->checkPid_value = $this->database->cleanIntList(GeneralUtility::_GP('pid'));


        if ($GLOBALS['TYPO3_CONF_VARS']['FE']['dontSetCookie']) {
            $this->frontendUserAuthentication->dontSetCookie = 1;
        }
        /* bug fix social register with direct login (A.Wuttig 14.10.2014)
         * if not set the user won´t logged in automatically
         */
        $this->frontendUserAuthentication->dontSetCookie = 0;

        $this->frontendUserAuthentication->start();
        $this->frontendUserAuthentication->unpack_uc('');
        $this->frontendUserAuthentication->fetchSessionData();

        $userdata[$this->frontendUserAuthentication->lastLogin_column] = $GLOBALS['EXEC_TIME'];
        $userdata['is_online'] = $GLOBALS['EXEC_TIME'];
        $this->frontendUserAuthentication->user = $userdata;

        $this->typoScriptFrontendController->fe_user = &$this->frontendUserAuthentication;
        $this->updateLastLogin();
    }

    /**
     * Fetch user data from fe_user table
     *
     * @param integer $uid
     *
     * @return array
     */
    protected function fetchUserData($uid)
    {
        return $this->database->exec_SELECTgetSingleRow('*', 'fe_users', 'uid = ' . (int)$uid);
    }

    /**
     * For every 60 seconds the is_online timestamp is updated.
     *
     * @return void
     */
    protected function updateLastLogin()
    {
        if ($this->frontendUserAuthentication->user) {
            $this->database->exec_UPDATEquery(
                'fe_users',
                'uid = ' . (int)$this->frontendUserAuthentication->user['uid'],
                [
                    $this->frontendUserAuthentication->lastLogin_column => (int)$GLOBALS['EXEC_TIME'],
                    'is_online' => (int)$GLOBALS['EXEC_TIME']
                ]
            );
        }
    }
}
