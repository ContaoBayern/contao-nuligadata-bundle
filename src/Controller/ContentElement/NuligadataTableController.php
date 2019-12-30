<?php declare(strict_types=1);

namespace ContaoBayern\NuligadataBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\ServiceAnnotation\ContentElement;
use Contao\Template;
use ContaoBayern\NuligadataBundle\Models\TeamModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @ContentElement(category="nuligadata")
 */
class NuligadataTableController extends AbstractContentElementController
{

    protected function getResponse(Template $template, ContentModel $model, Request $request): ?Response
    {
        $team = TeamModel::findById($model->team);
        $template->teamModel = $team;
        $tablerows = $team->current_table;
        $template->tablerows = (isset($tablerows) && is_array($tablerows)) ? $tablerows : [];

        return $template->getResponse();
    }

}
