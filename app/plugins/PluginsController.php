<?php

namespace App\Plugins;

use App\DocsController;

final class PluginsController extends DocsController
{
    protected function views(): array
    {
        return [
            'plugins' => ['aliases' => ['plugins'], 'handlers' => ['GET' => 'show']],
        ];
    }

    public function show(): void
    {
        $this->view('plugins', ['title' => 'Plugins']);
    }
}
