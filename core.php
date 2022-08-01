<?php

class vgsCore
{
    protected $tableGames;
    protected $db;
    protected $options;

    public function __construct($options)
    {
        global $wpdb;

        $this->db = $wpdb;
        $this->tableGames = $this->db->prefix . 'vgs_games';
        $this->options = $options;

        add_action('wp', array($this, 'actionWp'));
        add_shortcode('vgs-streams', array($this, 'showTableStreams'));

        add_action('wp_ajax_nopriv_vgs_get_table_data', array($this, 'showTableBodyAjax'));
        add_action('wp_ajax_vgs_get_table_data', array($this, 'showTableBodyAjax'));

        add_action('wp_head', function() use($options) {

            // Если загружается страница
            if (is_single() || is_page()) {

                $pageId = get_the_ID();
                $page = get_post($pageId);

                // Если на странице используются шоркоды плагина
                if (preg_match('#\[vgs-streams#suix', $page->post_content)) {

                    if (!is_admin()) {
                        ?>
                        <script type="text/javascript">
                            var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
                            var vgsTimeUpdateTable = <?= $options->get('time_update_cache') * 1000 ?>;
                        </script>
                        <script src="https://player.twitch.tv/js/embed/v1.js"></script>
                        <?php
                    }
                }
            }

        });

        if (!is_admin()) {
            add_filter('sidebars_widgets', array($this, 'unregisterWidget'));
        }
    }

    /**
     * Деактивация виджета, если нет стримов для него
     * @param $sidebars
     * @return mixed
     */
    public function unregisterWidget($sidebars)
    {
        $displayInWidget = get_option('vgs_display_in_widget', true);
        $displayInWidget = maybe_unserialize($displayInWidget);
        if (!is_array($displayInWidget)) $displayInWidget = array();

        $channels = $this->getChannels();
        $channels = $channels['channels'];

        $games = array();
        foreach ($channels as $item) {
            if ($item['viewers'] == 0) {
                continue;
            }

            $games[$item['game_name_display']][] = $item;
        }

        $numDisplayGames = 0;
        foreach ($games as $gameName => $channels) {
            if (!in_array($gameName, $displayInWidget)) continue;
            if (!empty($channels)) {
                $numDisplayGames = 1; break;
            }
        }

        if (!$numDisplayGames) {
            foreach ($sidebars as $sKey => $widgets) {
                foreach ($widgets as $wKey => $widget) {
                    if (preg_match('#vgs-widget#', $widget)) {
                        unset($sidebars[$sKey][$wKey]);
                    }
                }
            }
        }

        return $sidebars;
    }

    public function actionWp()
    {
        // Если загружается страница
        if (is_single() || is_page()) {

            $pageId = get_the_ID();
            $page = get_post($pageId);

            // Если на странице используются шоркоды плагина
            if (preg_match('#\[vgs-streams#suix', $page->post_content)) {

                if (!is_admin()) {
                    wp_enqueue_style('vgs-core', plugin_dir_url(__FILE__) . 'assets/css/core.css', array(), '1.3.5');

                    wp_enqueue_script('jquery-scrollTo', plugin_dir_url(__FILE__) . 'assets/js/jquery.scrollTo-min.js', array('jquery'), '1.4.2', true);
                    wp_enqueue_script('animator', plugin_dir_url(__FILE__) . 'assets/js/animator.js', array('jquery'), '1.1.10.2', true);
                    wp_enqueue_script('rankingTableUpdate', plugin_dir_url(__FILE__) . 'assets/js/rankingTableUpdate.js', array('jquery'), '1.0.4', true);
                    wp_enqueue_script('vgs-core', plugin_dir_url(__FILE__) . 'assets/js/core.js', array('jquery', 'jquery-scrollTo'), '1.4.4', true);
                }
            }
        }
    }

