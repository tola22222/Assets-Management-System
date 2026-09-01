<?php

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Thrown by AssetCodeService when an asset tag can't be built because of a
 * fixable data problem — a category with no short code, or a location with no
 * site code. Carries the form field the problem belongs to so the controllers
 * can turn it into a 422 on that field instead of an unexplained 500.
 *
 * Extends InvalidArgumentException so existing catch sites (and the service's
 * own documented contract) keep working unchanged.
 */
class AssetCodeException extends InvalidArgumentException
{
    /**
     * @param  string  $field  The request field the fix belongs to — 'location_id' or 'category_id'.
     */
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }
}
