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
use InitPHP\DotENV\Drift\DriftReport;

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

if (!function_exists('env_drift')) {
    /**
     * Compares the shared environment against a reference and reports drift.
     *
     * Thin global wrapper over {@see DotENV::drift()}. See that method for the
     * accepted reference shapes (a `.env.example` path or a required-keys
     * array) and the available options.
     *
     * @param string|array<int|string, mixed> $reference Reference file path or
     *                                                    required-keys list.
     * @param array{extra?: bool, empty?: bool} $options  Opt-in buckets.
     * @return DriftReport
     */
    function env_drift(string|array $reference, array $options = []): DriftReport
    {
        return DotENV::drift($reference, $options);
    }
}
