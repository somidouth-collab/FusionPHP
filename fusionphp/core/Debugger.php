<?php

namespace Fusion\Core;

class Debugger
{
    private static bool $enabled = false;
    private static float $startTime = 0;
    private static array $queries = [];
    private static array $logs = [];

    public static function boot(bool $dev = true): void
    {
        if (!$dev)
            return;

        self::$enabled = true;
        self::$startTime = microtime(true);
    }

    public static function isEnabled(): bool
    {
        return self::$enabled;
    }

    public static function logQuery(string $sql, array $bindings = [], float $time = 0): void
    {
        if (!self::$enabled)
            return;

        self::$queries[] = [
            'sql' => $sql,
            'bindings' => [],
            'time' => round($time * 1000, 3),
        ];
    }

    public static function log(mixed $value, string $label = ''): void
    {
        if (!self::$enabled)
            return;

        self::$logs[] = [
            'label' => $label,
            'value' => $value,
            'trace' => self::getCallerTrace(),
        ];
    }

    private static function collectRequest(): array
    {
        return [
            'url' => $_SERVER['REQUEST_URI'] ?? '/',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'headers' => self::getHeaders(),
            'get' => $_GET,
            'post' => array_map(static fn () => '[redacted]', $_POST),
        ];
    }

    private static function collectMemory(): array
    {
        return [
            'current' => self::formatBytes(memory_get_usage(true)),
            'peak' => self::formatBytes(memory_get_peak_usage(true)),
            'time' => round((microtime(true) - self::$startTime) * 1000, 2) . 'ms',
        ];
    }

    private static function collectClasses(): array
    {
        $included = get_included_files();
        return [
            'count' => count($included),
            'files' => array_map(fn($f) => str_replace(BASE_PATH . '/', '', $f), $included),
        ];
    }

    private static function collectSession(): array
    {
        return [
            'session' => array_map(static fn () => '[redacted]', $_SESSION ?? []),
            'cookies' => [],
        ];
    }

