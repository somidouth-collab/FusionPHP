<?php

namespace App\Styles;

use App\DocsController;

final class StylesController extends DocsController
{
    protected function views(): array
    {
        return [
            'styles' => ['aliases' => ['fusionphpstyle', 'styles'], 'handlers' => ['GET' => 'show']],
        ];
    }

    public function show(): void
    {
        $this->view('styles', ['title' => 'FusionPHPStyle']);
    }
}
