<?php

require_once '../init.php';

global $mdb, $redis;

$key = "autocomplete:" . date('YmdH');
if ($redis->get($key) == true) {
    exit();
}

$entities = $mdb->getCollection('information')->find()->sort(['type' => 1]);
$entities->timeout(0);

$toMove = [];

foreach ($entities as $entity) {
    $type = $entity['type'];
    $id = $entity['id'];
    $name = @$entity['name'];
    if ($name == '') {
        continue;
    }
    if (strpos($name, '!') !== false) {
        continue;
    }

    $isShip = false;
    $flag = '';
    switch ($type) {
        case 'locationID':
        case 'warID':
            continue;
        case 'corporationID':
            $flag = @$entity['ticker'];
            break;
        case 'allianceID':
            $flag = @$entity['ticker'];
            break;
        case 'typeID':
            if ($mdb->exists('killmails', ['involved.shipTypeID' => $id])) {
                $flag = strtolower($name);
            }
            if (@$entity['published'] != true && $flag == '') {
                continue;
            }
            $isShip = $flag != '';
            break;
        case 'solarSystemID':
            $regionID = Info::getInfoField('solarSystemID', $id, 'regionID');
            $regionName = Info::getInfoField('regionID', $regionID, 'name');
            $name = "$name ($regionName)";
            break;
    }

    if (!$isShip) {
        $setKey = "s:search:$type";
        $toMove[$setKey] = true;
        $redis->zAdd($setKey, 0, trim(strtolower($name))."\x00$id");
        //$mdb->insertUpdate("search", ['type' => $type, 'name' => strtolower($name)], ['id' => $id, 'dttm' => $mdb->now()]);
    }
    if (strlen($flag) > 0) {
        $setKey = "s:search:$type:flag";
        $toMove[$setKey] = true;
        $redis->zAdd($setKey, 0, strtolower("$flag\x00$id"));
        //$mdb->insertUpdate("search", ['type' => $type . ":flag", 'name' => strtolower($flag)], ['id' => $id, 'dttm' => $mdb->now()]);
    }
}

foreach ($toMove as $setKey => $set) {
    $newName = substr($setKey, 2);
    $redis->rename($setKey, substr($setKey, 2));
}

$redis->setex($key, 64800, true);
