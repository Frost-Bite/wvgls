<?php

class vgsAdmin extends scbAdminPage
{

    protected $tableGames;
    protected $db;

    function __construct($file = false, $options = null)
    {
        global $wpdb;

        $this->db = $wpdb;
        $this->tableGames = $this->db->prefix . 'vgs_games';

        parent::__construct($file, $options);
    }

    function page_head()
    {
        wp_enqueue_style('vgs-admin', $this->plugin_url . 'assets/css/admin.css', array(), '1.1.0');

        wp_enqueue_script('vgs-admin', $this->plugin_url . 'assets/js/admin.js', array('jquery'), '1.1.0');
        wp_enqueue_script('jquery-ui-autocomplete');
    }

    function page_content() { }

    /**
     * Возвращает список наименований игр, добавленных в БД
     */
    public function getGamesNames()
    {
        $query = "
        (SELECT game_name AS name FROM wp_vgs_games)
        UNION
        (SELECT game_name_display AS name FROM wp_vgs_games WHERE game_name_display != '')
        ORDER BY name ASC";

        return $this->db->get_col($query);
    }

    public function getGamesNamesCount()
    {
        $query = "
        (SELECT game_name AS name, COUNT(*) AS num FROM wp_vgs_games GROUP BY name)
        UNION
        (SELECT game_name_display AS name, COUNT(*) AS num FROM wp_vgs_games WHERE game_name_display != '' GROUP BY name)
        ORDER BY name ASC";

        return $this->db->get_results($query, ARRAY_A);
    }

}

/**
 * Список добавленных игр
 * Class vgsAdminList
 */
class vgsAdminList extends vgsAdmin
{
    public function setup()
    {
        $this->args = array(
            'page_slug' => 'vgs',
            'page_title' => 'Video Game Streams',
            'toplevel' => 'menu',
        );
    }

    function page_loaded()
    {
        // Удаление каналов
        if (
            isset($_GET['action']) && $_GET['action'] == 'delete'
            && isset($_GET['checked']) && !empty($_GET['checked']) && is_array($_GET['checked'])
        ) {
            $idsStr = implode(',', $_GET['checked']);

            $query = 'DELETE FROM ' . $this->tableGames . ' WHERE id IN (' . $idsStr . ')';
            $this->db->query($query);

            header('Location:' . menu_page_url('vgs', false) . '&deleted=1', '200');
        }

        if (isset($_GET['deleted'])) {
            add_action('admin_notices', function() {
                echo scb_admin_notice('Каналы удалены', 'updated');
            } );
        }
    }

