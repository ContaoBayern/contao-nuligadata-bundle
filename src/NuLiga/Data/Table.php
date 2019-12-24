<?php

namespace ContaoBayern\NuligadataBundle\NuLiga\Data;

// use Contao\CalendarEventsModel;
use Contao\CoreBundle\Monolog\ContaoContext;
use ContaoBayern\NuligadataBundle\Models\TeamModel;
use RuntimeException;

class Table extends BaseDataHandler
{
    const URL_PATTERN = 'rs/2014/federations/%s/clubs/%s/teams/%s/table';

    /**
     * @param string $fedNickname
     * @param string $clubNr
     * @param string $teamId
     * @throws RuntimeException
     */
    public function getAndStoreData(string $fedNickname, string $clubNr, string $teamId): void
    {
        $data = $this->getData($fedNickname, $clubNr, $teamId);
        if (isset($data['groupTable'][0]) && is_array($data['groupTable'][0])) {
            $this->storeData($data, $teamId);
        }
    }

    /**
     * @param string $fedNickname
     * @param string $clubNr
     * @param string $teamId
     * @return array
     */
    public function getData(string $fedNickname, string $clubNr, string $teamId): array
    {
        $this->prepareRequest();
        $fedNickname = rawurlencode($fedNickname);
        $url = sprintf(self::URL_PATTERN, $fedNickname, $clubNr, $teamId);
        $data = $this->authenticatedRequest->authenticatedRequest($url);
        if ($this->authenticatedRequest->getLastStatus() === 200) {
            return $data;
        } else {
            $this->logger->addError('nuliga:apiaccess "table" '.$this->authenticatedRequest->getLastStatusMessage(),
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]
            );
            return [];
        }
    }

    /**
     * @param $data
     * @param $teamId
     */
    protected function storeData($data, $teamId): void
    {
        $data_to_store = [];
        foreach($data['groupTable'][0]['groupTableTeam'] as $tableRow) {
            $dataRow = [];
            // Datenstruktur: // {"tableRank":1,"team":"HSG Lauingen-Wittislingen","tendency":"steady","meetings":5,"ownPoints":10,"otherPoints":0,"ownPointsHome":6,"otherPointsHome":0,"ownPointsGuest":4,"otherPointsGuest":0,"ownMatches":144,"otherMatches":110,"ownMatchesSingle":null,"otherMatchesSingle":null,"ownMatchesDouble":null,"otherMatchesDouble":null,"ownSets":0,"otherSets":0,"ownGames":0,"otherGames":0,"ownMeetings":5,"otherMeetings":0,"tieMeetings":0,"teamNr":1,"clubNr":"90507","teamId":"1327935","teamUri":"https:\/\/hbde-portal.liga.nu\/rs\/2014\/teams\/1327935","teamState":"active","teamReleasedDate":null,"contestTypeNickname":"MA","teamStatistics":null,"riseAndFallState":"none"}
            foreach ([
                         'tableRank',
                         'team',
                         'meetings',
                         'ownMeetings',
                         'tieMeetings',
                         'otherMeetings',
                         'ownPoints',
                         'otherPoints',
                         'ownMatches',
                         'otherMatches'
                     ] as $column) {
                $dataRow[$column] = $tableRow[$column];
            }
            $data_to_store[] = $dataRow;
        }

        $team = TeamModel::findBy(['nu_id=?'], $teamId);
        if ($team) {
            $team->current_table = $data_to_store;
            $team->save();
            $this->logger->addError("nuliga:apiaccess \"table\" für Team $teamId synchronisiert",
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::CRON)]
            );
        }
    }
}
