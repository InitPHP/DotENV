<?php
/**
 * helpers.php
 *
 * This file is part of InitPHP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    https://initphp.github.io/license.txt  MIT
 * @version    2.0
 * @link       https://www.muhammetsafak.com.tr
 */

if (!function_exists('env')) {
    /**
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    function env($name, $default = null)
    {
        return \InitPHP\DotENV\DotENV::get($name, $default);
    }
}
