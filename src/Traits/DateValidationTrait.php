<?php

namespace Nava\MyInvois\Traits;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Nava\MyInvois\Exception\ValidationException;

trait DateValidationTrait
{
    /**
     * Robustly parse a date string into a DateTimeImmutable.
     *
     * @param string|DateTimeInterface|null $date Date to parse
     * @param bool $required Whether the date is required
     * @return DateTimeImmutable|null Parsed date
     *
     * @throws \InvalidArgumentException If date is invalid and required
     */
    protected function parseDate($date, $required = false): ?DateTimeImmutable {
        // If date is already a DateTimeInterface, convert to DateTimeImmutable
        if ($date instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($date);
        }

        // If date is null and not required, return null
        if (null === $date) {
            if ($required) {
                throw new \InvalidArgumentException('Date is required');
            }
            return null;
        }

        // Trim and normalize the date string
        $date = trim($date);

        // If empty string and not required, return null
        if ('' === $date && !$required) {
            return null;
        }

        // List of supported date formats
        $formats = [
            'Y-m-d\TH:i:s\Z', // ISO8601 with Z
            'Y-m-d\TH:i:sP', // ISO8601 with timezone offset
            'Y-m-d H:i:s', // MySQL datetime
            'Y-m-d', // Simple date
            DateTimeInterface::ATOM,
            DateTimeInterface::RFC3339,
        ];

        // Try parsing with multiple formats
        foreach ($formats as $format) {
            try {
                return DateTimeImmutable::createFromFormat($format, $date) ?:
                new DateTimeImmutable($date);
            } catch (Exception $e) {
                continue;
            }
        }

        // If all parsing attempts fail
        if ($required) {
            throw new \InvalidArgumentException("Invalid date format: {$date}");
        }

        return null;
    }

    /**
     * Validate and parse a date range.
     *
     * @param string|DateTimeInterface|null $startDate Start date
     * @param string|DateTimeInterface|null $endDate End date
     * @param string $fieldName Field name for error messages
     * @param int|null $maxDaysDifference Maximum allowed days between dates
     * @return array{start: ?DateTimeImmutable, end: ?DateTimeImmutable}
     *
     * @throws ValidationException If date range is invalid
     */
    protected function validateDateRange(
        $startDate,
        $endDate,
        $fieldName = 'date',
        $maxDaysDifference = null
    ): array {
        // If both dates are null, return null
        if (null === $startDate && null === $endDate) {
            return ['start' => null, 'end' => null];
        }

        // Parse dates with lenient parsing
        try {
            // Parse start and end dates
            $parsedStartDate = $startDate ? $this->parseDate($startDate, false) : null;
            $parsedEndDate = $endDate ? $this->parseDate($endDate, false) : null;

            // If one date is provided, the other becomes required
            if ((null === $parsedStartDate) xor (null === $parsedEndDate)) {
                throw new ValidationException(
                    'Both start and end dates are required',
                    ["{$fieldName}_range" => ['Both start and end dates must be provided']]
                );
            }

            // Validate date order
            if ($parsedStartDate && $parsedEndDate && $parsedStartDate > $parsedEndDate) {
                throw new ValidationException(
                    'Start date must be before end date',
                    ["{$fieldName}_range" => ['Start date must be before end date']]
                );
            }

            // Validate maximum date difference if specified
            if (null !== $maxDaysDifference && $parsedStartDate && $parsedEndDate) {
                $daysDifference = $parsedStartDate->diff($parsedEndDate)->days;
                if ($daysDifference > $maxDaysDifference) {
                    throw new ValidationException(
                        "Date range cannot exceed {$maxDaysDifference} days",
                        ["{$fieldName}_range" => ["Date range cannot exceed {$maxDaysDifference} days"]]
                    );
                }
            }

            return [
                'start' => $parsedStartDate,
                'end' => $parsedEndDate,
            ];

        } catch (ValidationException $e) {
            // Preserve previously thrown validation semantics (range/order messages)
            throw $e;
        } catch (\Throwable $e) {
            throw new ValidationException(
                "Invalid {$fieldName} format",
                ["{$fieldName}_format" => [$e->getMessage()]]
            );
        }
    }
}
