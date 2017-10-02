<?php

use cvweiss\redistools\RedisQueue;

require_once '../init.php';

if ($redis->llen("queueProcess") > 100) exit();
$queueWars = new RedisQueue('queueWars');

if ($queueWars->size() == 0) {
    Status::check('crest');
    $wars = $mdb->getCollection('information')->find(['type' => 'warID'])->sort(['id' => -1]);
    foreach ($wars as $war) {
        $timeFinished = @$war['timeFinished'];
        if ($timeFinished != null) {
            $threeDays = date('Y-m-d', (time() - (86400 * 3)));
            $warFinished = substr($timeFinished, 0, 10);

            if ($warFinished < $threeDays) {
                continue;
            }
        }
        $queueWars->push($war['id']);
    }
}

$added = 0;
$minute = date('Hi');
while ($minute == date('Hi')) {
    Status::check('crest');
    sleep(1);
    $id = $queueWars->pop();
    if ($id == null) {
        exit();
    }
    $warRow = $mdb->findDoc('information', ['type' => 'warID', 'id' => $id]);

    $href = "$crestServer/wars/$id/";
    $war = CrestTools::getJSON($href);

    if (!is_array($war)) {
        // Some type of error occurred, come back later
        $queueWars->push($id);
        exit();
    }

    $war['lastApiUpdate'] = $mdb->now();
    $war['id'] = $id;
    $war['finished'] = false;
    $mdb->insertUpdate('information', ['type' => 'warID', 'id' => $id], $war);

    $prevKills = @$warRow['agrShipsKilled'] + @$warRow['dfdShipsKilled'];
    $currKills = $war['aggressor']['shipsKilled'] + $war['defender']['shipsKilled'];

    // Don't fetch killmail api for wars with no kill count change
    if ($prevKills != $currKills) {
        $kmHref = $war['killmails'];
        $page = floor($mdb->count('warmails', ['warID' => $id]) / 2000);
        if ($page == 0) {
            $page = 1;
        } elseif ($page > 1) {
            $kmHref .= "?page=$page";
        }
        while ($kmHref != null) {
            $killmails = CrestTools::getJSON($kmHref);

            if (is_array($killmails['items'])) {
                foreach ($killmails['items'] as $kill) {
                    $href = $kill['href'];
                    $exploded = explode('/', $href);
                    $killID = (int) $exploded[4];
                    $hash = $exploded[5];

                    $mdb->insertUpdate('warmails', ['warID' => $id, 'killID' => $killID]);
                    if (!$mdb->exists('crestmails',  ['killID' => $killID, 'hash' => $hash])) {
                        $mdb->insert('crestmails', ['killID' => (int) $killID, 'hash' => $hash, 'processed' => false, 'source' => 'war', 'added' => Mdb::now()]);
                        Util::out("New WARmail $killID");
                    }
                }
            }
            $next = @$killmails['next']['href'];
            if ($next != $kmHref) {
                $kmHref = $next;
            } else {
                $kmHref = null;
            }
        }
    }
}
