<?php

namespace ContaoBayern\NuligadataBundle\NuLiga\Data;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Database;
use Contao\System;
use ContaoBayern\NuligadataBundle\Models\TeamModel;
use RuntimeException;
use Ausi\SlugGenerator\SlugGenerator;

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
            $container = System::getContainer();
            $doDeleteUpcomingMeetings = $container->hasParameter('app.importData.deleteUcomingEvents') ? $container->getParameter('app.importData.deleteUcomingEvents') : true;
            if ($doDeleteUpcomingMeetings) {
                $this->deleteUpcomingMeetings($seasonNickname);
            } else {
                $this->logger->debug('Überspringe deleteUpcomingMeetings(), da app.importData.deleteUcomingEvents: false in parameters.yml gesetzt ist');
            }
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
            $this->logger->addError('nuliga:apiaccess "meetings" ' . $this->authenticatedRequest->getLastStatusMessage(),
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]
            );
            return [];
        }
    }

    /**
     * @param $data
     * @param string $clubNr
     * @noinspection PhpUndefinedFieldInspection für die tl_calendar_event Properties des Bundles
     */
    protected function storeData($data, string $clubNr): void
    {
        $meetings = $data['meetingAbbr'];

        // keine Daten?
        if (!is_array($data['meetingAbbr'])) {
            // TODO error-log
            return;
        }

        $slugGenerator = new SlugGenerator();

        foreach ($meetings as $i => $meeting) {
            // $ourTeamId = 0;
            $ourTeam = null;

            if ($meeting['teamHomeClubNr'] === $clubNr) {
                $meeting['homeaway'] = 1;
                $ourTeamId = $meeting['teamHomeId'];
            } else {
                $meeting['homeaway'] = 0; // 'guest';
                $ourTeamId = $meeting['teamGuestId'];
            }
            $ourTeam = TeamModel::findBy('nu_id', $ourTeamId);

            if (!$ourTeam) {
                // TODO error-log
                print "Team nicht gefunden: $ourTeamId";
                return;
            }
            if (!$ourTeam->calendar) {
                // TODO error-log
                return;
            }

            $timestamp = strtotime($meeting['scheduled']); // TODO: vs 'originalDate' vs 'endDate' UND UTC vs local time?

            $event = CalendarEventsModel::findBy(['meetingUuid=?'], [$meeting['meetingUuid']]);

            $alias = $slugGenerator->generate(sprintf('%s-%s_%s-%s',
                $meeting['leagueNickname'],
                $meeting['seasonNickname'],
                $meeting['teamHome'],
                $meeting['teamGuest']
            ));

            if (null === $event) {
                $event = new CalendarEventsModel();
                $event->meetingUuid = $meeting['meetingUuid'];
                $event->alias = $alias;
            }
            // Bei verschobenen Spielen ändert sich das Datum und damit der alias
            if ($event->alias !== $alias) {
                $event->alias = $alias;
            }
            $event->teamHome = $meeting['teamHome'];
            $event->teamGuest = $meeting['teamGuest'];
            $event->courtHallName = $meeting['courtHallName'] ?? 'nicht angegeben/noch nicht festgelegt'; // courtHallName might be null in the API response but we defined the column to be NOT NULL
            $event->homeaway = $meeting['homeaway'];
            $event->matchesHome = $meeting['matchesHome'];
            $event->matchesGuest = $meeting['matchesGuest'];
            $event->roundName = $meeting['roundName'];
            $event->json_data = json_encode($meeting);

            // Standard tl_calendar_event-Felder
            $event->pid = $ourTeam->calendar;
            $event->author = 1; // bei der Installation angelegter Administrator
            $event->tstamp = time();
            $event->published = true;
            $event->startDate = $timestamp; // TODO (?) timestamp zu 00:00 Uhr des Datumns?
            $event->addTime = true;
            $event->startTime = $timestamp;
            $event->endTime = $timestamp;
            $event->location = $meeting['courtHallName'] ?? 'nicht angegeben/noch nicht festgelegt'; // courtHallName might be null in the API response but we defined the column to be NOT NULL
            $event->title = sprintf('%s : %s', $meeting['teamHome'], $meeting['teamGuest']);

            $event->save();
        }

        $this->logger->addError('nuliga:apiaccess "meetings" synchronisiert',
            ['contao' => new ContaoContext(__METHOD__, ContaoContext::CRON)]
        );
    }

    /**
     * Spiele, die noch nicht stattgefunden haben löschen, damit sie bei einem erneuten Import neu angelegt
     * werden können oder entfallen, falls (z.B.) das Spiel abgesagt wurde.
     */
    protected function deleteUpcomingMeetings(string $seasonNickname): void
    {
        $teams = TeamModel::findBy('nu_season', $seasonNickname);
        if (!$teams) {
            return;
        }
        $teamCalendarIds = [];
        /** @var TeamModel $team */
        foreach ($teams as $team) {
            $teamCalendarIds[] = $team->calendar;
        }
        $query = 'DELETE FROM tl_calendar_events WHERE pid IN ('.implode(',', array_unique($teamCalendarIds)).') AND startTime>?';
        Database::getInstance()->prepare($query)->execute([time()]);
    }

}
