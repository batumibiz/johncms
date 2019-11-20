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

$config = di('config')['johncms'];

echo '<div class="phdr"><a href="./"><b>' . _t('Admin Panel') . '</b></a> | ' . _t('News on the mainpage') . '</div>';

// Получаем сохраненные настройки
$settings = $config['news'];

// Настройки Новостей
if (isset($_POST['submit'])) {
    // Принимаем настройки из формы
    $settings['view'] = isset($_POST['view']) && $_POST['view'] >= 0 && $_POST['view'] < 4 ? (int) ($_POST['view']) : 1;
    $settings['size'] = isset($_POST['size']) && $_POST['size'] > 49 && $_POST['size'] < 501 ? (int) ($_POST['size']) : 200;
    $settings['quantity'] = isset($_POST['quantity']) && $_POST['quantity'] > 0 && $_POST['quantity'] < 16 ? (int) ($_POST['quantity']) : 3;
    $settings['days'] = isset($_POST['days']) && $_POST['days'] > 0 && $_POST['days'] < 31 ? (int) ($_POST['days']) : 7;
    $settings['breaks'] = isset($_POST['breaks']);
    $settings['smileys'] = isset($_POST['smileys']);
    $settings['tags'] = isset($_POST['tags']);
    $settings['kom'] = isset($_POST['kom']);

    $config['news'] = $settings;
    $configFile = "<?php\n\n" . 'return ' . var_export(['johncms' => $config], true) . ";\n";

    if (! file_put_contents(CONFIG_PATH . 'autoload/system.local.php', $configFile)) {
        echo 'ERROR: Can not write system.local.php</body></html>';
        exit;
    }

    echo '<div class="gmenu"><p>' . _t('Settings are saved successfully') . '</p></div>';

    if (function_exists('opcache_reset')) {
        opcache_reset();
    }
}

// Форма ввода настроек
echo '<form action="?act=news" method="post"><div class="menu"><p>' .
    '<h3>' . _t('Appearance') . '</h3>' .
    '<input type="radio" value="1" name="view" ' . ($settings['view'] == 1 ? 'checked="checked"' : '') . '/>&#160;' . _t('Title + Text') . '<br>' .
    '<input type="radio" value="2" name="view" ' . ($settings['view'] == 2 ? 'checked="checked"' : '') . '/>&#160;' . _t('Title') . '<br>' .
    '<input type="radio" value="3" name="view" ' . ($settings['view'] == 3 ? 'checked="checked"' : '') . '/>&#160;' . _t('Text') . '<br>' .
    '<input type="radio" value="0" name="view" ' . (! $settings['view'] ? 'checked="checked"' : '') . '/>&#160;<span class="red">' . _t('Not to show') . '</span></p>' .
    '<p><input name="breaks" type="checkbox" value="1" ' . ($settings['breaks'] ? 'checked="checked"' : '') . ' />&#160;' . _t('Line breaks') . '<br>' .
    '<input name="smileys" type="checkbox" value="1" ' . ($settings['smileys'] ? 'checked="checked"' : '') . ' />&#160;' . _t('Smilies') . '<br>' .
    '<input name="tags" type="checkbox" value="1" ' . ($settings['tags'] ? 'checked="checked"' : '') . ' />&#160;' . _t('bbCode Tags') . '<br>' .
    '<input name="kom" type="checkbox" value="1" ' . ($settings['kom'] ? 'checked="checked"' : '') . ' />&#160;' . _t('Comments') . '</p>' .
    '<p><h3>' . _t('Text size') . '</h3>&#160;' .
    '<input type="text" size="3" maxlength="3" name="size" value="' . $settings['size'] . '" />&#160;(50 - 500)</p>' .
    '<p><h3>' . _t('Quantity of news') . '</h3>&#160;<input type="text" size="3" maxlength="2" name="quantity" value="' . $settings['quantity'] . '" />&#160;(1 - 15)</p>' .
    '<p><h3>' . _t('How many days to show?') . '</h3>&#160;<input type="text" size="3" maxlength="2" name="days" value="' . $settings['days'] . '" />&#160;(1 - 30)</p>' .
    '<br><p><input type="submit" value="' . _t('Save') . '" name="submit" /></p></div>' .
    '<div class="phdr"><a href="?act=news&amp;reset">' . _t('Reset Settings') . '</a>' .
    '</div></form>' .
    '<p><a href="./">' . _t('Admin Panel') . '</a></p>';

echo $view->render('system::app/old_content', [
    'title'   => _t('Admin Panel'),
    'content' => ob_get_clean(),
]);
