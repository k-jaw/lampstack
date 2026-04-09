<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/issues.php';

$statusFilter = isset($_GET['status']) ? trim((string) $_GET['status']) : '';
$assigneeFilter = isset($_GET['assigned_to']) ? trim((string) $_GET['assigned_to']) : '';
$priorityFilter = isset($_GET['priority']) ? trim((string) $_GET['priority']) : '';

try {
    $db = db_connect();
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Failed to connect to database: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}

try {
    $issues = fetch_issues(
        $db,
        $statusFilter !== '' ? $statusFilter : null,
        $assigneeFilter !== '' ? $assigneeFilter : null,
        $priorityFilter !== '' ? $priorityFilter : null
    );
    $issueIds = array_map(static fn (array $issue): int => (int) $issue['id'], $issues);
    $issueUpdates = fetch_issue_updates($db, $issueIds);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Failed to load issues: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}

$filenameParts = ['issues'];
if ($statusFilter !== '') {
    $filenameParts[] = strtolower(str_replace(' ', '-', $statusFilter));
}
if ($priorityFilter !== '') {
    $filenameParts[] = strtolower($priorityFilter);
}
$filenameBase = implode('-', $filenameParts) . '-' . date('Ymd');
$filename = $filenameBase . '.doc';

header('Content-Type: application/msword');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

function out(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Issue Export</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12pt; color: #1f2933; }
        h1 { margin-bottom: 0.5rem; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #cbd5e1; padding: 0.5rem; vertical-align: top; }
        th { background: #eff6ff; }
        .meta { margin-bottom: 1rem; font-size: 10pt; color: #475569; }
    </style>
</head>
<body>
    <h1>Community and Standards Team Issue Tracker</h1>
    <p class="meta">
        Generated on <?= out(date('Y-m-d H:i')) ?> with
        <?= count($issues) ?> issue<?= count($issues) === 1 ? '' : 's' ?>.
        <?php if ($statusFilter !== ''): ?>
            Status filter: <?= out($statusFilter) ?>.
        <?php endif; ?>
        <?php if ($assigneeFilter !== ''): ?>
            Assigned to: <?= out($assigneeFilter) ?>.
        <?php endif; ?>
        <?php if ($priorityFilter !== ''): ?>
            Priority filter: <?= out($priorityFilter) ?>.
        <?php endif; ?>
    </p>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Status</th>
                <th>Priority</th>
                <th>Assigned To</th>
                <th>Last Updated</th>
                <th>Description</th>
                <th>Update History</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($issues) === 0): ?>
                <tr>
                    <td colspan="8">No issues found for the selected filters.</td>
                </tr>
            <?php else: ?>
                    <?php foreach ($issues as $issue): ?>
                        <?php
                        $updatesForIssue = $issueUpdates[$issue['id']] ?? [];
                        if (count($updatesForIssue) === 0 && $issue['update_note'] !== null && trim((string) $issue['update_note']) !== '') {
                            $updatesForIssue = [[
                                'note' => (string) $issue['update_note'],
                                'created_at' => (string) $issue['updated_at'],
                            ]];
                        }
                        ?>
                        <tr>
                            <td><?= out((string) $issue['id']) ?></td>
                            <td><?= out($issue['title']) ?></td>
                            <td><?= out($issue['status']) ?></td>
                            <td><?= out($issue['priority']) ?></td>
                        <td><?= $issue['assigned_to'] !== null ? out((string) $issue['assigned_to']) : 'Unassigned' ?></td>
                        <td><?= out((new DateTime($issue['updated_at']))->format('Y-m-d H:i')) ?></td>
                        <td><?= nl2br(out($issue['description'])) ?></td>
                        <td>
                            <?php if (count($updatesForIssue) === 0): ?>
                                No updates recorded.
                            <?php else: ?>
                                <ul>
                                    <?php foreach ($updatesForIssue as $update): ?>
                                        <li><strong><?= out((new DateTime($update['created_at']))->format('Y-m-d H:i')) ?>:</strong> <?= nl2br(out($update['note'])) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
