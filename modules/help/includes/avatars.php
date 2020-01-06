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

// Каталог пользовательских Аватаров
if ($id && is_dir(ASSETS_PATH . 'avatars/' . $id)) {
    $avatar = isset($_GET['avatar']) ? (int) ($_GET['avatar']) : false;

    if ($user->isValid() && $avatar && is_file(ASSETS_PATH . 'avatars/' . $id . '/' . $avatar . '.png')) {
        if (isset($_POST['submit'])) {
            // Устанавливаем пользовательский Аватар
            if (@copy(ASSETS_PATH . 'avatars/' . $id . '/' . $avatar . '.png', UPLOAD_PATH . 'users/avatar/' . $user->id . '.png')) {
                echo '<div class="gmenu"><p>' . _t('Avatar has been successfully applied') . '<br />' .
                    '<a href="../profile/?act=edit">' . _t('Continue') . '</a></p></div>';
            } else {
                echo $tools->displayError(_t('An error occurred'), '<a href="' . $_SESSION['ref'] . '">' . _t('Back') . '</a>');
            }
        } else {
            echo '<div class="phdr"><a href="?act=avatars"><b>' . _t('Avatars') . '</b></a> | ' . _t('Set to Profile') . '</div>' .
                '<div class="rmenu"><p>' . _t('Are you sure you want to set yourself this avatar?') . '</p>' .
                '<p><img src="../assets/avatars/' . $id . '/' . $avatar . '.png" alt="" /></p>' .
                '<p><form action="?act=avatars&amp;id=' . $id . '&amp;avatar=' . $avatar . '" method="post"><input type="submit" name="submit" value="' . _t('Save') . '"/></form></p>' .
                '</div>' .
                '<div class="phdr"><a href="?act=avatars&amp;id=' . $id . '">' . _t('Cancel') . '</a></div>';
        }
    } else {
        // Показываем список Аватаров
        echo '<div class="phdr">' .
            '<a href="?act=avatars"><b>' . _t('Avatars') . '</b></a> | ' . htmlentities(file_get_contents(ASSETS_PATH . 'avatars/' . $id . '/name.txt'), ENT_QUOTES, 'utf-8') .
            '</div>';
        $array = glob(ASSETS_PATH . 'avatars/' . $id . '/*.png');
        $total = count($array);
        $end = $start + $user->config->kmess;

        if ($end > $total) {
            $end = $total;
        }

        if ($total > 0) {
            for ($i = $start; $i < $end; $i++) {
                echo $i % 2 ? '<div class="list2">' : '<div class="list1">';
                echo '<img src="../assets/avatars/' . $id . '/' . basename($array[$i]) . '" alt="" />';

                if ($user->isValid()) {
                    echo ' - <a href="?act=avatars&amp;id=' . $id . '&amp;avatar=' . basename($array[$i]) . '">' . _t('Select') . '</a>';
                }

                echo '</div>';
            }
        } else {
            echo '<div class="menu">' . _t('The list is empty') . '</div>';
        }

        echo '<div class="phdr">' . _t('Total') . ': ' . $total . '</div>';

        if ($total > $user->config->kmess) {
            echo '<p>' . $tools->displayPagination('?act=avatars&amp;id=' . $id . '&amp;', $start, $total, $user->config->kmess) . '</p>' .
                '<p><form action="?act=avatars&amp;id=' . $id . '" method="post">' .
                '<input type="text" name="page" size="2"/>' .
                '<input type="submit" value="' . _t('To Page') . ' &gt;&gt;"/>' .
                '</form></p>';
        }

        echo '<p><a href="?act=avatars">' . _t('Back') . '</a><br />';
    }
} else {
    // Показываем каталоги с Аватарами
    echo '<div class="phdr"><a href="?"><b>' . _t('Information, FAQ') . '</b></a> | ' . _t('Avatars') . '</div>';
    $dir = glob(ASSETS_PATH . 'avatars/*', GLOB_ONLYDIR);
    $total = 0;
    $total_dir = count($dir);

    for ($i = 0; $i < $total_dir; $i++) {
        $count = (int) count(glob($dir[$i] . '/*.png'));
        $total = $total + $count;
        echo $i % 2 ? '<div class="list2">' : '<div class="list1">';
        echo '<a href="?act=avatars&amp;id=' . basename($dir[$i]) . '">' . htmlentities(file_get_contents($dir[$i] . '/name.txt'), ENT_QUOTES, 'utf-8') .
            '</a> (' . $count . ')</div>';
    }

    echo '<div class="phdr">' . _t('Total') . ': ' . $total . '</div>' .
        '<p><a href="' . $_SESSION['ref'] . '">' . _t('Back') . '</a></p>';
}
