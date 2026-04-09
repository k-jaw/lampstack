<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

const ISSUE_STATUSES = ['Open', 'In Progress', 'Resolved', 'Closed'];
const ISSUE_PRIORITIES = ['Low', 'Medium', 'High', 'Critical'];

/**
 * Fetch all issues ordered by most recent update first.
 *
 * @return array<int, array<string, mixed>>
 */
function fetch_issues(mysqli $db, ?string $statusFilter = null, ?string $assigneeFilter = null, ?string $priorityFilter = null): array
{
    $sql = 'SELECT id, title, description, status, priority, assigned_to, update_note, created_at, updated_at
            FROM issues';

    $conditions = [];
    $params = [];
    $types = '';

    if ($statusFilter !== null && $statusFilter !== '' && validate_status($statusFilter)) {
        $conditions[] = 'status = ?';
        $params[] = $statusFilter;
        $types .= 's';
    }

    if ($assigneeFilter !== null && $assigneeFilter !== '') {
        $conditions[] = 'assigned_to = ?';
        $params[] = $assigneeFilter;
        $types .= 's';
    }

    if ($priorityFilter !== null && $priorityFilter !== '' && validate_priority($priorityFilter)) {
        $conditions[] = 'priority = ?';
        $params[] = $priorityFilter;
        $types .= 's';
    }

    if (!empty($conditions)) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $sql .= ' ORDER BY updated_at DESC';

    $stmt = $db->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('Prepare failed: ' . $db->error);
    }

    if (!empty($params)) {
        if (!$stmt->bind_param($types, ...$params)) {
            throw new RuntimeException('bind_param failed: ' . $stmt->error);
        }
    }

    if (!$stmt->execute()) {
        throw new RuntimeException('Execute failed: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    if ($result === false) {
        throw new RuntimeException('Failed to load issues: ' . $stmt->error);
    }

    $issues = [];
    while ($row = $result->fetch_assoc()) {
        $issues[] = $row;
    }
    $result->free();
    $stmt->close();

    return $issues;
}

/**
 * Insert a new issue into the database.
 */
function create_issue(mysqli $db, string $title, string $description, string $priority, ?string $assignedTo, ?string $updateNote): void
{
    $sql = 'INSERT INTO issues (title, description, priority, assigned_to, update_note) VALUES (?, ?, ?, ?, ?)';
    $stmt = $db->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('Prepare failed: ' . $db->error);
    }

    $assigned = $assignedTo !== null && $assignedTo !== '' ? $assignedTo : null;
    $update = $updateNote !== null && trim($updateNote) !== '' ? $updateNote : null;
    if (!$stmt->bind_param('sssss', $title, $description, $priority, $assigned, $update)) {
        throw new RuntimeException('bind_param failed: ' . $stmt->error);
    }
    if (!$stmt->execute()) {
        throw new RuntimeException('Execute failed: ' . $stmt->error);
    }

    $issueId = (int) $db->insert_id;
    $stmt->close();

    if ($update !== null) {
        create_issue_update($db, $issueId, $update);
    }
}

/**
 * Update the status or assignment of an existing issue.
 */
function update_issue(mysqli $db, int $issueId, string $status, ?string $assignedTo, ?string $updateNote): void
{
    $assigned = $assignedTo !== null && $assignedTo !== '' ? $assignedTo : null;
    $update = $updateNote !== null && trim($updateNote) !== '' ? $updateNote : null;

    if ($update !== null) {
        $sql = 'UPDATE issues SET status = ?, assigned_to = ?, update_note = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?';
        $stmt = $db->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Prepare failed: ' . $db->error);
        }
        if (!$stmt->bind_param('sssi', $status, $assigned, $update, $issueId)) {
            throw new RuntimeException('bind_param failed: ' . $stmt->error);
        }
    } else {
        $sql = 'UPDATE issues SET status = ?, assigned_to = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?';
        $stmt = $db->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Prepare failed: ' . $db->error);
        }
        if (!$stmt->bind_param('ssi', $status, $assigned, $issueId)) {
            throw new RuntimeException('bind_param failed: ' . $stmt->error);
        }
    }

    if (!$stmt->execute()) {
        throw new RuntimeException('Execute failed: ' . $stmt->error);
    }
    $stmt->close();

    if ($update !== null) {
        create_issue_update($db, $issueId, $update);
    }
}

/**
 * Soft delete (close) an issue.
 */
function close_issue(mysqli $db, int $issueId): void
{
    $sql = "UPDATE issues SET status = 'Closed', updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    $stmt = $db->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('Prepare failed: ' . $db->error);
    }
    if (!$stmt->bind_param('i', $issueId)) {
        throw new RuntimeException('bind_param failed: ' . $stmt->error);
    }
    if (!$stmt->execute()) {
        throw new RuntimeException('Execute failed: ' . $stmt->error);
    }
    $stmt->close();
}

/**
 * Permanently delete an issue.
 */