    public function page_content()
    {
        $totalItems = $this->db->get_var('SELECT COUNT(*) FROM '. $this->tableGames);
        $perPage = 1000;

        $wpTable = new WP_List_Table();
        $wpTable->set_pagination_args(array('total_items' => $totalItems, 'per_page' => $perPage));

        $limit = ' LIMIT ' . $perPage * ($wpTable->get_pagenum() - 1) . ', ' . $perPage;
        $query = 'SELECT * FROM ' . $this->tableGames . ' ' . $limit;
        $items = $this->db->get_results($query, ARRAY_A);

        // Кол-во просмотров стримов на twitch
        $channelsTwitch = $this->db->get_col('SELECT channel_twitch FROM ' . $this->tableGames . ' WHERE channel_twitch IS NOT NULL');
        $viewersTwitch = vgsRunApiAction('twitch', 'getStreamsViewers', $channelsTwitch);
        $viewersTwitch = $viewersTwitch['channelViewers'];

        // Кол-во просмотров стримов на goodgame
        $channelsGoodgame = $this->db->get_col('SELECT channel_goodgame FROM ' . $this->tableGames . ' WHERE channel_goodgame IS NOT NULL');
        $viewersGoodgame = vgsRunApiAction('goodgame', 'getStreamsViewers', $channelsGoodgame);
        $viewersGoodgame = $viewersGoodgame['channelViewers'];

        foreach ($items as &$item) {
            $item['viewers_twitch'] = $item['viewers_goodgame'] = 0;

            if (isset($viewersTwitch[$item['channel_twitch']]))
                $item['viewers_twitch'] = $viewersTwitch[$item['channel_twitch']];

            if (isset($viewersGoodgame[$item['channel_goodgame']]))
                $item['viewers_goodgame'] = $viewersGoodgame[$item['channel_goodgame']];

            $item['viewers'] = $item['viewers_twitch'] + $item['viewers_goodgame'];
        }

        usort($items, function($a, $b) {
            if ($a['viewers'] == $b['viewers']) {
                return 0;
            }
            return ($a['viewers'] > $b['viewers']) ? -1 : 1;
        });

?>
        <form action="">
            <input name="page" type="hidden" value="vgs"/>

            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <select name="action">
                        <option selected="selected" value="-1">Действия</option>
                        <option value="delete">Удалить</option>
                    </select>
                    <input type="submit" value="Применить" class="button action" id="doaction" name="">
                    <a class="button" href="<?= get_bloginfo('url') ?>/streams">Посмотреть стримы</a>
                </div>

                <?= $wpTable->pagination('top') ?>
            </div>

            <table class="wp-list-table widefat fixed">
                <thead>
                <tr>
                    <th class="manage-column column-cb check-column">
                        <input type="checkbox" id="cb-select-all-1">
                    </th>
                    <th class="manage-column">Twitch / Goodgame</th>
                    <th class="manage-column">Зрителей всего / twitch / goodgame</th>
                    <th class="manage-column">Язык</th>
                    <th class="manage-column">Имя автора</th>
                    <th class="manage-column">Название игры</th>
                    <th class="manage-column">Отображаемое название игры</th>
                    <th class="manage-column">Описание</th>
                    <th class="manage-column">Featured</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $num => $itemChannel): $num++?>
                    <tr class="<?= ($num % 2) ? 'alternate' : '' ?>">
                        <th class="check-column" scope="row">
                            <input type="checkbox" value="<?= $itemChannel['id'] ?>" name="checked[]">
                        </th>
                        <td>
                            <?php if ($itemChannel['channel_twitch']) : ?>
                            <a href="https://twitch.tv/<?= $itemChannel['channel_twitch'] ?>" target="_blank">
                                <?= $itemChannel['channel_twitch'] ?>
                            </a>
                            <?php else : ?>
                            -
                            <?php endif; ?>

                            /

                            <?php if ($itemChannel['channel_goodgame']) : ?>
                            <a href="https://goodgame.ru/channel/<?= $itemChannel['channel_goodgame'] ?>" target="_blank">
                                <?= $itemChannel['channel_goodgame'] ?>
                            </a>
                            <?php else : ?>
                            -
                            <?php endif; ?>

                            <div class="row-actions">
                                <span class="edit">
                                    <a title="Редактировать" href="<?= menu_page_url('vgs/add', false) ?>&vgs-action=edit&channel-id=<?= $itemChannel['id'] ?>">
                                        Редактировать
                                    </a> |
                                </span>

                                <span class="trash">
                                    <a href="<?= menu_page_url('vgs', false) ?>&action=delete&checked[]=<?= $itemChannel['id'] ?>" class="submitdelete">
                                        Удалить
                                    </a>
                                </span>
                            </div>
                        </td>
                        <td><?= $itemChannel['viewers'] ?> / <?= $itemChannel['viewers_twitch'] ?> / <?= $itemChannel['viewers_goodgame'] ?></td>
                        <td><?= $itemChannel['lang'] ?></td>
                        <td><?= ($itemChannel['author_display']) ? $itemChannel['author_display'] : $itemChannel['channel'] ?></td>
                        <td><?= $itemChannel['game_name'] ?></td>
                        <td><?= $itemChannel['game_name_display'] ?></td>
                        <td><?= $itemChannel['description'] ?></td>
                        <td><?= $itemChannel['featured'] ? '<i class="featured"></i>' : ''?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <div class="tablenav bottom">
                <?= $wpTable->pagination('bottom') ?>
            </div>

        </form>
<?php
    }

}

class vgsAdminEdit extends vgsAdmin
{

    private $action = 'add';

    /**
     * ID канала, редактирование которого происходит
     * @var int
     */
    private $channelIdEdit;

    private $formData = array();

