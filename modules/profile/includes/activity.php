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

// История активности
$textl = htmlspecialchars($foundUser['name']) . ': ' . _t('Activity');

echo '<div class="phdr"><a href="?user=' . $foundUser['id'] . '"><b>' . _t('Profile') . '</b></a> | ' . _t('Activity') . '</div>';
$menu = [
    (! $mod ? '<b>' . _t('Messages') . '</b>' : '<a href="?act=activity&amp;user=' . $foundUser['id'] . '">' . _t('Messages') . '</a>'),
    ($mod == 'topic' ? '<b>' . _t('Themes') . '</b>' : '<a href="?act=activity&amp;mod=topic&amp;user=' . $foundUser['id'] . '">' . _t('Themes') . '</a>'),
    ($mod == 'comments' ? '<b>' . _t('Comments') . '</b>' : '<a href="?act=activity&amp;mod=comments&amp;user=' . $foundUser['id'] . '">' . _t('Comments') . '</a>'),
];
echo '<div class="topmenu">' . implode(' | ', $menu) . '</div>' .
    '<div class="user"><p>' . $tools->displayUser($foundUser, ['iphide' => 1]) . '</p></div>';

switch ($mod) {
    case 'comments':
        // Список сообщений в Гостевой
        $total = $db->query("SELECT COUNT(*) FROM `guest` WHERE `user_id` = '" . $foundUser['id'] . "'" . ($user->rights >= 1 ? '' : " AND `adm` = '0'"))->fetchColumn();
        echo '<div class="phdr"><b>' . _t('Comments') . '</b></div>';

        if ($total > $user->config->kmess) {
            echo '<div class="topmenu">' . $tools->displayPagination('?act=activity&amp;mod=comments&amp;user=' . $foundUser['id'] . '&amp;', $start, $total, $user->config->kmess) . '</div>';
        }

        $req = $db->query("SELECT * FROM `guest` WHERE `user_id` = '" . $foundUser['id'] . "'" . ($user->rights >= 1 ? '' : " AND `adm` = '0'") . " ORDER BY `id` DESC LIMIT ${start}, " . $user->config->kmess);

        if ($req->rowCount()) {
            $i = 0;
            while ($res = $req->fetch()) {
                echo (($i % 2) ? '<div class="list2">' : '<div class="list1">') . $tools->checkout($res['text'], 2, 1) . '<div class="sub">' .
                    '<span class="gray">(' . $tools->displayDate($res['time']) . ')</span>' .
                    '</div></div>';
                ++$i;
            }
        } else {
            echo '<div class="menu"><p>' . _t('The list is empty') . '</p></div>';
        }
        break;

    case 'topic':
        // Список тем Форума
        $total = $db->query("SELECT COUNT(*) FROM `forum_topic` WHERE `user_id` = '" . $foundUser['id'] . "'" . ($user->rights >= 7 ? '' : " AND (`deleted`!='1' OR deleted IS NULL)"))->fetchColumn();
        echo '<div class="phdr"><b>' . _t('Forum') . '</b>: ' . _t('Themes') . '</div>';

        if ($total > $user->config->kmess) {
            echo '<div class="topmenu">' . $tools->displayPagination('?act=activity&amp;mod=topic&amp;user=' . $foundUser['id'] . '&amp;', $start, $total, $user->config->kmess) . '</div>';
        }

        $req = $db->query("SELECT * FROM `forum_topic` WHERE `user_id` = '" . $foundUser['id'] . "'" . ($user->rights >= 7 ? '' : " AND (`deleted`!='1' OR deleted IS NULL)") . " ORDER BY `id` DESC LIMIT ${start}, " . $user->config->kmess);

        if ($req->rowCount()) {
            $i = 0;

            while ($res = $req->fetch()) {
                $post = $db->query("SELECT * FROM `forum_messages` WHERE `topic_id` = '" . $res['id'] . "'" . ($user->rights >= 7 ? '' : " AND (`deleted`!='1' OR deleted IS NULL)") . ' ORDER BY `id` ASC LIMIT 1')->fetch();
                $section = $db->query("SELECT * FROM `forum_sections` WHERE `id` = '" . $res['section_id'] . "'")->fetch();
                $category = $db->query("SELECT * FROM `forum_sections` WHERE `id` = '" . $section['parent'] . "'")->fetch();
                $text = mb_substr($post['text'], 0, 300);
                $text = $tools->checkout($text, 2, 1);
                echo (($i % 2) ? '<div class="list2">' : '<div class="list1">') .
                    '<a href="' . $config->homeurl . '/forum/?type=topic&id=' . $res['id'] . '">' . $res['name'] . '</a>' .
                    '<br />' . $text . '...<a href="' . $config->homeurl . '/forum/?type=topic&id=' . $res['id'] . '"> &gt;&gt;</a>' .
                    '<div class="sub">' .
                    '<a href="' . $config->homeurl . '/forum/?id=' . $category['id'] . '">' . $category['name'] . '</a> | ' .
                    '<a href="' . $config->homeurl . '/forum/?type=topics&id=' . $section['id'] . '">' . $section['name'] . '</a>' .
                    '<br /><span class="gray">(' . $tools->displayDate($res['last_post_date']) . ')</span>' .
                    '</div></div>';
                ++$i;
            }
        } else {
            echo '<div class="menu"><p>' . _t('The list is empty') . '</p></div>';
        }
        break;

    default:
        // Список постов Форума
        $total = $db->query("SELECT COUNT(*) FROM `forum_messages` WHERE `user_id` = '" . $foundUser['id'] . "'" . ($user->rights >= 7 ? '' : " AND (`deleted`!='1' OR deleted IS NULL)"))->fetchColumn();
        echo '<div class="phdr"><b>' . _t('Forum') . '</b>: ' . _t('Messages') . '</div>';

        if ($total > $user->config->kmess) {
            echo '<div class="topmenu">' . $tools->displayPagination('?act=activity&amp;user=' . $foundUser['id'] . '&amp;', $start, $total, $user->config->kmess) . '</div>';
        }

        $req = $db->query(
            "SELECT * FROM `forum_messages` WHERE `user_id` = '" . $foundUser['id'] . "' " . ($user->rights >= 7 ? '' : " AND (`deleted`!='1' OR deleted IS NULL)") . " ORDER BY `id` DESC LIMIT ${start}, " . $user->config->kmess
        );

        if ($req->rowCount()) {
            $i = 0;

            while ($res = $req->fetch()) {
                $topic = $db->query("SELECT * FROM `forum_topic` WHERE `id` = '" . $res['topic_id'] . "'")->fetch();
                $section = $db->query("SELECT * FROM `forum_sections` WHERE `id` = '" . $topic['section_id'] . "'")->fetch();
                $category = $db->query("SELECT * FROM `forum_sections` WHERE `id` = '" . $section['parent'] . "'")->fetch();
                $text = mb_substr($res['text'], 0, 300);
                $text = $tools->checkout($text, 2, 1);
                $text = preg_replace('#\[c\](.*?)\[/c\]#si', '<div class="quote">\1</div>', $text);

                echo (($i % 2) ? '<div class="list2">' : '<div class="list1">') .
                    '<a href="' . $config->homeurl . '/forum/?type=topic&id=' . $topic['id'] . '">' . $topic['name'] . '</a>' .
                    '<br />' . $text . '...<a href="' . $config->homeurl . '/forum/?act=show_post&amp;id=' . $res['id'] . '"> &gt;&gt;</a>' .
                    '<div class="sub">' .
                    '<a href="' . $config->homeurl . '/forum/?id=' . $category['id'] . '">' . $category['name'] . '</a> | ' .
                    '<a href="' . $config->homeurl . '/forum/?type=topics&id=' . $section['id'] . '">' . $section['name'] . '</a>' .
                    '<br /><span class="gray">(' . $tools->displayDate($res['date']) . ')</span>' .
                    '</div></div>';
                ++$i;
            }
        } else {
            echo '<div class="menu"><p>' . _t('The list is empty') . '</p></div>';
        }
}

echo '<div class="phdr">' . _t('Total') . ': ' . $total . '</div>';

if ($total > $user->config->kmess) {
    echo '<div class="topmenu">' . $tools->displayPagination('?act=activity' . ($mod ? '&amp;mod=' . $mod : '') . '&amp;user=' . $foundUser['id'] . '&amp;', $start, $total, $user->config->kmess) . '</div>' .
        '<p><form action="?act=activity&amp;user=' . $foundUser['id'] . ($mod ? '&amp;mod=' . $mod : '') . '" method="post">' .
        '<input type="text" name="page" size="2"/>' .
        '<input type="submit" value="' . _t('To Page') . ' &gt;&gt;"/>' .
        '</form></p>';
}
