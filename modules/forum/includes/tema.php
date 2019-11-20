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
 * @var Johncms\Api\ConfigInterface $config
 * @var PDO                         $db
 * @var Johncms\Api\ToolsInterface  $tools
 * @var Johncms\Api\UserInterface   $user
 */

$delf = opendir(UPLOAD_PATH . 'forum/topics');
$tm = [];

while ($tt = readdir($delf)) {
    if ($tt != '.' && $tt != '..' && $tt != 'index.php') {
        $tm[] = $tt;
    }
}

closedir($delf);
$totalt = count($tm);

for ($it = 0; $it < $totalt; $it++) {
    $filtime[$it] = filemtime(UPLOAD_PATH . 'forum/topics/' . $tm[$it]);
    $tim = time();
    $ftime1 = $tim - 300;
    if ($filtime[$it] < $ftime1) {
        unlink(UPLOAD_PATH . 'forum/topics/' . $tm[$it]);
    }
}

if (! $id) {
    echo $tools->displayError(_t('Wrong data'));
    echo $view->render('system::app/old_content', ['title' => $textl ?? '', 'content' => ob_get_clean()]);
    exit;
}

$req = $db->query("SELECT * FROM `forum_topic` WHERE `id` = '${id}' AND (`deleted` != '1' OR `deleted` IS NULL)");

if (! $req->rowCount()) {
    echo $tools->displayError(_t('Wrong data'));
    echo $view->render('system::app/old_content', ['title' => $textl ?? '', 'content' => ob_get_clean()]);
    exit;
}

if (isset($_POST['submit'])) {
    $type1 = $req->fetch();
    $tema = $db->query("SELECT * FROM `forum_messages` WHERE `topic_id` = '${id}'" . ($user->rights >= 7 ? '' : " AND (`deleted` != '1' OR `deleted` IS NULL)") . ' ORDER BY `id` ASC');
    $mod = (int) ($_POST['mod']);

    switch ($mod) {
        case 1:
            // Сохраняем тему в текстовом формате
            $text = $type1['name'] . "\r\n\r\n";

            while ($arr = $tema->fetch()) {
                $txt_tmp = str_replace('[c]', _t('Quote') . ':{', $arr['text']);
                $txt_tmp = str_replace('[/c]', '}-' . _t('Answer') . ':', $txt_tmp);
                $txt_tmp = str_replace('&quot;', '"', $txt_tmp);
                $txt_tmp = str_replace('[l]', '', $txt_tmp);
                $txt_tmp = str_replace('[l/]', '-', $txt_tmp);
                $txt_tmp = str_replace('[/l]', '', $txt_tmp);
                $stroka = $arr['user_name'] . '(' . date('d.m.Y/H:i', $arr['date']) . ")\r\n" . $txt_tmp . "\r\n\r\n";
                $text .= $stroka;
            }

            $num = time() . $id;
            $fp = fopen(UPLOAD_PATH . 'forum/topics/' . $num . '.txt', 'a+');
            flock($fp, LOCK_EX);
            fwrite($fp, "${text}\r\n");
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
            @chmod("${fp}", 0777);
            @chmod(UPLOAD_PATH . 'forum/topics/' . $num . '.txt', 0777);
            echo '<a href="?act=loadtem&amp;n=' . $num . '">' . _t('Download') . '</a><br>' . _t('Link active 5 minutes') . '<br><a href="./">' . _t('Forum') . '</a><br>';
            break;

        case 2:
            // Сохраняем тему в формате HTML
            $text = "<!DOCTYPE html PUBLIC '-//W3C//DTD HTML 4.01 Transitional//EN'><html><head><meta http-equiv='Content-Type' content='text/html; charset=utf-8'>
<title>" . _t('Forum') . "</title>
<style type='text/css'>
body { color: #000000; background-color: #FFFFFF }
div { margin: 1px 0px 1px 0px; padding: 5px 5px 5px 5px;}
.b {background-color: #FFFFFF; }
.c {background-color: #EEEEEE; }
.quote{font-size: x-small; padding: 2px 0px 2px 4px; color: #878787; border-left: 3px solid #c0c0c0;
}
</style></head>
<body><p><b><u>{$type1['text']}</u></b></p>";

            $i = 1;

            while ($arr = $tema->fetch()) {
                $d = $i / 2;
                $d1 = ceil($d);
                $d2 = $d1 - $d;
                $d3 = ceil($d2);

                if ($d3 == 0) {
                    $div = "<div class='b'>";
                } else {
                    $div = "<div class='c'>";
                }

                $txt_tmp = htmlentities($arr['text'], ENT_QUOTES, 'UTF-8');
                $txt_tmp = di(Johncms\Api\BbcodeInterface::class)->tags($txt_tmp);
                $txt_tmp = preg_replace('#\[c\](.*?)\[/c\]#si', '<div class="quote">\1</div>', $txt_tmp);
                $txt_tmp = str_replace("\r\n", '<br>', $txt_tmp);
                $stroka = "${div} <b>" . $arr['user_name'] . '</b>(' . date('d.m.Y/H:i',
                        $arr['date']) . ")<br>${txt_tmp}</div>";
                $text = "${text} ${stroka}";
                ++$i;
            }
            $text = $text . '<p>' . _t('This theme was downloaded from the forum site') . ': <b>' . $config['copyright'] . '</b></p></body></html>';
            $num = time() . $id;
            $fp = fopen(UPLOAD_PATH . 'forum/topics/' . $num . '.htm', 'a+');
            flock($fp, LOCK_EX);
            fwrite($fp, "${text}\r\n");
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
            @chmod("${fp}", 0777);
            @chmod(UPLOAD_PATH . 'forum/topics/' . $num . '.htm', 0777);
            echo '<a href="?act=loadtem&amp;n=' . $num . '">' . _t('Download') . '</a><br>' . _t('Link active 5 minutes') . '<br><a href="./">' . _t('Forum') . '</a><br>';
            break;
    }
} else {
    echo '<p>' . _t('Select format') . '<br>' .
        '<form action="?act=tema&amp;id=' . $id . '" method="post">' .
        '<select name="mod"><option value="1">.txt</option>' .
        '<option value="2">.htm</option></select>' .
        '<input type="submit" name="submit" value="' . _t('Download') . '"/>' .
        '</form></p>';
}
