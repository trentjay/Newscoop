ALTER TABLE `Requests` ADD INDEX `requests_session_idx`(`session_id`);
ALTER TABLE `Requests` ADD INDEX `requests_object_idx`(`object_id`);
ALTER TABLE `Requests` DROP COLUMN `request_count`;
ALTER TABLE `Requests` DROP COLUMN `last_request_time`;
ALTER TABLE `Requests` ADD COLUMN `last_stats_update` DATETIME NOT NULL;


CREATE TABLE `RequestStats` (
  `object_id` INTEGER  NOT NULL,
  `date` DATE  NOT NULL,
  `hour` INTEGER  NOT NULL,
  `request_count` INTEGER  NOT NULL DEFAULT 0,
  PRIMARY KEY (`object_id`, `date`, `hour`),
  INDEX `stats_object_idx`(`object_id`),
  INDEX `stats_object_date_idx`(`object_id`, `date`),
  INDEX `stats_object_hour_idx`(`object_id`, `hour`)
);

CREATE TABLE `Authors` (
  `id` INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
  `first_name` VARCHAR(100)  NOT NULL,
  `last_name` VARCHAR(100)  NOT NULL,
  `email` VARCHAR(255) ,
  PRIMARY KEY (`id`),
  UNIQUE KEY authors_name_ukey (`first_name`, `last_name`)
);

CREATE TABLE `ArticleAuthors` (
  `fk_article_number` INTEGER UNSIGNED NOT NULL,
  `fk_language_id` INTEGER UNSIGNED NOT NULL,
  `fk_author_id` INTEGER UNSIGNED NOT NULL,
  PRIMARY KEY (`fk_article_number`, `fk_language_id`, `fk_author_id`)
);

ALTER TABLE `Articles` ADD COLUMN `fk_default_author_id` INTEGER UNSIGNED AFTER `IdUser`;
