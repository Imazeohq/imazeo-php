<?php

declare(strict_types=1);

namespace Imazeo\Errors;

final class ErrorMapper
{
    /**
     * @param array{status: int, code?: ?string, message: string, requestId?: ?string, details?: mixed} $init
     */
    public static function mapHttpError(array $init): ImazeoError
    {
        $status = $init['status'];
        $message = $init['message'];
        $code = $init['code'] ?? null;
        $requestId = $init['requestId'] ?? null;
        $details = $init['details'] ?? null;

        return match (true) {
            $status === 401 => new AuthenticationError($message, $status, $code, $requestId, $details),
            $status === 403 => new AuthorizationError($message, $status, $code, $requestId, $details),
            $status === 404 => new NotFoundError($message, $status, $code, $requestId, $details),
            $status === 409 => new ConflictError($message, $status, $code, $requestId, $details),
            $status === 429 => new RateLimitError($message, $status, $code, $requestId, $details),
            in_array($status, [400, 413, 415], true) => new ValidationError(
                $message,
                $status,
                $code,
                $requestId,
                $details,
            ),
            default => new ApiError($message, $status, $code, $requestId, $details),
        };
    }
}
