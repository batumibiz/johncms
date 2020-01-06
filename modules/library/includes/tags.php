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

$obj = new Library\Hashtags(0);

if (isset($_GET['tag'])) {
    $tag = urldecode($_GET['tag']);

    if ($obj->getAllTagStats($tag)) {
        $total = count($obj->getAllTagStats($tag));
        $page = $page >= ceil($total / $user->config->kmess) ? ceil($total / $user->config->kmess) : $page;
        $start = $page == 1 ? 0 : ($page - 1) * $user->config->kmess;

        echo '<div class="phdr"><a href="?"><strong>' . _t('Library') . '</strong></a> | ' . _t('Tags') . '</div>';

        if ($total > $user->config->kmess) {
            echo '<div class="topmenu">' . $tools->displayPagination('?act=tags&amp;tag=' . urlencode($tag) . '&amp;', $start, $total, $user->config->kmess) . '</div>';
        }

        foreach (new LimitIterator(new ArrayIterator($obj->getAllTagStats($tag)), $start, $user->config->kmess) as $txt) {
            $query = $db->query('SELECT `id`, `name`, `time`, `uploader`, `uploader_id`, `count_views`, `comm_count`, `comments` FROM `library_texts` WHERE `id` = ' . $txt);
            if ($query->rowCount()) {
                $row = $query->fetch();
                $obj = new Library\Hashtags($row['id']);
                echo '<div class="list' . (++$i % 2 ? 2 : 1) . '">'
                . (file_exists(UPLOAD_PATH . 'library/images/small/' . $row['id'] . '.png')
                    ? '<div class="avatar"><img src="../upload/library/images/small/' . $row['id'] . '.png" alt="screen" /></div>'
                    : '')
                . '<div class="righttable"><a href="?id=' . $row['id'] . '">' . $tools->checkout($row['name']) . '</a>'
                . '<div>' . $tools->checkout($db->query('SELECT SUBSTRING(`text`, 1 , 200) FROM `library_texts` WHERE `id`=' . $row['id'])->fetchColumn(), 0, 2) . '</div></div>'
                . '<div class="sub">' . _t('Who added') . ': ' . '<a href="' . di('config')['johncms']['homeurl'] . '/profile/?user=' . $row['uploader_id'] . '">' .
                    $tools->checkout($row['uploader']) . '</a>' . ' (' . $tools->displayDate($row['time']) . ')</div>'
                . '<div><span class="gray">' . _t('Number of readings') . ':</span> ' . $row['count_views'] . '</div>'
                . '<div>' . ($obj->getAllStatTags() ? _t('Tags') . ' [ ' . $obj->getAllStatTags(1) . ' ]' : '') . '</div>'
                . ($row['comments'] ? '<div><a href="?act=comments&amp;id=' . $row['id'] . '">' . _t('Comments') . '</a> (' . $row['comm_count'] . ')</div>' : '')
                . '</div>';
            }
        }

        echo '<div class="phdr">' . _t('Total') . ': ' . (int) $total . '</div>';

        if ($total > $user->config->kmess) {
            echo '<div class="topmenu">' . $tools->displayPagination('?act=tags&amp;tag=' . urlencode($tag) . '&amp;', $start, $total, $user->config->kmess) . '</div>';
        }
        echo '<p><a href="?">' . _t('To Library') . '</a></p>';
    } else {
        echo '<div class="menu"><p>' . _t('The list is empty') . '</p></div>';
    }
} else {
    Library\Utils::redir404();
}
