<?php

class vgsTwitchApi
{

    /**
     * Возвращает информацию о канале
     * @param $channel
     * @return array|bool
     */
    public static function getChannelInfo($channel)
    {
        $urlUserInfo = 'https://api.twitch.tv/kraken/users?login=' . $channel;
        if (!$dataUserInfo = self::getDataUrl($urlUserInfo)) {
            return false;
        }

        if (!$dataUserInfo->_total) {
            return false;
        }

        $userId = $dataUserInfo->users[0]->_id;

        $url = 'https://api.twitch.tv/kraken/channels/' . $userId;
        if (!$data = self::getDataUrl($url)) {
            return false;
        }

        return array(
            'game_name' => $data->game,
            'author_display' => $data->display_name,
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

        $cacheFilePath = dirname(__FILE__) . '/../cache/twitch_viewers';

        if (!file_exists($cacheFilePath)) {
            require_once dirname(__FILE__) . '/../cron/get_viewers.php';
        }

        $data = unserialize(file_get_contents($cacheFilePath));

        $viewers = array();
        if (isset($data->streams) && is_array($data->streams)) {
            foreach ($data->streams as $stream) {
                $viewers[$stream->channel->name] = $stream->viewers;
            }
        }

        $channelViewers = array();
        foreach ($channels as $channel) {
            $channelViewers[$channel] = (isset($viewers[$channel])) ? $viewers[$channel] : 0;
        }

        return array('channelViewers' => $channelViewers, 'channelsData' => $data);
    }

    /**
     * Возвращает распакованный массив данных, полученных по указанному Url
     * @param $url
     * @return array|bool|mixed
     */
    private static function getDataUrl($url)
    {

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: application/vnd.twitchtv.v5+json',
            'Client-ID: ckbj7089earv1z73tl7m4k188ltwks'
        ));
        $page = curl_exec($ch);
        curl_close($ch);

        if (!$page) {
            return false;
        }

        $data = json_decode($page);
        if (isset($data->error)) {
            return false;
        }

        return $data;
    }
}