Перед активацией плагина необходимо создать в БД таблицу для стримов

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

В шаблоне сайта в файле footer.php перед </body> добавить
<?php wp_footer(); ?>

Шорткод [vgs-streams] выводит таблицу со стримами

Виджет для вывода игр и стримов называется "Стримы игр"

Цвета при анимации можно изменить в файле assets/js/core.js, в конце файла
animationSettings: {
    up: {
        left: 0, // Move left
        backgroundColor: '#90C61E' // Dullish green
    },
    down: {
        left: 0, // Move right
        backgroundColor: '#FF1800' // Dullish red
    },
    fresh: {
        left: 0, //Stay put in first stage.
        backgroundColor: '#FFFF33' // Yellow
    },
    drop: {
        left: 0, //Stay put in first stage.
        backgroundColor: '#550055' // Purple
    }
}
-----




