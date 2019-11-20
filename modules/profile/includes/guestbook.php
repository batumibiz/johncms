<?php

declare(strict_types=1);

/*
 * This file is part of JohnCMS Content Management System.
 *
 * @copyright JohnCMS Community
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0
 * @link      https://johncms.com JohnCMS Project
 */

defined('_IN_JOHNCMS') || die('Error: restricted access');

$textl = _t('Profile') . ' | ' . _t('Guestbook');
$mod = isset($_GET['mod']) ? trim($_GET['mod']) : '';

$context_top = '<div class="phdr"><a href="?user=' . $foundUser['id'] . '"><b>' . _t('Profile') . '</b></a> | ' . _t('Guestbook') . '</div>' .
    '<div class="user"><p>' . $tools->displayUser($foundUser, ['iphide' => 1]) . '</p></div>';

// Параметры Гостевой
$arg = [
    'comments_table' => 'cms_users_guestbook', // Таблица Гостевой
    'object_table'   => 'users',               // Таблица комментируемых объектов
    'script'         => '?act=guestbook',      // Имя скрипта (с параметрами вызова)
    'sub_id_name'    => 'user',                // Имя идентификатора комментируемого объекта
    'sub_id'         => $foundUser['id'],      // Идентификатор комментируемого объекта
    'owner'          => $foundUser['id'],      // Владелец объекта
    'owner_delete'   => true,                  // Возможность владельцу удалять комментарий
    'owner_reply'    => true,                  // Возможность владельцу отвечать на комментарий
    'title'          => _t('Comments'),        // Название раздела
    'context_top'    => $context_top,          // Выводится вверху списка
];

// Показываем комментарии
$comm = new Johncms\Utility\Comments($arg);

// Обновляем счетчик непрочитанного
if (! $mod && $foundUser['id'] == $user->id && $foundUser['comm_count'] != $foundUser['comm_old']) {
    /** @var PDO $db */
    $db = di(PDO::class);
    $db->query("UPDATE `users` SET `comm_old` = '" . $foundUser['comm_count'] . "' WHERE `id` = " . $user->id);
}
