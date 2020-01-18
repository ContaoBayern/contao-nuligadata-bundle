<?php /** @noinspection PhpUndefinedClassInspection */

namespace ContaoBayern\NuligadataBundle\Command;

use Contao\System;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\Framework\FrameworkAwareInterface;
use Contao\CoreBundle\Framework\FrameworkAwareTrait;
use ContaoBayern\NuligadataBundle\Models\TeamModel;
use ContaoBayern\NuligadataBundle\NuLiga\Data\Meetings;
use ContaoBayern\NuligadataBundle\NuLiga\Data\Table;
use ContaoBayern\NuligadataBundle\NuLiga\Data\Teams;
use ContaoBayern\NuligadataBundle\NuLiga\Request\AuthenticatedRequest;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\Routing\Exception\InvalidParameterException;

class AccessApiCommand extends Command implements FrameworkAwareInterface
{

    use FrameworkAwareTrait;

    /**
     * @var AuthenticatedRequest
     */
    protected $nuApiRequest;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(AuthenticatedRequest $nuApiRequest, LoggerInterface $logger)
    {
        parent::__construct();

        $this->nuApiRequest = $nuApiRequest;
        $this->logger = $logger;
    }

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

            //$this->logger->addInfo('Aktualisiere Daten über die nuLiga API',
            //    ['contao' => new ContaoContext(__METHOD__, ContaoContext::CRON)]
            //);

            if (!$this->nuApiRequest->authenticate()) {
                $this->logger->addError('konnte nicht bei der nuLiga API authentifizieren',
                    ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]
                );
                throw new RuntimeException('konnte nicht authentifizieren');
            }

            if (in_array($action, ['all', 'teams'])) {
                $teams = new Teams($this->nuApiRequest, $this->logger);
                $teams->getAndStoreData($fedNickname, $seasonNickname, $clubNr);
            }

            if (in_array($action, ['all', 'meetings'])) {
                $meetings = new Meetings($this->nuApiRequest, $this->logger);
                $meetings->getAndStoreData($fedNickname, $seasonNickname, $clubNr);
            }

            if (in_array($action, ['all', 'table'])) {
                $table = new Table($this->nuApiRequest, $this->logger);
                $teams = TeamModel::findBy('nu_season', $seasonNickname);
                if ($teams) {
                    /** @var TeamModel $team */
                    foreach ($teams as $team) {
                        $table->getAndStoreData($fedNickname, $clubNr, $team->nu_id);
                    }
                }
            }

        } catch (RuntimeException $e) {
            print "RuntimeException: " . $e->getMessage() . "\n";
            return 1;
        }
        return 0;
    }

}