function delete_issue(mysqli $db, int $issueId): void
{
    $sql = 'DELETE FROM issues WHERE id = ?';
    $stmt = $db->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('Prepare failed: ' . $db->error);
    }
    if (!$stmt->bind_param('i', $issueId)) {
        throw new RuntimeException('bind_param failed: ' . $stmt->error);
    }
    if (!$stmt->execute()) {
        throw new RuntimeException('Execute failed: ' . $stmt->error);
    }
    $stmt->close();
}

/**
 * Record a new update entry linked to an issue.
 */
function create_issue_update(mysqli $db, int $issueId, string $note): void
{
    $sql = 'INSERT INTO issue_updates (issue_id, note) VALUES (?, ?)';
    $stmt = $db->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('Prepare failed: ' . $db->error);
    }
    if (!$stmt->bind_param('is', $issueId, $note)) {
        throw new RuntimeException('bind_param failed: ' . $stmt->error);
    }
    if (!$stmt->execute()) {
        throw new RuntimeException('Execute failed: ' . $stmt->error);
    }
    $stmt->close();
}

/**
 * Fetch update history for a set of issues keyed by issue ID.
 *
 * @param array<int, int> $issueIds
 * @return array<int, array<int, array{note:string, created_at:string}>>
 */
function fetch_issue_updates(mysqli $db, array $issueIds): array
{
    if (count($issueIds) === 0) {
        return [];
    }

    $uniqueIds = array_values(array_unique(array_map('intval', $issueIds)));
    $placeholders = implode(',', array_fill(0, count($uniqueIds), '?'));
    $types = str_repeat('i', count($uniqueIds));

    $sql = "SELECT issue_id, note, created_at
            FROM issue_updates
            WHERE issue_id IN ($placeholders)
            ORDER BY created_at DESC";

    $stmt = $db->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('Prepare failed: ' . $db->error);
    }

    if (!$stmt->bind_param($types, ...$uniqueIds)) {
        throw new RuntimeException('bind_param failed: ' . $stmt->error);
    }

    if (!$stmt->execute()) {
        throw new RuntimeException('Execute failed: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    if ($result === false) {
        throw new RuntimeException('Failed to fetch updates: ' . $stmt->error);
    }

    $updates = [];
    while ($row = $result->fetch_assoc()) {
        $issueId = (int) $row['issue_id'];
        if (!isset($updates[$issueId])) {
            $updates[$issueId] = [];
        }
        $updates[$issueId][] = [
            'note' => (string) $row['note'],
            'created_at' => (string) $row['created_at'],
        ];
    }
    $result->free();
    $stmt->close();

    return $updates;
}

/**
 * Fetch paginated update history joined with issue details.
 *
 * @return array{updates: array<int, array<string, mixed>>, total: int}
 */
function fetch_update_history(mysqli $db, int $page, int $perPage): array
{
    $page = max(1, $page);
    $perPage = max(1, $perPage);
    $offset = ($page - 1) * $perPage;

    $countResult = $db->query('SELECT COUNT(*) AS total FROM issue_updates');
    if ($countResult === false) {
        throw new RuntimeException('Failed to count updates: ' . $db->error);
    }
    $row = $countResult->fetch_assoc();
    $total = (int) ($row['total'] ?? 0);
    $countResult->free();

    if ($total === 0) {
        return ['updates' => [], 'total' => 0];
    }

    $sql = 'SELECT u.id, u.issue_id, u.note, u.created_at, i.title, i.status, i.priority, i.assigned_to
            FROM issue_updates u
            INNER JOIN issues i ON i.id = u.issue_id
            ORDER BY u.created_at DESC
            LIMIT ? OFFSET ?';
    $stmt = $db->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('Prepare failed: ' . $db->error);
    }
    if (!$stmt->bind_param('ii', $perPage, $offset)) {
        throw new RuntimeException('bind_param failed: ' . $stmt->error);
    }
    if (!$stmt->execute()) {
        throw new RuntimeException('Execute failed: ' . $stmt->error);
    }
    $result = $stmt->get_result();
    if ($result === false) {
        throw new RuntimeException('Failed to fetch update history: ' . $stmt->error);
    }

    $updates = [];
    while ($updateRow = $result->fetch_assoc()) {
        $updates[] = $updateRow;
    }
    $result->free();
    $stmt->close();

    return ['updates' => $updates, 'total' => $total];
}

/**
 * Fetch distinct assignees for filter dropdowns.
 *
 * @return array<int, string>
 */
function fetch_assignees(mysqli $db): array
{
    $result = $db->query("SELECT DISTINCT assigned_to FROM issues WHERE assigned_to IS NOT NULL AND assigned_to <> '' ORDER BY assigned_to");
    if ($result === false) {
        throw new RuntimeException('Failed to load assignees: ' . $db->error);
    }

    $assignees = [];
    while ($row = $result->fetch_assoc()) {
        $assignees[] = (string) $row['assigned_to'];
    }
    $result->free();

    return $assignees;
}

/**
 * Validate a provided status against the known allowlist.
 */
function validate_status(string $status): bool
{
    return in_array($status, ISSUE_STATUSES, true);
}

/**
 * Validate a provided priority against the allowlist.
 */
function validate_priority(string $priority): bool
{
    return in_array($priority, ISSUE_PRIORITIES, true);
}
