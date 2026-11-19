<?php

declare(strict_types=1);

namespace App\Application\Validation;

/**
 * A file name chosen by the client, without extension: letters, digits, - and _, up to 100 characters.
 */
final readonly class NameRule
{
    private const int MAX_LENGTH = 100;

    /**
     * @return string|null|ValidationError null when no name is given
     */
    public function check(Field $field, ?string $value): string|ValidationError|null
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (preg_match('/^[A-Za-z0-9_-]+\z/', $value) !== 1) {
            return new ValidationError(
                $field,
                ErrorCode::InvalidCharacters,
                sprintf('%s may hold only letters, digits, - and _.', $field->value),
            );
        }
        if (strlen($value) > self::MAX_LENGTH) {
            return new ValidationError(
                $field,
                ErrorCode::TooLong,
                sprintf('%s can be at most %d characters long.', $field->value, self::MAX_LENGTH),
            );
        }

        return $value;
    }

    // A name taken from somewhere the client controls only loosely, such as an uploaded file's own name
    public function safeVersionOf(string $value): string
    {
        $safe = substr(preg_replace('/[^A-Za-z0-9_-]/', '_', $value) ?? '', 0, self::MAX_LENGTH);

        return $safe === '' ? 'image' : $safe;
    }
}
