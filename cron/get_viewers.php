<?php

require_once dirname(__FILE__) . '/../../../../wp-load.php';

global $wpdb;

/**
 * Получаем кол-во зрителей twitch
 */
$query = 'SELECT channel_twitch FROM ' . $wpdb->prefix . 'vgs_games WHERE channel_twitch != "" AND channel_twitch IS NOT NULL';
$channelsTwitch = $wpdb->get_col($query);

// Получаем IDшники юзеров
$usersIds = [];
foreach (array_chunk($channelsTwitch, 100) as $usersLiginsChunk) {

    $urlUsersInfo = 'https://api.twitch.tv/kraken/users?login=' . implode(',', $usersLiginsChunk);
    $dataUsersInfo = getDataUrlTwtich($urlUsersInfo);
    if ($dataUsersInfo->_total) {

        foreach ($dataUsersInfo->users as $userItem) {
            $usersIds[$userItem->name] = $userItem->_id;
        }

    }

}

$url = 'https://api.twitch.tv/kraken/streams?channel=' . implode(',', $usersIds);

$cacheFilePath = dirname(__FILE__) . '/../cache/twitch_viewers';

$data = getDataUrlTwtich($url);

if ($data && isset($data->streams) && !empty($data->streams)) {
    // сохраняем информацию о предыдущем кол-ве зрителей
    if (file_exists($cacheFilePath)) {
        $prevData = unserialize(file_get_contents($cacheFilePath));
        $viewers = array();

        if ($prevData && isset($prevData->streams) && is_array($prevData->streams)) {
            foreach ($prevData->streams as $stream) {
                $viewers[$stream->channel->name] = $stream->viewers;
            }
            update_option('vgs_channels_viewers_twitch', serialize($viewers));
        }
    }

    // обновляем кеш
    file_put_contents($cacheFilePath, serialize($data));
}


$query = 'SELECT channel_goodgame FROM ' . $wpdb->prefix . 'vgs_games WHERE channel_goodgame != "" AND channel_goodgame IS NOT NULL';
$channelsGoodgame = $wpdb->get_col($query);
$url = 'http://goodgame.ru/api/getggchannelstatus?id=' . implode(',', $channelsGoodgame);

$cacheFilePath = dirname(__FILE__) . '/../cache/goodgame_viewers';

$data = getDataUrlGoodgame($url);
$data = handleDataViewers($data);

if ($data && !empty($data)) {
    // сохраняем информацию о предыдущем кол-ве зрителей
    if (file_exists($cacheFilePath)) {
        $prevData = unserialize(file_get_contents($cacheFilePath));
        $viewers = array();
        foreach ($prevData as $prevChannel => $prevItem) {
            $viewers[$prevChannel] = $prevItem['viewers'];
        }
        update_option('vgs_channels_viewers_goodgame', serialize($viewers));
    }

    // обновляем кеш
    file_put_contents($cacheFilePath, serialize($data));
}

function getDataUrlTwtich($url)
{

    $ch = curl_init();
    curl_setopt( $ch, CURLOPT_URL, $url);
    curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: application/vnd.twitchtv.v5+json',
        'Client-ID: ckbj7089earv1z73tl7m4k188ltwks'
    ));
    $page =  curl_exec( $ch );
    curl_close( $ch );

    if (!$page) {
        return false;
    }

    $data = json_decode($page);
    if (isset($data->error)) return false;

    return $data;
}

function getDataUrlGoodgame($url)
{

    $ch = curl_init();
    curl_setopt( $ch, CURLOPT_URL, $url);
    curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $page =  curl_exec( $ch );
    curl_close( $ch );

    if (!$page) {
        return false;
    }

    if (preg_match('#<root/>\s*$#suix', $page)) return false;

    return $page;
}

function handleDataViewers($data)
{
    preg_match_all('#key>(.*?)<.*?status>(.*?)<.*?viewers>(\d+)<.*?player(?:2|)\?(.*?)".*?games>(.*?)(?:<|,)#suix', $data, $viewersMatch, PREG_SET_ORDER);

    $returnData = array();
    foreach ($viewersMatch as $item) {
        $channel = mb_strtolower($item[1]);
        $status = $item[2];
        $viewers = ($status == 'Dead') ? 0 : $item[3];
        $playerId = $item[4];
        $game = $item[5];

        $returnData[$channel] = array('viewers' => $viewers, 'player_id' => $playerId, 'game' => $game);
    }

    return $returnData;
}