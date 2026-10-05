<?php
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'bootstrap.php';
$user = require_login();
$error = '';
$manager = can_manage($user);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_manager($user);
    require_csrf();
    $allowedStatuses = ['Planning', 'Assigned', 'In Progress', 'Editing', 'Review', 'Completed', 'Archived'];
    $action = post_string('action', 'create');
    if ($action === 'update_status') {
        $projectId = post_string('project_id');
        $status = post_string('status');
        if (!ctype_digit($projectId) || !in_array($status, $allowedStatuses, true)) {
            $error = 'Choose a valid project status.';
        } else {
            $statement = db()->prepare('UPDATE projects SET status = :status WHERE id = :id');
            $statement->execute(['status' => $status, 'id' => $projectId]);
            if ($statement->rowCount() === 0) {
                $exists = db()->prepare('SELECT 1 FROM projects WHERE id = :id');
                $exists->execute(['id' => $projectId]);
                if (!$exists->fetchColumn()) {
                    $error = 'That project could not be found.';
                }
            }
            if ($error === '') {
                flash('Project status updated.');
                redirect('projects.php');
            }
        }
    } elseif ($action === 'create') {
        $title = post_string('title');
        $eventId = post_string('event_id');
        $description = post_string('description');
        $status = post_string('status', 'Planning');
        $dueDate = post_string('due_date');
        $validDueDate = $dueDate === '' || is_valid_date($dueDate);
        $validEvent = $eventId === '';
        if (!$validEvent && ctype_digit($eventId)) {
            $eventCheck = db()->prepare('SELECT 1 FROM events WHERE id = :id');
            $eventCheck->execute(['id' => $eventId]);
            $validEvent = (bool) $eventCheck->fetchColumn();
        }
        $memberIds = $_POST['member_ids'] ?? [];
        $validMembers = is_array($memberIds);
        if ($validMembers) {
            foreach ($memberIds as $memberId) {
                if (!is_string($memberId) || !ctype_digit($memberId)) {
                    $validMembers = false;
                    break;
                }
            }
        }
        if ($validMembers && $memberIds !== []) {
            $uniqueMemberIds = array_values(array_unique($memberIds));
            $placeholders = implode(',', array_fill(0, count($uniqueMemberIds), '?'));
            $memberCheck = db()->prepare(
                'SELECT COUNT(*) FROM members m JOIN users u ON u.member_id = m.id
                 WHERE m.status = "Active" AND m.id IN (' . $placeholders . ')'
            );
            $memberCheck->execute($uniqueMemberIds);
            $validMembers = (int) $memberCheck->fetchColumn() === count($uniqueMemberIds);
        }
        if ($title === '' || !in_array($status, $allowedStatuses, true) || !$validDueDate || !$validEvent || !$validMembers) {
            $error = 'Check the project title, status, linked event, due date, and assignments.';
        } else {
            $pdo = db();
            $pdo->beginTransaction();
            $statement = $pdo->prepare(
                'INSERT INTO projects (event_id, title, description, status, due_date, created_by)
                 VALUES (:event_id, :title, :description, :status, :due_date, :created_by)'
            );
            $statement->execute([
                'event_id' => $eventId === '' ? null : (int) $eventId,
                'title' => $title,
                'description' => $description,
                'status' => $status,
                'due_date' => $dueDate === '' ? null : $dueDate,
                'created_by' => $user['id'],
            ]);
            $projectId = (int) $pdo->lastInsertId();
            if ($memberIds !== []) {
                $assignment = $pdo->prepare(
                    'INSERT IGNORE INTO project_members (project_id, member_id, assigned_role)
                     SELECT :project_id, m.id, :assigned_role FROM members m
                     JOIN users u ON u.member_id = m.id
                     WHERE m.id = :member_id AND m.status = "Active"'
                );
                foreach (array_unique($memberIds) as $memberId) {
                    $assignment->execute([
                        'project_id' => $projectId,
                        'assigned_role' => 'Team member',
                        'member_id' => (int) $memberId,
                    ]);
                }
            }
            $pdo->commit();
            flash('Project created.');
            redirect('projects.php');
        }
    } else {
        $error = 'Choose a valid project action.';
    }
}