    public function getChannels()
    {
        $channels = $this->db->get_results('SELECT * FROM ' . $this->tableGames, ARRAY_A);

        // Кол-во просмотров стримов на twitch
        $channelsTwitch = $this->db->get_col('SELECT channel_twitch FROM ' . $this->tableGames . ' WHERE channel_twitch IS NOT NULL');
        $viewersTwitch = vgsRunApiAction('twitch', 'getStreamsViewers', $channelsTwitch);
        $channelsDataTwitch = $viewersTwitch['channelsData'];
        $viewersTwitch = $viewersTwitch['channelViewers'];

        // Кол-во просмотров стримов на goodgame
        $channelsGoodgame = $this->db->get_col('SELECT channel_goodgame FROM ' . $this->tableGames . ' WHERE channel_goodgame IS NOT NULL');
        $viewersGoodgame = vgsRunApiAction('goodgame', 'getStreamsViewers', $channelsGoodgame);
        $channelsDataGoodgame = $viewersGoodgame['channelsData'];
        $viewersGoodgame = $viewersGoodgame['channelViewers'];


        $channelsDataTwitchArray = array();
        if (isset($channelsDataTwitch->streams) && is_array($channelsDataTwitch->streams)) {
            foreach ($channelsDataTwitch->streams as $channelsDataItem) {
                $channelsDataTwitchArray[$channelsDataItem->channel->name] = $channelsDataItem;
            }
        }

        // Самый популярный канал
        $topChannel = false;
        $topChannelViewers = 0;

        // Каналы, которые будут выводиться на странице
        $viewChannels = array();

        // Предыдущее кол-во зрителей
        if (!$prevViewersTwitch = get_option('vgs_channels_viewers_twitch')) {
            $prevViewersTwitch  = array();
        } else {
            $prevViewersTwitch  = maybe_unserialize($prevViewersTwitch );
        }

        if (!$prevViewersGoodgame= get_option('vgs_channels_viewers_goodgame')) {
            $prevViewersGoodgame = array();
        } else {
            $prevViewersGoodgame = maybe_unserialize($prevViewersGoodgame);
        }


        // Сравниваем названия игр из БД с данными, полученными от API
        foreach ($channels as &$channelsItem) {
            if ( isset($channelsDataTwitchArray[$channelsItem['channel_twitch']]) ) {
                if ( mb_strtolower($channelsDataTwitchArray[$channelsItem['channel_twitch']]->game) != mb_strtolower($channelsItem['game_name']) ) {
                    $newName = $channelsDataTwitchArray[$channelsItem['channel_twitch']]->game;
                    $channelsItem['game_name'] = $newName;

                    $this->db->query('UPDATE ' . $this->tableGames . ' SET game_name = "' . $newName . '" WHERE id = ' . $channelsItem['id']);
                }
            } elseif ( isset($channelsDataGoodgame[$channelsItem['channel_goodgame']]) ) {
                if ( mb_strtolower($channelsDataGoodgame[$channelsItem['channel_goodgame']]['game']) != mb_strtolower($channelsItem['game_name']) ) {
                    $newName = $channelsDataGoodgame[$channelsItem['channel_goodgame']]['game'];
                    $channelsItem['game_name'] = $newName;

                    $this->db->query('UPDATE ' . $this->tableGames . ' SET game_name = "' . $newName . '" WHERE id = ' . $channelsItem['id']);
                }
            }
        }

        foreach ($channels as &$item) {
            if (!$item['author_display']) {
                if ($item['channel_twitch'])
                    $item['author_display'] = $item['channel_twitch'];
                else
                    $item['author_display'] = $item['channel_goodgame'];
            }

            if (!$item['game_name_display']) {
                $item['game_name_display'] = $item['game_name'];
            }


            $item['viewers'] = 0;
            $item['viewers_twitch'] = 0;
            $item['viewers_goodgame'] = 0;

            if (isset($viewersTwitch[$item['channel_twitch']])) {
                $item['viewers'] += $viewersTwitch[$item['channel_twitch']];
                $item['viewers_twitch'] += $viewersTwitch[$item['channel_twitch']];
            }
            if (isset($viewersGoodgame[$item['channel_goodgame']])) {
                $item['viewers'] += $viewersGoodgame[$item['channel_goodgame']];
                $item['viewers_goodgame'] += $viewersGoodgame[$item['channel_goodgame']];
            }

            $item['link_twitch'] = 'https://www.twitch.tv/' . $item['channel_twitch'];
            $item['link_goodgame'] = 'https://goodgame.ru/channel/' . $item['channel_goodgame'];


            $item['prev_viewers'] = 0;

            if (isset($prevViewersTwitch[$item['channel_twitch']]))
                $item['prev_viewers'] += $prevViewersTwitch[$item['channel_twitch']];
            if (isset($prevViewersGoodgame[$item['channel_goodgame']]))
                $item['prev_viewers'] += $prevViewersGoodgame[$item['channel_goodgame']];

            if (isset($channelsDataGoodgame[$item['channel_goodgame']]))
                $item['goodgame_id'] = $channelsDataGoodgame[$item['channel_goodgame']]['player_id'];
            else
                $item['goodgame_id'] = '';

            if (
                $item['viewers'] > $this->options->get('min_viewers_table')
                || ( $item['featured'] && $item['viewers'] >= 1)
            ) {
                $viewChannels[] = $item;
            }

            if ($topChannelViewers < $item['viewers']) {
                $topChannelViewers = $item['viewers'];
                $topChannel = $item;
            }

        }

        usort($viewChannels, function($a, $b) {
            if ($a['viewers'] == $b['viewers']) {
                return 0;
            }
            return ($a['viewers'] > $b['viewers']) ? -1 : 1;
        });

        return array('channels' => $viewChannels, 'top_channel' => $topChannel);
    }

