jQuery(document).ready(function($) {

    // Получение данных об игре на странице добавления игры
    $(document).on('click', '#get-channel-twitch-info, #get-channel-goodgame-info', function() {
        var server = $(this).attr('id').match(/get-channel-(twitch|goodgame)-info/)[1];

        var channelUrl = $('input[name=channel_' + server + ']').val().trim();
        if ( !channelUrl ) {
            alert('Укажите адрес канала');
            return false;
        }

        $.ajax({
            type: 'POST',
            dataType: 'json',
            url: ajaxurl,
            data: 'action=get_channel_info&channel_url=' + channelUrl,
            beforeSend: function() {
                $('#process-load-data-' + server + ' .load-wrap').show();
                $('#get-channel-' + server + '-info').attr('disabled', 'disabled');
            },
            success: function(data) {
                $('#process-load-data-' + server + ' .load-wrap').hide();
                $('#get-channel-' + server + '-info').removeAttr('disabled');

                if (data.error != undefined) {

                    alert(data.error);

                } else if (data.results != undefined) {

                    $('input[name=game_name]').val(data.results.game_name);
                    $('input[name=author_display]').val(data.results.author_display);
                }
            },
            error: function() {
                $('#process-load-data-' + server + ' .load-wrap').hide();
                $('#get-channel-' + server + '-info').removeAttr('disabled');
                alert("При получении данных произошла непредвиденная ошибка. \nПопробуйте отправить запрос ещё раз.");
            }
        });

        return false;
    });

    $('input[name=min_viewers_table], input[name=num_channels_widget]').keypress(function (e) {
        // tab, backspace, 0-9
        if( e.which!=8 && e.which!=0 &&  e.which!=46 && (e.which<48 || e.which>57)) {
            return false;
        }
    });

});