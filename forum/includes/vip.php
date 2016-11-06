<?php

defined('_IN_JOHNCMS') or die('Error: restricted access');

/** @var Interop\Container\ContainerInterface $container */
$container = App::getContainer();

/** @var PDO $db */
$db = $container->get(PDO::class);

/** @var Johncms\User $systemUser */
$systemUser = $container->get(Johncms\User::class);

/** @var Johncms\Tools $tools */
$tools = $container->get('tools');

if ($systemUser->rights == 3 || $systemUser->rights >= 6) {
    if (empty($_GET['id'])) {
        require('../system/head.php');
        echo $tools->displayError(_t('Wrong data'));
        require('../system/end.php');
        exit;
    }

    if ($db->query("SELECT COUNT(*) FROM `forum` WHERE `id` = '" . $id . "' AND `type` = 't'")->fetchColumn()) {
        $db->exec("UPDATE `forum` SET  `vip` = '" . (isset($_GET['vip']) ? '1' : '0') . "' WHERE `id` = '$id'");
        header('Location: index.php?id=' . $id);
    } else {
        require('../system/head.php');
        echo $tools->displayError(_t('Wrong data'));
        require('../system/end.php');
        exit;
    }
}
