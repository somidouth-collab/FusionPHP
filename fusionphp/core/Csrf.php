<?php

namespace Fusion\Core;

final class Csrf
{
    private const SESSION_KEY = '_fusion_csrf';

    public static function token(): string
    {
        if (!Session::isStarted()) {
            throw new \LogicException('Start a session before generating a CSRF token.');
        }

        $token = Session::get(self::SESSION_KEY);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::set(self::SESSION_KEY, $token);
        }
        return $token;
    }

    public static function protect(Request $request): void
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        $expected = Session::isStarted() ? Session::get(self::SESSION_KEY) : null;
        if (!is_string($expected) || $expected === '') {
            throw new HttpException(403, 'Invalid CSRF token.');
        }

        $submitted = $request->header('X-CSRF-Token');
        if ($submitted === null) {
            $submitted = $request->isJson() ? $request->json('_csrf') : $request->post('_csrf');
        }
        if (!is_string($submitted) || !hash_equals($expected, $submitted)) {
            throw new HttpException(403, 'Invalid CSRF token.');
        }
    }

    public static function reset(): void
    {
        Session::delete(self::SESSION_KEY);
    }
}
