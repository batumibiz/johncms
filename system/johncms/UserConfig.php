<?php

namespace Johncms;

use Zend\Stdlib\ArrayObject;

/**
 * Class UserConfig
 *
 * @package Johncms
 *
 * @property $directUrl
 * @property $fieldHeight
 * @property $fieldWidth
 * @property $kmess
 * @property $timeshift
 * @property $skin
 */
class UserConfig extends ArrayObject
{
    public function __construct(User $user)
    {
        $input = empty($user->set_user) ? $this->getDefaults() : $this->unserialize($user->set_user);
        parent::__construct($input, parent::ARRAY_AS_PROPS);
    }

    private function getDefaults()
    {
        return [
            'directUrl'   => 0,  // Внешние ссылки
            'fieldHeight' => 3,  // Высота текстового поля ввода
            'fieldWidth'  => 40, // Ширина текстового поля ввода
            'kmess'       => 20, // Число сообщений на страницу
            'timeshift'   => 0,  // Временной сдвиг
            'skin'        => '', // Тема оформления
        ];
    }
}
