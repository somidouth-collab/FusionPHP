<?php

namespace App\Templates;

final class TemplatesModel
{
    public function page(): array
    {
        return [
            'phpExample' => <<<'EXAMPLE'
<?php layout('main'); ?>

<h1><?= e($title) ?></h1>
<?php foreach ($guides as $guide): ?>
    <p><a href="<?= e($guide['url']) ?>"><?= e($guide['title']) ?></a></p>
<?php endforeach; ?>
EXAMPLE,
            'title' => 'Template guide',
            'functions' => [
                ['example' => "{{ default title 'Untitled' }}", 'description' => 'Uses the fallback for a missing value, null, or an empty string. Keeps zero.'],
                ['example' => '{{ trim title }}', 'description' => 'Removes leading and trailing whitespace from a string.'],
                ['example' => '{{ length guides }}', 'description' => 'Counts array entries or characters in a UTF-8 string. Other values return zero.'],
                ['example' => "{{ join tags ' · ' }}", 'description' => 'Joins the scalar entries of an array using the quoted separator.'],
            ],
            'pageExample' => <<<'EXAMPLE'
{% extends "guide" %}
{% block content %}
<h1>{{ title }}</h1>
{% if guides %}
    {% for guide in guides %}
        <p><a href="{{ url guide.url }}">{{ guide.title }}</a></p>
    {% endfor %}
{% else %}
    <p>No guides yet.</p>
{% endif %}
{% endblock %}
EXAMPLE,
            'layoutExample' => <<<'EXAMPLE'
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ title }}</title>
</head>
<body>
    <main>{% yield content %}</main>
</body>
</html>
EXAMPLE,
            'dataExample' => "\$this->view('example', [\n    'title' => 'Example',\n    'guides' => [['url' => '/framework', 'title' => 'Framework guide']],\n]);",
        ];
    }
}
