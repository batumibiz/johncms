<?php

declare(strict_types=1);

/*
 * This file is part of JohnCMS Content Management System.
 *
 * @copyright JohnCMS Community
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0
 * @link      https://johncms.com JohnCMS Project
 */

/** @var Zend\I18n\Translator\Translator $translator */
$translator = di(Zend\I18n\Translator\Translator::class);
$translator->addTranslationFilePattern('gettext', __DIR__ . '/locale', '/%s/default.mo');

/** @var Johncms\Api\ToolsInterface $tools */
$tools = di(Johncms\Api\ToolsInterface::class);

// Принимаем данные, выводим форму поиска
$search_post = isset($_POST['search']) ? trim($_POST['search']) : false;
$search_get = isset($_GET['search']) ? rawurldecode(trim($_GET['search'])) : '';
$search = $search_post ? $search_post : $search_get;
echo '<div class="phdr"><a href="./"><b>' . _t('Community') . '</b></a> | ' . _t('User Search') . '</div>' .
    '<form action="?act=search" method="post">' .
    '<div class="gmenu"><p>' .
    '<input type="text" name="search" value="' . $tools->checkout($search) . '" />' .
    '<input type="submit" value="' . _t('Search') . '" name="submit" />' .
    '</p></div></form>';

// Проверям на ошибки
$error = [];

if (! empty($search) && (mb_strlen($search) < 2 || mb_strlen($search) > 20)) {
    $error[] = _t('Nickname') . ': ' . _t('Invalid length');
}

if (preg_match("/[^1-9a-z\-\@\*\(\)\?\!\~\_\=\[\]]+/", $tools->rusLat($search))) {
    $error[] = _t('Nickname') . ': ' . _t('Invalid characters');
}

if ($search && ! $error) {
    /** @var PDO $db */
    $db = di(PDO::class);

    // Выводим результаты поиска
    $search_db = $tools->rusLat($search);
    $search_db = strtr($search_db, [
        '_' => '\\_',
        '%' => '\\%',
    ]);
    $search_db = '%' . $search_db . '%';
    $total = $db->query('SELECT COUNT(*) FROM `users` WHERE `name_lat` LIKE ' . $db->quote($search_db))->fetchColumn();
    echo '<div class="phdr"><b>' . _t('Searching results') . '</b></div>';

    if ($total > $user->config->kmess) {
        echo '<div class="topmenu">' . $tools->displayPagination('?act=search&amp;search=' . urlencode($search) . '&amp;', $start, $total, $user->config->kmess) . '</div>';
    }

    if ($total) {
        $req = $db->query('SELECT * FROM `users` WHERE `name_lat` LIKE ' . $db->quote($search_db) . " ORDER BY `name` ASC LIMIT ${start}, " . $user->config->kmess);
        $i = 0;
        while ($res = $req->fetch()) {
            echo $i % 2 ? '<div class="list2">' : '<div class="list1">';
            $res['name'] = mb_strlen($search) < 2 ? $res['name'] : preg_replace('|(' . preg_quote($search, '/') . ')|siu', '<span style="background-color: #FFFF33">$1</span>', $res['name']);
            echo $tools->displayUser($res);
            echo '</div>';
            ++$i;
        }
    } else {
        echo '<div class="menu"><p>' . _t('Your search did not match any results') . '</p></div>';
    }

    echo '<div class="phdr">' . _t('Total') . ': ' . $total . '</div>';

    if ($total > $user->config->kmess) {
        echo '<div class="topmenu">' . $tools->displayPagination('?act=search&amp;search=' . urlencode($search) . '&amp;', $start, $total, $user->config->kmess) . '</div>' .
            '<p><form action="?act=search&amp;search=' . urlencode($search) . '" method="post">' .
            '<input type="text" name="page" size="2"/>' .
            '<input type="submit" value="' . _t('To Page') . ' &gt;&gt;"/>' .
            '</form></p>';
    }
} else {
    if ($error) {
        echo $tools->displayError($error);
    }
    echo '<div class="phdr"><small>' . _t('Search by Nickname are case insensitive. For example <strong>UsEr</strong> and <strong>user</strong> are identical.') . '</small></div>';
}

echo '<p>' . ($search && ! $error ? '<a href="?act=search">' . _t('New search') . '</a><br />' : '') .
    '<a href="./">' . _t('Back') . '</a></p>';

echo $view->render('system::app/old_content', [
    'title'   => _t('User Search'),
    'content' => ob_get_clean(),
]);
