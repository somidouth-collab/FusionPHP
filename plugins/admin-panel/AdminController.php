<?php

namespace App\Admin;

use Fusion\Core\Controller;

final class AdminController extends Controller
{
    protected function views(): array
    {
        return [
            'dashboard' => ['aliases' => ['admin'], 'handlers' => ['GET' => 'dashboard']],
        ];
    }

    public function dashboard(): void
    {
        header('Cache-Control: no-store');
        $file = __DIR__ . '/.plugin-password';
        $hash = is_readable($file) ? file_get_contents($file) : false;
        if ($hash === false) {
            $this->abort(503, 'The administrator password is unavailable.');
        }

        $username = $this->request->server('PHP_AUTH_USER');
        $password = $this->request->server('PHP_AUTH_PW');
        if ($username === null) {
            $authorization = $this->request->header('Authorization');
            if (is_string($authorization) && preg_match('/^Basic (\S+)$/iD', $authorization, $match)) {
                $credentials = base64_decode($match[1], true);
                if ($credentials !== false && str_contains($credentials, ':')) {
                    [$username, $password] = explode(':', $credentials, 2);
                }
            }
        }
        if ($username !== 'admin' || !is_string($password) || !password_verify($password, trim($hash))) {
            header('WWW-Authenticate: Basic realm="FusionPHP Admin", charset="UTF-8"');
            $this->abort(401);
        }

        $this->view('dashboard', [
            'title' => 'Admin Panel',
            'siteName' => config('app.name'),
            'version' => config('app.version'),
            'mode' => config('app.mode'),
            'debugEnabled' => config('app.debug'),
            'homeUrl' => $this->request->url('/'),
            'apiUrl' => config('app.mode') === 'both' ? $this->request->apiUrl() : null,
            'frameworkUrl' => $this->request->url('/framework'),
        ]);
    }
}
