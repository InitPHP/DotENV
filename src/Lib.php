<?php

/**
 * Lib.php
 *
 * This file is part of InitPHP DotENV.
 *
 * Backwards-compatibility shim. Prior to 3.0 the worker class was named
 * `InitPHP\DotENV\Lib`; it is now {@see Repository}. Requiring this file
 * (via PSR-4 autoloading of the `Lib` symbol) registers the legacy name as
 * an alias of the new class, so `new InitPHP\DotENV\Lib()` keeps working.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    https://github.com/InitPHP/DotENV/blob/main/LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 *
 * @deprecated 3.0 Use {@see \InitPHP\DotENV\Repository} instead.
 */

declare(strict_types=1);

namespace InitPHP\DotENV;

use function class_alias;
use function class_exists;

if (!class_exists(Lib::class, false)) {
    class_alias(Repository::class, Lib::class);
}
