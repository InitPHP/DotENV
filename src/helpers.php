<?php

/**
 * helpers.php
 *
 * This file is part of InitPHP DotENV.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    https://github.com/InitPHP/DotENV/blob/main/LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

use InitPHP\DotENV\DotENV;

if (!function_exists('env')) {
    /**
     * Returns an environment value from the shared DotENV repository.
     *
     * @param string $name    The environment variable name.
     * @param mixed  $default Returned when the variable is not defined.
     * @return mixed
     */
    function env(string $name, mixed $default = null): mixed
    {
        return DotENV::get($name, $default);
    }
}
