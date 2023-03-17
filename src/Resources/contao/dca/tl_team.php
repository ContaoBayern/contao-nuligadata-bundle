<?php

use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_team'] = [
    'config' => [
        'dataContainer'    => DC_Table::class,
        'enableVersioning' => true,
        'sql'              => [
            'keys' => [
                'id'    => 'primary',
                'nu_id' => 'index',
            ],
        ],
    ], // config

    'list' => [
        'sorting'           => [
            'mode'        => 1, // Records are sorted by a fixed field
            'fields'      => ['name'],
            'flag'        => 3, // Sort by initial two letters ascending
            'panelLayout' => 'filter;search,limit',
        ],
        'label'             => [
            'fields'         => ['name'],
            'label'          => '%s',
            'label_callback' => function(array $row) {
                $calendarLabel = '<span class="tl_red">nicht gesetzt</span>';
                if ($row['calendar'] > 0) {
                    $cal = CalendarModel::findById($row['calendar']);
                    if ($cal) {
                        $calendarLabel = $cal->title;
                    }
                }
                return sprintf("<span class='tl_blue'>%s</span>, Saison: %s, Kalender: %s <code class='tl_gray'>(id: %d, group: %d)</code>",
                    $row['name'],
                    $row['nu_season'],
                    $calendarLabel,
                    $row['nu_id'],
                    $row['nu_group']
                );
            },
        ],
        'global_operations' => [
            'all' => [
                'label'      => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset();"',
            ],
        ],
        'operations'        => [
            'edit'   => [
                'label' => &$GLOBALS['TL_LANG']['tl_team']['edit'],
                'href'  => 'act=edit',
                'icon'  => 'edit.svg',
            ],
            'copy'   => [
                'label' => &$GLOBALS['TL_LANG']['tl_team']['copy'],
                'href'  => 'act=copy',
                'icon'  => 'copy.svg',
            ],
            'delete' => [
                'label'      => &$GLOBALS['TL_LANG']['tl_team']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.svg',
                'attributes' => 'onclick="if (!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\')) return false; Backend.getScrollOffset();"',
            ],
            'show'   => [
                'label' => &$GLOBALS['TL_LANG']['tl_team']['show'],
                'href'  => 'act=show',
                'icon'  => 'show.svg',
            ],
        ], // operations
    ], // list

    'palettes' => [
        '__selector__' => [],
        'default'      => '{title_legend},name,calendar;{nuliga_legend},nu_id,nu_name,nu_group,nu_season;{data_legend},json_data',
    ], // palettes

    'fields' => [

        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],

        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],

        'name' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_team']['name'],
            'exclude'   => true,
            'inputType' => 'text',
            'search'    => true,
            'eval'      => ['tl_class' => 'w50', 'maxlength' => 128],
            'flag'      => 3, // Sort by initial X letters ascending (see length)
            'length'    => 5,
            'sql'       => "varchar(128) NOT NULL default ''",
        ],

        'calendar' => [
            'label'      => &$GLOBALS['TL_LANG']['tl_team']['calendar'],
            'exclude'    => true,
            'inputType'  => 'select',
            'filter'    => true,
            'eval'       => ['tl_class' => 'w50', 'includeBlankOption' => true],
            'foreignKey' => 'tl_calendar.title',
            'flag'       => 1, // Sort by initial letter ascending,
            'sql'        => "int(10) NOT NULL default '0'",
        ],

        'nu_id' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_team']['nu_id'],
            'inputType' => 'text',
            'eval'      => ['readonly' => true, 'tl_class' => 'w50', 'maxlength' => 64],
            'flag'      => 1, // Sort by initial letter ascending,
            'sql'       => "varchar(64) NOT NULL default ''",
        ],

        'nu_name' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_team']['nu_name'],
            'inputType' => 'text',
            'eval'      => ['readonly' => true, 'tl_class' => 'w50', 'maxlength' => 128],
            'sql'       => "varchar(128) NOT NULL default ''",
        ],

        'nu_group' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_team']['nu_group'],
            'inputType' => 'text',
            'eval'      => ['readonly' => true, 'tl_class' => 'w50', 'maxlength' => 128],
            'sql'       => "varchar(128) NOT NULL default ''",
        ],

        'nu_season' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_team']['nu_season'],
            'exclude'   => true,
            'search'    => false,
            'filter'    => true,
            'inputType' => 'text',
            'eval'      => ['readonly' => true, 'tl_class' => 'w50', 'maxlength' => 128],
            'sql'       => "varchar(128) NOT NULL default ''",
        ],

        'json_data' => [
            'inputType' => 'jsonWidget',
            'label'     => &$GLOBALS['TL_LANG']['tl_team']['json_data'],
            'eval'      => ['tl_class'=>'long', 'decodeEntities'=>true, 'allowHtml'=>true,'readonly' => true],
            'sql'       => "blob NULL",
        ],

], // fields

];
