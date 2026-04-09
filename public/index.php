<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/issues.php';

session_start();

$filterStatus = isset($_GET['status']) ? trim((string) $_GET['status']) : '';
$filterAssignee = isset($_GET['assigned_to']) ? trim((string) $_GET['assigned_to']) : '';
$filterPriority = isset($_GET['priority']) ? trim((string) $_GET['priority']) : '';

$messages = $_SESSION['flash'] ?? ['success' => null, 'error' => null];
unset($_SESSION['flash']);

try {
    $db = db_connect();
} catch (Throwable $e) {
    http_response_code(500);
    echo '<h1>Database connection error</h1>';
    echo '<p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    exit;
}

// Handle POST actions.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $flash = ['success' => null, 'error' => null];

    try {
        if ($action === 'create') {
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $priority = $_POST['priority'] ?? '';
            $assignedTo = trim($_POST['assigned_to'] ?? '');
            $updateNote = trim($_POST['update_note'] ?? '');

            if ($title === '' || $description === '') {
                throw new InvalidArgumentException('Title and description are required.');
            }
            if (!validate_priority($priority)) {
                throw new InvalidArgumentException('Invalid priority selected.');
            }

            create_issue($db, $title, $description, $priority, $assignedTo, $updateNote);
            $flash['success'] = 'Issue created successfully.';
        } elseif ($action === 'update') {
            $issueId = isset($_POST['issue_id']) ? (int) $_POST['issue_id'] : 0;
            $status = $_POST['status'] ?? '';
            $assignedTo = trim($_POST['assigned_to'] ?? '');
            $updateNote = trim($_POST['update_note'] ?? '');

            if ($issueId <= 0) {
                throw new InvalidArgumentException('Invalid issue identifier.');
            }
            if (!validate_status($status)) {
                throw new InvalidArgumentException('Invalid status selected.');
            }

            update_issue($db, $issueId, $status, $assignedTo, $updateNote);
            $flash['success'] = 'Issue updated successfully.';
        } elseif ($action === 'close') {
            $issueId = isset($_POST['issue_id']) ? (int) $_POST['issue_id'] : 0;
            if ($issueId <= 0) {
                throw new InvalidArgumentException('Invalid issue identifier.');
            }
            close_issue($db, $issueId);
            $flash['success'] = 'Issue closed successfully.';
        } elseif ($action === 'delete') {
            $issueId = isset($_POST['issue_id']) ? (int) $_POST['issue_id'] : 0;
            if ($issueId <= 0) {
                throw new InvalidArgumentException('Invalid issue identifier.');
            }
            delete_issue($db, $issueId);
            $flash['success'] = 'Issue deleted permanently.';
        } else {
            throw new InvalidArgumentException('Unknown action requested.');
        }
    } catch (Throwable $e) {
        $flash['error'] = $e->getMessage();
    }

    $_SESSION['flash'] = $flash;
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Retrieve issues for display.
try {
    $issues = fetch_issues(
        $db,
        $filterStatus !== '' ? $filterStatus : null,
        $filterAssignee !== '' ? $filterAssignee : null,
        $filterPriority !== '' ? $filterPriority : null
    );
    $assignees = fetch_assignees($db);
    $issueIds = array_map(static fn (array $issue): int => (int) $issue['id'], $issues);
    $issueUpdates = fetch_issue_updates($db, $issueIds);
} catch (Throwable $e) {
    $messages['error'] = 'Failed to load issues: ' . $e->getMessage();
    $issues = [];
    $assignees = [];
    $issueUpdates = [];
}

$totalIssues = count($issues);
$statusSummary = [
    'Open' => 0,
    'In Progress' => 0,
    'Resolved' => 0,
    'Closed' => 0,
];
foreach ($issues as $issue) {
    $status = $issue['status'] ?? null;
    if (isset($statusSummary[$status])) {
        $statusSummary[$status]++;
    }
}
$openCount = $statusSummary['Open'] ?? 0;
$inProgressCount = $statusSummary['In Progress'] ?? 0;
$resolvedCount = $statusSummary['Resolved'] ?? 0;

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function status_class(string $status): string
{
    return 'status-' . strtolower(str_replace(' ', '-', $status));
}

function selected_attr(string $value, string $current): string
{
    return $value === $current ? 'selected' : '';
}

