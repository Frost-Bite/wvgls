<?php

class vgsWidget extends scbWidget
{
    protected $defaults = array(
        'title' => 'Стримы игр',
        'description' => 'Выводит список стримов игр'
    );

    function __construct() {
        parent::__construct( 'vgs-widget', 'Стримы игр', array(
            'description' => 'Выводит список стримов игр'
        ) );
    }

    function form( $instance ) {
        echo html( 'p', $this->input( array(
            'type' => 'text',
            'name' => 'title',
            'desc' => 'Заголовок:'
        ), $instance ) );

        echo html( 'p', $this->input( array(
            'type' => 'text',
            'name' => 'num',
            'desc' =>  'Количество стримов у игры:'
        ), $instance ) );

        echo html( 'p', $this->input( array(
            'type' => 'textarea',
            'extra' => 'style="width: 100%"',
            'name' => 'text_after',
            'desc' =>  'Текст после:<br/>'
        ), $instance ) );
    }

    function content( $instance )
    {
        global $wpdb, $vgsCore;

        if (!$instance['num']) {
            $instance['num'] = 2;
        }

        $channels = $vgsCore->getChannels();
        $channels = $channels['channels'];

        usort($channels, function($a, $b) {
            if ($a['viewers'] == $b['viewers']) return 0;
            if (isset($a['featured'])) return -1;
            return ($a['viewers'] > $b['viewers']) ? -1 : 1;
        });

        $games = array();
        foreach ($channels as $item) {
            if ($item['viewers'] == 0) {
                continue;
            }

            $games[$item['game_name_display']][] = $item;
        }

        $displayInWidget = get_option('vgs_display_in_widget', true);
        $displayInWidget = maybe_unserialize($displayInWidget);
        if (!is_array($displayInWidget)) $displayInWidget = array();

        $priorityDisplay = get_option('vgs_priority_in_widget', true);
        $priorityDisplay = maybe_unserialize($priorityDisplay);
        if (!is_array($priorityDisplay)) $priorityDisplay = array();

        // Сортируем игры по заданному приоритету
        uksort($games, function($a, $b) use ($displayInWidget, $priorityDisplay) {
            $priorityA = $priorityB = 0;

            if ( ($keyItem = array_search($a, $displayInWidget)) !== false) {
                $priorityA = (isset($priorityDisplay[$keyItem])) ? (int) $priorityDisplay[$keyItem] : 0;
            }

            if ( ($keyItem = array_search($b, $displayInWidget)) !== false) {
                $priorityB = (isset($priorityDisplay[$keyItem])) ? (int) $priorityDisplay[$keyItem] : 0;
            }

            if ($priorityA == $priorityB) return 0;
            else return ($priorityA > $priorityB) ? -1 : 1;
        });

        $out = '<ul class="vgs-widget">';

        $numDisplayGames = 0;
        foreach ($games as $gameName => $channels) {
            if (!in_array($gameName, $displayInWidget)) continue;
            if (!empty($channels)) {
                $numDisplayGames = 1;
            }
        }

        foreach ($games as $gameName => $channels) {
            if (!in_array($gameName, $displayInWidget)) continue;

            $out .= '<li class="game-name">' . $gameName . '</li>';
            $out .= '<li><ul class="channels">';

            usort($channels, function($a, $b) {
                if ($a['viewers'] == $b['viewers']) {
                    return 0;
                }
                return ($a['viewers'] > $b['viewers']) ? -1 : 1;
            });

            foreach ($channels as $num => $channel) { $num++;
                if ($num > $instance['num']) continue;

                $out .= '<li>
                    <a href="' . get_bloginfo('url') . '/streams/#channel-' . $channel['id'] . '">'
                    . $channel['author_display']
                    . '</a> <span class="viewers">' . $channel['viewers'] . '</span></li>';
            }

            $out .= '</ul></li>';
        }

        $out .= '</ul>';

        $out .= $instance['text_after'];

        echo $out;
    }
}