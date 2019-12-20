<?php

namespace ContaoBayern\NuligadataBundle\NuLiga\Data;

// use Contao\CalendarEventsModel;
use ContaoBayern\NuligadataBundle\Models\TeamModel;
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
        $data = $this->getData($fedNickname, $seasonNickname, $clubNr);
        if (isset($data['meetingAbbr']) && is_array($data['meetingAbbr'])) {
            $this->storeData($data, $clubNr);
        }
    }

    public function getData(string $fedNickname, string $seasonNickname, string $clubNr): array
    {
        $this->prepareRequest();
        $fedNickname = rawurlencode($fedNickname);
        $seasonNickname = rawurlencode($seasonNickname);
        $maxResults = 1000;
        $url = sprintf(self::URL_PATTERN, $fedNickname, $seasonNickname, $clubNr, $maxResults);
        $data = $this->authenticatedRequest->authenticatedRequest($url);
        if ($this->authenticatedRequest->getLastStatus() === 200) {
            return $data;
        } else {
            return [];
        }
    }

    /**
     * @param $data
     * @param string $clubNr
     */
    protected function storeData($data, string $clubNr): void
    {
        printf("%d Ergebnisse erhalten\n", count($data['meetingAbbr']));

        // nur debug -- TODO über alle Einträge iterieren
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
                     'teamHomeClubNr',
                     'teamGuestClubNr',
                 ] as $key) {
            $meetingData[$key] = $meeting[$key];
        }

        $meetingData['homeaway'] = $meetingData['teamHomeClubNr'] === $clubNr ? 'home' : 'guest';
        if ($meetingData['teamHomeClubNr'] === $clubNr) {
            $meetingData['homeaway'] = 'home';
            $meetingData['team'] = TeamModel::findBy('nu_id', $meetingData['teamHomeId'])->name;
        } else {
            $meetingData['homeaway'] = 'guest';
            $meetingData['team'] = TeamModel::findBy('nu_id', $meetingData['teamGuestId'])->name;
        }

        print_r([
            'übermittelte meetings' => count($data['meetingAbbr']),
            'daten des ersten meetings' => $meetingData,
            ]
        );

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
