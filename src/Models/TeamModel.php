<?php

namespace ContaoBayern\NuligadataBundle\Models;

use Contao\Model;

/**
 * Class TeamModel
 *
 * @package ContaoBayern\NuligadataBundle\Models
 * @property int id
 * @property int tstamp
 * @property string $name App-interner Name
 * @property string $nu_id
 * @property string $nu_name Name uas der nuLiga API
 * @property string $nu_group
 * @property string $nu_season
 * @property int $calendar
 * @method static TeamModel|null findById($id, array $opt=array())
 */
class TeamModel extends Model
{
    protected static $strTable = 'tl_team';
}