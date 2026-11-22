<?php

namespace App\Services;

use App\DocsController;

final class ServicesController extends DocsController
{
    protected function views(): array
    {
        return [
            'services' => ['aliases' => ['services'], 'handlers' => ['GET' => 'show']],
        ];
    }

    protected function api(): array
    {
        return ['services' => ['GET' => 'describe']];
    }

    public function show(): void
    {
        $this->view('services', (new ServicesModel())->page());
    }

    public function describe(): void
    {
        $this->json(['data' => (new ServicesModel())->page()]);
    }
}
