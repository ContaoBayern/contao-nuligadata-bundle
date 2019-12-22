<?php

/**
 * Backendmodule
 */
array_insert($GLOBALS['BE_MOD'], 0, [
    'nu_liga' => [
        'teams' => [
            'tables' => ['tl_team'],
        ]
    ]
]);


/**
 * Models
 */

$GLOBALS['TL_MODELS']['tl_team'] = 'ContaoBayern\NuligadataBundle\Models\TeamModel';


/* Add to Backend CSS */
if (TL_MODE === 'BE') {
    $GLOBALS['TL_CSS'][] = 'bundles/contaobayernnuligadata/backend.css';
}