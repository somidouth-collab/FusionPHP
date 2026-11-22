<?php

namespace App\Classes;

use App\DocsController;

final class ClassesController extends DocsController
{
    protected function views(): array
    {
        return ['classes' => ['aliases' => ['classes'], 'handlers' => ['GET' => 'show']]];
    }

    protected function api(): array
    {
        return ['classes' => ['GET' => 'describe']];
    }

    public function show(): void
    {
        $this->view('classes', (new ClassesModel())->page());
    }

    public function describe(): void
    {
        $this->json(['data' => (new ClassesModel())->page()]);
    }
}
