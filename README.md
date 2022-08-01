# Wordpress video game streams (Twitch, Goodgame)

This version does not use ACF. Before activating the plugin, you must manually create a table for streams in the database:
```
CREATE TABLE `wp_vgs_games` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site` varchar(45) DEFAULT '',
  `channel` varchar(255) NOT NULL,
  `author_display` varchar(255) DEFAULT '',
  `description` text,
  `lang` varchar(45) DEFAULT '',
  `game_name` varchar(255) DEFAULT '',
  `game_name_display` varchar(255) DEFAULT '',
  `featured` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `channel_UNIQUE` (`channel`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
```
Shortcode [vgs-streams] displays a table with streams.

The widget for displaying games and streams is called "Game Streams".

Use WP or server cron for auto update data from Twitch API:
```
/usr/bin/php /home/admin/web/{{ domain }}/public_html/wp-content/plugins/video-game-streams/cron/get_viewers.php
```
