<?php
defined('TYPO3_MODE') || die();

$boot = function ($_EXTKEY) {
    /** @var \TYPO3\CMS\Extbase\SignalSlot\Dispatcher $signalSlotDispatcher */
    $signalSlotDispatcher = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\SignalSlot\Dispatcher::class);

    // Endpoint-Plugin
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
        'Causal.' . $_EXTKEY,
        'Server',
        array(
            'Server' => 'authorize,showAuthorizeClientForm,authorizeClient,showLoginForm,login',
        ),
        // non-cacheable actions
        array(
            'Server' => 'authorize,showAuthorizeClientForm,authorizeClient,showLoginForm,login',
        )
    );

    if (TYPO3_MODE == 'FE') {
        if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('static_info_tables')) {
            $signalSlotDispatcher->connect(
                \Causal\CslOauth2\Controller\ServerController::class,
                'profileActionAfterResponseParamsMapping',
                \Causal\CslOauth2\Aspect\StaticInfoTablesAspect::class,
                'convertStaticInfoTablesUidToIso2Code'
            );
        }
    }
};

$boot($_EXTKEY);
unset($boot);
