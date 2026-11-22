<main class="container mt-xl mb-xl">
    <header class="page-header">
        <h1><?= e($title) ?></h1>
        <p><?= e($siteName) ?></p>
    </header>

    <section class="mt-lg" aria-labelledby="application-heading">
        <h2 id="application-heading">Application</h2>
        <div class="grid grid-cols-1 grid-cols-3-md gap-md">
            <div class="card card-body"><h3>Version</h3><p><?= e($version) ?></p></div>
            <div class="card card-body"><h3>Mode</h3><p><?= e($mode) ?></p></div>
            <div class="card card-body"><h3>Debugging</h3><p><?= $debugEnabled ? 'On' : 'Off' ?></p></div>
        </div>
    </section>

    <nav class="flex flex-wrap gap-sm mt-lg" aria-label="Admin links">
        <a class="btn btn-outline" href="<?= e($homeUrl) ?>">Site home</a>
        <a class="btn btn-outline" href="<?= e($frameworkUrl) ?>">Framework guide</a>
        <?php if ($apiUrl !== null): ?>
            <a class="btn btn-outline" href="<?= e($apiUrl) ?>">API catalog</a>
        <?php endif; ?>
    </nav>
</main>
