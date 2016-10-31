<?php

defined('_IN_JOHNADM') or die('Error: restricted access');

/** @var Interop\Container\ContainerInterface $container */
$container = App::getContainer();

/** @var PDO $db */
$db = $container->get(PDO::class);

/** @var Johncms\Tools $tools */
$tools = $container->get('tools');

echo '<div class="phdr"><a href="index.php"><b>' . _t('Admin Panel') . '</b></a> | ' . _t('List of Users') . '</div>';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : '';
echo '<div class="topmenu"><span class="gray">' . _t('Sort') . ':</span> ';

switch ($sort) {
    case 'nick':
        $sort = 'nick';
        echo '<a href="index.php?act=usr&amp;sort=id">ID</a> | ' . _t('Nickname') . ' | <a href="index.php?act=usr&amp;sort=ip">IP</a></div>';
        $order = '`name` ASC';
        break;

    case 'ip':
        $sort = 'ip';
        echo '<a href="index.php?act=usr&amp;sort=id">ID</a> | <a href="index.php?act=usr&amp;sort=nick">' . _t('Nickname') . '</a> | IP</div>';
        $order = '`ip` ASC';
        break;

    default :
        $sort = 'id';
        echo 'ID | <a href="index.php?act=usr&amp;sort=nick">' . _t('Nickname') . '</a> | <a href="index.php?act=usr&amp;sort=ip">IP</a></div>';
        $order = '`id` ASC';
}

$total = $db->query("SELECT COUNT(*) FROM `users`")->fetchColumn();
$req = $db->query("SELECT * FROM `users` WHERE `preg` = 1 ORDER BY $order LIMIT " . $start . ", " . $kmess);
$i = 0;

while ($res = $req->fetch()) {
    $link = '';

    if ($rights >= 7) {
        $link .= '<a href="../profile/?act=edit&amp;user=' . $res['id'] . '">' . _t('Edit') . '</a> | <a href="index.php?act=usr_del&amp;id=' . $res['id'] . '">' . _t('Delete') . '</a> | ';
    }

    $link .= '<a href="../profile/?act=ban&amp;mod=do&amp;user=' . $res['id'] . '">' . _t('Ban') . '</a>';
    echo $i % 2 ? '<div class="list2">' : '<div class="list1">';
    echo functions::display_user($res, ['header' => ('<b>ID:' . $res['id'] . '</b>'), 'sub' => $link]);
    echo '</div>';
    ++$i;
}

echo '<div class="phdr">' . _t('Total') . ': ' . $total . '</div>';

if ($total > $kmess) {
    echo '<div class="topmenu">' . $tools->displayPagination('index.php?act=usr&amp;sort=' . $sort . '&amp;', $start, $total, $kmess) . '</div>';
    echo '<p><form action="index.php?act=usr&amp;sort=' . $sort . '" method="post"><input type="text" name="page" size="2"/><input type="submit" value="' . _t('To Page') . ' &gt;&gt;"/></form></p>';
}

echo '<p><a href="index.php">' . _t('Admin Panel') . '</a></p>';
