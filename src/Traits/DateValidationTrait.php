<?php

namespace Nava\MyInvois\Traits;

use Nava\MyInvois\Exception\ValidationException;

/**
 * Common trait for date validation functionality.
 */
trait DateValidationTrait
{
    /**
     * Validate a date range if both start and end dates are provided.
     *
     * @param string|null $fromDate Start date
     * @param string|null $toDate End date
     * @param string $fieldName Name of the date field for error messages
     * @param int $maxDays Maximum allowed days between dates (optional)
     * @throws ValidationException If date range is invalid
     */
    protected function validateDateRange(
        ?string $fromDate,
        ?string $toDate,
        string $fieldName,
        ?int $maxDays = null
    ): void {
        if ($fromDate && $toDate) {
            try {
                $from = new \DateTimeImmutable($fromDate);
                $to = new \DateTimeImmutable($toDate);

                if ($from > $to) {
                    throw new ValidationException(
                        "Invalid $fieldName range",
                        ["{$fieldName}_range" => ["Start date must be before end date"]]
                    );
                }

                if (null !== $maxDays) {
                    $windowLimit = $from->modify("+$maxDays days");
                    if ($to > $windowLimit) {
                        throw new ValidationException(
                            "Invalid $fieldName range",
                            ["{$fieldName}_range" => ["Date range cannot exceed $maxDays days"]]
                        );
                    }
                }
            } catch (\Exception $e) {
                if ($e instanceof ValidationException) {
                    throw $e;
                }
                throw new ValidationException(
                    "Invalid $fieldName format",
                    ["{$fieldName}_format" => ['Dates must be in valid format']]
                );
            }
        }

    }

    // Add ISO 8601 format validation
    private function validateIsoFormat(string $date): void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $date)) {
            throw new ValidationException(
                "Invalid date format for {$fieldName}",
                ["{$fieldName}_format" => ["Date must be in ISO 8601 format (YYYY-MM-DDTHH:mm:ssZ)"]]
            );
        }
    }

    private function validateTimeZone(string $date): void
    {
        $timezone = (new \DateTimeImmutable($date))->getTimezone();
        if ($timezone->getName() !== 'UTC') {
            throw new ValidationException(
                "Invalid timezone for {$fieldName}",
                ["{$fieldName}_timezone" => ["Date must be in UTC timezone"]]
            );
        }
    }
}
