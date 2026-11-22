<?php

namespace App\Appearance;

use App\DocsController;

final class AppearanceController extends DocsController
{
    protected function views(): array
    {
        return [
            'appearance' => ['aliases' => ['appearance'], 'handlers' => ['GET' => 'show']],
        ];
    }

    public function show(): void
    {
        $this->view('appearance', ['title' => 'Appearance']);
    }
}
