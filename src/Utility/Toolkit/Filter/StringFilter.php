<?php

declare(strict_types=1);

namespace Auth0\SDK\Utility\Toolkit\Filter;

/**
 * Class StringFilter.
 */
final class StringFilter
{
    /**
     * Values to process.
     *
     * @var array<string|null>
     */
    private array $subjects;

    /**
     * StringFilter constructor.
     *
     * @param  array<string|null>  $subjects  an array of string or null values
     */
    public function __construct(
        array $subjects,
    ) {
        $this->subjects = $subjects;
    }

    /**
     * Nullsafe trim. Trims the array of string values, or sets their value when invalid or empty.
     *
     * @return array<string|null>
     */
    public function trim(): array
    {
        $results = [];

        foreach ($this->subjects as $subject) {
            if (! \is_string($subject)) {
                $results[] = null;

                continue;
            }

            $value = trim($subject);

            if (0 === mb_strlen($value)) {
                $results[] = null;

                continue;
            }

            $results[] = $value;
        }

        return $results;
    }
}
