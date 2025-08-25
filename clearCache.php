<?php
require_once('init.php');
include __DIR__ . '/airtable_config.php';

header('Content-Type: text/html; charset=utf-8');

$cacheDir = __DIR__ . '/Data/cache';
$globalLock = $cacheDir . '/airtable_global.lock';
if (!file_exists($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}
// Detect a PHP CLI binary to run the warmer (avoid php-fpm)
$phpBinary = null;
$envPhp = getenv('PHP_CLI');
if ($envPhp && @is_executable($envPhp)) {
    @exec(escapeshellarg($envPhp) . ' -v 2>&1', $out, $code);
    $ver = strtolower(implode("\n", $out ?? []));
    if (strpos($ver, 'cli') !== false && strpos($ver, 'fpm') === false) {
        $phpBinary = $envPhp;
    }
}
if (!$phpBinary) {
    $phpCandidates = array_filter([
        (defined('PHP_BINARY') ? PHP_BINARY : null),
        (defined('PHP_BINDIR') ? (PHP_BINDIR . '/php') : null),
        '/usr/local/bin/php',
        '/usr/bin/php',
        '/opt/homebrew/bin/php',
    ]);
    foreach ($phpCandidates as $candidate) {
        if (!$candidate || !@is_executable($candidate)) continue;
        @exec(escapeshellarg($candidate) . ' -v 2>&1', $out, $code);
        $ver = strtolower(implode("\n", $out ?? []));
        if (strpos($ver, 'cli') !== false && strpos($ver, 'fpm') === false) {
            $phpBinary = $candidate;
            break;
        }
    }
}
if (!$phpBinary) {
    // Last resort: hope PATH resolves to a CLI php
    $phpBinary = '/usr/bin/env php';
}
$warmLogFile = $cacheDir . '/warm.log';

function airtableCacheBasename($tableId, $tableName)
{
    $baseUrl = 'https://api.airtable.com/v0/';
    $url = $baseUrl . $tableId . '/' . urlencode($tableName) . '?view=Grid%20view&maxRecords=3000';
    return __DIR__ . '/Data/cache/' . md5($url);
}

$message = '';
$isLocked = file_exists($globalLock);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    if ($isLocked && $action !== 'clearLegacy') {
        $message = 'Выполняется прогрев. Дождитесь завершения.';
    } elseif ($action === 'clearOne' && isset($_POST['tableId'], $_POST['tableName'])) {
        $tableId = (string)$_POST['tableId'];
        $tableName = (string)$_POST['tableName'];
        if (!file_exists($globalLock)) {
            @file_put_contents($globalLock, (string)time());
            $cmd = escapeshellarg($phpBinary) . ' ' . escapeshellarg(__DIR__ . '/warmAirtableCache.php') . ' --tableId=' . escapeshellarg($tableId) . ' --tableName=' . escapeshellarg($tableName) . ' >> ' . escapeshellarg($warmLogFile) . ' 2>&1 &';
            exec($cmd);
        }
        $message = 'Очищаю кэш: ' . htmlspecialchars($tableName) . ' (' . htmlspecialchars($tableId) . ')';
    } elseif ($action === 'clearAll') {
        if (!file_exists($globalLock)) {
            @file_put_contents($globalLock, (string)time());
            $cmd = escapeshellarg($phpBinary) . ' ' . escapeshellarg(__DIR__ . '/warmAirtableCache.php') . ' >> ' . escapeshellarg($warmLogFile) . ' 2>&1 &';
            exec($cmd);
        }
        $message = 'Очищаю все кэши';
    } elseif ($action === 'clearLegacy') {
        $deleted = 0;
        foreach (glob(__DIR__ . '/Data/cache_*') as $path) {
            if (@is_file($path) && @unlink($path)) {
                $deleted++;
            }
        }
        $message = 'Удалено файлов: ' . $deleted . ' (Data/cache_*)';
    }
}

