<?php
namespace Causal\CslOauth2\Controller;

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
use Causal\CslOauth2\Storage\Typo3Pdo;
use OAuth2\Request;
use OAuth2\Response;
use OAuth2\Server;
use TYPO3\CMS\Backend\FrontendBackendUserAuthentication;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\DatabaseConnection;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Saltedpasswords\Salt\SaltFactory;

/**
 * Class ServerController
 *
 * @package Causal\CslOauth2\Controller
 */
class ServerController extends ActionController
{

    /**
     * @var \Causal\CslOauth2\Utility\LoginFrontendUserUtility
     * @inject
     */
    protected $loginFrontendUserUtility;

    /**
     * @var \TYPO3\CMS\Extbase\Service\TypoScriptService
     * @inject
     */
    protected $typoScriptService;

    /**
     * @var array
     */
    protected $actionSettings = [];

    /**
     * @var FrontendBackendUserAuthentication
     */
    protected $backendUser;

    /**
     * @var array
     */
    protected $controllerSettings = [];

    /**
     * @var array
     */
    protected $clientDetails = [];

    /**
     * @var DatabaseConnection
     */
    protected $database;

    /**
     * @var Server
     */
    protected $oAuth2Server;

    /**
     * @var TypoScriptFrontendController
     */
    protected $typoScriptFrontendController;

    /**
     *
     */
    public function initializeAction()
    {
        parent::initializeAction();

        $this->controllerSettings = $this->settings['controllers'][$this->request->getControllerName()];
        $this->actionSettings = $this->controllerSettings['actions'][$this->request->getControllerActionName()];

        $storage = GeneralUtility::makeInstance(Typo3Pdo::class);
        $this->oAuth2Server = GeneralUtility::makeInstance(Server::class, $storage, ['allow_implicit' => true]);

        $this->backendUser = $GLOBALS['BE_USER'];
        $this->database = $GLOBALS['TYPO3_DB'];
        $this->typoScriptFrontendController = $GLOBALS['TSFE'];


        $getParams = GeneralUtility::_GET();
        if (array_key_exists('client_id', $getParams) && !empty($getParams['client_id'])) {
            $clientId = $getParams['client_id'];
            $clientStorage = $this->oAuth2Server->getStorage('client');
            $this->clientDetails = $clientStorage->getClientDetails($clientId);
        }
    }

    /**
     * @param ViewInterface $view
     */
    public function initializeView(ViewInterface $view)
    {
        parent::initializeView($view);

        $this->view->assignMultiple([
            'actionSettings' => $this->actionSettings,
            'clientDetails' => $this->clientDetails,
            'controllerSettings' => $this->controllerSettings,
            'siteName' => $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename']
        ]);
    }

    /**
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\InvalidArgumentNameException
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
     */
    public function authorizeAction()
    {
        $request = Request::createFromGlobals();
        $response = GeneralUtility::makeInstance(Response::class);

        // Validate the authorize request. if it is invalid, redirect back to the client with the errors in tow
        if (!$this->oAuth2Server->validateAuthorizeRequest($request, $response)) {
            $response->send();
            exit;
        }

        $isUserLoggedIn = false;
        $isClientAuthenticated = false;
        switch ($this->clientDetails['typo3_context']) {
            case 'BE':
                if ((bool)$this->backendUser->user['uid']) {
                    $isUserLoggedIn = true;
                    $sessionData = $this->backendUser->getSessionData($this->extensionName);
                    if ($sessionData['client_id'] === $this->clientDetails['client_id']) {
                        $isClientAuthenticated = true;
                    }
                }
                break;
            case 'FE':
                if ($this->typoScriptFrontendController->loginUser) {
                    $isUserLoggedIn = true;
                    $sessionData = $this->typoScriptFrontendController->fe_user->getKey('user', $this->extensionName);
                    if ($sessionData['client_id'] === $this->clientDetails['client_id']) {
                        $isClientAuthenticated = true;
                    }
                }
                break;
            default:
                throw new \InvalidArgumentException(
                    'Context "' . $this->clientDetails['typo3_context'] . '" is not yet implemented',
                    1459697724
                );
        }

        if ($isUserLoggedIn) {
            if ($isClientAuthenticated) {
                $this->request->setArgument('authorize', 1);
                $this->forward('authorizeClient');
            } else {
                if ((bool)$this->actionSettings['enable'] === true) {
                    $this->forward('showAuthorizeClientForm');
                } else {
                    $this->request->setArgument('authorize', 1);
                    $this->forward('authorizeClient');
                }
            }
        } else {
            $this->forward('showLoginForm');
        }
    }

