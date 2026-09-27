<?php

declare(strict_types=1);

if (! function_exists('get_client_ip')) {
    function get_client_ip(): string
    {
        $request = request();

        if (! $request->isFromTrustedProxy()) {
            return $request->ip() ?? '';
        }

        return $request->header('X-Real-IP') ?? $request->ip() ?? '';
    }
}
