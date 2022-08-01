<?php

/**
 * Возвращает ответ при запросе переданном с помощью ajax
 * @param $array
 */
function vgsReturnAjaxResults($array)
{
    echo json_encode($array);
    exit();
}

/**
 * Парсинг адреса канала. Возвращает сайт и название канала
 * @param $url
 * @return array|bool
 */
function parseChannelUrl($url)
{
    $pattern = '#(twitch|goodgame)\.(?:tv|ru)\/(?:channel\/|)(.*?)[\/]*$#suix';
    if (preg_match($pattern, $url, $match)) {
        return array('site' => $match[1], 'channel' => $match[2]);
    } else {
        return false;
    }
}

/**
 * Вызывает метод api для указанного сайта
 * @param $site
 * @param $action
 * @param $data
 * @return mixed
 */
function vgsRunApiAction($site, $action, $data)
{
    if ($site == 'twitch') {
        $class = new vgsTwitchApi();
    } elseif ($site == 'goodgame') {
        $class = new vgsGoodgameApi();
    } else {
        return false;
    }

    return $class::$action($data);
}