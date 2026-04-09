<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/issues.php';

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = isset($_GET['per_page']) ? min(100, max(5, (int) $_GET['per_page'])) : 20;

try {
    $db = db_connect();
} catch (Throwable $e) {
    http_response_code(500);
    echo '<h1>Database connection error</h1>';
    echo '<p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    exit;
}

try {
    $history = fetch_update_history($db, $page, $perPage);
} catch (Throwable $e) {
    http_response_code(500);
    echo '<h1>Failed to load update history</h1>';
    echo '<p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    exit;
}

$updates = $history['updates'];
$totalUpdates = $history['total'];
$totalPages = $totalUpdates === 0 ? 1 : (int) ceil($totalUpdates / $perPage);

if ($totalUpdates > 0 && $page > $totalPages) {
    $targetPage = $totalPages;
    header('Location: ' . query_with_page($targetPage, $perPage));
    exit;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function query_with_page(int $page, int $perPage): string
{
    return '?page=' . $page . '&per_page=' . $perPage;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Issue Update History</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root {
            font-family: system-ui, sans-serif;
            color: #1f2933;
            background: #f4f7fb;
        }
        body {
            margin: 0 auto;
            padding: 2rem;
            max-width: 960px;
        }
        a {
            color: #2563eb;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        h1 {
            margin-bottom: 0.5rem;
        }
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
            padding: 1.75rem;
            margin-bottom: 1.5rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 0.75rem;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #eff6ff;
            position: sticky;
            top: 0;
            z-index: 1;
        }
        .history-table {
            max-height: 540px;
            overflow-y: auto;
        }
        .meta {
            color: #475569;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
        }
        .pagination button {
            border: none;
            background: #2563eb;
            color: #fff;
            padding: 0.6rem 1rem;
            border-radius: 6px;
            cursor: pointer;
        }
        .pagination button[disabled] {
            background: #cbd5f5;
            cursor: not-allowed;
        }
        .pagination-select {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        select {
            padding: 0.5rem;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <a class="back-link" href="index.php">&larr; Back to Issue Tracker</a>
    <h1>Issue Update History</h1>
    <p class="meta">
        Showing page <?= e((string) $page) ?> of <?= e((string) $totalPages) ?> (<?= e((string) $totalUpdates) ?> updates total).
    </p>

    <section class="card">
        <div class="history-table">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Issue</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Assigned To</th>
                        <th>Update</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($updates) === 0): ?>
                        <tr>
                            <td colspan="6">No updates recorded yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($updates as $update): ?>
                            <tr>
                                <td><?= e((new DateTime($update['created_at']))->format('Y-m-d H:i')) ?></td>
                                <td>#<?= e((string) $update['issue_id']) ?> &mdash; <?= e($update['title']) ?></td>
                                <td><?= e($update['status']) ?></td>
                                <td><?= e($update['priority']) ?></td>
                                <td><?= $update['assigned_to'] !== null ? e((string) $update['assigned_to']) : 'Unassigned' ?></td>
                                <td><?= nl2br(e($update['note'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="pagination">
            <form method="get" class="pagination-select">
                <input type="hidden" name="page" value="<?= e((string) min($page, $totalPages)) ?>">
                <label for="per_page">Per Page</label>
                <select id="per_page" name="per_page" onchange="this.form.submit()">
                    <?php foreach ([10, 20, 30, 50, 100] as $option): ?>
                        <option value="<?= $option ?>" <?= $perPage === $option ? 'selected' : '' ?>><?= $option ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <div>
                <a href="<?= $page > 1 ? query_with_page($page - 1, $perPage) : '#' ?>">
                    <button type="button" <?= $page <= 1 ? 'disabled' : '' ?>>Previous</button>
                </a>
                <a href="<?= $page < $totalPages ? query_with_page($page + 1, $perPage) : '#' ?>">
                    <button type="button" <?= $page >= $totalPages ? 'disabled' : '' ?>>Next</button>
                </a>
            </div>
        </div>
    </section>
</body>
</html>
