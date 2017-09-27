<?php

require_once '../init.php';

$serverVersion = $redis->get('tqServerVersion');
if ($redis->get('tqGroups:serverVersion') == $serverVersion) {
    exit();
}
Util::out("Update Groups and Items: $serverVersion");

if ($redis->get("tqStatus") != "ONLINE") exit();
$groups = CrestTools::getJSON("$crestServer/inventory/groups/");
$newGroups = 0;
$newItems = 0;

do {
    foreach ($groups['items'] as $group) {
        if ($redis->get("tqStatus") != "ONLINE") exit();
        $href = $group['href'];
        $groupID = $group['id'];
        $name = $group['name'];

        $exists = $mdb->count('information', ['type' => 'groupID', 'id' => $groupID]);
        if ($exists == 0) {
            ++$newGroups;
        }

        $types = CrestTools::getJSON($href);
        $categoryID = getGroupID($types['category']['href']);
        if (!isset($types['name'])) {
            exit("failure\n");
        } // Data not there, something is wrong, come back later

        $mdb->insertUpdate('information', ['type' => 'groupID', 'id' => $groupID], ['name' => $name, 'categoryID' => (int) $categoryID, 'lastApiUpdate' => $mdb->now(-86400)]);

        $types = CrestTools::getJSON($href);
        if (@$types['types'] != null) {
            foreach ($types['types'] as $type) {
                $typeID = $type['id'];
                $name = $type['name'];

                $exists = $mdb->count('information', ['type' => 'typeID', 'id' => $typeID]);
                if ($exists == 0) {
                    Util::out("Discovered item: $name");
                    ++$newItems;
                }

                $mdb->insertUpdate('information', ['type' => 'typeID', 'id' => $typeID], ['name' => $name, 'groupID' => $groupID, 'lastApiUpdate' => new MongoDate(1)]);
            }
        }
    }
    $next = @$groups['next']['href'];
    if ($next != null) {
        $groups = CrestTools::getJSON($next);
    }
} while ($next != null);

$mdb->insertUpdate('storage', ['locker' => 'groupsPopulated'], ['contents' => true]);
$redis->set('tq:itemsPopulated', true);

$redis->set('tqGroups:serverVersion', $serverVersion);

function getTypeID($href)
{
    $ex = explode('/', $href);

    return $ex[4];
}
function getGroupID($href)
{
    $ex = explode('/', $href);

    return $ex[5];
}
