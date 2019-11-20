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
 * @var PDO                              $db
 * @var Johncms\Api\ToolsInterface       $tools
 * @var Johncms\Api\UserInterface        $user
 */

$config = di('config')['johncms'];

// Проверяем права доступа
if ($user->rights < 9) {
    exit(_t('Access denied'));
}

// Выводим список доступных языков
echo '<div class="phdr"><a href="./"><b>' . _t('Admin Panel') . '</b></a> | ' . _t('Default language') . '</div>';

if (isset($_POST['lng']) || isset($_GET['refresh'])) {
    if (isset($_POST['lng'])) {
        $select = trim($_POST['lng']);

        if (isset($config['lng_list'][$select])) {
            $config['lng'] = $select;
        }
    } elseif (isset($_GET['refresh'])) {
        // Обновляем список имеющихся языков
        $lng_list = [];

        foreach (glob(ROOT_PATH . 'system/locale/*/lng.ini') as $val) {
            $iso = basename(dirname($val));
            $desc = parse_ini_file($val);
            $lng_list[$iso] = isset($desc['name']) && ! empty($desc['name']) ? $desc['name'] : $iso;
        }

        $config['lng_list'] = $lng_list;
        echo '<div class="gmenu"><p>' . _t('Descriptions have been updated successfully') . '</p></div>';
    }

    $configFile = "<?php\n\n" . 'return ' . var_export(['johncms' => $config], true) . ";\n";

    if (! file_put_contents(CONFIG_PATH . 'autoload/system.local.php', $configFile)) {
        echo 'ERROR: Can not write system.local.php</body></html>';
        exit;
    }

    if (function_exists('opcache_reset')) {
        opcache_reset();
    }
}

echo '<div class="menu">'
    . '<form action="?act=languages" method="post">'
    . '<p><h3>' . _t('Select language') . '</h3>';

foreach ($config['lng_list'] as $key => $val) {
    echo '<div><input type="radio" value="' . $key . '" name="lng" ' . ($key == $config['lng'] ? 'checked="checked"' : '') . '/>&#160;' .
        $tools->getFlag($key) .
        $val .
        ($key == $config->lng ? ' <small class="red">[' . _t('Default', 'system') . ']</small>' : '') .
        '</div>';
}

echo '</p><p>'
    . '<input type="submit" name="submit" value="' . _t('Apply') . '" />'
    . '</p></form></div>'
    . '<div class="phdr">' . _t('Total') . ': <b>' . count($config['lng_list']) . '</b></div><p>'
    . '<a href="?act=languages&amp;refresh">' . _t('Update List') . '</a><br>'
    . '<a href="./">' . _t('Admin Panel') . '</a></p>';

echo $view->render('system::app/old_content', [
    'title'   => _t('Admin Panel'),
    'content' => ob_get_clean(),
]);
