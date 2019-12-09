<?php

namespace ContaoBayern\NuligadataBundle\NuLiga\Data;

// use Contao\CalendarEventsModel;
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
        $this->prepareRequest();
        $fedNickname = rawurlencode($fedNickname);
        $url = sprintf(self::URL_PATTERN, $fedNickname, $clubNr, $teamId);
        $data = $this->authenticatedRequest->authenticatedRequest($url);
        if ($this->authenticatedRequest->getLastStatus() === 200) {
            $this->storeData($data);
        } else {
            print_r($data);
        }
    }

    /**
     * @param $data
     */
    protected function storeData($data): void
    {
        // Datenausgabe zum visuellen Test/Debuggen
        foreach($data['groupTable'][0]['groupTableTeam'] as $tableRow) {
            // Datenstruktur: // {"tableRank":1,"team":"HSG Lauingen-Wittislingen","tendency":"steady","meetings":5,"ownPoints":10,"otherPoints":0,"ownPointsHome":6,"otherPointsHome":0,"ownPointsGuest":4,"otherPointsGuest":0,"ownMatches":144,"otherMatches":110,"ownMatchesSingle":null,"otherMatchesSingle":null,"ownMatchesDouble":null,"otherMatchesDouble":null,"ownSets":0,"otherSets":0,"ownGames":0,"otherGames":0,"ownMeetings":5,"otherMeetings":0,"tieMeetings":0,"teamNr":1,"clubNr":"90507","teamId":"1327935","teamUri":"https:\/\/hbde-portal.liga.nu\/rs\/2014\/teams\/1327935","teamState":"active","teamReleasedDate":null,"contestTypeNickname":"MA","teamStatistics":null,"riseAndFallState":"none"}
            printf("%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\n",
                $tableRow['tableRank'],
                $tableRow['team'],
                $tableRow['meetings'],
                $tableRow['ownMeetings'],
                $tableRow['tieMeetings'],
                $tableRow['otherMeetings'],
                $tableRow['ownPoints'],
                $tableRow['otherPoints'],
                $tableRow['ownMatches'],
                $tableRow['otherMatches']
            );
        }
        // TODO: wie speichern wir die Daten und wie werden sie an das Template des
        // (noch zu erstellenden) ContentElements übergeben?
        // * Als Teil eines MannschaftModels via https://github.com/fiedsch/contao-jsonwidget?
        // * als JSON (oder YAML)-Datei unter /files
    }
}
