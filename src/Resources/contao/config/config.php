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
