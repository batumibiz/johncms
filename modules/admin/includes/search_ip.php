<?php

declare(strict_types=1);

/*
 * This file is part of JohnCMS Content Management System.
 *
 * @copyright JohnCMS Community
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0
 * @link      https://johncms.com JohnCMS Project
 */

defined('_IN_JOHNADM') || die('Error: restricted access');
ob_start(); // Перехват вывода скриптов без шаблона

/**
 * @var Johncms\System\Legacy\Tools $tools
 */

$error = [];
$search_post = isset($_POST['search']) ? trim($_POST['search']) : '';
$search_get = isset($_GET['search']) ? rawurldecode(trim($_GET['search'])) : '';
$search = $search_post ? $search_post : $search_get;

if (isset($_GET['ip'])) {
    $search = trim($_GET['ip']);
}

$menu = [
    (! $mod ? '<b>' . _t('Actual IP') . '</b>' : '<a href="?act=search_ip&amp;search=' . rawurlencode($search) . '">' . _t('Actual IP') . '</a>'),
    ($mod == 'history' ? '<b>' . _t('IP history') . '</b>' : '<a href="?act=search_ip&amp;mod=history&amp;search=' . rawurlencode($search) . '">' . _t('IP history') . '</a>'),
];

echo '<div class="phdr"><a href="./"><b>' . _t('Admin Panel') . '</b></a> | ' . _t('Search IP') . '</div>' .
    '<div class="topmenu">' . implode(' | ', $menu) . '</div>' .
    '<form action="?act=search_ip" method="post"><div class="gmenu"><p>' .
    '<input type="text" name="search" value="' . $tools->checkout($search) . '" />' .
    '<input type="submit" value="' . _t('Search') . '" name="submit" /><br>' .
    '</p></div></form>';

if ($search) {
    if (strstr($search, '-')) {
        // Обрабатываем диапазон адресов
        $array = explode('-', $search);
        $ip = trim($array[0]);

        if (! preg_match('#^(?:(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\.){3}(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])$#', $ip)) {
            $error[] = _t('First IP is entered incorrectly');
        } else {
            $ip1 = ip2long($ip);
        }

        $ip = trim($array[1]);

        if (! preg_match('#^(?:(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\.){3}(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])$#', $ip)) {
            $error[] = _t('Second IP is entered incorrectly');
        } else {
            $ip2 = ip2long($ip);
        }
    } elseif (strstr($search, '*')) {
        // Обрабатываем адреса с маской
        $array = explode('.', $search);

        for ($i = 0; $i < 4; $i++) {
            if (! isset($array[$i]) || $array[$i] == '*') {
                $ipt1[$i] = '0';
                $ipt2[$i] = '255';
            } elseif (is_numeric($array[$i]) && $array[$i] >= 0 && $array[$i] <= 255) {
                $ipt1[$i] = $array[$i];
                $ipt2[$i] = $array[$i];
            } else {
                $error = _t('Invalid IP');
            }

            $ip1 = ip2long($ipt1[0] . '.' . $ipt1[1] . '.' . $ipt1[2] . '.' . $ipt1[3]);
            $ip2 = ip2long($ipt2[0] . '.' . $ipt2[1] . '.' . $ipt2[2] . '.' . $ipt2[3]);
        }
    } else {
        // Обрабатываем одиночный адрес
        if (! preg_match('#^(?:(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\.){3}(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])$#', $search)) {
            $error = _t('Invalid IP');
        } else {
            $ip1 = ip2long($search);
            $ip2 = $ip1;
        }
    }
}

