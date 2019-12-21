<?php /** @noinspection PhpUndefinedClassInspection */

namespace ContaoBayern\NuligadataBundle\Command;

use Contao\CoreBundle\Framework\FrameworkAwareInterface;
use Contao\CoreBundle\Framework\FrameworkAwareTrait;
use ContaoBayern\NuligadataBundle\NuLiga\Request\AuthenticatedRequest;
use ContaoBayern\NuligadataBundle\NuLiga\Data\Teams;
use ContaoBayern\NuligadataBundle\NuLiga\Data\Meetings;
use ContaoBayern\NuligadataBundle\NuLiga\Data\Table;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\Routing\Exception\InvalidParameterException;

class AccessApiCommand extends Command implements FrameworkAwareInterface, ContainerAwareInterface
{

    use FrameworkAwareTrait;
    use ContainerAwareTrait;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('nuliga:apiaccess')
            ->setDescription('Daten aus der nuLiga API holen.')
            ->addArgument('fedNickname', InputArgument::REQUIRED, 'Verband (z.B. "BHV")')
            ->addArgument('seasonNickname', InputArgument::REQUIRED, 'Saison (z.B. "19/20")')
            ->addArgument('clubNr', InputArgument::REQUIRED, 'Club-Nummer (z.B. 12345)')
            ->addArgument('action', InputArgument::REQUIRED, 'Action (all|teams|meetings|table)');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            // Contao "booten"
            $this->framework->initialize();

            $fedNickname = $input->getArgument('fedNickname');
            $seasonNickname = $input->getArgument('seasonNickname');
            $clubNr = $input->getArgument('clubNr');
            $action = $input->getArgument('action');

            if (!in_array($action, ['all', 'teams', 'meetings', 'table'])) {
                throw new InvalidParameterException('gültige Werte für action sind teams|meetings|table');
            }

            /** @var AuthenticatedRequest $nuApiRequest */
            $nuApiRequest = $this->container->get('nuliga.authenticated.request');

            if (!$nuApiRequest->authenticate()) {
                throw new RuntimeException('konnte nicht authentifizieren');
            }

                $teams = new Teams($nuApiRequest);
            if (in_array($action, ['all', 'teams'])) {
                $teams->getAndStoreData($fedNickname, $seasonNickname, $clubNr);
            }

                $meetings = new Meetings($nuApiRequest);
            if (in_array($action, ['all', 'meetings'])) {
                $meetings->getAndStoreData($fedNickname, $seasonNickname, $clubNr);
            }

                $table = new Table($nuApiRequest);
            if (in_array($action, ['all', 'table'])) {
                // TODO: alle TeamModel zur aktuellen Saison holen und über sie iterieren ($teamId)
                $teamId = '1327635';
                $table->getAndStoreData($fedNickname, $clubNr, $teamId);
            }

        } catch (RuntimeException $e) {
            print "RuntimeException: " . $e->getMessage() . "\n";
            return 1;
        }
        return 0;
    }

}
