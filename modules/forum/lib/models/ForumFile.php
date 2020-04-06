<?php

/**
 * This file is part of JohnCMS Content Management System.
 *
 * @copyright JohnCMS Community
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0
 * @link      https://johncms.com JohnCMS Project
 */

declare(strict_types=1);

namespace Forum\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Johncms\System\Users\User;

/**
 * Class File
 *
 * @package Forum\Models
 *
 * @mixin Builder
 * @property int $id
 * @property int $cat
 * @property int $subcat
 * @property int $topic
 * @property int $post
 * @property int $time
 * @property string $filename
 * @property int $filetype
 * @property int $dlcount
 * @property bool $del
 */
class ForumFile extends Model
{
    /**
     * Название таблицы
     *
     * @var string
     */
    protected $table = 'cms_forum_files';

    public $timestamps = false;

    protected $fillable = [
        'cat',
        'subcat',
        'topic',
        'post',
        'time',
        'filename',
        'filetype',
        'dlcount',
        'del',
    ];

    /**
     * Добавляем глобальные ограничения
     *
     * @return void
     */
    protected static function boot(): void
    {
        parent::boot();

        static::addGlobalScope(
            'access',
            static function (Builder $builder) {
                /** @var User $user */
                $user = di(User::class);
                if ($user->rights < 7) {
                    $builder->where('del', '!=', 1);
                }
            }
        );
    }
}