if ($search && ! $error) {
    /** @var PDO $db */
    $db = di(PDO::class);

    // Выводим результаты поиска
    echo '<div class="phdr">' . _t('Search results') . '</div>';

    if ($mod == 'history') {
        $total = $db->query("SELECT COUNT(DISTINCT `cms_users_iphistory`.`user_id`) FROM `cms_users_iphistory` WHERE `ip` BETWEEN ${ip1} AND ${ip2} OR `ip_via_proxy` BETWEEN ${ip1} AND ${ip2}")->fetchColumn();
    } else {
        $total = $db->query("SELECT COUNT(*) FROM `users` WHERE `ip` BETWEEN ${ip1} AND ${ip2} OR `ip_via_proxy` BETWEEN ${ip1} AND ${ip2}")->fetchColumn();
    }

    if ($total > $user->config->kmess) {
        echo '<div class="topmenu">' . $tools->displayPagination('?act=search_ip' . ($mod == 'history' ? '&amp;mod=history' : '') . '&amp;search=' . urlencode($search) . '&amp;', $start, $total, $user->config->kmess) . '</div>';
    }

    if ($total) {
        if ($mod == 'history') {
            $req = $db->query(
                "SELECT `cms_users_iphistory`.*, `users`.`name`, `users`.`rights`, `users`.`lastdate`, `users`.`sex`, `users`.`status`, `users`.`datereg`, `users`.`id`, `users`.`browser`
                FROM `cms_users_iphistory` LEFT JOIN `users` ON `cms_users_iphistory`.`user_id` = `users`.`id`
                WHERE `cms_users_iphistory`.`ip` BETWEEN ${ip1} AND ${ip2} OR `cms_users_iphistory`.`ip_via_proxy` BETWEEN ${ip1} AND ${ip2}
                GROUP BY `users`.`id`
                ORDER BY `ip` ASC, `name` ASC LIMIT " . $start . ',' . $user->config->kmess
            );
        } else {
            $req = $db->query(
                "SELECT * FROM `users`
            WHERE `ip` BETWEEN ${ip1} AND ${ip2} OR `ip_via_proxy` BETWEEN ${ip1} AND ${ip2}
            ORDER BY `ip` ASC, `name` ASC LIMIT " . $start . ',' . $user->config->kmess
            );
        }

        $i = 0;

        while ($res = $req->fetch()) {
            echo $i % 2 ? '<div class="list2">' : '<div class="list1">';
            echo $tools->displayUser($res, ['iphist' => 1]);
            echo '</div>';
            ++$i;
        }
    } else {
        echo '<div class="menu"><p>' . _t('At your request, nothing found') . '</p></div>';
    }

    echo '<div class="phdr">' . _t('Total') . ': ' . $total . '</div>';

    if ($total > $user->config->kmess) {
        // Навигация по страницам
        echo '<div class="topmenu">' . $tools->displayPagination('?act=search_ip' . ($mod == 'history' ? '&amp;mod=history' : '') . '&amp;search=' . urlencode($search) . '&amp;', $start, $total, $user->config->kmess) . '</div>' .
            '<p><form action="?act=search_ip' . ($mod == 'history' ? '&amp;mod=history' : '') . '&amp;search=' . urlencode($search) . '" method="post">' .
            '<input type="text" name="page" size="2"/><input type="submit" value="' . _t('To Page') . ' &gt;&gt;"/>' .
            '</form></p>';
    }
    echo '<p><a href="?act=search_ip">' . _t('New Search') . '</a><br><a href="./">' . _t('Admin Panel') . '</a></p>';
} else {
    // Выводим сообщение об ошибке
    if ($error) {
        echo $tools->displayError($error);
    }

    // Инструкции для поиска
    echo '<div class="phdr"><small>' . _t('<b>Sample queries:</b><br><span class="red">10.5.7.1</span> - Search for a single address<br><span class="red">10.5.7.1-10.5.7.100</span> - Search a range address (forbidden to use mask symbol *)<br><span class="red">10.5.*.*</span> - Search mask. Will be found all subnet addresses starting with 0 and ending with 255') . '</small></div>'; // phpcs:ignore
    echo '<p><a href="./">' . _t('Admin Panel') . '</a></p>';
}

echo $view->render(
    'system::app/old_content',
    [
        'title' => _t('Admin Panel'),
        'content' => ob_get_clean(),
    ]
);
