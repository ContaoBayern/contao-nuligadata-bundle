<?php

namespace ContaoBayern\NuligadataBundle\Models;

use Contao\Model;

/**
 * Class TeamModel
 *
 * @package ContaoBayern\NuligadataBundle\Models
 * @property string $nu_id
 * @property string $nu_name
 * @property string $nu_group
 * @property string $nu_season
 * @property int $calendar
 * @method static TeamModel|null findById($id, array $opt=array())
 */
class TeamModel extends Model
{
    protected static $strTable = 'tl_team';
}