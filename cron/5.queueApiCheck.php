<?php

use cvweiss\redistools\RedisQueue;
use cvweiss\redistools\RedisTimeQueue;

require_once "../init.php";

$queueApiCheck = new RedisQueue('queueApiCheck');
$esi = new RedisTimeQueue('tqApiESI', 3600);
$esiCorp = new RedisTimeQueue('tqCorpApiESI', 3600);

$bumped = [];
$minute = date('Hi');
while ($minute == date('Hi')) {
    $killID = $queueApiCheck->pop();
    if ($killID > 0) {
	continue;
        $killmail = $mdb->findDoc("killmails", ['killID' => $killID]);

        // Only do this for recent killmails
        if ($killmail['dttm']->sec < (time() - 3600)) continue;

        $involved = $killmail['involved'];
        foreach ($involved as $entity) {
            $charID = @$entity['characterID'];
            $corpID = Info::getInfoField("characterID", $charID, "corporationID");

            $lastChecked = $redis->get("apiVerified:$charID");
            $redis->setex("recentKillmailActivity:$charID", 3600, "true");
            $redis->setex("recentKillmailActivity:$corpID", 3600, "true");

            if ($lastChecked > 0 && (time() - $lastChecked) > 300 && !in_array($charID, $bumped)) {
                if ($esi->isMember($charID)) $esi->setTime($charID, 1);
                $bumped[] = $charID;
            }
            if ($lastChecked > 0 && (time() - $lastChecked) > 300 && !in_array($corpID, $bumped)) {
                if ($esiCorp->isMember($charID)) $esiCorp->setTime($charID, 1);
                $bumped[] = $corpID;
            }
        }
    } else sleep(1);
}
