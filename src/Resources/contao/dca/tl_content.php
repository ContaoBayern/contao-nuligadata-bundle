<?php

$GLOBALS['TL_DCA']['tl_content']['palettes']['nuligadata_table'] =
    '{type_legend},type;{nuligadata_legend},team'
;

$GLOBALS['TL_DCA']['tl_content']['fields']['team'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['team'],
    'inputType' => 'select',
    'foreignKey' => "tl_team.CONCAT(nu_name,', ',nu_season)",

    'eval' => ['tl_class'=>'w50', 'mandatory'=>true, 'includeBlankOption'=>true],
    'sql' => ['type' => 'integer', 'notnull' => true, 'unsigned' => true, 'default' => 0],
];
