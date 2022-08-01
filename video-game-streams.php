<?php
/*
Plugin Name: Wordpress Video Game Streams
Version: 1.3.5
Description: Позволяет отображать на сайте стримы видео с twitch.tv
Author: Khovl
*/

require_once dirname( __FILE__ ) . '/functions.php';
require_once dirname( __FILE__ ) . '/scb/load.php';

require_once dirname( __FILE__ ) . '/api/twitch.php';
require_once dirname( __FILE__ ) . '/api/goodgame.php';

function vgsInit()
{

    global $vgsCore, $vgsOptions;

    $vgsOptions = new scbOptions('vgs_options', __FILE__, array(
        'min_viewers_table' => '100',
        'time_update_cache' => '2',
    ) );

    require_once dirname( __FILE__ ) . '/core.php';
    $vgsCore = new vgsCore($vgsOptions);

    require_once dirname(__FILE__) . '/widget.php';
    scbWidget::init('vgsWidget');

    if (is_admin()) {

        require_once dirname(__FILE__) . '/admin.php';
        require_once dirname(__FILE__) . '/admin-ajax.php';

        new vgsAdminList(__FILE__, array());
        new vgsAdminEdit(__FILE__, array());
        new vgsAdminConfig(__FILE__, $vgsOptions);
    }
}

scb_init('vgsInit');

