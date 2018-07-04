<?php
defined('TYPO3_MODE') || die();

$locallangPrefix = 'LLL:EXT:csl_oauth2/Resources/Private/Language/locallang_db.xlf:';

return [
    'ctrl' => [
        'title' => $locallangPrefix . 'tx_csloauth2_oauth_clients',
        'label' => 'name',
        'default_sortby' => 'name',
        'adminOnly' => 1,
        'rootLevel' => 1,
        'dividers2tabs' => true,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'iconfile' => 'EXT:csl_oauth2/Resources/Public/Icons/tx_csloauth2_oauth_clients.png',
    ],
    'interface' => [
        'showRecordFieldList' => 'hidden,name,typo3_context,client_id,client_secret,reset_client_secret,redirect_uri',
    ],
    'types' => [
        '1' => [
            'showitem' => '
                name, typo3_context, client_id, --palette--;;client_secret, --palette--;;restrictions,
                --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xml:tabs.access,
                    hidden
            '
        ],
    ],
    'palettes' => [
        'client_secret' => [
            'showitem' => 'client_secret, reset_client_secret',
            'canNotCollapse' => 1,
        ],
        'restrictions' => [
            'showitem' => 'redirect_uri',
            'canNotCollapse' => 1,
        ],
    ],
    'columns' => [
        'hidden' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.hidden',
            'config' => [
                'type' => 'check',
                'default' => '0'
            ]
        ],
        'name' => [
            'exclude' => 0,
            'label' => $locallangPrefix . 'tx_csloauth2_oauth_clients.name',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'eval' => 'required,trim',
            ]
        ],
        'typo3_context' => [
            'exclude' => 0,
            'label' => $locallangPrefix . 'tx_csloauth2_oauth_clients.typo3_context',
            'config' => [
                'type' => 'select',
                'items' => [
                    [
                        $locallangPrefix . 'tx_csloauth2_oauth_clients.typo3_context.BE',
                        'BE'
                    ],
                    [
                        $locallangPrefix . 'tx_csloauth2_oauth_clients.typo3_context.FE',
                        'FE'
                    ],
                ],
                'size' => 1,
                'maxitems' => 1,
            ]
        ],
        'client_id' => [
            'exclude' => 0,
            'label' => $locallangPrefix . 'tx_csloauth2_oauth_clients.client_id',
            'config' => [
                'type' => 'input',
                'size' => '40',
                'readOnly' => true,
            ]
        ],
        'client_secret' => [
            'exclude' => 0,
            'label' => $locallangPrefix . 'tx_csloauth2_oauth_clients.client_secret',
            'config' => [
                'type' => 'input',
                'size' => '40',
                'readOnly' => true,
            ]
        ],
        'reset_client_secret' => [
            'exclude' => 1,
            'label' => '',
            'config' => [
                'type' => 'user',
                'userFunc' => \Causal\CslOauth2\Tca\ClientsWizard::class . '->resetClientSecret',
            ],
        ],
        'redirect_uri' => [
            'exclude' => 0,
            'label' => $locallangPrefix . 'tx_csloauth2_oauth_clients.redirect_uri',
            'config' => [
                'type' => 'text',
                'cols' => 40,
                'rows' => 15,
                'eval' => 'trim,required',
                'placeholder' => 'https://www.example.com/oauth2callback',
                'wizards' => [
                    'specialWizards' => [
                        'type' => 'userFunc',
                        'userFunc' => \Causal\CslOauth2\Tca\ClientsWizard::class . '->enhance',
                    ],
                ],
            ],
        ],
    ],
];
