<?php

namespace ContaoBayern\NuligadataBundle\NuLiga\Data;

use ContaoBayern\NuligadataBundle\Models\TeamModel;
use RuntimeException;

class Teams extends BaseDataHandler
{
    const URL_PATTERN = 'rs/2014/federations/%s/seasons/%s/clubs/%s/teams';

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
        $url = sprintf(self::URL_PATTERN, $fedNickname, $seasonNickname, $clubNr);
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
        foreach ($data['teamAbbr'] as $team) {
            $teamData = [
                'nu_id'              => $team['teamId'],
                'nu_name'            => $team['name'],
                'nu_group'           => $team['groupId'],
                'nu_season'          => $team['seasonNickname'],
            ];
            $team = TeamModel::findBy(['nu_id=?'], [$teamData['nu_id']]);
            if (null === $team) {
                $team = new TeamModel();
                $team->nu_id = $teamData['nu_id'];
            }

            $team->tstamp = time();
            $team->nu_name = $teamData['nu_name'];
            $team->nu_group = $teamData['nu_group'];
            $team->nu_season = $teamData['nu_season'];
            if (!$team->name) {
                $team->name = $teamData['nu_name'];
            }
            $team->save();
        }
    }
}