$events = db()->query('SELECT id, event_name FROM events ORDER BY event_date DESC')->fetchAll();
$members = $manager ? db()->query(
    'SELECT m.id, m.full_name FROM members m JOIN users u ON u.member_id = m.id
     WHERE m.status = "Active" ORDER BY m.full_name'
)->fetchAll() : [];
if ($manager) {
    $projects = db()->query(
        'SELECT p.id, p.title, p.description, p.status, p.due_date, e.event_name,
         GROUP_CONCAT(m.full_name ORDER BY m.full_name SEPARATOR ", ") AS assigned_members
         FROM projects p LEFT JOIN events e ON e.id = p.event_id
         LEFT JOIN project_members pm ON pm.project_id = p.id
         LEFT JOIN members m ON m.id = pm.member_id
         GROUP BY p.id, p.title, p.description, p.status, p.due_date, e.event_name
         ORDER BY p.updated_at DESC'
    )->fetchAll();
} else {
    $statement = db()->prepare(
        'SELECT p.id, p.title, p.description, p.status, p.due_date, e.event_name,
         GROUP_CONCAT(m.full_name ORDER BY m.full_name SEPARATOR ", ") AS assigned_members
         FROM projects p JOIN project_members pm ON pm.project_id = p.id
         JOIN users u ON u.member_id = pm.member_id
         LEFT JOIN events e ON e.id = p.event_id
         LEFT JOIN members m ON m.id = pm.member_id
         WHERE u.id = :user_id
         GROUP BY p.id, p.title, p.description, p.status, p.due_date, e.event_name
         ORDER BY p.updated_at DESC'
    );
    $statement->execute(['user_id' => $user['id']]);
    $projects = $statement->fetchAll();
}

render_header('Projects', $user);
show_flash();
echo '<div class="page-heading"><div><p class="eyebrow">FROM ASSIGNMENT TO ARCHIVE</p><h1>Projects</h1><p class="muted">Coordinate documentation work and follow its progress.</p></div></div>';
if ($manager) {
    echo '<section class="panel form-panel"><div class="section-heading"><div><p class="eyebrow">START SOMETHING NEW</p><h2>Create project</h2></div></div>';
    if ($error !== '') {
        echo '<div class="notice notice-error">' . e($error) . '</div>';
    }
    echo '<form method="post" class="form-grid"><input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '"><input type="hidden" name="action" value="create">'
        . '<label>Project name<input name="title" maxlength="160" required></label><label>Related event<select name="event_id"><option value="">No linked event</option>';
    foreach ($events as $event) {
        echo '<option value="' . (int) $event['id'] . '">' . e($event['event_name']) . '</option>';
    }
    echo '</select></label><label>Status<select name="status">';
    foreach (['Planning', 'Assigned', 'In Progress', 'Editing', 'Review', 'Completed', 'Archived'] as $status) {
        echo '<option value="' . e($status) . '">' . e($status) . '</option>';
    }
    echo '</select></label><label>Due date<input type="date" name="due_date"></label>'
        . '<label class="span-two">Assign registered members<select name="member_ids[]" multiple size="5">';
    foreach ($members as $member) {
        echo '<option value="' . (int) $member['id'] . '">' . e($member['full_name']) . '</option>';
    }
    echo '</select><small>Use Ctrl or Cmd to select more than one person.</small></label>'
        . '<label class="span-two">Description<textarea name="description" rows="3" maxlength="3000"></textarea></label>'
        . '<div class="span-two"><button class="button button-primary" type="submit">Create project</button></div></form></section>';
}
echo '<section class="panel table-panel"><div class="section-heading"><div><p class="eyebrow">PROJECT LIFECYCLE</p><h2>' . ($manager ? 'All projects' : 'Your assignments') . '</h2></div></div>';
if ($projects === []) {
    echo '<p class="empty-state">No projects to show yet.</p>';
} else {
    echo '<div class="table-wrap"><table><thead><tr><th>Project</th><th>Event</th><th>Team</th><th>Status</th><th>Due</th></tr></thead><tbody>';
    foreach ($projects as $project) {
        $statusCell = '<span class="status-badge">' . e($project['status']) . '</span>';
        if ($manager) {
            $statusCell = '<form method="post" class="status-form"><input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">'
                . '<input type="hidden" name="action" value="update_status"><input type="hidden" name="project_id" value="' . (int) $project['id'] . '">'
                . '<select name="status" aria-label="Status for ' . e($project['title']) . '">';
            foreach (['Planning', 'Assigned', 'In Progress', 'Editing', 'Review', 'Completed', 'Archived'] as $statusOption) {
                $selected = $statusOption === $project['status'] ? ' selected' : '';
                $statusCell .= '<option value="' . e($statusOption) . '"' . $selected . '>' . e($statusOption) . '</option>';
            }
            $statusCell .= '</select><button type="submit" class="button button-secondary">Update</button></form>';
        }
        echo '<tr><td class="strong-cell">' . e($project['title']) . '<small class="table-subtext">' . e($project['description']) . '</small></td>'
            . '<td>' . e($project['event_name'] ?? '—') . '</td><td>' . e($project['assigned_members'] ?? '—') . '</td>'
            . '<td>' . $statusCell . '</td><td>' . e(format_date($project['due_date'])) . '</td></tr>';
    }
    echo '</tbody></table></div>';
}
echo '</section>';
render_footer();
