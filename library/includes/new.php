<?php
/**
 * @package     JohnCMS
 * @link        http://johncms.com
 * @copyright   Copyright (C) 2008-2015 JohnCMS Community
 * @license     LICENSE.txt (see attached file)
 * @version     VERSION.txt (see attached file)
 * @author      http://johncms.com/about
 */

defined('_IN_JOHNCMS') or die('Error: restricted access');
echo '<div class="phdr"><strong><a href="?">' . $lng['library'] . '</a></strong> | ' . $lng_lib['new_articles'] . '</div>';
$total = mysql_result(mysql_query("SELECT COUNT(*) FROM `library_texts` WHERE `time` > '" . (time() - 259200) . "' AND `premod`=1"), 0);
$page = $page >= ceil($total / $kmess) ? ceil($total / $kmess) : $page;
$start = $page == 1 ? 0 : ($page - 1) * $kmess;
$sql = mysql_query("SELECT `id`, `name`, `time`, `author`, `count_views`, `comments`, `count_comments`, `cat_id`, `announce` FROM `library_texts` WHERE `time` > '" . (time() - 259200) . "' AND `premod`=1 ORDER BY `time` DESC LIMIT " . $start . "," . $kmess);
$nav = ($total > $kmess) ? '<div class="topmenu">' . functions::display_pagination('?act=new&amp;', $start, $total, $kmess) . '</div>' : '';
echo $nav;
if ($total) {
    $i = 0;
    while ($row = mysql_fetch_assoc($sql)) {
        echo '<div class="list' . (++$i % 2 ? 2 : 1) . '">'
            . (file_exists('../files/library/images/small/' . $row['id'] . '.png')
                ? '<div class="avatar"><img src="../files/library/images/small/' . $row['id'] . '.png" alt="screen" /></div>'
                : '')
            . '<div class="righttable"><h4><a href="?do=text&amp;id=' . $row['id'] . '">' . functions::checkout($row['name']) . '</a></h4>'
            . '<div><small>' . bbcode::notags($row['announce']) . '</small></div></div>';

        // Описание к статье
        $obj = new Hashtags($id);
        $rate = new Rating($row['id']);
        echo '<table class="desc">'
            // Раздел
            . '<tr>'
            . '<td class="caption">' . $lng['section'] . ':</td>'
            . '<td><a href="?do=dir&amp;id=' . $row['cat_id'] . '">' . functions::checkout(mysql_result(mysql_query("SELECT `name` FROM `library_cats` WHERE `id`=" . $row['cat_id']), 0)) . '</a></td>'
            . '</tr>'
            // Тэги
            . ($obj->get_all_stat_tags() ? '<tr><td class="caption">' . $lng_lib['tags'] . ':</td><td>' . $obj->get_all_stat_tags(1) . '</td></tr>' : '')
            // Кто добавил?
            . '<tr>'
            . '<td class="caption">' . $lng_lib['added'] . ':</td>'
            . '<td>' . functions::checkout($row['author']) . ' (' . functions::display_date($row['time']) . ')</td>'
            . '</tr>'
            // Рейтинг
            . '<tr>'
            . '<td class="caption">' . $lng['rating'] . ':</td>'
            . '<td>' . $rate->view_rate() . '</td>'
            . '</tr>'
            // Прочтений
            . '<tr>'
            . '<td class="caption">' . $lng_lib['reads'] . ':</td>'
            . '<td>' . $row['count_views'] . '</td>'
            . '</tr>'
            // Комментарии
            . '<tr>';
        if ($row['comments']) {
            echo '<td class="caption"><a href="?act=comments&amp;id=' . $row['id'] . '">' . $lng['comments'] . '</a>:</td><td>' . $row['count_comments'] . '</td>';
        } else {
            echo '<td class="caption">' . $lng['comments'] . ':</td><td>' . $lng['closed'] . '</td>';
        }
        echo '</tr></table>';

        echo '</div>';
    }
}
echo '<div class="phdr">' . $lng['total'] . ': ' . intval($total) . '</div>';
echo $nav;
echo '<p><a href="?">' . $lng_lib['to_library'] . '</a></p>';