$sources = airtable_sources();
$counterPath = __DIR__ . '/Data/cache/airtable_requests.json';
$requestsCount = 0;
$yearMonth = date('Y-m');
$monthlyLimit = 1000;
if (file_exists($counterPath)) {
    $raw = @file_get_contents($counterPath);
    $data = @json_decode($raw, true);
    if (is_array($data)) {
        $requestsCount = (int)($data['count'] ?? 0);
        $yearMonth = $data['yearMonth'] ?? $yearMonth;
    }
}
$percentage = (int)floor(min(100, ($requestsCount / max(1, $monthlyLimit)) * 100));
$barColor = $percentage >= 100 ? '#d9534f' : '#0d6efd';
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Управление кэшем Airtable</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
            margin: 24px;
        }

        h1 {
            font-size: 20px;
            margin: 0 0 16px;
        }

        .actions {
            margin: 16px 0;
        }

        .message {
            padding: 10px 12px;
            background: #f0f7ff;
            border: 1px solid #cfe3ff;
            border-radius: 6px;
            color: #083d77;
            margin-bottom: 16px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            text-align: left;
            padding: 8px 10px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }

        th {
            background: #fafafa;
            font-weight: 600;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 12px;
            border: 1px solid #ddd;
            background: #fff;
        }

        .btn {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 6px;
            text-decoration: none;
            border: 1px solid #ccc;
            background: #fff;
            color: #222;
        }

        .btn:hover {
            background: #f7f7f7;
        }

        .btn-danger {
            border-color: #d9534f;
            color: #d9534f;
        }

        .btn-danger:hover {
            background: #fff5f5;
        }

        .btn-primary {
            border-color: #0d6efd;
            color: #0d6efd;
        }

        .btn-primary:hover {
            background: #f0f6ff;
        }

        .muted {
            color: #666;
            font-size: 12px;
        }

        .counter {
            margin: 12px 0 20px;
        }

        .counter-header {
            margin-bottom: 6px;
            font-size: 14px;
            color: #333;
        }

        .progress {
            width: 256px;
            height: 10px;
            background: #eee;
            border-radius: 6px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
        }
    </style>
    <script>
        function confirmAction(message, url) {
            if (confirm(message)) {
                window.location.href = url;
            }
            return false;
        }
    </script>
</head>

<body>
    <h1>Управление кэшем Airtable</h1>
    <?php if ($message) { ?><div class="message"><?php echo $message; ?></div><?php } ?>

    <div class="counter">
        <div class="counter-header">Запросы Airtable (<?php echo htmlspecialchars($yearMonth); ?>): <?php echo $requestsCount; ?> / <?php echo $monthlyLimit; ?></div>
        <div class="progress">
            <div class="progress-bar" style="width: <?php echo $percentage; ?>%; background: <?php echo $barColor; ?>"></div>
        </div>
    </div>

    <div class="actions">
        <form method="post" style="display:inline" onsubmit="return <?php echo $isLocked ? 'false' : 'confirm(\'Очистить кэш для всех источников и запустить прогрев?\')'; ?>">
            <input type="hidden" name="action" value="clearAll" />
            <button type="submit" class="btn btn-danger" <?php echo $isLocked ? 'disabled title="Прогрев уже выполняется"' : ''; ?>>Очистить всё</button>
        </form>
        <?php if (file_exists($globalLock)) { ?>
            <span class="badge">Выполняется прогрев…</span>
        <?php } ?>
    </div>

    <table>
        <thead>
            <tr>
                <th>Метка</th>
                <th>Статус кэша</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sources as $src) {
                $label = $src['label'];
                $tableName = $src['tableName'];
                $tableId = $src['tableId'];
                $base = airtableCacheBasename($tableId, $tableName);
                $exists = file_exists($base);
                $size = $exists ? filesize($base) : 0;
                $mtime = $exists ? date('Y-m-d H:i:s', filemtime($base)) : null;
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($label); ?></td>
                    <td>
                        <?php if ($exists) { ?>
                            Есть (<?php echo number_format($size / 1024, 1); ?> KB), обновлён: <?php echo htmlspecialchars($mtime); ?>
                        <?php } else { ?>
                            Нет
                        <?php } ?>
                    </td>
                    <td>
                        <form method="post" style="display:inline" onsubmit="return <?php echo $isLocked ? 'false' : 'confirm(\'Очистить кэш и запустить прогрев только для этого источника?\')'; ?>">
                            <input type="hidden" name="action" value="clearOne" />
                            <input type="hidden" name="tableId" value="<?php echo htmlspecialchars($tableId); ?>" />
                            <input type="hidden" name="tableName" value="<?php echo htmlspecialchars($tableName); ?>" />
                            <button type="submit" class="btn btn-danger" <?php echo $isLocked ? 'disabled title="Прогрев уже выполняется"' : ''; ?>>Очистить</button>
                        </form>
                    </td>
                </tr>
            <?php } ?>
            <tr>
                <td>Очистить остальные кэши (зачала/святые/братский лекционарий)</td>
                <td>—</td>
                <td>
                    <form method="post" style="display:inline" onsubmit="return confirm('Удалить все файлы Data/cache_*?')">
                        <input type="hidden" name="action" value="clearLegacy" />
                        <button type="submit" class="btn btn-danger">Очистить</button>
                    </form>
                </td>
            </tr>
        </tbody>
    </table>
</body>

</html>