    /**
     *
     */
    public function showLoginFormAction()
    {
        if ((bool)$this->actionSettings['useExternal']['enable'] === true) {
            if (\is_array($this->actionSettings['useExternal']['link'])) {
                $linkConf =
                    $this->typoScriptService->convertPlainArrayToTypoScriptArray($this->actionSettings['useExternal']);
                $link = $this->typoScriptFrontendController->cObj->cObjGetSingle($linkConf['link'], $linkConf['link.']);
            } elseif (is_numeric($this->actionSettings['useExternal']['link'])) {
                $link = $this->uriBuilder
                    ->setTargetPageUid((int)$this->actionSettings['useExternal']['link'])
                    ->setAbsoluteUriScheme(true)
                    ->setUseCacheHash(false)
                    ->build();
            } else {
                $link = $this->actionSettings['useExternal']['link'];
            }

            if ((bool)$this->actionSettings['useExternal']['appendOriginalUrlAsParameter']['enable'] === true) {
                $link .= (strpos($link, '?') !== false ? '&' : '?') .
                    $this->actionSettings['useExternal']['appendOriginalUrlAsParameter']['parameterName'] . '=' .
                    rawurlencode(GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL'));
            }
            HttpUtility::redirect($link);
        } else {
            $oAuth2Params = GeneralUtility::_GET();
            unset(
                $oAuth2Params['id'],
                $oAuth2Params['type'],
                $oAuth2Params['tx_csloauth2_server']
            );
            $oAuth2Params['tx_csloauth2_server']['action'] = 'login';

            // need to create action uri in controller because using $oAuthParams (w/o action above) in
            // <f:form additionalParams="..." results in an action uri without an action although an action
            // was defined using the action attribute
            $actionUri = $this->uriBuilder
                ->setTargetPageUid((int)$this->settings['oAuth2Server']['pageUid'])
                ->setTargetPageType((int)$this->settings['oAuth2Server']['pageType'])
                ->setArguments($oAuth2Params)
                ->setCreateAbsoluteUri(true)
                ->setUseCacheHash(false)
                ->build();

            $this->view->assignMultiple([
                'actionUri' => $actionUri,
                'oAuth2Params' => $oAuth2Params
            ]);
        }
    }

    /**
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\NoSuchArgumentException
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
     */
    public function loginAction()
    {
        if ($this->request->hasArgument('username') || $this->request->hasArgument('password')) {
            if (empty($this->request->getArgument('username')) || empty($this->request->getArgument('password'))) {
                $this->forward('showLoginForm');
            }
        }

        switch ($this->clientDetails['typo3_context']) {
            case 'BE':
                $table = 'be_users';
                $additionalWhere = BackendUtility::BEenableFields($table) . BackendUtility::deleteClause($table);
                break;
            case 'FE':
                $table = 'fe_users';
                $additionalWhere = $this->typoScriptFrontendController->cObj->enableFields($table);
                break;
            default:
                throw new \InvalidArgumentException(
                    'Context "' . $this->clientDetails['typo3_context'] . '" is not yet implemented',
                    1459697724
                );
        }

        $user = $this->database->exec_SELECTgetSingleRow(
            'uid, password',
            $table,
            'username=' . $this->database->fullQuoteStr($this->request->getArgument('username'), $table) .
            $additionalWhere
        );
        if (!empty($user)) {
            $hashedPassword = $user['password'];
            $objInstanceSaltedPW = SaltFactory::getSaltingInstance($hashedPassword);
            if (\is_object($objInstanceSaltedPW)) {
                $validPasswd = $objInstanceSaltedPW->checkPassword(
                    $this->request->getArgument('password'),
                    $hashedPassword
                );
                if ($validPasswd) {
                    switch ($this->clientDetails['typo3_context']) {
                        case 'BE':
                            // ToDo
                            break;
                        case 'FE':
                            $this->loginFrontendUserUtility->loginUser((int)$user['uid']);
                            $this->forward('authorize');
                            break;
                        default:
                            throw new \InvalidArgumentException(
                                'Context "' . $this->clientDetails['typo3_context'] . '" is not yet implemented',
                                1459697724
                            );
                    }
                } else {
                    $this->addFlashMessage(
                        LocalizationUtility::translate('login.error.message', $this->extensionName),
                        LocalizationUtility::translate('login.error.title', $this->extensionName),
                        AbstractMessage::ERROR
                    );
                    $this->forward('showLoginForm');
                }
            }
        }
    }

    /**
     * @return void
     */
    public function showAuthorizeClientFormAction()
    {
        $oAuth2Params = GeneralUtility::_GET();
        unset(
            $oAuth2Params['id'],
            $oAuth2Params['type'],
            $oAuth2Params['tx_csloauth2_server']
        );
        $oAuth2Params['tx_csloauth2_server']['action'] = 'authorizeClient';

        // need to create action uri in controller because using $oAuthParams (w/o action above) in
        // <f:form additionalParams="..." results in an action uri without an action although an action
        // was defined using the action attribute
        $actionUri = $this->uriBuilder
            ->setTargetPageUid((int)$this->settings['oAuth2Server']['pageUid'])
            ->setTargetPageType((int)$this->settings['oAuth2Server']['pageType'])
            ->setArguments($oAuth2Params)
            ->setCreateAbsoluteUri(true)
            ->setUseCacheHash(false)
            ->build();

        $this->view->assignMultiple([
            'actionUri' => $actionUri,
            'oAuth2Params' => $oAuth2Params
        ]);
    }

    /**
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\NoSuchArgumentException
     */
    public function authorizeClientAction()
    {
        if ($this->request->hasArgument('authorize') && (bool)$this->request->getArgument('authorize') === true) {
            $isAuthorized = (bool)$this->request->getArgument('authorize');
            $request = Request::createFromGlobals();
            /** @var Response $response */
            $response = GeneralUtility::makeInstance(Response::class);

            switch ($this->clientDetails['typo3_context']) {
                case 'BE':
                    $this->backendUser->setAndSaveSessionData(
                        $this->extensionName,
                        ['client_id' => $this->clientDetails['client_id']]
                    );
                    $userUid = $this->backendUser->user['uid'];
                    break;
                case 'FE':
                    $this->typoScriptFrontendController->fe_user->setKey(
                        'user',
                        $this->extensionName,
                        ['client_id' => $this->clientDetails['client_id']]
                    );
                    $this->typoScriptFrontendController->fe_user->storeSessionData();
                    $userUid = $this->typoScriptFrontendController->fe_user->user['uid'];
                    break;
                default:
                    throw new \InvalidArgumentException(
                        'Context "' . $this->clientDetails['typo3_context'] . '" is not yet implemented',
                        1459697724
                    );
            }

            $this->oAuth2Server->handleAuthorizeRequest($request, $response, $isAuthorized, $userUid)->send();
        } else {
            $redirectUri = GeneralUtility::_GET('redirect_uri');
            $redirectUri = substr(
                $redirectUri,
                0,
                strpos($redirectUri, '/', 8) !== false ? strpos($redirectUri, '/', 8) : null
            );
            HttpUtility::redirect($redirectUri);
        }
    }

    /**
     * @return void
     */
    public function tokenAction()
    {
        $request = Request::createFromGlobals();
        /** @var Response $response */
        $response = $this->oAuth2Server->handleTokenRequest($request);
        $response->send();
        exit;
    }

    /**
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotException
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotReturnException
     */
    public function profileAction()
    {
        $defaultAllowedFields = [
            'username' => 'username',
            'name' => 'displayName',
            'first_name' => 'firstName',
            'middle_name' => 'middleName',
            'last_name' => 'lastName',
            'address' => 'address',
            'telephone' => 'telephone',
            'fax' => 'fax',
            'email' => 'email',
            'title' => 'title',
            'zip' => 'zip',
            'city' => 'city',
            'country' => 'country',
            'company' => 'company'
        ];
        if (\is_array($this->actionSettings['allowedFields']) && !empty($this->actionSettings['allowedFields'])) {
            $allowedFieldsDb = array_keys($this->actionSettings['allowedFields']);
            $allowedFieldsMapped = $this->actionSettings['allowedFields'];
        } else {
            $allowedFieldsDb = array_keys($defaultAllowedFields);
            $allowedFieldsMapped = $defaultAllowedFields;
        }
        $allowedFieldsDb = $this->cleanAllowedFields($allowedFieldsDb);

        $request = Request::createFromGlobals();
        /** @var Response $response */
        $response = GeneralUtility::makeInstance(Response::class);

        // Validate the authorize request. if it is invalid, redirect back to the client with the errors in tow
        if (!$this->oAuth2Server->verifyResourceRequest($request, $response)) {
            if ($response->getParameter('error') === null) {
                $response->setError(403, 'invalid_access_token', 'Invalid or no access token given');
            }
            $response->send();
            exit;
        }

        $token = $this->oAuth2Server->getAccessTokenData($request);
        $userUid = $token['user_id'];
        $clientId = $token['client_id'];
        /** @var Typo3Pdo $clientStorage */
        $clientStorage = $this->oAuth2Server->getStorage('client');
        $this->clientDetails = $clientStorage->getClientDetails($clientId);

        switch ($this->clientDetails['typo3_context']) {
            case 'BE':
                $select = '*, realName AS name';
                $table = 'be_users';
                $additionalWhere = BackendUtility::BEenableFields($table) . BackendUtility::deleteClause($table);
                break;
            case 'FE':
                $select = '*';
                $table = 'fe_users';
                $additionalWhere = $this->typoScriptFrontendController->cObj->enableFields($table);
                break;
            default:
                throw new \InvalidArgumentException(
                    'Context "' . $this->clientDetails['typo3_context'] . '" is not yet implemented',
                    1459697724
                );
        }
        $user = $this->database->exec_SELECTgetSingleRow($select, $table, 'uid=' . (int)$userUid . $additionalWhere);

        $requestParams = $request->getAllQueryParameters();
        $requestedFields = GeneralUtility::trimExplode(',', $requestParams['fields'], true);

        foreach ($requestedFields as $requestedField) {
            if (\in_array($requestedField, $allowedFieldsDb, true)) {
                $responseParams[$allowedFieldsMapped[$requestedField]] = $user[$requestedField];
            }
        }
        $responseParams['identifier'] = (string)$this->actionSettings['identifierPrefix'] . $user['uid'];
        $responseParams['uid'] = $user['uid'];

        $this->signalSlotDispatcher->dispatch(
            __CLASS__,
            __FUNCTION__ . 'AfterResponseParamsMapping',
            [&$responseParams, &$this]
        );
        $response->setParameters($responseParams);
        $response->send();
        exit;
    }

    /**
     * @param array $allowedFields
     *
     * @return array
     */
    private function cleanAllowedFields($allowedFields)
    {
        $disallowedFields = [
            'password',
            'admin',
            'uc',
            'felogin_forgotHash'
        ];
        $cleanAllowedFields = array_unique($allowedFields);

        foreach ($disallowedFields as $disallowedField) {
            $hits = array_keys($cleanAllowedFields, $disallowedField);
            foreach ($hits as $hit) {
                unset($cleanAllowedFields[$hit]);
            }
        }
        return $cleanAllowedFields;
    }
}
