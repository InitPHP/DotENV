<?php

/**
 * DriftReport.php
 *
 * This file is part of InitPHP DotENV.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    https://github.com/InitPHP/DotENV/blob/main/LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\DotENV\Drift;

use function array_values;
use function implode;

/**
 * Immutable result of a drift check between the loaded environment and a
 * reference (a `.env.example` file or a declared required-keys list).
 *
 * Drift is grouped into three buckets:
 *
 * - **missing** — a key in the reference that is absent from the actual env.
 *   This is the dangerous one: a value the code expects that was never
 *   provisioned.
 * - **extra** — a key present in the actual env but not in the reference
 *   (only collected when the caller opts in; usually noise).
 * - **empty** — a key present in both reference and env, but whose actual
 *   value is empty (only collected when the caller opts in).
 *
 * ```php
 * $report = $env->drift('/path/.env.example', ['extra' => true]);
 * if ($report->hasDrift()) {
 *     echo $report; // human-readable summary
 * }
 * ```
 */
final class DriftReport
{
    /** @var list<string> Reference keys absent from the actual environment. */
    private array $missing;

    /** @var list<string> Loaded keys absent from the reference. */
    private array $extra;

    /** @var list<string> Reference keys present but with an empty value. */
    private array $empty;

    /**
     * @param list<string> $missing
     * @param list<string> $extra
     * @param list<string> $empty
     */
    public function __construct(array $missing = [], array $extra = [], array $empty = [])
    {
        $this->missing = array_values($missing);
        $this->extra = array_values($extra);
        $this->empty = array_values($empty);
    }

    /**
     * Reference keys that are not defined in the actual environment.
     *
     * @return list<string>
     */
    public function getMissing(): array
    {
        return $this->missing;
    }

    /**
     * Loaded keys that are not present in the reference. Always empty unless
     * the caller opted into extra-key detection.
     *
     * @return list<string>
     */
    public function getExtra(): array
    {
        return $this->extra;
    }

    /**
     * Reference keys that are defined but resolve to an empty value. Always
     * empty unless the caller opted into empty-value detection.
     *
     * @return list<string>
     */
    public function getEmpty(): array
    {
        return $this->empty;
    }

    /**
     * Whether any drift at all was detected.
     *
     * @return bool
     */
    public function hasDrift(): bool
    {
        return $this->missing !== [] || $this->extra !== [] || $this->empty !== [];
    }

    /**
     * Whether the report is clean (the inverse of {@see hasDrift()}). Reads
     * naturally at a call site: `if ($report->isEmpty()) { ... }`.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return !$this->hasDrift();
    }

    /**
     * Total number of drifted keys across every bucket.
     *
     * @return int
     */
    public function count(): int
    {
        return \count($this->missing) + \count($this->extra) + \count($this->empty);
    }

    /**
     * The report as a plain array, keyed by bucket. Handy for JSON output or
     * assertions.
     *
     * @return array{missing: list<string>, extra: list<string>, empty: list<string>}
     */
    public function toArray(): array
    {
        return [
            'missing' => $this->missing,
            'extra'   => $this->extra,
            'empty'   => $this->empty,
        ];
    }

    /**
     * A human-readable, multi-line summary suitable for CI logs.
     *
     * @return string
     */
    public function __toString(): string
    {
        if ($this->isEmpty()) {
            return 'No environment drift detected.';
        }

        $lines = [];
        if ($this->missing !== []) {
            $lines[] = 'Missing (in reference, not in environment): ' . implode(', ', $this->missing);
        }
        if ($this->empty !== []) {
            $lines[] = 'Empty (required but blank): ' . implode(', ', $this->empty);
        }
        if ($this->extra !== []) {
            $lines[] = 'Extra (in environment, not in reference): ' . implode(', ', $this->extra);
        }

        return \sprintf('Environment drift detected (%d): %s', $this->count(), implode('; ', $lines));
    }
}
