<?php

namespace App\Templates;

use App\DocsController;

final class TemplatesController extends DocsController
{
    protected function views(): array
    {
        return [
            'templates' => ['aliases' => ['templates'], 'handlers' => ['GET' => 'show']],
        ];
    }

    public function show(): void
    {
        $this->view('templates', (new TemplatesModel())->page());
    }

    protected function api(): array
    {
        return ['templates' => ['GET' => 'describe']];
    }

    public function describe(): void
    {
        $this->json(['data' => (new TemplatesModel())->page()]);
    }
}