    private $formFields = array(
        'channel_twitch' => array(
            'title' => 'Ссылка на канал twitch.tv',
            'type'  => 'text',
            'name'  => 'channel_twitch',
            'extra' => array('class' => 'regular-text'),
            'desc'  => '<p>Например, https://twitch.tv/beyondthesummit</p>',
        ),
        'channel_goodgame' => array(
            'title' => 'Ссылка на канал goodgame.ru',
            'type'  => 'text',
            'name'  => 'channel_goodgame',
            'extra' => array('class' => 'regular-text'),
            'desc'  => '<p>Например, https://goodgame.ru/channel/Miker/</p>',
        ),
        'author_display' => array(
            'title' => 'Отображаемое имя автора канала',
            'type'  => 'text',
            'name'  => 'author_display',
            'desc'  => '<p>Например, BeyondTheSummit</p>',
        ),
        'game_name' => array(
            'title' => 'Название игры',
            'type'  => 'text',
            'name'  => 'game_name',
            'extra' => array('required' => 'required', 'class' => 'regular-text'),
        ),
        'game_name_display' => array(
            'title' => 'Отображать как',
            'type'  => 'text',
            'name'  => 'game_name_display',
            'desc'  => '<p>Если не указано, то будет браться значение из поля "Название игры"</p>',
        ),
        'description' => array(
            'title' => 'Описание',
            'type'  => 'textarea',
            'name'  => 'description',
            'extra' => array('rows' => 5, 'class' => 'large-text')
        ),
        'lang' => array(
            'title' => 'Язык',
            'name'  => 'lang',
            'type'  => 'select',
            'choices' => array(
                'ru' => 'RU',
                'en' => 'EN'
            ),
        ),
        'featured' => array(
            'title' => 'Featured',
            'name'  => 'featured',
            'type'  => 'checkbox',
        )
    );

    function setup()
    {
        $this->args = array(
            'page_slug' => 'vgs/add',
            'page_title' => 'Добавить канал',
            'parent' => 'vgs',
        );

        if (
            isset($_GET['vgs-action'])
            && $_GET['vgs-action'] == 'edit'
            && isset($_GET['channel-id'])
            && $this->db->get_var('SELECT COUNT(*) FROM ' . $this->tableGames . ' WHERE id = ' . $_GET['channel-id'])
        ) {
            $this->action = 'edit';
            $this->channelIdEdit = $_GET['channel-id'];

            $this->formData = $this->db->get_row(
                'SELECT * FROM ' . $this->tableGames . ' WHERE id = ' . $this->channelIdEdit, ARRAY_A
            );
            $this->formData['channel_twitch'] = ($this->formData['channel_twitch']) ? 'http://www.twitch.tv/' . $this->formData['channel_twitch'] : '';
            $this->formData['channel_goodgame'] = ($this->formData['channel_goodgame']) ? 'http://goodgame.ru/' . $this->formData['channel_goodgame'] : '';
        }
    }

    function page_loaded()
    {
        if ( $this->formValidate() ) {
            $this->updateChannel($this->formData);
        }

    }

    /**
     * Валидация формы добавления канала
     * @return bool
     */
    function formValidate()
    {
        if (!isset($_POST['submit'])) return false;

        $formData = wp_array_slice_assoc($_POST, array_keys($this->formFields) );
        $formData = array_map('trim', stripslashes_deep($formData));
        $errorMsg = false;

        if (!$formData['channel_twitch'] && !$formData['channel_goodgame']) {
            $errorMsg = 'Вы не указали ссылку на канал';
            goto end;
        }

        $channelDataTwitch = parseChannelUrl($formData['channel_twitch']);
        $channelDataGoodgame = parseChannelUrl($formData['channel_goodgame']);

        if (
            $channelDataTwitch &&
            !vgsRunApiAction($channelDataTwitch['site'], 'getChannelInfo', $channelDataTwitch['channel'])
        ) {
            $errorMsg = 'Вы указали неверную ссылку на канал twitch';
        }

        if (
            $channelDataGoodgame &&
            !vgsRunApiAction($channelDataGoodgame['site'], 'getChannelInfo', $channelDataGoodgame['channel'])
        ) {
            $errorMsg = 'Вы указали неверную ссылку на канал goodgame';
        }

        if (
            $channelDataTwitch['channel'] &&
            $this->action == 'add' &&
            $this->db->get_var('SELECT COUNT(*) FROM ' . $this->tableGames . ' WHERE channel_twitch = "' . $channelDataTwitch['channel'] . '"')
        ) {
            $errorMsg = 'Указанный канал twitch уже был добавлен';
        }

        if (
            $channelDataGoodgame['channel'] &&
            $this->action == 'add' &&
            $this->db->get_var('SELECT COUNT(*) FROM ' . $this->tableGames . ' WHERE channel_goodgame = "' . $channelDataGoodgame['channel'] . '"')
        ) {
            $errorMsg = 'Указанный канал goodgame уже был добавлен';
        }

        if ( !$formData['game_name'] ) {
            $errorMsg = 'Вы не указали название игры';
        }

        if ( !$formData['lang'] ) {
            $errorMsg = 'Вы не указали язык';
        }

        end:

        if ($channelDataTwitch) $formData['channel_twitch_name'] = $channelDataTwitch['channel'];
        if ($channelDataGoodgame) $formData['channel_goodgame_name'] = $channelDataGoodgame['channel'];

        $this->formData = $formData;

        if ($errorMsg) {
            add_action('admin_notices', function() use($errorMsg) {
                echo scb_admin_notice($errorMsg, 'error');
            } );

            return false;
        }

        return true;
    }

