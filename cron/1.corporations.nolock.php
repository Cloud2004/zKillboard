<?php

use cvweiss\redistools\RedisTimeQueue;

require_once "../init.php";

if ($redis->get("zkb:reinforced") == true) exit();
$guzzler = new Guzzler($esiCorpKillmails, 25);

$esi = new RedisTimeQueue('tqCorpApiESI', 3600);
if (date('i') == 22 || $esi->size() < 100) {
    $esis = $mdb->find("scopes", ['scope' => 'esi-killmails.read_corporation_killmails.v1']);
    foreach ($esis as $row) {
        if (!isset($row['characterID'])) {
            $mdb->remove("scopes", $row);
            continue;
        }
        $charID = $row['characterID'];
        $esi->add($charID);
    }
}

$mdb->set("scopes", ['scope' => "esi-killmails.read_corporation_killmails.v1", 'lastFetch' => ['$exists' => false]], ['lastFetch' => 0], true);
$unique = sizeof($mdb->getCollection("scopes")->distinct("corporationID", ['scope' => 'esi-killmails.read_corporation_killmails.v1', 'iterated' => true]));
$redis->set("tqCorpApiESICount", $unique);

$minute = date('Hi');
while ($minute == date('Hi')) {
    $charID = (int) $esi->next();
    $corpID = (int) Info::getInfoField('characterID', $charID, 'corporationID');
    if ($charID > 1 && $corpID > 1999999) {
        if ($redis->get("zkb:corpInProgress:$corpID") == "true" || $redis->get("zkb:recentCorpCheck:$corpID") == "true") {
            $esi->setTime($charID, time() + 60);
            continue;
        } 

        $row = $mdb->findDoc("scopes", ['corporationID' => $corpID, 'scope' => "esi-killmails.read_corporation_killmails.v1"], ['lastFetch' => 1]);
        if ($row == null) $row = $mdb->findDoc("scopes", ['characterID' => $charID, 'scope' => "esi-killmails.read_corporation_killmails.v1"]);
        if ($row != null) {
            $refreshToken = $row['refreshToken'];
            $row['corporationID'] = $corpID;
            $params = ['row' => $row, 'esi' => $esi, 'tokenTime' => time(), 'refreshToken' => $refreshToken, 'corpID' => $corpID];

            $redis->setex("zkb:corpInProgress:$corpID", 3600, "true");
            $redis->setex("zkb:recentCorpCheck:$corpID", 300, "true");
            CrestSSO::getAccessTokenCallback($guzzler, $refreshToken, "accessTokenDone", "accessTokenFail", $params);
        } else {
            $esi->remove($charID);
        }
    }
    if (($charID <= 1) || ($corpID > 1 && $corpID > 0 && $corpID < 1999999)) {
        $mdb->remove("scopes", ['scope' => "esi-killmails.read_corporation_killmails.v1", 'characterID' => $charID]);
        $esi->remove($charID);
    }
    if ($charID == 0) {
        $guzzler->tick();
        sleep(1);
    }
}
$guzzler->finish();


function accessTokenDone(&$guzzler, &$params, $content)
{
    global $ccpClientID, $ccpSecret, $esiServer;

    $response = json_decode($content, true);
    $accessToken = $response['access_token'];
    $params['content'] = $content;
    $row = $params['row'];

    $headers = [];
    $headers['Content-Type'] = 'application/json';
    $headers['Authorization'] = "Bearer $accessToken";
    $headers['etag'] = true;

    $charID = $row['characterID'];
    $corpID = $params['corpID'];

    $url = "$esiServer/v1/corporations/$corpID/killmails/recent/";
    $guzzler->call($url, "success", "fail", $params, $headers, 'GET');
}

