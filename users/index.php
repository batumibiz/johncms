<?php

define('_IN_JOHNCMS', 1);

$id = isset($_REQUEST['id']) ? abs(intval($_REQUEST['id'])) : 0;
$act = isset($_GET['act']) ? trim($_GET['act']) : '';
$mod = isset($_GET['mod']) ? trim($_GET['mod']) : '';
$page = isset($_REQUEST['page']) && $_REQUEST['page'] > 0 ? intval($_REQUEST['page']) : 1;
$start = isset($_REQUEST['page']) ? $page * $kmess - $kmess : (isset($_GET['start']) ? abs(intval($_GET['start'])) : 0);

$headmod = 'users';
require('../system/bootstrap.php');

/** @var Interop\Container\ContainerInterface $container */
$container = App::getContainer();

/** @var Johncms\User $systemUser */
$systemUser = $container->get(Johncms\User::class);

/** @var Johncms\Config $config */
$config = $container->get(Johncms\Config::class);

/** @var Zend\I18n\Translator\Translator $translator */
$translator = $container->get(Zend\I18n\Translator\Translator::class);
$translator->addTranslationFilePattern('gettext', __DIR__ . '/locale', '/%s/default.mo');

/** @var Johncms\Tools $tools */
$tools = $container->get('tools');

// Закрываем от неавторизованных юзеров
if (!$systemUser->isValid() && !$config->active) {
    require('../system/head.php');
    echo $tools->displayError(_t('For registered users only'));
    require('../system/end.php');
    exit;
}

// Переключаем режимы работы
$array = [
    'admlist'  => 'includes',
    'birth'    => 'includes',
    'online'   => 'includes',
    'top'      => 'includes',
    'userlist' => 'includes',
];
$path = !empty($array[$act]) ? $array[$act] . '/' : '';

if (array_key_exists($act, $array) && file_exists($path . $act . '.php')) {
    require_once($path . $act . '.php');
} else {
    /** @var PDO $db */
    $db = $container->get(PDO::class);

    /** @var Johncms\Counters $counters */
    $counters = $container->get('counters');

    // Актив сайта
    $textl = _t('Community');
    require('../system/head.php');

    $brth = $db->query("SELECT COUNT(*) FROM `users` WHERE `dayb` = '" . date('j', time()) . "' AND `monthb` = '" . date('n', time()) . "' AND `preg` = '1'")->fetchColumn();
    $count_adm = $db->query("SELECT COUNT(*) FROM `users` WHERE `rights` > 0")->fetchColumn();

    echo '<div class="phdr"><b>' . _t('Community') . '</b></div>' .
        '<div class="gmenu"><form action="search.php" method="post">' .
        '<p><h3><img src="../images/search.png" width="16" height="16" class="left" />&#160;' . _t('Look for the User') . '</h3>' .
        '<input type="text" name="search"/>' .
        '<input type="submit" value="' . _t('Search') . '" name="submit" /><br />' .
        '<small>' . _t('The search is performed by Nickname and are case-insensitive.') . '</small></p></form></div>' .
        '<div class="menu"><p>' .
        $tools->image('contacts.png', ['width' => 16, 'height' => 16]) . '<a href="index.php?act=userlist">' . _t('Users') . '</a> (' . $container->get('counters')->users() . ')<br />' .
        $tools->image('users.png', ['width' => 16, 'height' => 16]) . '<a href="index.php?act=admlist">' . _t('Administration') . '</a> (' . $count_adm . ')<br>' .
        ($brth ? $tools->image('award.png', ['width' => 16, 'height' => 16]) . '<a href="index.php?act=birth">' . _t('Birthdays') . '</a> (' . $brth . ')<br>' : '') .
        $tools->image('photo.gif', ['width' => 16, 'height' => 16]) . '<a href="../album/index.php">' . _t('Photo Albums') . '</a> (' . $counters->album() . ')<br>' .
        $tools->image('rate.gif', ['width' => 16, 'height' => 16]) . '<a href="index.php?act=top">' . _t('Top Activity') . '</a></p>' .
        '</div>' .
        '<div class="phdr"><a href="index.php">' . _t('Back') . '</a></div>';
}

require_once('../system/end.php');
