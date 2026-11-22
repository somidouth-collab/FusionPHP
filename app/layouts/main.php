<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'FusionPHP') ?></title>
    <link rel="stylesheet" href="<?= e(\Fusion\Core\Request::getInstance()->url('/assets/css/fusionphpstyle.css', true)) ?>">
    <script src="<?= e(\Fusion\Core\Request::getInstance()->url('/assets/js/fusionphp.js', true)) ?>" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>

    <main class="main-content">
        <?= $content ?>
    </main>

</body>

</html>