<?php /** @noinspection PhpUndefinedClassInspection */

namespace ContaoBayern\NuligadataBundle\Command;

use Contao\CoreBundle\Framework\FrameworkAwareInterface;
use Contao\CoreBundle\Framework\FrameworkAwareTrait;
use ContaoBayern\NuligadataBundle\NuLiga\Data\Meetings;
use ContaoBayern\NuligadataBundle\NuLiga\Data\Table;
use ContaoBayern\NuligadataBundle\NuLiga\Data\Teams;
use ContaoBayern\NuligadataBundle\NuLiga\Request\AuthenticatedRequest;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

class TestCommand extends Command implements FrameworkAwareInterface, ContainerAwareInterface
{

    use FrameworkAwareTrait;
    use ContainerAwareTrait;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('test:command')
            ->setDescription('Daten uas der nuLiga API holen.')
            ->addArgument('fedNickname', InputArgument::OPTIONAL, 'fedNickname (Verband)')
            ->addArgument('seasonNickname', InputArgument::OPTIONAL, 'seasonNickname (Saison)')
            ->addArgument('clubNr', InputArgument::OPTIONAL, 'clubNr (Club)')//->addOption('bar', null, InputOption::VALUE_REQUIRED, 'Bar (Beispieloptiopn)', 1)
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Contao "booten" (falls benötigt -- z.B. um Models zu verwenden)
        $this->framework->initialize();
        try {
            // Aufruf: vendor/bin/contao-console test:command BHV' 19/20 123
            // printf("fedNickname: '%s'\n", $input->getArgument('fedNickname'));
            // printf("seasonNickname: '%s'\n", $input->getArgument('seasonNickname'));
            // printf("clubNr: '%s'\n", $input->getArgument('clubNr'));
            // return 0;
            // TODO:
            // * InputArgument::OPTIONAL auf InputArgument::REQUIRED setzen
            // * $input->getArgument('...') holen und hier verwenden (anstelle der hart codierten Werte)

            // Parameter des Commands (Übergabe via cron job; für Tests hier hart codiert)
            $fedNickname = 'BHV';
            $seasonNickname = '19/20';
            $clubNr = '11059';

            /** @var AuthenticatedRequest $nuApiRequest */
            $nuApiRequest = $this->container->get('nuliga.authenticated.request');

            if (!$nuApiRequest->authenticate()) {
                throw new RuntimeException('konnte nicht authentifizieren');
            }

            $teams = new Teams($nuApiRequest);
            $teams->getAndStoreData($fedNickname, $seasonNickname, $clubNr);

            $meetings = new Meetings($nuApiRequest);
            $meetings->getAndStoreData($fedNickname, $seasonNickname, $clubNr);

            // TODO: alle TeamModel zur aktuellen Saison holen und über sie iterieren ($teamId)
            $table = new Table($nuApiRequest);
            $teamId = '1327635';
            $table->getAndStoreData($fedNickname, $clubNr, $teamId);

        } catch (RuntimeException $e) {
            print "RuntimeException: " . $e->getMessage() . "\n";
            return 1;
        }
        return 0;
    }

}
