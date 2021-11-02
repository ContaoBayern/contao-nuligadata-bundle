<?php

$GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['default']
    .= ';{nuLiga_legend},team,roundName,homeaway,courtHallName,teamHome,teamGuest,matchesHome,matchesGuest,meetingUuid,tstamp,json_data';

// // TODO: das 'team' sollte eigentlich ein Attribut des tl_calendar sein (-> pid dieses tl_calendar_events)
//
// $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['team'] = [
//     'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events']['team'],
//     'inputType'  => 'select',
//     'eval'       => ['readonly' => true, 'mandatory' => false, 'tl_class' => 'w50'],
//     'foreignKey' => 'tl_team.nu_name',
//     'sql'        => "int(10) NOT NULL default '0'",
// ];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['homeaway'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['homeaway'],
    'inputType' => 'select',
    'options'   => [
        0 => &$GLOBALS['TL_LANG']['tl_calendar_events']['away'],
        1 => &$GLOBALS['TL_LANG']['tl_calendar_events']['home'],
    ],
    'eval'      => ['isAssociative' => true, 'readonly' => true, 'tl_class' => 'w50 clr'],
    'sql'       => "int(10) unsigned NOT NULL default '0'",
];

foreach ([
             'roundName',
             'teamHome',
             'teamGuest',
             'courtHallName',
             'meetingUuid',
             'matchesHome',
             'matchesGuest',
         ] as $key) {
    $GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$key] = [
        'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events'][$key],
        'inputType' => 'text',
        'eval'      => ['readonly' => true, 'maxlength' => 128, 'tl_class' => 'w50'],
        'sql'       => "varchar(128) NOT NULL default ''",
    ];
}

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['json_data'] = [
    'inputType' => 'jsonWidget',
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['json_data'],
    'eval'      => ['tl_class'=>'long clr', 'decodeEntities'=>true, 'allowHtml'=>false,'readonly' => true],
    'sql'       => "blob NULL",
];



// Show the time stamp (which is the date when we last fetched data)
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tstamp']['label'] = &$GLOBALS['TL_LANG']['tl_calendar_events']['tstamp'];
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tstamp']['eval'] = ['rgxp' => 'datim', 'readonly'=>true, 'tl_class' => 'w50'];
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tstamp']['inputType'] = 'text';
