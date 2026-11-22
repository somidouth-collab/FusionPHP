<?php

namespace App\Home;

final class HomeModel
{
    public function page(): array
    {
        return [
            'title' => config('app.name'),
            'guides' => [
                ['title' => 'FusionPHPStyle', 'url' => '/fusionphpstyle', 'description' => 'Install and use the CSS toolkit. Browse layouts, themes, interactive components, and AJAX helpers.'],
                ['title' => 'Plugins', 'url' => '/plugins', 'description' => 'Install bundled plugins or generate and share your own module.'],
                ['title' => 'Appearance', 'url' => '/appearance', 'description' => 'Choose a theme and font, and download the reusable FusionPHP stylesheet.'],
                ['title' => 'Class reference', 'url' => '/classes', 'description' => 'Look up classes, method signatures, and extension points.'],
            ],
        ];
    }
}
