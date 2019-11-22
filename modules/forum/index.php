<?php

declare(strict_types=1);

/*
 * This file is part of JohnCMS Content Management System.
 *
 * @copyright JohnCMS Community
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0
 * @link      https://johncms.com JohnCMS Project
 */

use Johncms\Api\ConfigInterface;
use Johncms\Api\NavChainInterface;
use Johncms\Api\ToolsInterface;
use Johncms\Api\UserInterface;
use Johncms\Utility\Counters;
use Johncms\View\Extension\Assets;
use League\Plates\Engine;
use Zend\I18n\Translator\Translator;

defined('_IN_JOHNCMS') || die('Error: restricted access');
ob_start(); // Перехват вывода скриптов без шаблона

/**
 * @var Assets            $assets
 * @var ConfigInterface   $config
 * @var Counters          $counters
 * @var PDO               $db
 * @var ToolsInterface    $tools
 * @var UserInterface     $user
 * @var Engine            $view
 * @var NavChainInterface $nav_chain
 */
$assets = di(Assets::class);
$config = di(ConfigInterface::class);
$counters = di('counters');
$db = di(PDO::class);
$user = di(UserInterface::class);
$tools = di(ToolsInterface::class);
$view = di(Engine::class);
$nav_chain = di(NavChainInterface::class);

// Регистрируем папку с языками модуля
di(Translator::class)->addTranslationFilePattern('gettext', __DIR__ . '/locale', '/%s/default.mo');

// Регистрируем Namespace для шаблонов модуля
$view->addFolder('forum', __DIR__ . '/templates/');

// Добавляем раздел в навигационную цепочку
$nav_chain->add(_t('Forum'), '/forum/');

$id = isset($_REQUEST['id']) ? abs((int) ($_REQUEST['id'])) : 0;
$act = isset($_GET['act']) ? trim($_GET['act']) : '';
$mod = isset($_GET['mod']) ? trim($_GET['mod']) : '';
$do = isset($_REQUEST['do']) ? trim($_REQUEST['do']) : false;

if (isset($_SESSION['ref'])) {
    unset($_SESSION['ref']);
}

// Настройки форума
$set_forum = $user->isValid() ? unserialize($user->set_forum, ['allowed_classes' => false]) : [
    'farea'    => 0,
    'upfp'     => 0,
    'preview'  => 1,
    'postclip' => 1,
    'postcut'  => 2,
];

// Список расширений файлов, разрешенных к выгрузке

// Файлы архивов
$ext_arch = [
    'zip',
    'rar',
    '7z',
    'tar',
    'gz',
    'apk',
];
// Звуковые файлы
$ext_audio = [
    'mp3',
    'amr',
];
// Файлы документов и тексты
$ext_doc = [
    'txt',
    'pdf',
    'doc',
    'docx',
    'rtf',
    'djvu',
    'xls',
    'xlsx',
];
// Файлы Java
$ext_java = [
    'sis',
    'sisx',
    'apk',
];
// Файлы картинок
$ext_pic = [
    'jpg',
    'jpeg',
    'gif',
    'png',
    'bmp',
];
// Файлы SIS
$ext_sis = [
    'sis',
    'sisx',
];
// Файлы видео
$ext_video = [
    '3gp',
    'avi',
    'flv',
    'mpeg',
    'mp4',
];
// Файлы Windows
$ext_win = [
    'exe',
    'msi',
];
// Другие типы файлов (что не перечислены выше)
$ext_other = ['wmf'];

// Ограничиваем доступ к Форуму
$error = '';

if (! $config->mod_forum && $user->rights < 7) {
    $error = _t('Forum is closed');
} elseif ($config->mod_forum == 1 && ! $user->isValid()) {
    $error = _t('For registered users only');
}

if ($error) {
    echo $view->render('system::pages/result', [
        'title'   => _t('Forum'),
        'type'    => 'alert-danger',
        'message' => $error,
    ]);
    exit;
}
$show_type = $_REQUEST['type'] ?? 'section';

// Переключаем режимы работы
$mods = [
    'addfile',
    'addvote',
    'close',
    'deltema',
    'delvote',
    'editpost',
    'editvote',
    'file',
    'files',
    'filter',
    'loadtem',
    'massdel',
    'new',
    'nt',
    'per',
    'show_post',
    'ren',
    'restore',
    'say',
    'search',
    'tema',
    'users',
    'vip',
    'vote',
    'who',
    'curators',
];

