<?php

declare(strict_types=1);

namespace App\Application\Validation;

enum ErrorCode: string
{
    case Required = 'required';
    case Empty = 'empty';
    case TooLarge = 'too_large';
    case UnsupportedType = 'unsupported_type';
    case ExtensionMismatch = 'extension_mismatch';
    case DimensionsTooLarge = 'dimensions_too_large';
    case SmallerThanFrame = 'smaller_than_frame';
    case NotInteger = 'not_integer';
    case OutOfRange = 'out_of_range';
    case InvalidCharacters = 'invalid_characters';
    case TooLong = 'too_long';
    case Unsupported = 'unsupported';
    case NotFound = 'not_found';
    case OutsideInputDirectory = 'outside_input_directory';
}
