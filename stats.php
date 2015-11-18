<?php
class RecentStatsCommand extends Command {
    public function execute($args) {
        if(isset($args[2])) {
            $user = $args[2];
        } else {
            $args[2] = $this->getDefaultUser($this->getUser()->id_str);
            $user = $args[2];
        }
        if(preg_match('/^[A-Za-z0-9_]{1,32}$/', $args[2])) {
            $json = json_decode(file_get_contents('https://pvp.minecraft.jp/'. $user. '.json'), true);
            preg_match('/HTTP\/1\.[0|1|x] ([0-9]{3})/', $http_response_header[0], $matches);
            var_dump($matches[1]);
            if($matches[1] == '200' || $matches[1] == '302' || $matches[1] == '307') {
                $payload = $json['player']['Player'];
                if (isset($payload['last_login'])) {
                    $result = array(
                        'kill' => 0,
                        'death' => 0,
                        'env_death' => 0,
                        'matches' => array(
                            'total' => 0,
                            'win' => 0,
                            'lose' => 0,
                            'draw' => 0
                        ),
                        'played_time' => 0,
                        'boundary' => 0
                    );
                    foreach ($payload['matches'] as $match) {
                        if ($match['gamemode'] != 'paintball' && $match['gamemode'] != 'splatt' && $match['gamemode'] != 'blitz') {
                            // Kill and death
                            $result['kill'] += $match['kill_count'];
                            $result['death'] += $match['death_count'];
                            $result['env_death'] += $match['envdeath_count'];
                            // Match Result
                            $result['matches']['total']++;
                            $result['matches'][$match['result']]++;
                            // Match Time
                            $playTime = $match['finished']['sec'] - $match['started']['sec'];
                            if ($playTime < 2000) {
                                $result['played_time'] += $playTime;
                            }
                            if($result['boundary'] == 0) {
                                $result['boundary'] = $match['started']['sec'];
                            }
                        }
                    }
                    foreach ($payload['objective']['destroys'] as $destroy) {
                        if($destroy['time']['sec'] > $result['boundary']) {
                            $result['obj']++;
                        }
                    }
                    foreach ($payload['objective']['core_leaks'] as $core) {
                        if($core['time']['sec'] > $result['boundary']) {
                            $result['obj']++;
                        }
                    }
                    foreach ($payload['ctw']['wool_places'] as $wool) {
                        if ($wool['time']['sec'] > $result['boundary']) {
                            $result['obj']++;
                        }
                    }
                    $status = sprintf("@%s %s の直近%d試合の結果統計\nK: %d D: %d\nK/K: %s K/D: %s\n%sKill/h %sDeath/h %sObj/h\n勝率: %s%% 敗率: %s%%",
                        $this->getUser()->screen_name,
                        $payload['name'],
                        $result['matches']['total'],
                        $result['kill'],
                        $result['death'],
                        $this->calculationRatio($result['kill'], $result['death']),
                        $this->calculationRatio($result['kill'], ($result['death'] + $result['env_death'])),
                        $this->calculationEfficient($result['kill'], $result['played_time']),
                        $this->calculationEfficient($result['death'], $result['played_time']),
                        $this->calculationEfficient($result['obj'], $result['played_time']),
                        $this->calculationPercent($result['matches']['win'], $result['matches']['lose']),
                        $this->calculationPercent($result['matches']['lose'], $result['matches']['win'])
                    );
                    $this->getTwitter()->post('statuses/update', array(
                        'status' => $status,
                        'in_reply_to_status_id' => $this->getStatus()->id_str
                    ));
                } else {
                    $this->getTwitter()->post('statuses/update', array(
                        'status' => '@'.$this->getUser()->screen_name." =Unknown User",
                        'in_reply_to_status_id' => $this->getStatus()->id_str
                    ));
                }
            } else {
                Logger::log("Failed to request: ". $matches[1]);
                $this->getTwitter()->post('statuses/update', array(
                    'status' => '@'. $this->getUser()->screen_name. " DetaBase conection is lost",
                    'in_reply_to_status_id' => $this->getStatus()->id_str
                ));
            }
        } else {
            $this->getTwitter()->post('statuses/update', array(
                'status' => '@'. $this->getUser()->screen_name. " Unknown",
                'in_reply_to_status_id' => $this->getStatus()->id_str
            ));
        }
    }
    private function getDefaultUser($twtrid) {
    }
    function calculationRatio($foo, $bar) {
        if($foo != 0) {
            if($bar == 0) {
                $bar = 1;
            }
            return round($foo / $bar, 3);
        } else {
            return 0;
        }
    }
    function calculationEfficient($foo, $time) {
        if ($foo == 0) {
            return 0;
        } else {
            return round($foo * (3600 / $time), 2);
        }
    }
    function calculationPercent($foo , $bar) {
        return round($foo / ($foo + $bar) * 100, 1);
    }
}