if ($act && ($key = array_search($act, $mods)) !== false && file_exists(__DIR__ . '/includes/' . $mods[$key] . '.php')) {
    require __DIR__ . '/includes/' . $mods[$key] . '.php';
} else {
    // Заголовки страниц форума
    if (! empty($id)) {
        // Фиксируем местоположение и получаем заголовок страницы
        switch ($show_type) {
            case 'topics':
            case 'section':
                $res = $db->query('SELECT `name` FROM `forum_sections` WHERE `id`= ' . $id)->fetch();
                break;

            case 'topic':
                $res = $db->query('SELECT `name` FROM `forum_topic` WHERE `id`= ' . $id)->fetch();
                break;

            default:
                $headmod = 'forum';
        }

        $hdr = preg_replace('#\[c\](.*?)\[/c\]#si', '', $res['name']);
        $hdr = strtr($hdr, [
            '&laquo;' => '',
            '&raquo;' => '',
            '&quot;'  => '',
            '&amp;'   => '',
            '&lt;'    => '',
            '&gt;'    => '',
            '&#039;'  => '',
        ]);
        $hdr = mb_substr($hdr, 0, 30);
        $hdr = $tools->checkout($hdr, 2, 2);
        $textl = empty($hdr) ? _t('Forum') : $hdr;
    }

    // Редирект на новые адреса страниц
    if (! empty($id)) {
        $check_section = $db->query("SELECT * FROM `forum_sections` WHERE `id`= '${id}'");
        if (! $check_section->rowCount() && (empty($_REQUEST['type']) || (! empty($_REQUEST['act']) && $_REQUEST['act'] == 'post'))) {
            $check_link = $db->query("SELECT * FROM `forum_redirects` WHERE `old_id`= '${id}'")->fetch();
            if (! empty($check_link)) {
                http_response_code(301);
                header('Location: ' . $check_link['new_link']);
                exit;
            }
        }
    }

    // Если форум закрыт, то для Админов выводим напоминание
    //TODO: Move to template

    /*  if (! $config->mod_forum) {
            echo '<div class="alarm">' . _t('Forum is closed') . '</div>';
        } elseif ($config->mod_forum == 3) {
            echo '<div class="rmenu">' . _t('Read only') . '</div>';
        }*/

    if (! $user->isValid()) {
        if (isset($_GET['newup'])) {
            $_SESSION['uppost'] = 1;
        }

        if (isset($_GET['newdown'])) {
            $_SESSION['uppost'] = 0;
        }
    }

    if ($id) {
        // Определяем тип запроса (каталог, или тема)
        if ($show_type == 'topic') {
            $type = $db->query("SELECT * FROM `forum_topic` WHERE `id`= '${id}'");
        } else {
            $type = $db->query("SELECT * FROM `forum_sections` WHERE `id`= '${id}'");
        }

        if (! $type->rowCount()) {
            // Если темы не существует, показываем ошибку
            echo $view->render('system::pages/result', [
                'title'    => _t('Forum'),
                'type'     => 'alert-danger',
                'message'  => _t('Topic has been deleted or does not exists'),
                'back_url' => '/forum/',
            ]);
            exit;
        }

        $type1 = $type->fetch();

        // Фиксация факта прочтения Топика
        if ($user->isValid() && $show_type == 'topic') {
            $db->query("INSERT INTO `cms_forum_rdm` (topic_id,  user_id, `time`)
                VALUES ('${id}', '" . $user->id . "', '" . time() . "')
                ON DUPLICATE KEY UPDATE `time` = VALUES(`time`)");
        }

        // Получаем структуру форума
        $res = true;
        $allow = 0;

        $parent = $show_type == 'topic' ? $type1['section_id'] : $type1['parent'];

        while (! empty($parent) && $res != false) {
            $res = $db->query("SELECT * FROM `forum_sections` WHERE `id` = '${parent}' LIMIT 1")->fetch();

            $nav_chain->add($res['name'], '/forum/?' . ($res['section_type'] == 1 ? 'type=topics&amp;' : '') . 'id=' . $parent);

            // TODO: Replace to nav chain
            $tree[] = '<a href="?' . ($res['section_type'] == 1 ? 'type=topics&amp;' : '') . 'id=' . $parent . '">' . $res['name'] . '</a>';

            /*if ($res['type'] == 'r' && !empty($res['edit'])) {
                $allow = intval($res['edit']);
            }*/

            $parent = $res['parent'];
        }

        $tree[] = '<a href="./">' . _t('Forum') . '</a>';
        krsort($tree);

        $tree[] = '<b>' . $type1['name'] . '</b>';

        $nav_chain->add($type1['name']);

        // Счетчик файлов и ссылка на них
        $sql = ($user->rights == 9) ? '' : " AND `del` != '1'";

        if ($show_type == 'topic') {
            $count = $db->query("SELECT COUNT(*) FROM `cms_forum_files` WHERE `topic` = '${id}'" . $sql)->fetchColumn();

            if ($count > 0) {
                $filelink = '<a href="?act=files&amp;t=' . $id . '">' . _t('Topic Files') . '</a>';
            }
        } elseif ($type1['section_type'] == 0) {
            $count = $db->query('SELECT COUNT(*) FROM `cms_forum_files` WHERE `cat` = ' . $type1['id'] . $sql)->fetchColumn();

            if ($count > 0) {
                $filelink = '<a href="?act=files&amp;c=' . $id . '">' . _t('Category Files') . '</a>';
            }
        } elseif ($type1['section_type'] == 1) {
            $count = $db->query("SELECT COUNT(*) FROM `cms_forum_files` WHERE `subcat` = '${id}'" . $sql)->fetchColumn();

            if ($count > 0) {
                $filelink = '<a href="?act=files&amp;s=' . $id . '">' . _t('Section Files') . '</a>';
            }
        }

        $filelink = isset($filelink) ? $filelink . '&#160;<span class="red">(' . $count . ')</span>' : false;

        // Счетчик "Кто в теме?"
        $wholink = false;

        if ($user->isValid() && $show_type == 'topic') {
            $online = $db->query('SELECT (
SELECT COUNT(*) FROM `users` WHERE `lastdate` > ' . (time() - 300) . " AND `place` LIKE '/forum?type=topic&id=${id}%') AS online_u, (
SELECT COUNT(*) FROM `cms_sessions` WHERE `lastdate` > " . (time() - 300) . " AND `place` LIKE '/forum?type=topic&id=${id}%') AS online_g")->fetch();
            $wholink = '<a href="?act=who&amp;id=' . $id . '">' . _t('Who is here') . '?</a>&#160;<span class="red">(' . $online['online_u'] . '&#160;/&#160;' . $online['online_g'] . ')</span>';
        }

        if ($show_type !== 'section' && $show_type !== 'topics') {
            // Выводим верхнюю панель навигации
            echo '<a id="up"></a><p>' . $counters->forumNew(1) . '</p>' .
                '<div class="phdr">' . implode(' / ', $tree) . '</div>' .
                '<div class="topmenu"><a href="?act=search&amp;id=' . $id . '">' . _t('Search') . '</a>' . ($filelink ? ' | ' . $filelink : '') . ($wholink ? ' | ' . $wholink : '') . '</div>';
        }

        switch ($show_type) {
            case 'section':
                // List of forum sections
                $req = $db->query("SELECT * FROM `forum_sections` WHERE `parent`='${id}' ORDER BY `sort`");
                $total = $req->rowCount();
                $sections = [];
                if ($total) {
                    while ($res = $req->fetch()) {
                        if ($res['section_type'] == 1) {
                            $children_count = $db->query("SELECT COUNT(*) FROM `forum_topic` WHERE `section_id` = '" . $res['id'] . "'" . ($user->rights >= 7 ? '' : " AND (`deleted` != '1' OR deleted IS NULL)"))->fetchColumn();
                        } else {
                            $children_count = $db->query("SELECT COUNT(*) FROM `forum_sections` WHERE `parent` = '" . $res['id'] . "'")->fetchColumn();
                        }

                        $res['children_count'] = $children_count;
                        $res['url'] = '?' . ($res['section_type'] == 1 ? 'type=topics&amp;' : '') . 'id=' . $res['id'];
                        $sections[] = $res;
                    }
                    unset($_SESSION['fsort_id'], $_SESSION['fsort_users']);
                }

                $online = $db->query('SELECT (SELECT COUNT(*) FROM `users` WHERE `lastdate` > ' . (time() - 300) . " AND `place` LIKE '/forum%') AS online_u, 
       (SELECT COUNT(*) FROM `cms_sessions` WHERE `lastdate` > " . (time() - 300) . " AND `place` LIKE '/forum%') AS online_g")->fetch();

                echo $view->render('forum::section', [
                    'title'        => $type1['name'],
                    'page_title'   => $type1['name'],
                    'sections'     => $sections,
                    'online'       => $online,
                    'total'        => $total,
                    'files_count'  => $count,
                    'unread_count' => $counters->forumUnreadCount(),
                ]);
                exit; // TODO: Remove this later
                break;

            case 'topics':
                // List of forum topics
                $total = $db->query("SELECT COUNT(*) FROM `forum_topic` WHERE `section_id` = '${id}'" . ($user->rights >= 7 ? '' : " AND (`deleted` != '1' OR deleted IS NULL)"))->fetchColumn();
                if ($total) {
                    $req = $db->query('SELECT tpc.*, (
SELECT COUNT(*) FROM `cms_forum_rdm` WHERE `time` >= tpc.last_post_date AND `topic_id` = tpc.id AND `user_id` = ' . $user->id . ") as `np`
FROM `forum_topic` tpc WHERE `section_id` = '${id}'" . ($user->rights >= 7 ? '' : " AND (`deleted` <> '1' OR deleted IS NULL)") . "
ORDER BY `pinned` DESC, `last_post_date` DESC LIMIT ${start}, " . $user->config->kmess);

                    $topics = [];
                    while ($res = $req->fetch()) {

                        if ($user->rights >= 7) {
                            $res['show_posts_count'] = $res['mod_post_count'];
                            $res['show_last_author'] = $res['mod_last_post_author_name'];
                            $res['show_last_post_date'] = $tools->displayDate($res['mod_last_post_date']);
                        } else {
                            $res['show_posts_count'] = $res['post_count'];
                            $res['show_last_author'] = $res['last_post_author_name'];
                            $res['show_last_post_date'] = $tools->displayDate($res['last_post_date']);
                        }

                        $res['url'] = '/forum/?type=topic&amp;id=' . $res['id'];

                        // Url to last page
                        $res['last_page_url'] = '';
                        $cpg = ceil($res['show_posts_count'] / $user->config->kmess);
                        if ($cpg > 1) {
                            $res['last_page_url'] = '/forum/?type=topic&amp;id=' . $res['id'] . '&amp;page=' . $cpg;
                        }

                        // Icons
                        $icons = [
                            ($res['np']
                                ? (! $res['pinned'] ? '<img src="' . $assets->url('images/old/op.gif') . '" alt="" class="icon">' : '')
                                : '<img src="' . $assets->url('images/old/np.gif') . '" alt="" class="icon">'
                            ),
                            ($res['pinned']
                                ? '<img src="' . $assets->url('images/old/pt.gif') . '" alt="" class="icon">'
                                : ''
                            ),
                            ($res['has_poll']
                                ? '<img src="' . $assets->url('images/old/rate.gif') . '" alt="" class="icon">'
                                : ''
                            ),
                            ($res['closed']
                                ? '<img src="' . $assets->url('images/old/tz.gif') . '" alt="" class="icon">'
                                : ''
                            ),
                        ];
                        $res['icons'] = implode('', array_filter($icons));

                        $topics[] = $res;
                    }
                    unset($_SESSION['fsort_id'], $_SESSION['fsort_users']);
                }

                $online = $db->query('SELECT (SELECT COUNT(*) FROM `users` WHERE `lastdate` > ' . (time() - 300) . " AND `place` LIKE '/forum%') AS online_u, 
       (SELECT COUNT(*) FROM `cms_sessions` WHERE `lastdate` > " . (time() - 300) . " AND `place` LIKE '/forum%') AS online_g")->fetch();

                // Check access to create topic
                $create_access = false;
                if (($user->isValid() && ! isset($user->ban['1']) && ! isset($user->ban['11']) && $config->mod_forum != 4) || $user->rights) {
                    $create_access = true;
                }

                echo $view->render('forum::topics', [
                    'pagination'    => $tools->displayPagination('?type=topics&id=' . $id . '&amp;', $start, $total, $user->config->kmess),
                    'id'            => $id,
                    'create_access' => $create_access,
                    'title'         => $type1['name'],
                    'page_title'    => $type1['name'],
                    'topics'        => $topics ?? [],
                    'online'        => $online,
                    'total'         => $total,
                    'files_count'   => $count,
                    'unread_count'  => $counters->forumUnreadCount(),
                ]);
                exit; // TODO: Remove this later
                break;

            case 'topic':
                ////////////////////////////////////////////////////////////
                // Показываем тему с постами                              //
                ////////////////////////////////////////////////////////////
                $filter = isset($_SESSION['fsort_id']) && $_SESSION['fsort_id'] == $id ? 1 : 0;
                $sql = '';

                if ($filter && ! empty($_SESSION['fsort_users'])) {
                    // Подготавливаем запрос на фильтрацию юзеров
                    $sw = 0;
                    $sql = ' AND (';
                    $fsort_users = unserialize($_SESSION['fsort_users'], ['allowed_classes' => false]);

                    foreach ($fsort_users as $val) {
                        if ($sw) {
                            $sql .= ' OR ';
                        }

                        $sortid = (int) $val;
                        $sql .= "`forum_messages`.`user_id` = '${sortid}'";
                        $sw = 1;
                    }
                    $sql .= ')';
                }

                // Если тема помечена для удаления, разрешаем доступ только администрации
                if ($user->rights < 6 && $type1['deleted'] == 1) {
                    echo '<div class="rmenu"><p>' . _t('Topic deleted') . '<br><a href="?type=topics&amp;id=' . $type1['section_id'] . '">' . _t('Go to Section') . '</a></p></div>';
                    echo $view->render('system::app/old_content',
                        ['title' => $textl ?? '', 'content' => ob_get_clean()]);
                    exit;
                }

                $view_count = (int) ($type1['view_count']);
                // Фиксируем количество просмотров топика
                if (! empty($type1['id']) && (empty($_SESSION['viewed_topics']) || ! in_array($type1['id'],
                            $_SESSION['viewed_topics']))) {
                    $view_count = (int) ($type1['view_count']) + 1;
                    $db->query('UPDATE forum_topic SET view_count = ' . $view_count . ' WHERE id = ' . $type1['id']);
                    $_SESSION['viewed_topics'][] = $type1['id'];
                }

                // Счетчик постов темы
                $colmes = $db->query("SELECT COUNT(*) FROM `forum_messages` WHERE `topic_id`='${id}'${sql}" . ($user->rights >= 7 ? '' : " AND (`deleted` != '1' OR `deleted` IS NULL)"))->fetchColumn();

                if ($start >= $colmes) {
                    // Исправляем запрос на несуществующую страницу
                    $start = max(0,
                        $colmes - (($colmes % $user->config->kmess) == 0 ? $user->config->kmess : ($colmes % $user->config->kmess)));
                }

                // Выводим название топика
                echo '<div class="phdr"><a href="#down"><img src="' . $assets->url('images/old/down.png') . '" alt=""></a>&#160;&#160;<b>' . (empty($type1['name']) ? '-----' : $type1['name']) . '</b></div>';

                if ($colmes > $user->config->kmess) {
                    echo '<div class="topmenu">' . $tools->displayPagination('?type=topic&amp;id=' . $id . '&amp;',
                            $start, $colmes, $user->config->kmess) . '</div>';
                }

                // Метка удаления темы
                if ($type1['deleted']) {
                    echo '<div class="rmenu">' . _t('Topic deleted by') . ': <b>' . $type1['deleted_by'] . '</b></div>';
                } elseif (! empty($type1['deleted_by']) && $user->rights >= 7) {
                    echo '<div class="gmenu"><small>' . _t('Undelete topic') . ': <b>' . $type1['deleted_by'] . '</b></small></div>';
                }

                // Метка закрытия темы
                if ($type1['closed']) {
                    echo '<div class="rmenu">' . _t('Topic closed') . '</div>';
                }

                // Блок голосований
                if ($type1['has_poll']) {
                    $clip_forum = isset($_GET['clip']) ? '&amp;clip' : '';
                    $topic_vote = $db->query("SELECT `fvt`.`name`, `fvt`.`time`, `fvt`.`count`, (
SELECT COUNT(*) FROM `cms_forum_vote_users` WHERE `user`='" . $user->id . "' AND `topic`='" . $id . "') as vote_user
FROM `cms_forum_vote` `fvt` WHERE `fvt`.`type`='1' AND `fvt`.`topic`='" . $id . "' LIMIT 1")->fetch();
                    echo '<div  class="gmenu"><b>' . $tools->checkout($topic_vote['name']) . '</b><br />';
                    $vote_result = $db->query("SELECT `id`, `name`, `count` FROM `cms_forum_vote` WHERE `type`='2' AND `topic`='" . $id . "' ORDER BY `id` ASC");

                    if (! $type1['closed'] && ! isset($_GET['vote_result']) && $user->isValid() && $topic_vote['vote_user'] == 0) {
                        // Выводим форму с опросами
                        echo '<form action="?act=vote&amp;id=' . $id . '" method="post">';

                        while ($vote = $vote_result->fetch()) {
                            echo '<input type="radio" value="' . $vote['id'] . '" name="vote"/> ' . $tools->checkout($vote['name'],
                                    0, 1) . '<br />';
                        }

                        echo '<p><input type="submit" name="submit" value="' . _t('Vote') . '"/><br /><a href="?type=topic&amp;id=' . $id . '&amp;start=' . $start . '&amp;vote_result' . $clip_forum .
                            '">' . _t('Results') . '</a></p></form></div>';
                    } else {
                        // Выводим результаты голосования?>
                        <div class="vote-results">
                            <?php
                            while ($vote = $vote_result->fetch()) {
                                $count_vote = $topic_vote['count'] ? round(100 / $topic_vote['count'] * $vote['count']) : 0;
                                $color = '';
                                if ($count_vote > 0 && $count_vote <= 25) {
                                    $color = 'progress-bg-green';
                                } elseif ($count_vote > 25 && $count_vote <= 50) {
                                    $color = 'progress-bg-blue';
                                } elseif ($count_vote > 50 && $count_vote <= 75) {
                                    $color = 'progress-bg-yellow';
                                } elseif ($count_vote > 75 && $count_vote <= 100) {
                                    $color = 'progress-bg-red';
                                } ?>
                                <div class="vote-name">
                                    <?= ($tools->checkout($vote['name'], 0, 1) . ' [' . $vote['count'] . ']') ?>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar <?= $color ?>"
                                         style="width: <?= $count_vote ?>%"><?= $count_vote ?>%
                                    </div>
                                </div>
                                <?php
                            } ?>
                        </div>
                        <?php

                        echo '</div><div class="bmenu">' . _t('Total votes') . ': ';

                        if ($user->rights > 6) {
                            echo '<a href="?act=users&amp;id=' . $id . '">' . $topic_vote['count'] . '</a>';
                        } else {
                            echo $topic_vote['count'];
                        }

                        echo '</div>';

                        if ($user->isValid() && $topic_vote['vote_user'] == 0) {
                            echo '<div class="bmenu"><a href="?type=topic&amp;id=' . $id . '&amp;start=' . $start . $clip_forum . '">' . _t('Vote') . '</a></div>';
                        }
                    }
                }

                // Получаем данные о кураторах темы
                $curators = ! empty($type1['curators']) ? unserialize($type1['curators'], ['allowed_classes' => false]) : [];
                $curator = false;

                if ($user->rights < 6 && $user->rights != 3 && $user->isValid()) {
                    if (array_key_exists($user->id, $curators)) {
                        $curator = true;
                    }
                }

                // Фиксация первого поста в теме
                if (($set_forum['postclip'] == 2 && ($set_forum['upfp'] ? $start < (ceil($colmes - $user->config->kmess)) : $start > 0)) || isset($_GET['clip'])) {
                    $postres = $db->query("SELECT `forum_messages`.*, `users`.`sex`, `users`.`rights`, `users`.`lastdate`, `users`.`status`, `users`.`datereg`
                    FROM `forum_messages` LEFT JOIN `users` ON `forum_messages`.`user_id` = `users`.`id`
                    WHERE `forum_messages`.`topic_id` = '${id}'" . ($user->rights >= 7 ? '' : " AND (`forum_messages`.`deleted` != '1' OR `forum_messages`.`deleted` IS NULL)") . '
                    ORDER BY `forum_messages`.`id` LIMIT 1')->fetch();
                    echo '<div class="topmenu"><p>';

                    if ($user->isValid() && $user->id != $postres['user_id']) {
                        echo '<a href="../profile/?user=' . $postres['user_id'] . '&amp;fid=' . $postres['id'] . '"><b>' . $postres['user_name'] . '</b></a> ' .
                            '<a href="?act=say&amp;id=' . $postres['id'] . '&amp;start=' . $start . '"> ' . _t('[r]') . '</a> ' .
                            '<a href="?act=say&amp;id=' . $postres['id'] . '&amp;start=' . $start . '&amp;cyt"> ' . _t('[q]') . '</a> ';
                    } else {
                        echo '<b>' . $postres['user_name'] . '</b> ';
                    }

                    $user_rights = [
                        3 => '(FMod)',
                        6 => '(Smd)',
                        7 => '(Adm)',
                        9 => '(SV!)',
                    ];
                    echo @$user_rights[$postres['rights']];
                    echo time() > $postres['lastdate'] + 300 ? '<span class="red"> [Off]</span>' : '<span class="green"> [ON]</span>';
                    echo ' <span class="gray">(' . $tools->displayDate($postres['date']) . ')</span><br>';

                    if ($postres['deleted']) {
                        echo '<span class="red">' . _t('Post deleted') . '</span><br>';
                    }

                    echo $tools->checkout(mb_substr($postres['text'], 0, 500), 0, 2);

                    if (mb_strlen($postres['text']) > 500) {
                        echo '...<a href="?act=show_post&amp;id=' . $postres['id'] . '">' . _t('Read more') . '</a>';
                    }

                    echo '</p></div>';
                }

                // Памятка, что включен фильтр
                if ($filter) {
                    echo '<div class="rmenu">' . _t('Filter by author is activated') . '</div>';
                }

                // Задаем правила сортировки (новые внизу / вверху)
                if ($user->isValid()) {
                    $order = $set_forum['upfp'] ? 'DESC' : 'ASC';
                } else {
                    $order = ((empty($_SESSION['uppost'])) || ($_SESSION['uppost'] == 0)) ? 'ASC' : 'DESC';
                }

                ////////////////////////////////////////////////////////////
                // Основной запрос в базу, получаем список постов темы    //
                ////////////////////////////////////////////////////////////
                $req = $db->query("
                  SELECT `forum_messages`.*, `users`.`sex`, `users`.`rights`, `users`.`lastdate`, `users`.`status`, `users`.`datereg`, (
                  SELECT COUNT(*) FROM `cms_forum_files` WHERE `post` = forum_messages.id) as file
                  FROM `forum_messages` LEFT JOIN `users` ON `forum_messages`.`user_id` = `users`.`id`
                  WHERE `forum_messages`.`topic_id` = '${id}'"
                    . ($user->rights >= 7 ? '' : " AND (`forum_messages`.`deleted` != '1' OR `forum_messages`.`deleted` IS NULL)") . "${sql}
                  ORDER BY `forum_messages`.`id` ${order} LIMIT ${start}, " . $user->config->kmess);

                // Верхнее поле "Написать"
                if (($user->isValid() && ! $type1['closed'] && $set_forum['upfp'] && $config->mod_forum != 3 && $allow != 4) || ($user->rights >= 7 && $set_forum['upfp'])) {
                    echo '<div class="gmenu"><form name="form1" action="?act=say&amp;id=' . $id . '" method="post">';

                    if ($set_forum['farea']) {
                        $token = mt_rand(1000, 100000);
                        $_SESSION['token'] = $token;
                        echo '<p>' .
                            di(Johncms\Api\BbcodeInterface::class)->buttons('form1', 'msg') .
                            '<textarea rows="' . $user->config->fieldHeight . '" name="msg"></textarea></p>' .
                            '<p><input type="checkbox" name="addfiles" value="1" /> ' . _t('Add File') .
                            '</p><p><input type="submit" name="submit" value="' . _t('Write') . '" style="width: 107px; cursor: pointer;"/> ' .
                            (isset($set_forum['preview']) && $set_forum['preview'] ? '<input type="submit" value="' . _t('Preview') . '" style="width: 107px; cursor: pointer;"/>' : '') .
                            '<input type="hidden" name="token" value="' . $token . '"/>' .
                            '</p></form></div>';
                    } else {
                        echo '<p><input type="submit" name="submit" value="' . _t('Write') . '"/></p></form></div>';
                    }
                }

                // Для администрации включаем форму массового удаления постов
                if ($user->rights == 3 || $user->rights >= 6) {
                    echo '<form action="?act=massdel" method="post">';
                }
                $i = 1;

                ////////////////////////////////////////////////////////////
                // Основной список постов                                 //
                ////////////////////////////////////////////////////////////
                while ($res = $req->fetch()) {
                    // Фон поста
                    if ($res['deleted']) {
                        echo '<div class="rmenu">';
                    } else {
                        echo ($i % 2) ? '<div class="list2">' : '<div class="list1">';
                    }

                    // Пользовательский аватар
                    echo '<table cellpadding="0" cellspacing="0"><tr><td>';

                    if (file_exists(('upload/users/avatar/' . $res['user_id'] . '.png'))) {
                        echo '<img src="../upload/users/avatar/' . $res['user_id'] . '.png" alt="" />&#160;';
                    } else {
                        echo '<img src="' . $assets->url('images/old/empty.png') . '" alt="">&#160;';
                    }
                    echo '</td><td>';

                    // Метка пола
                    if ($res['sex']) {
                        echo '<img src="' . $assets->url('images/old/' . ($res['sex'] == 'm' ? 'm' : 'w') . ($res['datereg'] > time() - 86400 ? '_new' : '') . '.png') . '" alt="" class="icon-inline">';
                    } else {
                        echo '<img src="' . $assets->url('images/old/del.png') . '" alt="" class="icon">';
                    }

                    // Ник юзера и ссылка на его анкету
                    if ($user->isValid() && $user->id != $res['user_id']) {
                        echo '<a href="../profile/?user=' . $res['user_id'] . '"><b>' . $res['user_name'] . '</b></a> ';
                    } else {
                        echo '<b>' . $res['user_name'] . '</b> ';
                    }

                    // Метка должности
                    $user_rights = [
                        3 => '(FMod)',
                        6 => '(Smd)',
                        7 => '(Adm)',
                        9 => '(SV!)',
                    ];
                    echo $user_rights[$res['rights']] ?? '';

                    // Метка онлайн/офлайн
                    echo time() > $res['lastdate'] + 300 ? '<span class="red"> [Off]</span> ' : '<span class="green"> [ON]</span> ';

                    // Ссылка на пост
                    echo '<a href="?act=show_post&amp;id=' . $res['id'] . '" title="Link to post">[#]</a>';

                    // Ссылки на ответ и цитирование
                    if ($user->isValid() && $user->id != $res['user_id']) {
                        echo '&#160;<a href="?act=say&amp;type=reply&amp;id=' . $res['id'] . '&amp;start=' . $start . '">' . _t('[r]') . '</a>&#160;' .
                            '<a href="?act=say&amp;type=reply&amp;id=' . $res['id'] . '&amp;start=' . $start . '&amp;cyt">' . _t('[q]') . '</a> ';
                    }

                    // Время поста
                    echo ' <span class="gray">(' . $tools->displayDate($res['date']) . ')</span><br />';

                    // Статус пользователя
                    if (! empty($res['status'])) {
                        echo '<div class="status"><img src="' . $assets->url('images/old/label.png') . '" alt="" class="icon-inline">' . $res['status'] . '</div>';
                    }

                    // Закрываем таблицу с аватаром
                    echo '</td></tr></table>';

                    ////////////////////////////////////////////////////////////
                    // Вывод текста поста                                     //
                    ////////////////////////////////////////////////////////////
                    $text = $res['text'];
                    $text = $tools->checkout($text, 1, 1);
                    $text = $tools->smilies($text, $res['rights'] ? 1 : 0);
                    echo $text;

                    // Если пост редактировался, показываем кем и когда
                    if ($res['edit_count']) {
                        echo '<br /><span class="gray"><small>' . _t('Edited') . ' <b>' . $res['editor_name'] . '</b> (' . $tools->displayDate($res['edit_time']) . ') <b>[' . $res['edit_count'] . ']</b></small></span>';
                    }

                    // Задаем права на редактирование постов
                    if (
                        (($user->rights == 3 || $user->rights >= 6 || $curator) && $user->rights >= $res['rights'])
                        || ($res['user_id'] == $user->id && ! $set_forum['upfp'] && ($start + $i) == $colmes && $res['date'] > time() - 300)
                        || ($res['user_id'] == $user->id && $set_forum['upfp'] && $start == 0 && $i == 1 && $res['date'] > time() - 300)
                        || ($i == 1 && $allow == 2 && $res['user_id'] == $user->id)
                    ) {
                        $allowEdit = true;
                    } else {
                        $allowEdit = false;
                    }

                    // Если есть прикрепленные файлы, выводим их
                    if ($res['file']) {
                        $freq = $db->query("SELECT * FROM `cms_forum_files` WHERE `post` = '" . $res['id'] . "'");

                        echo '<div class="post-files">';
                        while ($fres = $freq->fetch()) {
                            $fls = round(@filesize(UPLOAD_PATH . 'forum/attach/' . $fres['filename']) / 1024, 2);
                            echo '<div class="gray" style="font-size: x-small;background-color: rgba(128, 128, 128, 0.1);padding: 2px 4px;float: left;margin: 4px 4px 0 0;">' . _t('Attachment') . ':';
                            // Предпросмотр изображений
                            $att_ext = strtolower(pathinfo(UPLOAD_PATH . 'forum/attach/' . $fres['filename'],
                                PATHINFO_EXTENSION));
                            $pic_ext = [
                                'gif',
                                'jpg',
                                'jpeg',
                                'png',
                            ];

                            if (in_array($att_ext, $pic_ext)) {
                                echo '<div><a class="image-preview" title="' . $fres['filename'] . '" data-source="?act=file&amp;id=' . $fres['id'] . '" href="?act=file&amp;id=' . $fres['id'] . '">';
                                echo '<img src="../assets/modules/forum/thumbinal.php?file=' . (urlencode($fres['filename'])) . '" alt="' . _t('Click to view image') . '" /></a></div>';
                            } else {
                                echo '<br><a href="?act=file&amp;id=' . $fres['id'] . '">' . $fres['filename'] . '</a>';
                            }

                            echo ' (' . $fls . ' кб.)<br>';
                            echo _t('Downloads') . ': ' . $fres['dlcount'] . ' ' . _t('Time');

                            if ($allowEdit) {
                                echo '<br><a href="?act=editpost&amp;do=delfile&amp;fid=' . $fres['id'] . '&amp;id=' . $res['id'] . '">' . _t('Delete') . '</a>';
                            }

                            echo '</div>';
                            $file_id = $fres['id'];
                        }
                        echo '<div style="clear: both;"></div></div>';
                    }

                    // Ссылки на редактирование / удаление постов
                    if ($allowEdit) {
                        echo '<div class="sub">';

                        // Чекбокс массового удаления постов
                        if ($user->rights == 3 || $user->rights >= 6) {
                            echo '<input type="checkbox" name="delch[]" value="' . $res['id'] . '"/>&#160;';
                        }

                        // Служебное меню поста
                        $menu = [
                            '<a href="?act=editpost&amp;id=' . $res['id'] . '">' . _t('Edit') . '</a>',
                            ($user->rights >= 7 && $res['deleted'] == 1 ? '<a href="?act=editpost&amp;do=restore&amp;id=' . $res['id'] . '">' . _t('Restore') . '</a>' : ''),
                            ($res['deleted'] == 1 ? '' : '<a href="?act=editpost&amp;do=del&amp;id=' . $res['id'] . '">' . _t('Delete') . '</a>'),
                        ];
                        echo implode(' | ', array_filter($menu));

                        // Показываем, кто удалил пост
                        if ($res['deleted']) {
                            echo '<div class="red">' . _t('Post deleted') . ': <b>' . $res['deleted_by'] . '</b></div>';
                        } elseif (! empty($res['deleted_by'])) {
                            echo '<div class="green">' . _t('Post restored by') . ': <b>' . $res['deleted_by'] . '</b></div>';
                        }

                        // Показываем IP и Useragent
                        if ($user->rights == 3 || $user->rights >= 6) {
                            if ($res['ip_via_proxy']) {
                                echo '<div class="gray"><b class="red"><a href="' . $config->homeurl . '/admin/?act=search_ip&amp;ip=' . long2ip($res['ip']) . '">' . long2ip($res['ip']) . '</a></b> - ' .
                                    '<a href="' . $config->homeurl . '/admin/?act=search_ip&amp;ip=' . long2ip($res['ip_via_proxy']) . '">' . long2ip($res['ip_via_proxy']) . '</a>' .
                                    ' - ' . $res['user_agent'] . '</div>';
                            } else {
                                echo '<div class="gray"><a href="' . $config->homeurl . '/admin/?act=search_ip&amp;ip=' . long2ip((int) $res['ip']) . '">' . long2ip((int) $res['ip']) . '</a> - ' . $res['user_agent'] . '</div>';
                            }
                        }

                        echo '</div>';
                    }

                    echo '</div>';
                    ++$i;
                }

                // Кнопка массового удаления постов
                if ($user->rights == 3 || $user->rights >= 6) {
                    echo '<div class="rmenu"><input type="submit" value=" ' . _t('Delete') . ' "/></div>';
                    echo '</form>';
                }

                // Нижнее поле "Написать"
                if (($user->isValid() && ! $type1['closed'] && ! $set_forum['upfp'] && $config->mod_forum != 3 && $allow != 4) || ($user->rights >= 7 && ! $set_forum['upfp'])) {
                    echo '<div class="gmenu"><form name="form2" action="?act=say&amp;type=post&amp;id=' . $id . '" method="post">';

                    if ($set_forum['farea']) {
                        $token = mt_rand(1000, 100000);
                        $_SESSION['token'] = $token;
                        echo '<p>';
                        echo di(Johncms\Api\BbcodeInterface::class)->buttons('form2', 'msg');
                        echo '<textarea rows="' . $user->config->fieldHeight . '" name="msg"></textarea><br></p>' .
                            '<p><input type="checkbox" name="addfiles" value="1" /> ' . _t('Add File');

                        echo '</p><p><input type="submit" name="submit" value="' . _t('Write') . '" style="width: 107px; cursor: pointer;"/> ' .
                            (isset($set_forum['preview']) && $set_forum['preview'] ? '<input type="submit" value="' . _t('Preview') . '" style="width: 107px; cursor: pointer;"/>' : '') .
                            '<input type="hidden" name="token" value="' . $token . '"/>' .
                            '</p></form></div>';
                    } else {
                        echo '<p><input type="submit" name="submit" value="' . _t('Write') . '"/></p></form></div>';
                    }
                }

                echo '<div class="phdr"><a id="down"></a><a href="#up"><img src="' . $assets->url('images/old/up.png') . '" alt=""></a>&#160;&#160;' . _t('Total') . ': ' . $colmes . '</div>';

                // Постраничная навигация
                if ($colmes > $user->config->kmess) {
                    echo '<div class="topmenu">' . $tools->displayPagination('?type=topic&amp;id=' . $id . '&amp;',
                            $start, $colmes, $user->config->kmess) . '</div>' .
                        '<p><form action="?type=topic&amp;id=' . $id . '" method="post">' .
                        '<input type="text" name="page" size="2"/>' .
                        '<input type="submit" value="' . _t('To Page') . ' &gt;&gt;"/>' .
                        '</form></p>';
                } else {
                    echo '<br />';
                }

                // Список кураторов
                if ($curators) {
                    $array = [];

                    foreach ($curators as $key => $value) {
                        $array[] = '<a href="../profile/?user=' . $key . '">' . $value . '</a>';
                    }

                    echo '<p><div class="func">' . _t('Curators') . ': ' . implode(', ', $array) . '</div></p>';
                }

                // Ссылки на модерские функции управления темой
                if ($user->rights == 3 || $user->rights >= 6) {
                    echo '<p><div class="func">';

                    if ($user->rights >= 7) {
                        echo '<a href="?act=curators&amp;id=' . $id . '&amp;start=' . $start . '">' . _t('Curators of the Topic') . '</a><br />';
                    }

                    echo isset($topic_vote) && $topic_vote > 0
                        ? '<a href="?act=editvote&amp;id=' . $id . '">' . _t('Edit Poll') . '</a><br><a href="?act=delvote&amp;id=' . $id . '">' . _t('Delete Poll') . '</a><br>'
                        : '<a href="?act=addvote&amp;id=' . $id . '">' . _t('Add Poll') . '</a><br>';
                    echo '<a href="?act=ren&amp;id=' . $id . '">' . _t('Rename Topic') . '</a><br>';

                    // Закрыть - открыть тему
                    if ($type1['closed'] == 1) {
                        echo '<a href="?act=close&amp;id=' . $id . '">' . _t('Open Topic') . '</a><br>';
                    } else {
                        echo '<a href="?act=close&amp;id=' . $id . '&amp;closed">' . _t('Close Topic') . '</a><br>';
                    }

                    // Удалить - восстановить тему
                    if ($type1['deleted'] == 1) {
                        echo '<a href="?act=restore&amp;id=' . $id . '">' . _t('Restore Topic') . '</a><br>';
                    }

                    echo '<a href="?act=deltema&amp;id=' . $id . '">' . _t('Delete Topic') . '</a><br>';

                    if ($type1['pinned'] == 1) {
                        echo '<a href="?act=vip&amp;id=' . $id . '">' . _t('Unfix Topic') . '</a>';
                    } else {
                        echo '<a href="?act=vip&amp;id=' . $id . '&amp;vip">' . _t('Pin Topic') . '</a>';
                    }

                    echo '<br><a href="?act=per&amp;id=' . $id . '">' . _t('Move Topic') . '</a></div></p>';
                }

                echo '<div>' . _t('Views') . ': ' . $view_count . '</div>';

                // Ссылка на список "Кто в теме"
                if ($wholink) {
                    echo '<div>' . $wholink . '</div>';
                }

                // Ссылка на фильтр постов
                if ($filter) {
                    echo '<div><a href="?act=filter&amp;id=' . $id . '&amp;do=unset">' . _t('Cancel Filter') . '</a></div>';
                } else {
                    echo '<div><a href="?act=filter&amp;id=' . $id . '&amp;start=' . $start . '">' . _t('Filter by author') . '</a></div>';
                }

                // Ссылка на скачку темы
                echo '<a href="?act=tema&amp;id=' . $id . '">' . _t('Download Topic') . '</a>';
                break;

            default:
                // Если неверные данные, показываем ошибку
                echo $tools->displayError(_t('Wrong data'));
                break;
        }
    } else {
        // Forum categories

        $count = $db->query('SELECT COUNT(*) FROM `cms_forum_files`' . ($user->rights >= 7 ? '' : " WHERE `del` != '1'"))->fetchColumn();
        $req = $db->query('SELECT sct.`id`, sct.`name`, sct.`description`, (
SELECT COUNT(*) FROM `forum_sections` WHERE `parent`=sct.id) as cnt
FROM `forum_sections` sct WHERE sct.parent IS NULL OR sct.parent = 0 ORDER BY sct.`sort`');

        $sections = [];
        while ($res = $req->fetch()) {
            $subsections_array = [];
            $subsections = $db->query('SELECT * FROM `forum_sections` WHERE parent = ' . $res['id'] . ' ORDER BY `sort`');
            while ($arr = $subsections->fetch()) {
                $type = ! empty($arr['section_type']) ? 'topics' : 'sections';
                $arr['url'] = '/forum/?type=' . $type . '&id=' . $arr['id'];
                $subsections_array[] = $arr;
            }

            $res['subsections'] = $subsections_array;
            $res['url'] = '/forum/?id=' . $res['id'];
            $sections[] = $res;
        }

        $online = $db->query('SELECT (SELECT COUNT(*) FROM `users` WHERE `lastdate` > ' . (time() - 300) . " AND `place` LIKE '/forum%') AS online_u, 
       (SELECT COUNT(*) FROM `cms_sessions` WHERE `lastdate` > " . (time() - 300) . " AND `place` LIKE '/forum%') AS online_g")->fetch();
        unset($_SESSION['fsort_id'], $_SESSION['fsort_users']);

        echo $view->render('forum::index', [
            'title'        => _t('Forum'),
            'page_title'   => _t('Forum'),
            'sections'     => $sections,
            'online'       => $online,
            'files_count'  => $count,
            'unread_count' => $counters->forumUnreadCount(),
        ]);
        exit; // TODO: Remove this later
    }

    // Навигация внизу страницы
    echo '<p>' . ($id ? '<a href="">' . _t('Forum') . '</a><br />' : '');

    if (! $id) {
        echo '<a href="../help/?act=forum">' . _t('Forum rules') . '</a>';
    }

    echo '</p>';

    if (! $user->isValid()) {
        if ((empty($_SESSION['uppost'])) || ($_SESSION['uppost'] == 0)) {
            echo '<a href="?id=' . $id . '&amp;page=' . $page . '&amp;newup">' . _t('New at the top') . '</a>';
        } else {
            echo '<a href="?id=' . $id . '&amp;page=' . $page . '&amp;newdown">' . _t('New at the bottom') . '</a>';
        }
    }
}

echo $view->render('system::app/old_content', ['title' => $textl ?? '', 'content' => ob_get_clean()]);
