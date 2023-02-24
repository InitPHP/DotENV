<?php
/**
 * DotENV.php
 *
 * This file is part of InitPHP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    https://initphp.github.io/license.txt  MIT
 * @version    2.0
 * @link       https://www.muhammetsafak.com.tr
 */

namespace InitPHP\DotENV;

/**
 * @method static void create(string $path, bool $debug = true)
 * @method static mixed get(string $name, mixed $default = null)
 * @method static mixed env(string $name, mixed $default = null)
 */
class DotENV
{

    /** @var Lib */
    protected static $instance;

    /**
     * @return Lib
     */
    protected static function getInstance()
    {
        if(!isset(self::$instance)){
            self::$instance = new Lib();
        }
        return self::$instance;
    }

    public function __call($name, $arguments)
    {
        return self::getInstance()->{$name}(...$arguments);
    }

    public static function __callStatic($name, $arguments)
    {
        return self::getInstance()->{$name}(...$arguments);
    }

}
