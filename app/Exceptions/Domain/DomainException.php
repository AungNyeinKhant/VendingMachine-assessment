<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Base for business-rule failures. The web controller maps them to
 * ValidationException (inline form errors); the API renders them via
 * render() as JSON with the matching status code.
 */
abstract class DomainException extends RuntimeException
{
    /** The form field the error belongs to ("quantity", "amount_paid", ...). */
    abstract public function errorKey(): string;

    abstract public function status(): int;

    public function render(Request $request): ?JsonResponse
    {
        if (! $request->expectsJson()) {
            return null; // web flow: the controller turns this into a ValidationException
        }

        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [$this->errorKey() => [$this->getMessage()]],
        ], $this->status());
    }
}
