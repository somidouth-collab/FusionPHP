<?php

namespace App\Api;

use App\DocsController;

final class ApiController extends DocsController
{
    protected function views(): array
    {
        return [
            'api' => ['aliases' => ['api-guide'], 'handlers' => ['GET' => 'show']],
        ];
    }

    protected function api(): array
    {
        return [
            '/' => ['GET' => 'index'],
            'example' => ['POST' => 'example'],
        ];
    }

    public function show(): void
    {
        $this->view('api', (new ApiModel($this->request))->page());
    }

    public function index(): void
    {
        $this->json([
            'name' => config('app.name'),
            'version' => config('app.version'),
            'endpoints' => (new ApiModel($this->request))->endpoints(),
        ]);
    }

    public function example(): void
    {
        if (!$this->request->isJson()) {
            $this->abort(415, 'Use Content-Type: application/json');
        }
        $data = $this->validate(['name' => 'required|string|min:2|max:80']);
        $this->json(['data' => ['name' => $data['name']]]);
    }
}
