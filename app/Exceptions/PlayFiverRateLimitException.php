<?php



namespace App\Exceptions;

use RuntimeException;

class PlayFiverRateLimitException extends RuntimeException
{
    public function __construct(
        string $message = 'Você foi limitado devido ao grande número de solicitações.',
        public array $payload = []
    ) {
        parent::__construct($message, 429);
    }
}