function success($guzzler, $params, $content) 
{
    global $mdb, $redis;

    $row = $params['row'];
    $esi = $params['esi'];

    $newKills = 0;
    $kills = $content == "" ? [] : json_decode($content, true);
    foreach ($kills as $kill) {
        $killID = $kill['killmail_id'];
        $hash = $kill['killmail_hash'];

        $newKills += addMail($killID, $hash);
    }

    $charID = $row['characterID'];
    $corpID = $params['corpID'];

    $successes = 1 + ((int) @$row['successes']);
    $modifiers = ['corporationID' => $corpID, 'lastFetch' => $mdb->now(), 'successes' => $successes];
    if (!isset($row['added'])) $modifiers['added'] = $mdb->now();
    if (!isset($row['iterated'])) $modifiers['iterated'] = false;
    if ($content != "" && sizeof($kills) > 0) $modifiers['last_has_data'] = $mdb->now();
    $mdb->set("scopes", $row, $modifiers);

    $name = Info::getInfoField('characterID', $charID, 'name');
    $corpName = Info::getInfoField('corporationID', $corpID, 'name');
    if ($corpName == "") $corpName = "Corporation $corpID";
    $verifiedKey = "apiVerified:$corpID";
    $corpVerified = $redis->get($verifiedKey);
    if ($corpVerified === false) {
        ZLog::add("$corpName ($name) is now verified.", $charID);
    }
    $redis->setex($verifiedKey, 86400, time());
    $redis->del("zkb:corpInProgress:$corpID");

    if ($newKills > 0) {
        if ($name === null) $name = $charID;
        while (strlen("$newKills") < 3) $newKills = " " . $newKills;
        ZLog::add("$newKills kills added by corp $corpName", $charID);
        if ($newKills >= 10) User::sendMessage("$newKills kills added for corp $corpName", $charID);
    }
    $headers = $guzzler->getLastHeaders();
    if ($redis->get("recentKillmailActivity:$corpID") == "true") {
        $headers = $guzzler->getLastHeaders();
        $expires = $headers['expires'][0];
        $time = strtotime($expires);
        $esi->setTime($charID, $time + 2);
    }
    $h = [];
    foreach ($headers as $key => $value) {
        $h[$key] = $value[0];
    }
    //Log::log(print_r($h, true));
}

function addMail($killID, $hash) 
{
    global $mdb;

    $exists = $mdb->exists('crestmails', ['killID' => $killID, 'hash' => $hash]);
    if (!$exists) {
        try {
            $mdb->getCollection('crestmails')->save(['killID' => (int) $killID, 'hash' => $hash, 'processed' => false, 'source' => 'esi', 'added' => $mdb->now()]);
            return 1;
        } catch (MongoDuplicateKeyException $ex) {
            // ignore it *sigh*
        }
    }
    return 0;
}

function fail($guzzer, $params, $ex) 
{
    global $mdb, $redis;

    $row = $params['row'];
    $esi = $params['esi'];
    $charID = $row['characterID'];
    $code = $ex->getCode();

    $json = json_decode($params['content'], true);
    $code = isset($json['sso_status']) ? $json['sso_status'] : $code;
    $corpID = Info::getInfoField('characterID', (int) $charID, 'corporationID');

    if (@$json['error'] == 'invalid_grant' || @$json['error'] == "Character does not have required role(s)" || @$json['error'] == 'invalid_token') {
        $mdb->remove("scopes", $row);
        $esi->remove($charID);
        return;
    }
    if (@$json['error'] == "Character is not in the corporation") {
        $chars = new RedisTimeQueue("zkb:characterID", 86400);
        if ($chars->isMember($charID) == false) $chars->add($charID);
        else $chars->setTime($charID, 0);
        return;
    }

    switch ($code) {
        case 403:
        case 420:
        case 500:
        case 502: // Server error, try again in 5 minutes
        case 503: // gateway timeout
        case 504:
        case "": // typically a curl timeout error
            $esi->setTime($charID, time() + 30);
            break;
        default:
            Util::out("corp killmail: " . $ex->getMessage() . "\n" . $params['content']);
    }
}

function accessTokenFail(&$guzzler, &$params, $ex)
{
    global $mdb;

    $row = $params['row'];
    $esi = $params['esi'];
    $charID = $row['characterID'];
    $code = $ex->getCode();

    $json = json_decode($params['content'], true);
    $code = isset($json['sso_status']) ? $json['sso_status'] : $code;

    if (@$json['error'] == 'invalid_grant' || @$json['error'] == 'invalid_token') {
        $mdb->remove("scopes", $row);
        $esi->remove($charID);
        return;
    }

    switch ($code) {
        case 403: // A 403 without an invalid_grant is invalid
        case 500:
        case 502: // Server error, try again in 5 minutes
        case "": // typically a curl timeout error
            $esi->setTime($charID, time() + 30);
            break;
        default:
            Util::out("corp token: $charID " . $ex->getMessage() . "\n" . $params['content'] . "\n" . "code $code");
    }
}
