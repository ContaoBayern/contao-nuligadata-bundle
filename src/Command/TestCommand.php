<?php /** @noinspection PhpUndefinedClassInspection */

namespace ContaoBayern\NuligadataBundle\Command;

use Contao\CoreBundle\Framework\FrameworkAwareInterface;
use Contao\CoreBundle\Framework\FrameworkAwareTrait;
use ContaoBayern\NuligadataBundle\Models\TeamModel;
use ContaoBayern\NuligadataBundle\NuLiga\Request\AuthenticatedRequest;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Cache\InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
            ->setDescription('Ein Test-Command.')
            ->addArgument('foo', InputArgument::OPTIONAL, 'Foo (Beispielparameter)')
            ->addOption('bar', null, InputOption::VALUE_REQUIRED, 'Bar (Beispieloptiopn)', 1);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /*
        // Contao "booten" (falls benötigt -- z.B. um Models zu verwenden)
        $this->framework->initialize();
        // $model = CalendarEventsModel::findById(1);
        $model = TeamModel::findById(2);
        $model->nu_name = 'In ' . __CLASS__ . ' gesetzt';
        $model->save();
        $output->writeln(print_r($model, true));
        return 0;
        */

        try {
            $nuApiRequest = new AuthenticatedRequest($this->container);

            if (!$nuApiRequest->authenticate()) {
                throw new RuntimeException('konnte nicht authentifizieren');
            }

            // Parameter hier jeweils hart kodiert (als Test)

            /*
            // (1) Teams des Vereins nach Saison
            $fedNickname = 'BHV';
            //$seasonNickname = rawurlencode('Q 19/20');
            $seasonNickname = rawurlencode('19/20'); // ohne Q wie Qualifier
            $clubNr = '11059';
            $url = sprintf('rs/2014/federations/%s/seasons/%s/clubs/%s/teams',
                $fedNickname,
                $seasonNickname,
                $clubNr
            );
            // TODO: Ergebnisse in tl_team schreiben (TeamModel verwenden; insert oder update)
            */


            /**/
            // (2) Begegnungen des Vereins nach Saison (alle Teams)
            //
            $fedNickname = 'BHV';
            //$seasonNickname = rawurlencode('Q 19/20');
            $seasonNickname = rawurlencode('19/20'); // ohne Q wie Qualifier
            $clubNr = '11059';

            $url = sprintf('rs/2014/federations/%s/seasons/%s/clubs/%s/meetings?maxResults=%s',
                $fedNickname,
                $seasonNickname,
                $clubNr,
                1000
            );
            // TODO: Ergebnisse in tl_calendar_events schreiben (CalendarEventsModel verwenden; insert oder update)
            /**/

            /*
             // (3) Abfrage für die Tabelle einer Mannschaft (identifiziert durch ihre ID,
             // die implizit auch die Saison identifiziert. ID der Mannschaft(en) über obige
             // Abfrage (1) "ermittelt".
            $fedNickname = 'BHV';
            $clubNr = '11059';
            $teamId = '1327635';
            $url = sprintf('rs/2014/federations/%s/clubs/%s/teams/%s/table',
                $fedNickname,
                $clubNr,
                $teamId
            );
            */

            $data = $nuApiRequest->authenticatedRequest($url);
            print_r($data);

        } catch (RequestException $e) {
            print "RequestException: " . $e->getMessage() . "\n";
        } catch (RuntimeException $e) {
            print "RuntimeException: " . $e->getMessage() . "\n";
        } catch (InvalidArgumentException $e) {
            print "InvalidArgumentException: " . $e->getMessage() . "\n";
        } catch (GuzzleException $e) {
            print "GuzzleException: " . $e->getMessage() . "\n";
        }

        return 0;
    }

}
