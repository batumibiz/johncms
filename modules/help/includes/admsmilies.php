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

/**
 * @var Johncms\System\Legacy\Tools $tools
 * @var Johncms\System\Users\User $user
 */

// Каталог Админских Смайлов
if ($user->rights < 1) {
    echo $tools->displayError(_t('Wrong data'), '<a href="?act=smilies">' . _t('Back') . '</a>');
    echo $view->render('system::app/old_content', ['title' => $textl ?? '', 'content' => ob_get_clean()]);
    exit;
}

echo '<div class="phdr"><a href="?act=smilies"><b>' . _t('Smilies') . '</b></a> | ' . _t('For administration') . '</div>';
$user_sm = unserialize($user->smileys, ['allowed_classes' => false]);

if (! is_array($user_sm)) {
    $user_sm = [];
}

echo '<div class="topmenu"><a href="?act=my_smilies">' . _t('My smilies') . '</a>  (' . count($user_sm) . ' / ' . $user_smileys . ')</div>' .
    '<form action="?act=set_my_sm&amp;start=' . $start . '&amp;adm" method="post">';
$array = [];
$dir = opendir(ASSETS_PATH . 'emoticons/admin');

while (($file = readdir($dir)) !== false) {
    if (($file != '.') && ($file != '..') && ($file != 'name.dat') && ($file != '.svn') && ($file != 'index.php')) {
        $array[] = $file;
    }
}

closedir($dir);
$total = count($array);

if ($total > 0) {
    $end = $start + $user->config->kmess;

    if ($end > $total) {
        $end = $total;
    }

    for ($i = $start; $i < $end; $i++) {
        $smile = preg_replace('#^(.*?).(gif|jpg|png)$#isU', '$1', $array[$i], 1);
        echo ($i % 2) ? '<div class="list2">' : '<div class="list1">';
        $smileys = (in_array($smile, $user_sm) ? ''
            : '<input type="checkbox" name="add_sm[]" value="' . $smile . '" />&#160;');
        echo $smileys . '<img src="../assets/emoticons/admin/' . $array[$i] . '" alt="" /> - :' . $smile . ': ' . _t('or') . ' :' . $tools->trans($smile) . ':</div>';
    }
} else {
    echo '<div class="menu"><p>' . _t('The list is empty') . '</p></div>';
}

echo '<div class="gmenu"><input type="submit" name="add" value=" ' . _t('Add') . ' "/></div></form>';
echo '<div class="phdr">' . _t('Total') . ': ' . $total . '</div>';

if ($total > $user->config->kmess) {
    echo '<div class="topmenu">' . $tools->displayPagination('?act=admsmilies&amp;', $start, $total, $user->config->kmess) . '</div>';
    echo '<p><form action="?act=admsmilies" method="post">' .
        '<input type="text" name="page" size="2"/>' .
        '<input type="submit" value="' . _t('To Page') . ' &gt;&gt;"/></form></p>';
}

echo '<p><a href="' . $_SESSION['ref'] . '">' . _t('Back') . '</a></p>';
