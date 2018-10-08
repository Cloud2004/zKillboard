<?php

use cvweiss\redistools\RedisTtlCounter;

require_once '../init.php';

$key = date('YmdH');
if ($redis->get($key) == 1) {
    exit();
}

$mdb = new Mdb();

$killsLastHour = new RedisTtlCounter('killsLastHour', 3600);
$kills = $killsLastHour->count();
$count = $mdb->count('killmails');

if ($kills > 0) {
    Util::out(number_format($kills, 0).' kills added, now at '.number_format($count, 0).' kills.');
}

$redis->set('zkb:totalKills', $count);

$parameters = ['groupID' => 30, 'isVictim' => false, 'pastSeconds' => (86400 * 90), 'nolimit' => true];
$data['titans']['data'] = Stats::getTop('characterID', $parameters);
$redis->set('zkb:titans', serialize(Stats::getTop('characterID', $parameters)));

$parameters = ['groupID' => 659, 'isVictim' => false, 'pastSeconds' => (86400 * 90), 'nolimit' => true];
$redis->set('zkb:supers', serialize(Stats::getTop('characterID', $parameters)));

// Cleanup old tickets > 3 months old
$time = time() - (86400 * 90);
$mdb->getCollection('tickets')->deleteMany(['dttm' => ['$lte' => $time]]);

$redis->setex($key, 3600, 1);
