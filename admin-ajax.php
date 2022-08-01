<?php

// Получение информации о канале (название, описание...)
add_action('wp_ajax_nopriv_get_channel_info', 'vgsGetChannelInfo');
add_action('wp_ajax_get_channel_info', 'vgsGetChannelInfo');
function vgsGetChannelInfo()
{

    if (
        !isset($_POST['channel_url'])
        || ! $channelData = parseChannelUrl($_POST['channel_url'])
    ) {

        vgsReturnAjaxResults( array('error' => 'Вы указали неверный адрес канала') );

    } else {

        if ($results = vgsRunApiAction( $channelData['site'], 'getChannelInfo', $channelData['channel'] )) {
            vgsReturnAjaxResults( array('results' => $results) );
        } else {
            vgsReturnAjaxResults( array('error' => 'Не удалось получить информацию о канале') );
        }

    }

}