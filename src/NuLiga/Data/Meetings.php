<?php

namespace ContaoBayern\NuligadataBundle\NuLiga\Data;

// use Contao\CalendarEventsModel;
use RuntimeException;

class Meetings extends BaseDataHandler
{
    const URL_PATTERN = 'rs/2014/federations/%s/seasons/%s/clubs/%s/meetings?maxResults=%d';

    /**
     * @param string $fedNickname
     * @param string $seasonNickname
     * @param string $clubNr
     * @throws RuntimeException
     */
    public function getAndStoreData(string $fedNickname, string $seasonNickname, string $clubNr): void
    {
        $this->prepareRequest();
        $fedNickname = rawurlencode($fedNickname);
        $seasonNickname = rawurlencode($seasonNickname);
        $maxResults = 1000;
        $url = sprintf(self::URL_PATTERN, $fedNickname, $seasonNickname, $clubNr, $maxResults);
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
        printf("%d Ergebnisse erhalten\n", count($data['meetingAbbr']));

        // nur debug; TODO über alle Einträge iterieren
        $meeting = $data['meetingAbbr'][0];

        $meetingData = [];
        foreach ([
                     'meetingUuid',
                     'meetingId',
                     'scheduled',
                     'endDate',
                     'roundName',
                     'courtHallName',
                     'teamHome',
                     'teamGuest',
                     'teamHomeId',
                     'teamGuestId',
                     'groupName',
                     'matchesHome',
                     'matchesGuest',
                     'championshipRegion',
                     'championshipNickname',
                 ] as $key) {
            $meetingData[$key] = $meeting[$key];
        }

        print_r($meetingData);

        //$meetingData['homeaway'] = ...// TODO: aus $meeting['teamHomeClubNr'], $meeting['teamGuestClubNr'] und $clubNr
        //$meetingData['team'] = ... // TODO: interne Daten über TeamModel

        //$event = CalendarEventsModel::findBy(['nu_XXX=?'], [$meetingData['team']->XXX]);
        // if (null === $event) {
        //        $event = new CalendarEventsModel();
        //        $event->XXX = $meetingData['nu_XXX'];
        // }
        // $event->tstamp = time();
        // $event->nu_name = $teamData['nu_name'];
        // $event->nu_group = $teamData['nu_group'];
        // $event->nu_season = $teamData['nu_season'];
        // $event->save();
    }

}
