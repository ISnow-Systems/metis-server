CREATE TABLE `books`
(
	`isbn`       CHAR(13) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL COMMENT 'ISBN',
	`title`      TEXT                                                  NOT NULL COMMENT 'タイトル',
	`title_kana` TEXT                                                  NOT NULL COMMENT 'タイトル読み',
	`volume`     INT UNSIGNED NOT NULL DEFAULT '1' COMMENT '巻数',
	`authors`    TEXT                                                  NOT NULL COMMENT '著者',
	`publishers` TEXT                                                  NOT NULL COMMENT '出版社',
	PRIMARY KEY (
				 `isbn`(13)
		)
) ENGINE = InnoDB CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;