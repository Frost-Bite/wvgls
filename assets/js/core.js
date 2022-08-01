jQuery(document).ready(function($) {

    vgsZebraStripingBgTable();
    vgsFilterSelectVisibleOptions();

    // Запуск стрима указанного канала
    function openChannel(channelId, runStream)
    {
        if ( $('#vgs-streams-table tbody tr[data-id=' + channelId + ']').length == 0 ) {
            return false;
        }

        var tableItem = $('#vgs-streams-table tbody tr[data-id=' + channelId + ']');

        if ($(tableItem).is('.selected')) {
            return false;
        }

        $('#vgs-streams-table tbody tr').removeClass('selected');

        var channelTwitch = $(tableItem).attr('data-channel-twitch'),
            channelGoodgame = $(tableItem).attr('data-channel-goodgame'),
            goodgameId = $(tableItem).attr('data-goodgame-id'),
            author = $(tableItem).find('td.column-author').text().trim(),
            linkTwitch = $(tableItem).attr('data-link-twitch'),
            linkGoodgame = $(tableItem).attr('data-link-goodgame'),
            viewersTwitch = $(tableItem).attr('data-viewers-twitch'),
            viewersGoodgame = $(tableItem).attr('data-viewers-goodgame');

        $(tableItem).addClass('selected');

        if (runStream) {
            var player, value, playerGoodgame, valueGoodgame;

            if (window.twitch_player != undefined) {
                window.twitch_player.setChannel(channelTwitch);
            } else {
                var options = {
                    channel: channelTwitch
                };
                window.twitch_player = new Twitch.Player("player-twitch-frame", options);
            }


            //$('#vgs-streams #player-twitch .stream').html(player);


            if ($('#player-goodgame iframe').is('[data-src]') && $('#player-goodgame iframe').attr('data-src').length > 0) {
                $('#player-goodgame iframe').attr('src', $('#player-goodgame iframe').attr('data-src') );
                $('#player-goodgame iframe').attr('data-src', '');
            }

            playerGoodgame = $('#vgs-streams #player-goodgame iframe').clone();
            //valueGoodgame = $(playerGoodgame).attr('src').replace(/player\?.*$/, 'player?'+goodgameId+'');
            $(playerGoodgame).attr('src', 'https://goodgame.ru/player?' + goodgameId);

            $('#vgs-streams #player-goodgame .stream').html(playerGoodgame);

            if ( !channelTwitch || (channelTwitch && channelGoodgame && parseInt(viewersTwitch) == 0) ) {
                $('#player-twitch').hide();
                window.twitch_player.pause();
                $('#player-goodgame').show();
            } else {
                $('#player-twitch').show();
                $('#player-goodgame').hide();
            }

            $.scrollTo('#vgs-streams .stream');
        }

        $('.stream-action a.open-chat-twitch').attr('data-channel', channelTwitch);
        $('.stream-action a.open-site-twitch').attr('href', linkTwitch);
        $('.stream-action span.channel-twitch').text(author);

        $('.stream-action a.open-chat-goodgame').attr('data-channel', channelGoodgame);
        $('.stream-action a.open-site-goodgame').attr('href', linkGoodgame);
        $('.stream-action span.channel-goodgame').text(author);

        $('.stream-action button[class=toggle-server][data-show=twitch] span').text(viewersTwitch);
        $('.stream-action button[class=toggle-server][data-show=goodgame] span').text(viewersGoodgame);

        $('div.post h2:eq(0), title').text(author + ' - Трансляции');

        if ( channelTwitch != '' && channelGoodgame != '' && parseInt(viewersTwitch) > 0 && parseInt(viewersGoodgame) > 0 ) {
            $('.toggle-servers').show();
        } else {
            $('.toggle-servers').hide();
        }
    }

    // Если на страницу перешли по ссылки из виджета
    if ( window.location.hash.match(/channel-/) ) {
        var openChannelName = window.location.hash.match(/channel-(.*)$/)[1];
        openChannel(openChannelName, true);
    } else {
        var options = {
            channel: $('#player-twitch-frame').attr('data-default-channel')
        };
        window.twitch_player = new Twitch.Player("player-twitch-frame", options);

        openChannel( $('#vgs-streams-table tbody tr:eq(0)').attr('data-id'), false );
    }

    /*
    $(document).on('click', '.vgs-widget .channels a[href*=#channel-]', function() {
        var openChannelName = $(this).attr('href').match(/channel-(.*)$/)[1];
        openChannel(openChannelName, true);
    });*/

    // Переключение стримов при клике на строку в таблице
    $(document).on('click', '#vgs-streams-table tbody tr', function(){
        var channel = $(this).attr('data-id');
        openChannel(channel, true);
    });

    // Открытие окна чата
    $(document).on('click', '#vgs-streams .open-chat-twitch', function() {
        var channel = $(this).attr('data-channel');
        var url = 'http://www.twitch.tv/' + channel + '/chat';

        window.open(url, '', 'toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,resizable=yes,copyhistory=yes,width=420,height=600');
    });

    $(document).on('click', '#vgs-streams .open-chat-goodgame', function() {
        var channel = $(this).attr('data-channel');
        var url = 'http://goodgame.ru/chat/' + channel;

        window.open(url, '', 'toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,resizable=yes,copyhistory=yes,width=420,height=600');
    });

    $('select#games_show').change(function() {
        vgsFilterGames( $('#vgs-streams-table table') );
    });

    $('input#lang_ru').change(function() {
        vgsFilterGames( $('#vgs-streams-table table') );
    });

    $(document).on('click', '.toggle-server', function() {
        var server = $(this).attr('data-show');

        if (server == 'twitch') {
            $('#player-twitch').show();

            $('#player-goodgame').hide();
            $('#player-goodgame iframe').attr('data-src', $('#player-goodgame iframe').attr('src') );
            $('#player-goodgame iframe').attr('src', '');
        } else {
            $('#player-twitch').hide();
            window.twitch_player.pause();

            $('#player-goodgame').show();
            if ($('#player-goodgame iframe').is('[data-src]') && $('#player-goodgame iframe').attr('data-src').length > 0) {
                $('#player-goodgame iframe').attr('src', $('#player-goodgame iframe').attr('data-src'));
                $('#player-goodgame iframe').attr('data-src', '');
            }
        }
    });
});

