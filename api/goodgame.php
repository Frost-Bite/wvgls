<?php

class vgsGoodgameApi
{
    /**
     * Возвращает информацию о канале
     * @param $channel
     * @return array|bool
     */
    public static function getChannelInfo($channel)
    {
        $url = 'http://goodgame.ru/api/getchannelstatus?id=' . $channel;
        if (!$data = self::getDataUrl($url)) {
            return false;
        }

        preg_match('#games>(.*?)(?:<|,)#suix', $data, $gameNameMatch);
        preg_match('#url>.*?channel/(.*?)/#suix', $data, $authorMatch);

        return array(
            'game_name' => $gameNameMatch[1],
            'author_display' => $authorMatch[1],
        );
    }

    /**
     * Возвращает количество зрителей у указанных каналов
     * @param $channels
     * @return array|bool
     */
    public static function getStreamsViewers($channels)
    {
        global $vgsOptions;

        /*
        $url = 'http://goodgame.ru/api/getggchannelstatus?id=' . implode(',', $channels);

        $cacheFileName = md5($url);
        $cacheFilePath = dirname(__FILE__) . '/../cache/goodgame_' . $cacheFileName;

        $lastTimeUpdateCache = (int) get_option('vgs_last_time_update_cache_goodgame');
        $timeUpdateCache = (int) $vgsOptions->get('time_update_cache');

        // Если нужно обновить кеш

        if (!$lastTimeUpdateCache || $lastTimeUpdateCache < (time() - $timeUpdateCache) || !file_exists($cacheFilePath)) {

            // Если не удалось получить данные берем их из кеша
            if ( !$data = self::getDataUrl($url) ) {

                if ( !file_exists($cacheFilePath) ) {
                    return false;
                } else {
                    $data = unserialize(file_get_contents($cacheFilePath));
                }

            } else {

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
                $data = self::handleDataViewers($data);
                file_put_contents($cacheFilePath, serialize($data));
                update_option('vgs_last_time_update_cache_goodgame', time()-5);
            }

        } else {
            $data = unserialize(file_get_contents($cacheFilePath));
        }*/

        $cacheFilePath = dirname(__FILE__) . '/../cache/goodgame_viewers';

        if (!file_exists($cacheFilePath)) {
            require_once dirname(__FILE__) . '/../cron/get_viewers.php';
        }

        $data = unserialize(file_get_contents($cacheFilePath));

        $channelViewers = array();
        foreach ($channels as $channel) {
            $channelViewers[$channel] = (isset($data[$channel])) ? $data[$channel]['viewers'] : 0;
        }

        return array('channelViewers' => $channelViewers, 'channelsData' => $data);
    }

    private static function handleDataViewers($data)
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

    /**
     * Возвращает распакованный массив данных, полученных по указанному Url
     * @param $url
     * @return array|bool|mixed
     */
    private static function getDataUrl($url)
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
}