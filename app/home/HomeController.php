<?php

namespace App\Home;

use App\DocsController;

final class HomeController extends DocsController
{
    protected function views(): array
    {
        return [
            'home' => ['aliases' => ['/', 'home'], 'handlers' => ['GET' => 'show']],
        ];
    }

    public function show(): void
    {
        $data = (new HomeModel())->page();
        foreach ($data['guides'] as &$guide) {
            $guide['url'] = $this->request->url($guide['url']);
        }
        $this->view('home', $data);
    }
}
