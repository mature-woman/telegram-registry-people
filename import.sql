-- phpMyAdmin SQL Dump
-- version 5.1.1deb5ubuntu1
-- https://www.phpmyadmin.net/
--
-- Хост: localhost:3306
-- Время создания: Июн 04 2023 г., 07:56
-- Версия сервера: 10.6.12-MariaDB-0ubuntu0.22.04.1
-- Версия PHP: 8.2.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `telegram-registry-people`
--
CREATE DATABASE IF NOT EXISTS `telegram-registry-people` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `telegram-registry-people`;

DELIMITER $$
--
-- Функции
--
CREATE DEFINER=`root`@`localhost` FUNCTION `LEVENSHTEIN` (`s1` VARCHAR(255) CHARACTER SET utf8, `s2` VARCHAR(255) CHARACTER SET utf8) RETURNS INT(11) BEGIN
    DECLARE s1_len, s2_len, i, j, c, c_temp, cost INT;
    DECLARE s1_char CHAR CHARACTER SET utf8;
    -- max strlen=255 for this function
    DECLARE cv0, cv1 VARBINARY(256);

    SET s1_len = CHAR_LENGTH(s1),
        s2_len = CHAR_LENGTH(s2),
        cv1 = 0x00,
        j = 1,
        i = 1,
        c = 0;

    IF (s1 = s2) THEN
      RETURN (0);
    ELSEIF (s1_len = 0) THEN
      RETURN (s2_len);
    ELSEIF (s2_len = 0) THEN
      RETURN (s1_len);
    END IF;

    WHILE (j <= s2_len) DO
      SET cv1 = CONCAT(cv1, CHAR(j)),
          j = j + 1;
    END WHILE;

    WHILE (i <= s1_len) DO
      SET s1_char = SUBSTRING(s1, i, 1),
          c = i,
          cv0 = CHAR(i),
          j = 1;

      WHILE (j <= s2_len) DO
        SET c = c + 1,
            cost = IF(s1_char = SUBSTRING(s2, j, 1), 0, 1);

        SET c_temp = ORD(SUBSTRING(cv1, j, 1)) + cost;
        IF (c > c_temp) THEN
          SET c = c_temp;
        END IF;

        SET c_temp = ORD(SUBSTRING(cv1, j+1, 1)) + 1;
        IF (c > c_temp) THEN
          SET c = c_temp;
        END IF;

        SET cv0 = CONCAT(cv0, CHAR(c)),
            j = j + 1;
      END WHILE;

      SET cv1 = cv0,
          i = i + 1;
    END WHILE;

    RETURN (c);
  END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Структура таблицы `accounts`
--

CREATE TABLE `accounts` (
  `id` int(11) NOT NULL COMMENT 'Идентификатор',
  `id_telegram` int(11) NOT NULL COMMENT 'Идентификатор в телеграм',
  `status` varchar(20) NOT NULL DEFAULT 'active' COMMENT 'Статус',
  `admin` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Администратор?',
  `created` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Дата создания'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `people`
--

CREATE TABLE `people` (
  `id` int(11) NOT NULL COMMENT 'Идентификатор',
  `name` varchar(80) DEFAULT NULL COMMENT 'Имя',
  `surname` varchar(80) DEFAULT NULL COMMENT 'Фамилия',
  `patronymic` varchar(80) DEFAULT NULL COMMENT 'Отчество',
  `phone` bigint(20) DEFAULT NULL COMMENT 'Номер смартфона',
  `address` varchar(255) DEFAULT NULL COMMENT 'Адрес',
  `day` int(2) DEFAULT NULL COMMENT 'День рождения',
  `month` int(2) DEFAULT NULL COMMENT 'Месяц рождения',
  `year` int(4) DEFAULT NULL COMMENT 'Год рождения',
  `data` text DEFAULT NULL COMMENT 'Информация',
  `cover` varchar(255) DEFAULT NULL COMMENT 'Обложка',
  `created` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Дата создания',
  `updated` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Дата обновления'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `accounts`
--
ALTER TABLE `accounts`
  ADD UNIQUE KEY `id_2` (`id`),
  ADD KEY `id` (`id`);

--
-- Индексы таблицы `people`
--
ALTER TABLE `people`
  ADD UNIQUE KEY `id_2` (`id`),
  ADD KEY `id` (`id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `accounts`
--
ALTER TABLE `accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор';

--
-- AUTO_INCREMENT для таблицы `people`
--
ALTER TABLE `people`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор';
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