    public static function decorate(string $html): string
    {
        $position = self::$enabled ? strripos($html, '</body>') : false;
        if ($position === false) {
            return $html;
        }
        ob_start();
        try {
            self::renderBar();
            $bar = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        return substr_replace($html, $bar, $position, 0);
    }

    private static function encode(mixed $data): string
    {
        return json_encode($data, JSON_HEX_TAG | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }

    public static function renderBar(): void
    {
        if (!self::$enabled)
            return;

        $memory = self::collectMemory();
        $request = self::collectRequest();
        $classes = self::collectClasses();
        $session = self::collectSession();
        $queries = self::$queries;
        $logs = self::$logs;
        $count = count($queries);
        $slow = array_filter($queries, fn($q) => $q['time'] > 100);
        $totalMs = round(array_sum(array_column($queries, 'time')), 3);
        $dotColor = self::queryDotColor($count, $slow);
        $method = htmlspecialchars($request['method']);
        $uri = htmlspecialchars($request['url']);
        $logCount = count($logs);
        $debugUrl = htmlspecialchars(Request::getInstance()->url('/_debug'), ENT_QUOTES, 'UTF-8');
        $phpVersion = PHP_VERSION;
        $memTime = $memory['time'];
        $memCurrent = $memory['current'];
        $memPeak = $memory['peak'];
        $fileCount = $classes['count'];

        $queriesJson = self::encode($queries);
        $requestJson = self::encode($request);
        $classesJson = self::encode($classes['files']);
        $sessionJson = self::encode($session);
        $logsJson = self::encode($logs);

        echo <<<HTML
    <style>
        #fdbg{position:fixed;bottom:0;left:0;right:0;z-index:99999;font-family:monospace;display:flex;flex-direction:column;background:#1a1a2e;border-top:2px solid #4361ee}
        #fdbg-resize{height:4px;background:transparent;cursor:ns-resize;position:absolute;top:-2px;left:0;right:0}
        #fdbg-resize:hover{background:#4361ee55}
        #fdbg-bar{height:36px;display:flex;align-items:center;padding:0 12px;gap:10px;flex-shrink:0;border-bottom:1px solid #2a2a4a}
        .fdbg-brand{color:#4361ee;font-weight:bold;font-size:13px}
        .fdbg-stat{display:flex;align-items:center;gap:5px;padding:3px 8px;border-radius:4px;background:rgba(255,255,255,0.05);font-size:11px;cursor:pointer;color:#ccc;border:none;font-family:monospace}
        .fdbg-stat:hover{background:rgba(255,255,255,0.1)}
        .fdbg-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0}
        .dot-green{background:#06d6a0}.dot-blue{background:#4361ee}.dot-yellow{background:#ffd166}.dot-red{background:#ef476f}
        .fdbg-spacer{flex:1}
        .fdbg-btn{background:rgba(255,255,255,0.05);border:none;color:#888;font-size:12px;cursor:pointer;padding:4px 8px;border-radius:4px;font-family:monospace}
        .fdbg-btn:hover{background:rgba(255,255,255,0.12);color:#e0e0e0}
        #fdbg-tabs{display:flex;background:#111122;border-bottom:1px solid #2a2a4a;padding:0 12px;flex-shrink:0}
        .fdbg-tab{padding:7px 12px;cursor:pointer;font-size:11px;border:none;border-bottom:2px solid transparent;color:#555;background:none;font-family:monospace}
        .fdbg-tab:hover{color:#ccc}
        .fdbg-tab.active{color:#4361ee;border-bottom-color:#4361ee}
        .fdbg-badge{display:inline-block;padding:1px 5px;border-radius:8px;font-size:10px;margin-left:3px;background:rgba(67,97,238,0.2);color:#4361ee}
        .fdbg-badge.red{background:rgba(239,71,111,0.2);color:#ef476f}
        #fdbg-content{overflow-y:auto;padding:12px 16px}
        .fdbg-sec{display:none}.fdbg-sec.active{display:block}
        .fdbg-title{font-size:10px;text-transform:uppercase;color:#444;letter-spacing:1px;margin-bottom:10px}
        .fdbg-query{background:#111122;border:1px solid #2a2a4a;border-radius:5px;padding:9px 11px;margin-bottom:7px}
        .fdbg-sql{color:#569cd6;font-size:12px;line-height:1.5}
        .fdbg-meta{margin-top:5px;font-size:11px;color:#444;display:flex;gap:12px}
        .fdbg-t{color:#06d6a0}.fdbg-t.slow{color:#ef476f}
        .fdbg-kv{display:grid;grid-template-columns:160px 1fr}
        .fdbg-k{padding:5px 8px;font-size:11px;color:#555;border-bottom:1px solid #1e1e3a}
        .fdbg-v{padding:5px 8px;font-size:11px;color:#ccc;border-bottom:1px solid #1e1e3a}
        .fdbg-file{font-size:11px;color:#555;padding:3px 0;border-bottom:1px solid #1a1a2e}
        .fdbg-file:hover{color:#999}
        .fdbg-log{background:#111122;border-left:3px solid #4361ee;padding:7px 10px;margin-bottom:6px;border-radius:0 4px 4px 0}
        .fdbg-log-label{color:#4361ee;font-size:10px;margin-bottom:3px}
        .fdbg-log-val{color:#ccc;font-size:11px;white-space:pre-wrap}
        .fdbg-log-trace{color:#333;font-size:10px;margin-top:3px}
        .fdbg-empty{color:#333;font-size:12px;padding:6px 0}
    </style>

    <div id="fdbg">
        <div id="fdbg-resize"></div>
        <div id="fdbg-bar">
            <span class="fdbg-brand">⚡ FusionPHP</span>
            <button class="fdbg-stat" onclick="fdbgTab('memory')">
                <span class="fdbg-dot dot-green"></span>{$memTime}
            </button>
            <button class="fdbg-stat" onclick="fdbgTab('memory')">
                <span class="fdbg-dot dot-blue"></span>{$memCurrent} / {$memPeak}
            </button>
            <button class="fdbg-stat" onclick="fdbgTab('queries')">
                <span class="fdbg-dot {$dotColor}"></span>{$count} queries · {$totalMs}ms
            </button>
            <button class="fdbg-stat" onclick="fdbgTab('request')">
                <span class="fdbg-dot dot-blue"></span>{$method} {$uri}
            </button>
            <span class="fdbg-spacer"></span>
            <a href="{$debugUrl}" target="_blank" class="fdbg-btn">⬡ Full page</a>
            <button class="fdbg-btn" onclick="fdbgToggle()" id="fdbg-toggle-btn">▼</button>
            <button class="fdbg-btn" onclick="document.getElementById('fdbg').remove();document.body.style.paddingBottom='0'">✕</button>
        </div>
        <div id="fdbg-tabs">
            <button class="fdbg-tab active" onclick="fdbgTab('queries')">SQL <span class="fdbg-badge">{$count}</span></button>
            <button class="fdbg-tab" onclick="fdbgTab('request')">Request</button>
            <button class="fdbg-tab" onclick="fdbgTab('memory')">Memory</button>
            <button class="fdbg-tab" onclick="fdbgTab('classes')">Files <span class="fdbg-badge">{$fileCount}</span></button>
            <button class="fdbg-tab" onclick="fdbgTab('session')">Session</button>
            <button class="fdbg-tab" onclick="fdbgTab('logs')">Logs <span class="fdbg-badge red">{$logCount}</span></button>
        </div>
        <div id="fdbg-content">
            <div class="fdbg-sec active" id="fdbg-sec-queries"></div>
            <div class="fdbg-sec" id="fdbg-sec-request"></div>
            <div class="fdbg-sec" id="fdbg-sec-memory"></div>
            <div class="fdbg-sec" id="fdbg-sec-classes"></div>
            <div class="fdbg-sec" id="fdbg-sec-session"></div>
            <div class="fdbg-sec" id="fdbg-sec-logs"></div>
        </div>
    </div>
HTML;

        echo <<<'SCRIPT'
    <script>
    (function() {
        var panel = document.getElementById('fdbg');
        var content = document.getElementById('fdbg-content');
        var tabs = document.getElementById('fdbg-tabs');
        var toggleBtn = document.getElementById('fdbg-toggle-btn');
        var resize = document.getElementById('fdbg-resize');
        var collapsed = false;
        var panelH = 280;
        var dragging = false;
        var startY = 0;
        var startH = 0;
SCRIPT;

        echo "var queries = {$queriesJson};";
        echo "var request = {$requestJson};";
        echo "var classes = {$classesJson};";
        echo "var session = {$sessionJson};";
        echo "var logs = {$logsJson};";
        echo "var memTime = '{$memTime}';";
        echo "var memCur = '{$memCurrent}';";
        echo "var memPeak = '{$memPeak}';";
        echo "var phpVer = '{$phpVersion}';";

        echo <<<'SCRIPT'
        function esc(s) {
            return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }
        function kv(k, v) {
            return '<div class="fdbg-k">' + esc(k) + '</div><div class="fdbg-v">' + v + '</div>';
        }
        function renderQueries() {
            var html = '<div class="fdbg-title">SQL — ' + queries.length + ' queries</div>';
            if (!queries.length) {
                html += '<div class="fdbg-empty">No queries on this request.</div>';
            } else {
                queries.forEach(function(q, i) {
                    var slowClass = q.time > 100 ? ' slow' : '';
                    var slowWarn = q.time > 100 ? ' ⚠ slow' : '';
                    var border = q.time > 100 ? ' style="border-color:#ef476f44"' : '';
                    html += '<div class="fdbg-query"' + border + '>';
                    html += '<div class="fdbg-sql">' + esc(q.sql) + '</div>';
                    html += '<div class="fdbg-meta"><span>Query #' + (i+1) + '</span>';
                    html += '<span class="fdbg-t' + slowClass + '">' + q.time + 'ms' + slowWarn + '</span>';
                    if (q.bindings && q.bindings.length) html += '<span>Bindings: ' + esc(JSON.stringify(q.bindings)) + '</span>';
                    html += '</div></div>';
                });
            }
            document.getElementById('fdbg-sec-queries').innerHTML = html;
        }
        function renderRequest() {
            var html = '<div class="fdbg-title">Request</div><div class="fdbg-kv">';
            html += kv('URL', request.url);
            html += kv('Method', '<span style="color:#4361ee">' + esc(request.method) + '</span>');
            html += kv('IP', esc(request.ip));
            Object.entries(request.headers || {}).forEach(function(e) { html += kv(e[0], esc(e[1])); });
            html += '</div>';
            if (Object.keys(request.get || {}).length) {
                html += '<div class="fdbg-title" style="margin-top:12px">GET params</div><div class="fdbg-kv">';
                Object.entries(request.get).forEach(function(e) { html += kv(e[0], esc(e[1])); });
                html += '</div>';
            }
            if (Object.keys(request.post || {}).length) {
                html += '<div class="fdbg-title" style="margin-top:12px">POST data</div><div class="fdbg-kv">';
                Object.entries(request.post).forEach(function(e) {
                    var val = ['password','token','secret'].includes(e[0].toLowerCase()) ? '••••••••' : esc(e[1]);
                    html += kv(e[0], val);
                });
                html += '</div>';
            }
            document.getElementById('fdbg-sec-request').innerHTML = html;
        }
        function renderMemory() {
            var html = '<div class="fdbg-title">Performance</div><div class="fdbg-kv">';
            html += kv('Execution time', '<span style="color:#06d6a0">' + memTime + '</span>');
            html += kv('Memory current', memCur);
            html += kv('Memory peak', memPeak);
            html += kv('PHP version', phpVer);
            html += '</div>';
            document.getElementById('fdbg-sec-memory').innerHTML = html;
        }
        function renderClasses() {
            var html = '<div class="fdbg-title">Loaded files — ' + classes.length + '</div>';
            classes.forEach(function(f) { html += '<div class="fdbg-file">' + esc(f) + '</div>'; });
            document.getElementById('fdbg-sec-classes').innerHTML = html;
        }
        function renderSession() {
            var html = '<div class="fdbg-title">Session</div>';
            var sess = session.session || {};
            var cooks = session.cookies || {};
            if (!Object.keys(sess).length) html += '<div class="fdbg-empty">Session is empty.</div>';
            else {
                html += '<div class="fdbg-kv">';
                Object.entries(sess).forEach(function(e) { html += kv(e[0], esc(JSON.stringify(e[1]))); });
                html += '</div>';
            }
            html += '<div class="fdbg-title" style="margin-top:12px">Cookies</div>';
            if (!Object.keys(cooks).length) html += '<div class="fdbg-empty">No cookies.</div>';
            else {
                html += '<div class="fdbg-kv">';
                Object.entries(cooks).forEach(function(e) { html += kv(e[0], esc(e[1])); });
                html += '</div>';
            }
            document.getElementById('fdbg-sec-session').innerHTML = html;
        }
        function renderLogs() {
            var html = '<div class="fdbg-title">Logs — ' + logs.length + '</div>';
            if (!logs.length) html += '<div class="fdbg-empty">No logs. Use debug($val, "label") anywhere.</div>';
            else {
                logs.forEach(function(l) {
                    html += '<div class="fdbg-log">';
                    if (l.label) html += '<div class="fdbg-log-label">' + esc(l.label) + '</div>';
                    html += '<div class="fdbg-log-val">' + esc(JSON.stringify(l.value, null, 2)) + '</div>';
                    if (l.trace) html += '<div class="fdbg-log-trace">' + esc(l.trace) + '</div>';
                    html += '</div>';
                });
            }
            document.getElementById('fdbg-sec-logs').innerHTML = html;
        }
        window.fdbgTab = function(name) {
            document.querySelectorAll('.fdbg-sec').forEach(function(s) { s.classList.remove('active'); });
            document.querySelectorAll('.fdbg-tab').forEach(function(t) { t.classList.remove('active'); });
            document.getElementById('fdbg-sec-' + name).classList.add('active');
            document.querySelectorAll('.fdbg-tab').forEach(function(t) {
                if (t.getAttribute('onclick') === "fdbgTab('" + name + "')") t.classList.add('active');
            });
            if (collapsed) fdbgExpand();
        };
        function fdbgCollapse() {
            collapsed = true;
            content.style.display = 'none';
            tabs.style.display = 'none';
            panel.style.height = '36px';
            toggleBtn.textContent = '▲';
            resize.style.display = 'none';
            sync();
            sessionStorage.setItem('fdbg-h', 'collapsed');
        }
        function fdbgExpand() {
            collapsed = false;
            content.style.display = 'block';
            tabs.style.display = 'flex';
            panel.style.height = panelH + 'px';
            toggleBtn.textContent = '▼';
            resize.style.display = 'block';
            sync();
            sessionStorage.removeItem('fdbg-h');
        }
        window.fdbgToggle = function() { collapsed ? fdbgExpand() : fdbgCollapse(); };
        function sync() { document.body.style.paddingBottom = panel.offsetHeight + 'px'; }
        resize.addEventListener('mousedown', function(e) {
            dragging = true;
            startY = e.clientY;
            startH = panel.offsetHeight;
            document.body.style.cursor = 'ns-resize';
            e.preventDefault();
        });
        document.addEventListener('mousemove', function(e) {
            if (!dragging) return;
            var delta = startY - e.clientY;
            var newH = Math.max(80, Math.min(window.innerHeight * 0.9, startH + delta));
            panelH = newH;
            panel.style.height = newH + 'px';
            content.style.height = (newH - 72) + 'px';
            sessionStorage.setItem('fdbg-h', newH);
            sync();
        });
        document.addEventListener('mouseup', function() {
            if (!dragging) return;
            dragging = false;
            document.body.style.cursor = '';
        });
        document.addEventListener('keydown', function(e) { if (e.key === 'Escape') fdbgToggle(); });
        var saved = sessionStorage.getItem('fdbg-h');
        if (saved === 'collapsed') fdbgCollapse();
        else if (saved) panelH = parseInt(saved);
        renderQueries();
        renderRequest();
        renderMemory();
        renderClasses();
        renderSession();
        renderLogs();
        panel.style.height = panelH + 'px';
        content.style.height = (panelH - 72) + 'px';
        sync();
    })();
    </script>
SCRIPT;
    }

    public static function renderPage(): void
    {
        if (!self::$enabled) {
            http_response_code(404);
            echo '<h1>404</h1>';
            exit;
        }

        $request = self::collectRequest();
        $memory = self::collectMemory();
        $classes = self::collectClasses();
        $session = self::collectSession();
        $queries = self::$queries;
        $logs = self::$logs;

        $totalMs = array_sum(array_column($queries, 'time'));
        $queryCount = count($queries);

        ob_start(); ?>
        <!DOCTYPE html>
        <html>

        <head>
            <meta charset="UTF-8">
            <title>FusionPHP Debugger</title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box
                }

                body {
                    font-family: monospace;
                    background: #0f0f1a;
                    color: #e0e0e0
                }

                .dbg-header {
                    background: #1a1a2e;
                    border-bottom: 2px solid #4361ee;
                    padding: 1rem 2rem;
                    display: flex;
                    align-items: center;
                    gap: 2rem
                }

                .dbg-header h1 {
                    color: #4361ee;
                    font-size: 1.2rem
                }

                .dbg-stat {
                    background: rgba(255, 255, 255, 0.05);
                    padding: 4px 14px;
                    border-radius: 20px;
                    font-size: 12px
                }

                .dbg-tabs {
                    display: flex;
                    gap: 0;
                    background: #111122;
                    border-bottom: 1px solid #2a2a4a;
                    padding: 0 2rem
                }

                .dbg-tab {
                    padding: 10px 20px;
                    cursor: pointer;
                    font-size: 13px;
                    border-bottom: 2px solid transparent;
                    color: #888;
                    transition: all 0.15s
                }

                .dbg-tab:hover {
                    color: #e0e0e0
                }

                .dbg-tab.active {
                    color: #4361ee;
                    border-bottom-color: #4361ee
                }

                .dbg-panel {
                    display: none;
                    padding: 2rem
                }

                .dbg-panel.active {
                    display: block
                }

                .dbg-section {
                    margin-bottom: 2rem
                }

                .dbg-section h2 {
                    font-size: 12px;
                    text-transform: uppercase;
                    color: #555;
                    letter-spacing: 1px;
                    margin-bottom: 1rem
                }

                .dbg-table {
                    width: 100%;
                    border-collapse: collapse
                }

                .dbg-table th,
                .dbg-table td {
                    padding: 8px 12px;
                    text-align: left;
                    font-size: 12px;
                    border-bottom: 1px solid #1e1e3a
                }

                .dbg-table th {
                    color: #666;
                    font-weight: normal;
                    width: 180px
                }

                .dbg-table td {
                    color: #ccc
                }

                .dbg-query {
                    background: #111122;
                    border: 1px solid #2a2a4a;
                    border-radius: 6px;
                    padding: 1rem;
                    margin-bottom: 1rem
                }

                .dbg-query .sql {
                    color: #569cd6;
                    font-size: 13px;
                    line-height: 1.6
                }

                .dbg-query .meta {
                    margin-top: 6px;
                    font-size: 11px;
                    color: #555
                }

                .dbg-query .time {
                    color: #06d6a0
                }

                .dbg-query .time-slow {
                    color: #ef476f
                }

                .dbg-badge {
                    display: inline-block;
                    padding: 2px 8px;
                    border-radius: 10px;
                    font-size: 11px
                }

                .badge-green {
                    background: rgba(6, 214, 160, 0.15);
                    color: #06d6a0
                }

                .badge-red {
                    background: rgba(239, 71, 111, 0.15);
                    color: #ef476f
                }

                .badge-blue {
                    background: rgba(67, 97, 238, 0.15);
                    color: #4361ee
                }

                .dbg-file {
                    font-size: 11px;
                    color: #666;
                    padding: 3px 0;
                    border-bottom: 1px solid #1a1a2e
                }

                .dbg-file:hover {
                    color: #aaa
                }

                .dbg-empty {
                    color: #444;
                    font-size: 13px;
                    padding: 1rem 0
                }

                .dbg-log {
                    background: #111122;
                    border-left: 3px solid #4361ee;
                    padding: 10px 14px;
                    margin-bottom: 8px;
                    border-radius: 0 6px 6px 0
                }

                .dbg-log .log-label {
                    color: #4361ee;
                    font-size: 11px;
                    margin-bottom: 4px
                }

                .dbg-log .log-value {
                    color: #e0e0e0;
                    font-size: 12px
                }

                .dbg-log .log-trace {
                    color: #444;
                    font-size: 11px;
                    margin-top: 4px
                }

                pre {
                    white-space: pre-wrap;
                    word-break: break-all
                }
            </style>
        </head>

        <body>
            <div class="dbg-header">
                <h1>⚡ FusionPHP Debugger</h1>
                <span class="dbg-stat">
                    <?= $memory['time'] ?>
                </span>
                <span class="dbg-stat">
                    <?= $memory['peak'] ?> peak
                </span>
                <span class="dbg-stat">
                    <?= $queryCount ?> queries ·
                    <?= round($totalMs, 3) ?>ms
                </span>
                <span class="dbg-stat">
                    <?= $classes['count'] ?> files loaded
                </span>
                <span class="dbg-stat">
                    <?= $request['method'] ?>
                    <?= htmlspecialchars($request['url']) ?>
                </span>
            </div>
            <div class="dbg-tabs">
                <div class="dbg-tab active" onclick="showTab('queries')">SQL Queries <span class="dbg-badge badge-blue">
                        <?= $queryCount ?>
                    </span></div>
                <div class="dbg-tab" onclick="showTab('request')">Request</div>
                <div class="dbg-tab" onclick="showTab('memory')">Memory</div>
                <div class="dbg-tab" onclick="showTab('classes')">Files <span class="dbg-badge badge-blue">
                        <?= $classes['count'] ?>
                    </span></div>
                <div class="dbg-tab" onclick="showTab('session')">Session & Cookies</div>
                <?php if (!empty($logs)): ?>
                    <div class="dbg-tab" onclick="showTab('logs')">Logs <span class="dbg-badge badge-red">
                            <?= count($logs) ?>
                        </span></div>
                <?php endif ?>
            </div>
            <div id="tab-queries" class="dbg-panel active">
                <div class="dbg-section">
                    <h2>SQL Queries (
                        <?= $queryCount ?> ·
                        <?= round($totalMs, 3) ?>ms total)
                    </h2>
                    <?php if (empty($queries)): ?>
                        <p class="dbg-empty">No queries executed on this request.</p>
                    <?php else:
                        foreach ($queries as $i => $q): ?>
                            <div class="dbg-query">
                                <div class="sql">
                                    <?= htmlspecialchars($q['sql']) ?>
                                </div>
                                <?php if (!empty($q['bindings'])): ?>
                                    <div class="meta">Bindings:
                                        <?= htmlspecialchars(json_encode($q['bindings'])) ?>
                                    </div>
                                <?php endif ?>
                                <div class="meta">Query #
                                    <?= $i + 1 ?> &nbsp;·&nbsp;<span class="<?= $q['time'] > 100 ? 'time-slow' : 'time' ?>">
                                        <?= $q['time'] ?>ms
                                        <?= $q['time'] > 100 ? ' ⚠ slow' : '' ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; endif ?>
                </div>
            </div>
            <div id="tab-request" class="dbg-panel">
                <div class="dbg-section">
                    <h2>Request</h2>
                    <table class="dbg-table">
                        <tr>
                            <th>URL</th>
                            <td>
                                <?= htmlspecialchars($request['url']) ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Method</th>
                            <td><span class="dbg-badge badge-blue">
                                    <?= $request['method'] ?>
                                </span></td>
                        </tr>
                        <tr>
                            <th>IP</th>
                            <td>
                                <?= $request['ip'] ?>
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="dbg-section">
                    <h2>Headers</h2>
                    <table class="dbg-table">
                        <?php foreach ($request['headers'] as $k => $v): ?>
                            <tr>
                                <th>
                                    <?= htmlspecialchars($k) ?>
                                </th>
                                <td>
                                    <?= htmlspecialchars(is_scalar($v) ? (string) $v : self::encode($v)) ?>
                                </td>
                            </tr>
                        <?php endforeach ?>
                    </table>
                </div>
                <?php if (!empty($request['get'])): ?>
                    <div class="dbg-section">
                        <h2>GET params</h2>
                        <table class="dbg-table">
                            <?php foreach ($request['get'] as $k => $v): ?>
                                <tr>
                                    <th>
                                        <?= htmlspecialchars($k) ?>
                                    </th>
                                    <td>
                                        <?= htmlspecialchars(is_scalar($v) ? (string) $v : self::encode($v)) ?>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        </table>
                    </div>
                <?php endif ?>
                <?php if (!empty($request['post'])): ?>
                    <div class="dbg-section">
                        <h2>POST data</h2>
                        <table class="dbg-table">
                            <?php foreach ($request['post'] as $k => $v): ?>
                                <tr>
                                    <th>
                                        <?= htmlspecialchars($k) ?>
                                    </th>
                                    <td>
                                        <?= htmlspecialchars(in_array(strtolower($k), ['password', 'token', 'secret']) ? '••••••••' : $v) ?>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        </table>
                    </div>
                <?php endif ?>
            </div>
            <div id="tab-memory" class="dbg-panel">
                <div class="dbg-section">
                    <h2>Performance</h2>
                    <table class="dbg-table">
                        <tr>
                            <th>Execution time</th>
                            <td>
                                <?= $memory['time'] ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Memory current</th>
                            <td>
                                <?= $memory['current'] ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Memory peak</th>
                            <td>
                                <?= $memory['peak'] ?>
                            </td>
                        </tr>
                        <tr>
                            <th>PHP version</th>
                            <td>
                                <?= PHP_VERSION ?>
                            </td>
                        </tr>
                        <tr>
                            <th>OPcache</th>
                            <td>
                                <?= function_exists('opcache_get_status') ? 'enabled' : 'disabled' ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            <div id="tab-classes" class="dbg-panel">
                <div class="dbg-section">
                    <h2>Loaded Files (
                        <?= $classes['count'] ?>)
                    </h2>
                    <?php foreach ($classes['files'] as $file): ?>
                        <div class="dbg-file">
                            <?= htmlspecialchars($file) ?>
                        </div>
                    <?php endforeach ?>
                </div>
            </div>
            <div id="tab-session" class="dbg-panel">
                <div class="dbg-section">
                    <h2>Session</h2>
                    <?php if (empty($session['session'])): ?>
                        <p class="dbg-empty">Session is empty.</p>
                    <?php else: ?>
                        <table class="dbg-table">
                            <?php foreach ($session['session'] as $k => $v): ?>
                                <tr>
                                    <th>
                                        <?= htmlspecialchars($k) ?>
                                    </th>
                                    <td>
                                        <pre><?= htmlspecialchars(print_r($v, true)) ?></pre>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        </table>
                    <?php endif ?>
                </div>
                <div class="dbg-section">
                    <h2>Cookies</h2>
                    <?php if (empty($session['cookies'])): ?>
                        <p class="dbg-empty">No cookies.</p>
                    <?php else: ?>
                        <table class="dbg-table">
                            <?php foreach ($session['cookies'] as $k => $v): ?>
                                <tr>
                                    <th>
                                        <?= htmlspecialchars($k) ?>
                                    </th>
                                    <td>
                                        <?= htmlspecialchars(is_scalar($v) ? (string) $v : self::encode($v)) ?>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        </table>
                    <?php endif ?>
                </div>
            </div>
            <?php if (!empty($logs)): ?>
                <div id="tab-logs" class="dbg-panel">
                    <div class="dbg-section">
                        <h2>Debug Logs (
                            <?= count($logs) ?>)
                        </h2>
                        <?php foreach ($logs as $log): ?>
                            <div class="dbg-log">
                                <?php if ($log['label']): ?>
                                    <div class="log-label">
                                        <?= htmlspecialchars($log['label']) ?>
                                    </div>
                                <?php endif ?>
                                <div class="log-value">
                                    <pre><?= htmlspecialchars(print_r($log['value'], true)) ?></pre>
                                </div>
                                <div class="log-trace">
                                    <?= htmlspecialchars($log['trace']) ?>
                                </div>
                            </div>
                        <?php endforeach ?>
                    </div>
                </div>
            <?php endif ?>
            <script>
                function showTab(name) {
                    const panel = document.getElementById('tab-' + name);
                    if (!panel) return;
                    document.querySelectorAll('.dbg-panel').forEach(item => item.classList.toggle('active', item === panel));
                    document.querySelectorAll('.dbg-tab').forEach(item => {
                        item.classList.toggle('active', item.getAttribute('onclick') === "showTab('" + name + "')");
                    });
                }
                if (location.hash) showTab(location.hash.slice(1));
            </script>
        </body>

        </html>
        <?php
        echo ob_get_clean();
        exit;
    }

    private static function queryDotColor(int $count, array $slow): string
    {
        if (!empty($slow))
            return 'dot-red';
        if ($count > 10)
            return 'dot-yellow';
        return 'dot-green';
    }

    private static function getHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (in_array($key, ['HTTP_HOST', 'HTTP_ACCEPT', 'HTTP_USER_AGENT'], true)) {
                $headers[str_replace('_', '-', substr($key, 5))] = $value;
            }
        }
        return $headers;
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576)
            return round($bytes / 1048576, 2) . 'MB';
        if ($bytes >= 1024)
            return round($bytes / 1024, 2) . 'KB';
        return $bytes . 'B';
    }

    private static function getCallerTrace(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);
        foreach ($trace as $frame) {
            $file = $frame['file'] ?? '';
            if ($file && !str_contains($file, 'Debugger.php')) {
                return str_replace(BASE_PATH . '/', '', $file) . ':' . ($frame['line'] ?? '');
            }
        }
        return '';
    }
}