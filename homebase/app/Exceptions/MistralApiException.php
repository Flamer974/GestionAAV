<?php

namespace App\Exceptions;

use Exception;

class MistralApiException extends Exception
{
    public function render($request)
    {
        return response()->json([
            'error' => 'api_error',
            'message' => $this->getMessage(),
        ], 503);
    }
}