// Фильтр игр
function vgsFilterGames(table)
{
    var gameName = jQuery('select#games_show').val();
    var langChecked = jQuery('input#lang_ru').is(':checked');

    jQuery(table).find('tbody tr').removeClass('hidden');

    jQuery.each( jQuery(table).find('tbody tr'), function() {

        var gameNameItem = jQuery(this).find('td.column-game-name').text();
        var lang = jQuery(this).find('td.column-lang').text();

        if (
            ( langChecked && lang != 'RU' ) // скрываем если язык не соответствует заданному
            || ( gameName == 'other' && in_array(gameNameItem, window.displayInFilter) != false )
            || ( gameName != 'other' && gameName != 'all' && gameName != gameNameItem)
        ) {
            jQuery(this).addClass('hidden');
        }

    });

    vgsZebraStripingBgTable();
}

// Выводим в фильтре только те игры, которые есть в таблице
function vgsFilterSelectVisibleOptions()
{
    jQuery('select#games_show option').show();

    jQuery.each( jQuery('select#games_show option'), function() {
        var gameName = jQuery(this).val();
        if ( gameName != 'other' && gameName != 'all' && in_array(gameName, window.displayInTable) == false) {
            jQuery(this).hide();
        }
    });
}

//Фон строк в таблице
function vgsZebraStripingBgTable(table)
{

    if (table == undefined)
        table = jQuery('#vgs-streams-table tbody');

    table.find('tr:visible:odd').css({'background-color': '#fff'});
    table.find('tr:visible:even').css({'background-color': '#F4F3EF'});
}

// Обновление данных в таблице
window.vgsUpdatingTableData = false;
function updateTable()
{
    if (window.vgsUpdatingTableData  == true) return;

    var table = jQuery('#vgs-streams-table table');
    var selectedChannel = jQuery('#vgs-streams-table table tr.selected').attr('data-id');

    jQuery.ajax({
        type: 'POST',
        url: window.ajaxurl,
        data: 'action=vgs_get_table_data',
        beforeSend: function() {
            window.vgsUpdatingTableData  = true;
        },
        success: function(data) {
            window.vgsUpdatingTableData  = false;

            jQuery('#vgs-streams-table').css('height', jQuery('#vgs-streams-table table').outerHeight() + 'px');

            var newTable = jQuery('#vgs-streams-table table').clone();
            newTable.find('tbody').html(data);
            vgsFilterGames( newTable );

            // Сперва обновляем кол-во зрителей в текущей таблице
            var newNumViewers = {};
            jQuery.each(newTable.find('tbody tr'), function() {
                var channel = jQuery(this).attr('data-id');
                var viewers = jQuery(this).find('td.column-viewers').html();
                newNumViewers[channel] = viewers;
            });

            jQuery.each(table.find('tbody tr'), function() {
                var channel = jQuery(this).attr('data-id');
                if (newNumViewers[channel] != undefined) {
                    jQuery(this).find('td.column-viewers').html(newNumViewers[channel] );
                }
            });

            // Блокируем фильтр во время анимации
            jQuery('#vgs-filter').find('select, input').attr('disabled', 'disabled');

            // Обновляем таблицу
            table.rankingTableUpdate(newTable, {
                animationSettings: {
                    up: {
                        left: 0, // Move left
                        backgroundColor: '#b7ffb7' // Dullish green
                    },
                    down: {
                        left: 0, // Move right
                        backgroundColor: '#ffbbbb' // Dullish red
                    },
                    fresh: {
                        left: 0, //Stay put in first stage.
                        backgroundColor: '#fdfd97' // Yellow
                    },
                    drop: {
                        left: 0, //Stay put in first stage.
                        backgroundColor: '#ffa9ff' // Purple
                    }
                },
                onComplete: function() {

                    jQuery('#vgs-filter').find('select, input').removeAttr('disabled');

                    newTable.find('tbody').html(data);
                    vgsFilterGames( newTable );

                    table = newTable;
                    var a = jQuery(newTable).find('tbody').html();
                    jQuery('#vgs-streams-table table tbody').html(a);
                    table.find('tr[data-id=' + selectedChannel +']').addClass('selected');

                    vgsZebraStripingBgTable();
                    vgsFilterSelectVisibleOptions();
                }
            });


        },
        error: function() {
            window.vgsUpdatingTableData  = false;
        }
    });
}

setInterval(updateTable, window.vgsTimeUpdateTable);

function in_array(needle, haystack, strict) {	// Checks if a value exists in an array
    //
    // +   original by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)

    var found = false, key, strict = !!strict;

    for (key in haystack) {
        if ((strict && haystack[key] === needle) || (!strict && haystack[key] == needle)) {
            found = true;
            break;
        }
    }

    return found;
}
