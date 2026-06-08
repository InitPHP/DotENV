<?php

/**
 * DotENVException.php
 *
 * This file is part of InitPHP DotENV.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    https://github.com/InitPHP/DotENV/blob/main/LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\DotENV\Exceptions;

use InvalidArgumentException;

/**
 * Raised when a `.env` / `.env.php` file cannot be located, read, or parsed.
 *
 * Extends {@see InvalidArgumentException} for backwards compatibility, so
 * existing `catch (\InvalidArgumentException $e)` blocks keep working.
 */
class DotENVException extends InvalidArgumentException
{
}
