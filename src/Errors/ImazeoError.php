<?php

declare(strict_types=1);

namespace Imazeo\Errors;

class ImazeoError extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $requestId = null,
        public readonly mixed $details = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Imazeo API error code (e.g. API_KEY_INVALID).
     * Named separately from Exception::$code (integer).
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }
}
