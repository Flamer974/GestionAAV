<?php

namespace App\Exceptions;

use Exception;

class MistralRateLimitException extends Exception
{
    public function render($request)
    {
        return response()->json([
            'error' => 'rate_limit',
            'message' => $this->getMessage(),
        ], 429);
    }
}