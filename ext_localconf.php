<?php
defined('TYPO3_MODE') || die();

$boot = function ($_EXTKEY) {
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
};

$boot($_EXTKEY);
unset($boot);
