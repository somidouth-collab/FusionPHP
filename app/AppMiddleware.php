<?php

namespace App;

use Fusion\Core\Middleware;
use Fusion\Core\Session;
use Fusion\Core\Flash;

class AppMiddleware extends Middleware
{
    public function before(): bool
    {
        if (!$this->request->isApi() || config('api.session')) {
            if (!Session::isStarted()) {
                Session::start();
            }
            Flash::age();
        }

        // Security headers
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');

        return true;
    }

    public function after(): void
    {
        // runs after response is sent — good place for cleanup later
    }
}