    function page_header() {
        $pageTitle = ($this->action == 'add') ? 'Добавить канал' : 'Изменить канал';

        echo "<div class='wrap'>\n";
        echo html('h2', $pageTitle);
    }

    function page_content()
    {

        $pageTitle = ($this->action == 'add') ? 'Добавить канал' : 'Изменение канала ' . $this->formData['channel_url'];

        $outButtonTwitch = '<br><div><button id="get-channel-twitch-info" class="button">Получить данные через API</button><div id="process-load-data-twitch">';
        $outButtonGoodgame = '<br><div><button id="get-channel-goodgame-info" class="button">Получить данные через API</button><div id="process-load-data-goodgame">';

        $outButtonLoad = '
                <div class="load-wrap">
                    <div id="floatingCirclesG">
                        <div class="f_circleG" id="frotateG_01">
                        </div>
                        <div class="f_circleG" id="frotateG_02">
                        </div>
                        <div class="f_circleG" id="frotateG_03">
                        </div>
                        <div class="f_circleG" id="frotateG_04">
                        </div>
                        <div class="f_circleG" id="frotateG_05">
                        </div>
                        <div class="f_circleG" id="frotateG_06">
                        </div>
                        <div class="f_circleG" id="frotateG_07">
                        </div>
                        <div class="f_circleG" id="frotateG_08">
                        </div>
                    </div>
                </div>
            </div>';
        $outButtonTwitch .= $outButtonLoad . '</div>';
        $outButtonGoodgame .= $outButtonLoad . '</div>';

        $this->formFields['channel_twitch']['desc'] .= $outButtonTwitch;
        $this->formFields['channel_goodgame']['desc'] .= $outButtonGoodgame;

        $out = html('h3', $pageTitle) . $this->table($this->formFields, $this->formData);

        $out = $this->form_wrap($out, array('value' => 'Сохранить', 'class' => 'button button-primary'));

        echo html('div id="vgs-edit-form"', $out);

        if ($this->action == 'add') {
            echo "<script>
                jQuery(document).ready(function($) {
                    $('#vgs-edit-form form input:text').val('');

                    var gamesNames = " . json_encode( $this->getGamesNames() ) . ";

                    $('input[name=game_name_display]').autocomplete({
                        source: gamesNames,
                        minLength: 0
                    }).focus(function(){
                        if (this.value == '')
                            $(this).autocomplete('search');
                    });
                });
            </script>";
        }
    }

    function updateChannel($formData)
    {
        $insertData = array(
            'channel_twitch'    => strtolower($formData['channel_twitch_name']),
            'channel_goodgame'  => strtolower($formData['channel_goodgame_name']),
            'author_display'    => $formData['author_display'],
            'description'       => $formData['description'],
            'lang'              => $formData['lang'],
            'game_name'         => $formData['game_name'],
            'game_name_display' => $formData['game_name_display'],
            'featured'          => (isset($formData['featured'])) ? 1 : 0,
        );

        $message = '';

        if ($this->action == 'add') {
            $this->db->insert($this->tableGames, $insertData);
            $message = 'Канал добавлен';
        } elseif ($this->action == 'edit') {
            $this->db->update( $this->tableGames, $insertData, array('id' => $this->channelIdEdit) );
            $message = 'Канал обновлен';
        }

        add_action('admin_notices', function() use($message) {
            echo scb_admin_notice($message, 'updated');
        } );
    }
}

class vgsAdminConfig extends vgsAdmin
{
    function setup()
    {
        $this->args = array(
            'page_slug' => 'vgs/config',
            'page_title' => 'Настройки',
            'parent' => 'vgs',
        );
    }

