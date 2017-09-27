<?php

require_once '../init.php';

if ($redis->get('tqGroups:serverVersion') == $redis->get('tqCategories:serverVersion')) {
    exit();
}
Util::out("Updating categories");

if ($redis->get("tqStatus") != "ONLINE") exit();
$categories = CrestTools::getJSON("$crestServer/inventory/categories/");

foreach ($categories['items'] as $category) {
    if ($redis->get("tqStatus") != "ONLINE") exit();
    $id = (int) $category['id'];
    $cat = $mdb->findDoc("information", ['type' => 'categoryID', 'id' => $id]);
    if ($cat == null) $cat = [];
    $cat['id'] = $id;
    $cat['type'] = 'categoryID';
    $cat['name'] = $category['name'];
    $mdb->save("information", $cat);
}

$redis->set('tqCategories:serverVersion', $redis->get('tqGroups:serverVersion'));
