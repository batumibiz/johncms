<?php

defined('_IN_JOHNCMS') or die('Restricted access');

class core
{
    public static $ip;                        // Путь к корневой папке
    public static $ip_via_proxy = 0;          // IP адрес за прокси-сервером
    public static $user_agent;                // User Agent
    public static $system_set;                //TODO: Удалить
    public static $lng_iso = 'en';            // Двухбуквенный ISO код языка
    public static $lng_list = [];             // Список имеющихся языков
    public static $deny_registration = false; // Запрет регистрации пользователей

    public static $user_id = false;           //TODO: Удалить
    public static $user_data = [];            //TODO: Удалить
    public static $user_set = [];             // Пользовательские настройки

    /**
     * @var Interop\Container\ContainerInterface
     */
    private $container;

    /**
     * @var PDO
     */
    private $db;

    private $config;

    function __construct()
    {
        $this->container = App::getContainer();

        /** @var Johncms\Environment $env */
        $env = $this->container->get('env');
        self::$ip = $env->getIp();
        self::$ip_via_proxy = $env->getIpViaProxy();
        self::$user_agent = $env->getUserAgent();

        // Получаем глобальную конфигурацию
        $this->config = $this->container->get('config');

        // Получаем объект PDO
        $this->db = $this->container->get(PDO::class);

        // Получаем системные настройки
        self::$system_set = $this->container->get('config')['johncms'];
        self::$lng_iso = self::$system_set['lng'];

        // Автоочистка системы
        //TODO: перенести после авторизации и добавить чистку данных пользователей
        $this->auto_clean();

        // Авторизация пользователей
        $this->authorize();

        // Определяем язык системы
        $this->lng_detect();
    }

    /**
     * Определяем язык
     */
    private function lng_detect()
    {
        $setlng = isset($_POST['setlng']) ? substr(trim($_POST['setlng']), 0, 2) : '';

        if (!empty($setlng) && array_key_exists($setlng, self::$lng_list)) {
            $_SESSION['lng'] = $setlng;
        }

        if (isset($_SESSION['lng']) && array_key_exists($_SESSION['lng'], self::$lng_list)) {
            self::$lng_iso = $_SESSION['lng'];
        } elseif (self::$user_id && isset(self::$user_set['lng']) && array_key_exists(self::$user_set['lng'], self::$lng_list)) {
            self::$lng_iso = self::$user_set['lng'];
        } elseif (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $accept = explode(',', strtolower(trim($_SERVER['HTTP_ACCEPT_LANGUAGE'])));

            foreach ($accept as $var) {
                $lng = substr($var, 0, 2);

                if (array_key_exists($lng, self::$lng_list)) {
                    self::$lng_iso = $lng;
                    break;
                }
            }
        }
    }

    /**
     * Авторизация пользователя и получение его данных из базы
     */
    private function authorize()
    {
        $user_id = false;
        $user_ps = false;

        if (isset($_SESSION['uid']) && isset($_SESSION['ups'])) {
            // Авторизация по сессии
            $user_id = abs(intval($_SESSION['uid']));
            $user_ps = $_SESSION['ups'];
        } elseif (isset($_COOKIE['cuid']) && isset($_COOKIE['cups'])) {
            // Авторизация по COOKIE
            $user_id = abs(intval(base64_decode(trim($_COOKIE['cuid']))));
            $_SESSION['uid'] = $user_id;
            $user_ps = md5(trim($_COOKIE['cups']));
            $_SESSION['ups'] = $user_ps;
        }

        if ($user_id && $user_ps) {
            $req = $this->db->query('SELECT * FROM `users` WHERE `id` = ' . $user_id);

            if ($req->rowCount()) {
                $user_data = $req->fetch();
                $permit = $user_data['failed_login'] < 3 || $user_data['failed_login'] > 2 && $user_data['ip'] == self::$ip && $user_data['browser'] == self::$user_agent ? true : false;

                if ($permit && $user_ps === $user_data['password']) {
                    // Если авторизация прошла успешно
                    self::$user_id = $user_data['preg'] ? $user_id : false;
                    self::$user_data = $user_data;
                    self::$user_set = !empty($user_data['set_user']) ? unserialize($user_data['set_user']) : $this->user_setings_default();
                } else {
                    // Если авторизация не прошла
                    $this->db->query("UPDATE `users` SET `failed_login` = '" . ($user_data['failed_login'] + 1) . "' WHERE `id` = '" . $user_data['id'] . "'");
                    $this->user_unset();
                }
            } else {
                // Если пользователь не существует
                $this->user_unset();
            }
        } else {
            // Для неавторизованных, загружаем настройки по-умолчанию
            self::$user_set = $this->user_setings_default();
        }
    }

    /**
     * Пользовательские настройки по умолчанию
     *
     * @return array
     */
    private function user_setings_default()
    {
        return [
            'avatar'     => 1, // Показывать аватары
            'digest'     => 0, // Показывать Дайджест
            'direct_url' => 0, // Внешние ссылки
            'field_h'    => 3, // Высота текстового поля ввода
            'field_w'    => 40, // Ширина текстового поля ввода
            'kmess'      => 20, // Число сообщений на страницу
            'quick_go'   => 1, // Быстрый переход
            'timeshift'  => 0, // Временной сдвиг
            'skin'       => self::$system_set['skindef'], // Тема оформления
            'smileys'    => 1, // Включить(1) выключить(0) смайлы
            'translit'   => 0 // Транслит
        ];
    }

    /**
     * Уничтожаем данные авторизации юзера
     */
    private function user_unset()
    {
        self::$user_id = false;
        self::$user_set = $this->user_setings_default();
        self::$user_data = [];
        unset($_SESSION['uid']);
        unset($_SESSION['ups']);
        setcookie('cuid', '');
        setcookie('cups', '');
    }

    /**
     * Автоочистка системы
     */
    private function auto_clean()
    {
        //TODO: переделать очистку
//        if (self::$system_set['clean_time'] < time() - 86400) {
//            $this->db->exec("DELETE FROM `cms_sessions` WHERE `lastdate` < '" . (time() - 86400) . "'");
//            $this->db->exec("DELETE FROM `cms_users_iphistory` WHERE `time` < '" . (time() - 2592000) . "'");
//            $this->db->exec("UPDATE `cms_settings` SET  `val` = '" . time() . "' WHERE `key` = 'clean_time' LIMIT 1");
//            $this->db->query("OPTIMIZE TABLE `cms_sessions` , `cms_users_iphistory`, `cms_mail`, `cms_contact`");
//        }
    }
}
