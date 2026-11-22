<!doctype html>
<html lang="en" data-font="inter">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'FusionPHP') ?></title>
    <script src="<?= e($appearanceScriptUrl) ?>"></script>
    <script src="<?= e($fusionScriptUrl) ?>" defer></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400..800&amp;family=Inter:wght@400..800&amp;family=Source+Serif+4:wght@400..800&amp;display=swap">
    <link rel="stylesheet" href="<?= e($styleCssUrl) ?>">
    <link rel="stylesheet" href="<?= e($docsCssUrl) ?>">
</head>
<body class="docs">
    <header class="navbar">
        <nav class="navbar-container" aria-label="Documentation">
            <a class="navbar-brand" href="<?= e($homeUrl) ?>"<?= $docsPage === 'home' ? ' aria-current="page"' : '' ?>><?= e($siteName) ?></a>
            <button type="button" class="btn btn-outline btn-sm navbar-toggle" data-fusion-toggle="docs-navigation" aria-controls="docs-navigation" aria-expanded="false" hidden>Menu</button>
            <ul class="navbar-nav" id="docs-navigation" data-fusion-navbar>
                <?php foreach ([
                    'framework' => [$frameworkUrl, 'Framework'],
                    'templates' => [$templatesUrl, 'Templates'],
                    'api' => [$apiGuideUrl, 'API'],
                    'services' => [$servicesUrl, 'Core services'],
                    'classes' => [$classesUrl, 'Classes'],
                    'plugins' => [$pluginsUrl, 'Plugins'],
                    'styles' => [$stylesUrl, 'CSS'],
                    'javascript' => [$fusionJsGuideUrl, 'JavaScript'],
                    'appearance' => [$appearanceUrl, 'Appearance'],
                ] as $page => [$url, $label]): ?>
                <li><a class="nav-link" href="<?= e($url) ?>"<?= $docsPage === $page ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
                <?php endforeach ?>
            </ul>
        </nav>
    </header>
    <main class="container docs-main">
        <?= $content ?>
    </main>
</body>
</html>
