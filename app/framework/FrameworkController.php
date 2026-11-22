<?php

namespace App\Framework;

use App\DocsController;

final class FrameworkController extends DocsController
{
    protected function views(): array
    {
        return [
            'framework' => ['aliases' => ['framework', 'about'], 'handlers' => ['GET' => 'show']],
        ];
    }

    public function show(): void
    {
        $this->view('framework', (new FrameworkModel())->page());
    }

    protected function api(): array
    {
        return ['framework' => ['GET' => 'describe']];
    }

    public function describe(): void
    {
        $this->json(['data' => (new FrameworkModel())->page()]);
    }
}