function build_report_url(string $format, string $status, string $assignee, string $priority): string
{
    $params = ['format' => $format];
    if ($status !== '') {
        $params['status'] = $status;
    }
    if ($assignee !== '') {
        $params['assigned_to'] = $assignee;
    }
    if ($priority !== '') {
        $params['priority'] = $priority;
    }

    return 'report.php?' . http_build_query($params);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Community and Standards Team Issue Tracker</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root {
            font-family: "Helvetica Neue", Arial, sans-serif;
            color: #14213d;
            background-color: #eef1f6;
        }
        *, *::before, *::after {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            min-height: 100vh;
            background: linear-gradient(180deg, #0b1f4b 0%, #162b62 45%, #f5f7fb 45%);
            color: inherit;
        }
        a {
            color: inherit;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        button {
            cursor: pointer;
            border: none;
            border-radius: 999px;
            font-weight: 600;
            padding: 0.75rem 1.4rem;
            font-size: 0.95rem;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        button:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(10, 47, 110, 0.25);
        }
        .hero {
            background: linear-gradient(120deg, #0b1f4b 0%, #1446a0 45%, #1d7cf2 100%);
            color: #fff;
            padding: 3.5rem 2rem 6rem;
        }
        .hero__inner {
            max-width: 1100px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }
        .hero__heading {
            max-width: 720px;
        }
        .hero__eyebrow {
            text-transform: uppercase;
            letter-spacing: 0.3em;
            font-size: 0.75rem;
            opacity: 0.75;
            display: inline-block;
            margin-bottom: 0.75rem;
        }
        .hero__title {
            margin: 0;
            font-size: 2.75rem;
            font-weight: 600;
            line-height: 1.2;
        }
        .hero__subtitle {
            margin: 1rem 0 0;
            font-size: 1.1rem;
            line-height: 1.6;
            opacity: 0.92;
        }
        .hero__stats {
            display: flex;
            flex-wrap: wrap;
            gap: 1.2rem;
        }
        .stat {
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 14px;
            padding: 1rem 1.5rem;
            min-width: 160px;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.08);
        }
        .stat__label {
            display: block;
            font-size: 0.75rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            opacity: 0.7;
        }
        .stat__value {
            font-size: 1.75rem;
            font-weight: 600;
            margin-top: 0.3rem;
        }
        .hero__actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .hero__cta {
            background: #f26b38;
            color: #fff;
            border-radius: 999px;
            padding: 0.85rem 1.6rem;
            font-weight: 600;
            box-shadow: 0 12px 30px rgba(242, 107, 56, 0.35);
        }
        .hero__cta:hover {
            box-shadow: 0 16px 40px rgba(242, 107, 56, 0.45);
        }
        .hero__ghost {
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 999px;
            padding: 0.85rem 1.6rem;
            font-weight: 600;
            opacity: 0.9;
        }
        .hero__cta,
        .hero__ghost {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .hero__ghost:hover {
            background: rgba(255, 255, 255, 0.12);
        }
        main.content-wrap {
            max-width: 1100px;
            margin: -4rem auto 4rem;
            padding: 0 2rem;
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }
        .alert-stack {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .alert {
            padding: 0.85rem 1.15rem;
            border-radius: 12px;
            font-weight: 600;
        }
        .alert--success {
            background: #e8f8f0;
            color: #1e6f43;
            border: 1px solid #b0e6c6;
        }
        .alert--error {
            background: #fdeaea;
            color: #b4231f;
            border: 1px solid #f2b8b5;
        }
        .card {
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 20px 45px rgba(13, 32, 91, 0.12);
            padding: 2rem;
            border: 1px solid #e1e6f0;
        }
        .card h2 {
            margin-top: 0;
            font-size: 1.4rem;
            font-weight: 600;
            color: #182c4f;
        }
        .card--form p {
            margin-top: 0;
            margin-bottom: 1.5rem;
            color: #4a556d;
        }
        label {
            display: block;
            margin-bottom: 0.45rem;
            font-weight: 600;
            color: #1f2f4b;
        }
        input[type="text"],
        select,
        textarea {
            width: 100%;
            padding: 0.85rem;
            border: 1px solid #cbd4e6;
            border-radius: 12px;
            font-size: 1rem;
            background: #f7f9fc;
            color: inherit;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        input[type="text"]:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #3870ff;
            box-shadow: 0 0 0 3px rgba(56, 112, 255, 0.12);
            background: #fff;
        }
        textarea {
            min-height: 120px;
            resize: vertical;
        }
        button.primary {
            background: #f26b38;
            color: #fff;
        }
        button.secondary {
            background: #1c5de7;
            color: #fff;
        }
        button.danger {
            background: #d62828;
            color: #fff;
        }
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 1.25rem;
            align-items: flex-end;
            background: #f7f9fd;
            padding: 1.25rem;
            border-radius: 14px;
            border: 1px solid #dde4f3;
        }
        .filter-form .filter-control {
            min-width: 200px;
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }
        .filter-form .filter-actions {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            flex-wrap: wrap;
            margin-left: auto;
        }
        .filter-form .export-link,
        .filter-form .reset-link {
            font-weight: 600;
            color: #1c5de7;
            text-decoration: none;
            padding: 0.45rem 0.75rem;
            border-radius: 999px;
            border: 1px solid transparent;
        }
        .filter-form .export-link:hover,
        .filter-form .reset-link:hover {
            border-color: rgba(28, 93, 231, 0.25);
        }
        .issue-grid {
            display: grid;
            gap: 1.5rem;
        }
        @media (min-width: 900px) {
            .issue-grid {
                grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
            }
        }
        .issue-card {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            border-radius: 18px;
            border: 1px solid #e0e6f5;
            padding: 1.75rem;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        }
        .issue-card__header {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        @media (min-width: 640px) {
            .issue-card__header {
                flex-direction: row;
                justify-content: space-between;
                align-items: flex-start;
            }
        }
        .issue-card h3 {
            margin: 0;
            font-size: 1.25rem;
            color: #1b2940;
        }
        .issue-description {
            margin: 0.25rem 0 0;
            color: #4b5670;
            line-height: 1.55;
        }
        .issue-badges {
            display: flex;
            gap: 0.6rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .status-pill,
        .priority-pill {
            padding: 0.35rem 0.8rem;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .status-open { background: #e0f2fe; color: #0b5ed7; }
        .status-in-progress { background: #fff2cc; color: #9a6b00; }
        .status-resolved { background: #dcfce7; color: #166534; }
        .status-closed { background: #e2e8f0; color: #475569; }
        .priority-low { background: #cbf4f0; color: #0f766e; }
        .priority-medium { background: #eef2ff; color: #3730a3; }
        .priority-high { background: #fee2e2; color: #b91c1c; }
        .priority-critical { background: #ffe4e6; color: #be123c; }
        .issue-meta {
            display: flex;
            gap: 2rem;
            margin: 0;
            flex-wrap: wrap;
        }
        .issue-meta div {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .issue-meta dt {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #6b7280;
        }
        .issue-meta dd {
            margin: 0;
            font-weight: 600;
            color: #1b2940;
        }
        .issue-updates {
            background: #f5f9ff;
            border: 1px solid #d9e4ff;
            border-radius: 14px;
            padding: 1rem 1.25rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .issue-updates ul {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
        }
        .issue-updates li {
            color: #27334f;
            line-height: 1.5;
        }
        .issue-update-date {
            font-weight: 600;
            color: #1c5de7;
            margin-right: 0.4rem;
        }
        .issue-update-empty {
            margin: 0;
            color: #4b5670;
            font-style: italic;
        }
        .issue-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 1.25rem;
            align-items: flex-start;
            justify-content: space-between;
        }
        .issue-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 0.9rem;
            flex: 1 1 60%;
            min-width: 280px;
        }
        .issue-form .control-group {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }
        .issue-form textarea {
            min-height: 110px;
            grid-column: 1 / -1;
        }
.issue-form__actions {
            grid-column: 1 / -1;
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .issue-form__primary {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        .issue-form__primary button,
        .delete-form button {
            border-radius: 12px;
        }
        .button-ghost {
            background: transparent;
            color: #1c5de7;
            border: 1px solid rgba(28, 93, 231, 0.35);
        }
        .button-ghost:hover {
            background: rgba(28, 93, 231, 0.08);
        }
        .delete-form {
            margin-left: auto;
            align-self: flex-start;
        }
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }
        @media (max-width: 768px) {
            .hero {
                padding: 3rem 1.5rem 5rem;
            }
            main.content-wrap {
                padding: 0 1.5rem;
                margin: -3rem auto 3rem;
            }
            .issue-form {
                grid-template-columns: 1fr;
            }
            .issue-actions {
                flex-direction: column;
            }
            .issue-form__actions {
                flex-direction: column;
                align-items: stretch;
            }
            .issue-form__primary,
            .issue-form__primary button,
            .delete-form {
                width: 100%;
            }
            .delete-form {
                margin-left: 0;
            }
            .hero__title {
                font-size: 2.2rem;
            }
        }
    </style>
</head>
<body>
    <header class="hero">
        <div class="hero__inner">
            <div class="hero__heading">
                <span class="hero__eyebrow">Community &amp; Standards</span>
                <h1 class="hero__title">Issue Operations Hub</h1>
                <p class="hero__subtitle">Monitor escalations, prioritize fixes, and keep stakeholders aligned with a workspace built for the Community and Standards team.</p>
            </div>
            <div class="hero__stats">
                <div class="stat">
                    <span class="stat__label">Total Issues</span>
                    <span class="stat__value"><?= e((string) $totalIssues) ?></span>
                </div>
                <div class="stat">
                    <span class="stat__label">Open</span>
                    <span class="stat__value"><?= e((string) $openCount) ?></span>
                </div>
                <div class="stat">
                    <span class="stat__label">In Progress</span>
                    <span class="stat__value"><?= e((string) $inProgressCount) ?></span>
                </div>
                <div class="stat">
                    <span class="stat__label">Resolved YTD</span>
                    <span class="stat__value"><?= e((string) $resolvedCount) ?></span>
                </div>
            </div>
            <div class="hero__actions">
                <a class="hero__cta" href="#new-issue">New Issue</a>
                <a class="hero__ghost" href="updates.php">View Update History</a>
            </div>
        </div>
    </header>

    <main class="content-wrap">
        <?php if ($messages['success'] !== null || $messages['error'] !== null): ?>
            <div class="alert-stack">
                <?php if ($messages['success'] !== null): ?>
                    <div class="alert alert--success"><?= e($messages['success']) ?></div>
                <?php endif; ?>
                <?php if ($messages['error'] !== null): ?>
                    <div class="alert alert--error"><?= e($messages['error']) ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <section class="card card--form" id="new-issue">
            <h2>Create New Issue</h2>
            <p>Capture the context, priority, and ownership detail so the right people can take action quickly.</p>
            <form method="post">
            <input type="hidden" name="action" value="create">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" required maxlength="255" placeholder="Short summary of the issue">

            <label for="description">Description</label>
            <textarea id="description" name="description" required placeholder="Describe the bug, request, or task."></textarea>

            <label for="priority">Priority</label>
            <select id="priority" name="priority" required>
                <?php foreach (ISSUE_PRIORITIES as $priority): ?>
                    <option value="<?= e($priority) ?>"><?= e($priority) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="update_note">Update (optional)</label>
            <textarea id="update_note" name="update_note" placeholder="Share the latest context or progress."></textarea>

            <label for="assigned_to">Assigned To (optional)</label>
            <input type="text" id="assigned_to" name="assigned_to" placeholder="Name, email, or team">

            <button type="submit" class="primary">Create Issue</button>
            </form>
        </section>

        <section class="card issue-list">
        <h2>Issue List</h2>
        <form method="get" class="filter-form">
            <div class="filter-control">
                <label class="control-label" for="filter-status">Status</label>
                <select id="filter-status" name="status">
                    <option value="">All Statuses</option>
                    <?php foreach (ISSUE_STATUSES as $status): ?>
                        <option value="<?= e($status) ?>" <?= selected_attr($status, $filterStatus) ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-control">
                <label class="control-label" for="filter-assigned">Assigned To</label>
                <select id="filter-assigned" name="assigned_to">
                    <option value="">All Assignees</option>
                    <?php foreach ($assignees as $assignee): ?>
                        <option value="<?= e($assignee) ?>" <?= selected_attr($assignee, $filterAssignee) ?>><?= e($assignee) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-control">
                <label class="control-label" for="filter-priority">Priority</label>
                <select id="filter-priority" name="priority">
                    <option value="">All Priorities</option>
                    <?php foreach (ISSUE_PRIORITIES as $priority): ?>
                        <option value="<?= e($priority) ?>" <?= selected_attr($priority, $filterPriority) ?>><?= e($priority) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit" class="secondary">Apply Filters</button>
                <a class="export-link" href="<?= e(build_report_url('doc', $filterStatus, $filterAssignee, $filterPriority)) ?>">Export Word</a>
                <?php if ($filterStatus !== '' || $filterAssignee !== '' || $filterPriority !== ''): ?>
                    <a class="reset-link" href="<?= e($_SERVER['PHP_SELF']) ?>">Clear</a>
                <?php endif; ?>
                <a class="reset-link" href="updates.php">View Update History</a>
            </div>
        </form>
        <?php if (count($issues) === 0): ?>
            <p>No issues yet. Use the form above to create one.</p>
        <?php else: ?>
            <div class="issue-grid">
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
                    <article class="issue-card">
                        <header class="issue-card__header">
                            <div>
                                <h3><?= e($issue['title']) ?></h3>
                                <p class="issue-description"><?= nl2br(e($issue['description'])) ?></p>
                            </div>
                            <div class="issue-badges">
                                <span class="status-pill <?= e(status_class($issue['status'])) ?>"><?= e($issue['status']) ?></span>
                                <span class="priority-pill priority-<?= e(strtolower($issue['priority'])) ?>"><?= e($issue['priority']) ?></span>
                            </div>
                        </header>
                        <dl class="issue-meta">
                            <div>
                                <dt>Assigned</dt>
                                <dd><?= $issue['assigned_to'] !== null && $issue['assigned_to'] !== '' ? e($issue['assigned_to']) : '<em>Unassigned</em>' ?></dd>
                            </div>
                            <div>
                                <dt>Last Updated</dt>
                                <dd><?= e((new DateTime($issue['updated_at']))->format('Y-m-d H:i')) ?></dd>
                            </div>
                        </dl>
                        <div class="issue-updates">
                            <strong>Update History</strong>
                            <?php if (count($updatesForIssue) === 0): ?>
                                <p class="issue-update-empty">No updates yet.</p>
                            <?php else: ?>
                                <ul>
                                    <?php foreach ($updatesForIssue as $update): ?>
                                        <li>
                                            <span class="issue-update-date"><?= e((new DateTime($update['created_at']))->format('Y-m-d H:i')) ?>:</span>
                                            <span><?= nl2br(e($update['note'])) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                        <footer class="issue-actions">
                            <form method="post" class="issue-form">
                                <input type="hidden" name="issue_id" value="<?= e((string) $issue['id']) ?>">
                                <div class="control-group">
                                    <label class="control-label" for="status-<?= e((string) $issue['id']) ?>">Status</label>
                                    <select id="status-<?= e((string) $issue['id']) ?>" name="status" required>
                                        <?php foreach (ISSUE_STATUSES as $status): ?>
                                            <option value="<?= e($status) ?>" <?= $status === $issue['status'] ? 'selected' : '' ?>><?= e($status) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="control-group">
                                    <label class="control-label" for="assigned-<?= e((string) $issue['id']) ?>">Assigned To</label>
                                    <input id="assigned-<?= e((string) $issue['id']) ?>" type="text" name="assigned_to" value="<?= e((string) $issue['assigned_to']) ?>" placeholder="Name, email, or team">
                                </div>
                                <label class="sr-only" for="update-<?= e((string) $issue['id']) ?>">Update</label>
                                <textarea id="update-<?= e((string) $issue['id']) ?>" name="update_note" rows="2" placeholder="Add a quick update..."></textarea>
                                <div class="issue-form__actions">
                                    <div class="issue-form__primary">
                                        <button type="submit" name="action" value="update" class="secondary">Save</button>
                                        <?php if ($issue['status'] !== 'Closed'): ?>
                                            <button type="submit" name="action" value="close" class="button-ghost" onclick="return confirm('Close this issue?');">Close</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </form>
                            <form class="delete-form" method="post" onsubmit="return confirm('Delete this issue permanently?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="issue_id" value="<?= e((string) $issue['id']) ?>">
                                <button type="submit" class="danger">Delete</button>
                            </form>
                        </footer>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    </main>
</body>
</html>