    public function showTableStreams()
    {

        $channels = $this->getChannels();
        $topChannel = $channels['top_channel'];
        $channels = $channels['channels'];

        $displayInFilter = get_option('vgs_display_in_filter', true);
        $displayInFilter = maybe_unserialize($displayInFilter);
        if (!is_array($displayInFilter)) $displayInFilter = array();

        $displayInTable = array();
        foreach ($channels as $channelItem) {
            $displayInTable[] = $channelItem['game_name_display'];
        }

        $userAgenr = $_SERVER['HTTP_USER_AGENT'];
        $html5Player = (preg_match('#iPhone|iPod|iPad|Android#', $userAgenr)) ? true : false;

        // Определяем какой сервер будет открыт первым
        $twitchShow = true;
        if ($topChannel['channel_twitch'] && $topChannel['channel_goodgame'] && $topChannel['viewers_twitch'] == 0)
            $twitchShow = false;
?>
        <div id="vgs-streams">

            <div id="player-twitch" <?= (!$twitchShow) ? 'style="display:none"' : ''; ?>>
                <div class="stream">
                    <div id="player-twitch-frame" data-default-channel="<?= $topChannel['channel_twitch'] ?>"></div>
                </div>

                <div class="stream-action">
                    <p>
                        <a class="open-chat-twitch" data-channel="<?= $topChannel['channel_twitch'] ?>" href="javascript:void(null)">
                            Открыть окно чата <span class="channel-twitch"><?= $topChannel['author_display'] ?></span>
                        </a>

                        <br/>

                        <a class="open-site-twitch" href="<?= $topChannel['link_twitch'] ?>" target="_blank">
                            Смотреть <span class="channel-twitch"><?= $topChannel['channel_twitch'] ?></span>
                            на <span class="site">Twitch</span>
                        </a>
                    </p>

                    <div class="toggle-servers">
                        <button class="toggle-server" data-show="twitch">Twtich (<span><?= $topChannel['viewers_twitch'] ?></span>)</button>
                        <button class="toggle-server" data-show="goodgame">Goodgame (<span>(<?= $topChannel['viewers_goodgame'] ?></span>)</button>
                    </div>
                </div>
            </div>

            <div id="player-goodgame" <?= ($twitchShow) ? 'style="display:none"' : ''; ?>>
                <div class="stream">
                    <iframe frameborder="0" width="800" height="480" src="https://goodgame.ru/player?<?= $topChannel['goodgame_id'] ?>"></iframe>
                </div>

                <div class="stream-action">
                    <p>
                        <a class="open-chat-goodgame" data-channel="<?= $topChannel['channel_goodgame'] ?>" href="javascript:void(null)">
                            Открыть окно чата <span class="channel-goodgame"><?= $topChannel['author_display'] ?></span>
                        </a>

                        <br/>

                        <a class="open-site-goodgame" href="<?= $topChannel['link_goodgame'] ?>" target="_blank">
                            Смотреть <span class="channel-goodgame"><?= $topChannel['channel_goodgame'] ?></span>
                            на <span class="site-goodgame">Goodgame</span>
                        </a>
                    </p>

                    <div class="toggle-servers">
                        <button class="toggle-server" data-show="twitch">Twtich (<span><?= $topChannel['viewers_twitch'] ?></span>)</button>
                        <button class="toggle-server" data-show="goodgame">Goodgame (<span>(<?= $topChannel['viewers_goodgame'] ?></span>)</button>
                    </div>
                </div>
            </div>

            <div id="vgs-filter">
                <select name="games_show" id="games_show">
                    <option value="all">Все игры</option>
                    <?php foreach ($displayInFilter as $itemFilter) : ?>
                        <option><?= $itemFilter ?></option>
                    <?php endforeach; ?>
                    <option value="other">Другие игры</option>
                </select>
				<div class="boxes">
				<input type="checkbox" name="lang_ru" id="lang_ru"/>
                <label for="lang_ru"> только на русском</label>
				</div>
            </div>


            <div style="position: relative">
                <div id="border-vgs-streams-table"></div>
                <div id="vgs-streams-table">
                    <table>
                        <thead>
                            <tr>
                                <th class="anim:id column-author">Канал (автор)</th>
                                <th class="anim:constant column-lang">Язык</th>
                                <th class="anim:constant column-site">Сервер</th>
                                <th class="anim:constant column-game-name">Игра</th>
                                <th class="anim:constant column-viewers">Зрителей</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($channels)): ?>
                            <?php $this->showTableBody($channels) ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5">В данный момент технические неисправности, сохраняйте спокойствие, скоро все заработает</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <script>
            var displayInFilter = <?= json_encode($displayInFilter) ?>,
                displayInTable = <?= json_encode($displayInTable) ?>;
        </script>
<?php
    }

    public function showTableBody($channels)
    {
?>
    <?php foreach ($channels as $num => $item): ?>
        <tr onclick="" data-id="<?= $item['id']?>" data-goodgame-id="<?= $item['goodgame_id']?>"
            data-channel-twitch="<?= $item['channel_twitch'] ?>"
            data-channel-goodgame="<?= $item['channel_goodgame'] ?>"
            data-link-twitch="<?= $item['link_twitch'] ?>"
            data-link-goodgame="<?= $item['link_goodgame'] ?>"
            data-viewers-twitch="<?= $item['viewers_twitch'] ?>"
            data-viewers-goodgame="<?= $item['viewers_goodgame'] ?>">

            <td class="column-author"><?= $item['author_display'] ?><?= $item['featured'] ? '<i class="fa fa-star"></i>' : ''?></td>
            <td class="column-lang"><?= strtoupper($item['lang']) ?></td>
            <td class="column-site">
                <?php
                    if ($item['channel_twitch'] && $item['channel_goodgame']) echo 'twitch / goodgame';
                    elseif ($item['channel_twitch']) echo 'twitch';
                    elseif ($item['channel_goodgame']) echo 'goodgame';
                ?>
            </td>
            <td class="column-game-name"><?= $item['game_name_display'] ?></td>
            <td class="column-viewers">

                <?php if ( ($item['viewers'] - $item['prev_viewers']) >= 50) : ?>
                    <span style="color:green">↑</span>
                <?php elseif ( ($item['prev_viewers'] - $item['viewers']) >= 50) : ?>
                    <span style="color:red">↓</span>
                <?php endif; ?>

                <?= $item['viewers'] ?>
            </td>
        </tr>
    <?php endforeach; ?>

<?php
    }

    public function showTableBodyAjax()
    {
        $channels = $this->getChannels();
        $this->showTableBody($channels['channels']);
        exit();
    }
}