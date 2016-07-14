<?php

defined('_IN_JOHNCMS') or die('Error: restricted access');

/** @var PDO $db */
$db = App::getContainer()->get(PDO::class);

// Загрузка выбранного файла и обработка счетчика скачиваний
$error = array ();
$req = $db->query("SELECT * FROM `cms_album_files` WHERE `id` = '$img'");

if ($req->rowCount()) {
    $res = $req->fetch();
    // Проверка прав доступа
    if ($rights < 6 && $user_id != $res['user_id']) {
        $req_a = $db->query("SELECT * FROM `cms_album_cat` WHERE `id` = '" . $res['album_id'] . "'");

        if ($req_a->rowCount()) {
            $res_a = $req_a->fetch();
            if($res_a['access'] == 1 || $res_a['access'] == 2 && (!isset($_SESSION['ap']) || $_SESSION['ap'] != $res_a['password']))
                $error[] = $lng['access_forbidden'];
        } else {
            $error[] = $lng['error_wrong_data'];
        }
    }
    // Проверка наличия файла
    if (!$error && !file_exists('../files/users/album/' . $res['user_id'] . '/' . $res['img_name']))
        $error[] = $lng['error_file_not_exist'];
} else {
    $error[] = $lng['error_wrong_data'];
}
if (!$error) {
    // Счетчик скачиваний
    if (!$db->query("SELECT COUNT(*) FROM `cms_album_downloads` WHERE `user_id` = '$user_id' AND `file_id` = '$img'")->fetchColumn()) {
        $db->exec("INSERT INTO `cms_album_downloads` SET `user_id` = '$user_id', `file_id` = '$img', `time` = '" . time() . "'");
        $downloads = $db->query("SELECT COUNT(*) FROM `cms_album_downloads` WHERE `file_id` = '$img'")->fetchColumn();
        $db->exec("UPDATE `cms_album_files` SET `downloads` = '$downloads' WHERE `id` = '$img'");
    }
    // Отдаем файл
    header('location: ' . $set['homeurl'] . '/files/users/album/' . $res['user_id'] . '/' . $res['img_name']);
} else {
    require('../incfiles/head.php');
    echo functions::display_error($error, '<a href="album.php">' . $lng['back'] . '</a>');
}