    function page_loaded()
    {
        $this->form_handler();

        // Сохранение информации об играх, которые необходимо отображать в видежете
        if (isset($_POST['save_display_in_widget_info'])) {
            $gamesDisplay = $priorityDisplay = $gamesFilter = array();

            if ($_POST['display_widget']) {
                foreach ($_POST['display_widget'] as $gameName => $display) {
                    $gamesDisplay[] = htmlspecialchars_decode($gameName);
                    $priorityDisplay[] = (int) $_POST['priority_widget'][$gameName];
                }
            }

            if ($_POST['display_filter']) {
                foreach ($_POST['display_filter'] as $gameName => $display) {
                    $gamesFilter[] = htmlspecialchars_decode($gameName);
                }
            }

            update_option('vgs_display_in_widget', maybe_serialize($gamesDisplay));
            update_option('vgs_priority_in_widget', maybe_serialize($priorityDisplay));
            update_option('vgs_display_in_filter', maybe_serialize($gamesFilter));
        }
    }

    function page_content()
    {
        // Общие настройки
        $rows = array(
            array(
                'title' => 'Отображать стримы при количестве зрителей более',
                'type' => 'text',
                'name' => 'min_viewers_table'
            ),
            array(
                'title' => 'Частота обновления кол-ва зрителей (сек)',
                'type' => 'text',
                'name' => 'time_update_cache'
            ),
        );

        $out = html('h3', 'Настройки');
        $out .= $this->table($rows);
        $out = html('div id="vgs-config"', $out);
        echo $this->form_wrap($out, array('value' => 'Сохранить настройки', 'class' => 'button button-primary'));

        // Настройки игр
        $displayInWidget = get_option('vgs_display_in_widget', true);
        $displayInWidget = maybe_unserialize($displayInWidget);
        if (!is_array($displayInWidget)) $displayInWidget = array();

        $priorityDisplay = get_option('vgs_priority_in_widget', true);
        $priorityDisplay = maybe_unserialize($priorityDisplay);
        if (!is_array($priorityDisplay)) $priorityDisplay = array();

        $displayInFilter = get_option('vgs_display_in_filter', true);
        $displayInFilter = maybe_unserialize($displayInFilter);
        if (!is_array($displayInFilter)) $displayInFilter = array();

        $games = $this->getGamesNamesCount();

        foreach ($games as &$item) {
            if (  ($keyItem = array_search($item['name'], $displayInWidget)) !== false) {
                $item['widget'] = true;
                $item['priority'] = $priorityDisplay[$keyItem];
            } else {
                $item['widget'] = false;
                $item['priority'] = 0;
            }

            if (in_array($item['name'], $displayInFilter)) {
                $item['filter'] = true;
            } else {
                $item['filter'] = false;
            }
        }

?>
        <div id="vgs-games-list-form">

            <h3>Список игр</h3>

            <?php if ($games && !empty($games)) : ?>
            <form action="" method="POST">
                <table class="wp-list-table widefat fixed">
                    <thead>
                    <tr>
                        <th class="manage-column">Название игры</th>
                        <th class="manage-column">Кол-во каналов</th>
                        <th class="manage-column">Выводить в виджете</th>
                        <th class="manage-column">Приоритет при выводе в виджете</th>
                        <th class="manage-column">Выводить в фильтре игр</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($games as $gameItem): ?>
                    <tr>
                        <td class="game-name">
                            <?= $gameItem['name'] ?>
                        </td>
                        <td><?= $gameItem['num'] ?></td>
                        <td class="display-widget">
                            <input type="checkbox" name="display_widget[<?= htmlspecialchars($gameItem['name']) ?>]" id="display_widget" <?= ($gameItem['widget']) ? 'checked' : '' ?>/>
                        </td>
                        <td class="priority-widget">
                            <input type="text" name="priority_widget[<?= htmlspecialchars($gameItem['name']) ?>]" value="<?= ($gameItem['priority']) ? (int) $gameItem['priority'] : 0 ?>"/>
                        </td>
                        <td class="display-widget">
                            <input type="checkbox" name="display_filter[<?= htmlspecialchars($gameItem['name']) ?>]" <?= ($gameItem['filter']) ? 'checked' : '' ?>/>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <p>
                    <input type="submit" name="save_display_in_widget_info" value="Сохранить" class="button button-primary"/>
                </p>
            </form>
            <?php else: ?>
                <p>Вы ещё не добавили стримы.</p>
            <?php endif; ?>

        </div>

<?php
    }
}
