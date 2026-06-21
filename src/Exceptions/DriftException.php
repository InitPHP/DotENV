<?php

/**
 * DriftException.php
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

use InitPHP\DotENV\Drift\DriftReport;

/**
 * Thrown by the strict {@see \InitPHP\DotENV\Repository::assertNoDrift()} mode
 * when the loaded environment drifts from its reference.
 *
 * Extends {@see DotENVException} (and therefore `InvalidArgumentException`), so
 * existing `catch (\InitPHP\DotENV\Exceptions\DotENVException $e)` /
 * `catch (\InvalidArgumentException $e)` blocks keep working. The offending
 * {@see DriftReport} is attached for inspection.
 */
class DriftException extends DotENVException
{
    /** @var DriftReport The drift that triggered this exception. */
    private DriftReport $report;

    /**
     * @param DriftReport $report  The detected drift.
     * @param string|null $message Overrides the default message built from the
     *                             report when provided.
     */
    public function __construct(DriftReport $report, ?string $message = null)
    {
        parent::__construct($message ?? (string) $report);
        $this->report = $report;
    }

    /**
     * Returns the drift report that triggered this exception.
     *
     * @return DriftReport
     */
    public function getReport(): DriftReport
    {
        return $this->report;
    }
}
