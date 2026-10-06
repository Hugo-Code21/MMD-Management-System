<?php
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'bootstrap.php';
$user = require_login();

$counts = [
    'members' => (int) db()->query('SELECT COUNT(*) FROM members WHERE status = "Active"')->fetchColumn(),
    'projects' => (int) db()->query('SELECT COUNT(*) FROM projects WHERE status NOT IN ("Completed", "Archived")')->fetchColumn(),
    'events' => (int) db()->query('SELECT COUNT(*) FROM events WHERE event_date >= CURDATE()')->fetchColumn(),
    'equipment' => 0,
];
$upcomingEvents = db()->query(
    'SELECT event_name, event_date, start_time, location FROM events
     WHERE event_date >= CURDATE() ORDER BY event_date, start_time LIMIT 5'
)->fetchAll();
if (can_manage($user)) {
    $recentProjects = db()->query(
        'SELECT p.id, p.title, p.status, p.due_date, e.event_name
         FROM projects p LEFT JOIN events e ON e.id = p.event_id
         ORDER BY p.updated_at DESC LIMIT 5'
    )->fetchAll();
    $progressProjects = db()->query(
        'SELECT id, title, status FROM projects
         WHERE status != "Archived" ORDER BY updated_at DESC'
    )->fetchAll();
} else {
    $statement = db()->prepare(
        'SELECT p.id, p.title, p.status, p.due_date, e.event_name
         FROM projects p JOIN project_members pm ON pm.project_id = p.id
         JOIN users u ON u.member_id = pm.member_id
         LEFT JOIN events e ON e.id = p.event_id
         WHERE u.id = :user_id ORDER BY p.updated_at DESC LIMIT 5'
    );
    $statement->execute(['user_id' => $user['id']]);
    $recentProjects = $statement->fetchAll();
    $progressStatement = db()->prepare(
        'SELECT p.id, p.title, p.status FROM projects p
         JOIN project_members pm ON pm.project_id = p.id
         WHERE pm.member_id = :member_id AND p.status != "Archived"
         ORDER BY p.updated_at DESC'
    );
    $progressStatement->execute(['member_id' => $user['member_id']]);
    $progressProjects = $progressStatement->fetchAll();
}
$statusProgress = [
    'Planning' => 0,
    'Assigned' => 20,
    'In Progress' => 40,
    'Editing' => 60,
    'Review' => 80,
    'Completed' => 100,
];
$pipelineQuery = can_manage($user)
    ? db()->query('SELECT status, COUNT(*) AS total FROM projects GROUP BY status')
    : db()->prepare(
        'SELECT p.status, COUNT(DISTINCT p.id) AS total
         FROM projects p JOIN project_members pm ON pm.project_id = p.id
         WHERE pm.member_id = :member_id GROUP BY p.status'
    );
if ($pipelineQuery instanceof PDOStatement && !can_manage($user)) {
    $pipelineQuery->execute(['member_id' => $user['member_id']]);
}
$projectPipeline = array_fill_keys(
    ['Planning', 'Assigned', 'In Progress', 'Editing', 'Review', 'Completed', 'Archived'],
    0
);
foreach ($pipelineQuery->fetchAll() as $row) {
    if (array_key_exists($row['status'], $projectPipeline)) {
        $projectPipeline[$row['status']] = (int) $row['total'];
    }
}
$pipelineMaximum = max(1, ...array_values($projectPipeline));

render_header('Dashboard', $user);
show_flash();
echo '<div class="welcome-row"><div><p class="eyebrow">MMD MEDIA MANAGEMENT</p><h1>Welcome back, ' . e($user['full_name']) . '</h1><p class="muted">Here is what is happening across your team.</p></div>'
    . '<a class="button button-secondary" href="https://smkmahardhika.sch.id/link-foto/" target="_blank" rel="noopener noreferrer">Open Link Foto ↗</a></div>'
    . '<section class="stat-grid" aria-label="Overview">'
    . '<article class="stat-card"><span>Active members</span><strong>' . $counts['members'] . '</strong><small>Registered MMD roster</small></article>'
    . '<article class="stat-card"><span>Active projects</span><strong>' . $counts['projects'] . '</strong><small>Not completed or archived</small></article>'
    . '<article class="stat-card"><span>Upcoming events</span><strong>' . $counts['events'] . '</strong><small>Scheduled from today</small></article>'
    . '<article class="stat-card"><span>Equipment</span><strong>—</strong><small>Equipment module planned next</small></article></section>'
    . '<section class="panel pipeline-panel"><div class="section-heading"><div><p class="eyebrow">PROJECT OVERVIEW</p><h2>Project pipeline</h2></div><a href="' . e(url('projects.php')) . '">Explore projects</a></div>'
    . '<div class="pipeline-chart" role="img" aria-label="Project counts by status">';
foreach ($projectPipeline as $status => $total) {
    $barWidth = (int) round(($total / $pipelineMaximum) * 100);
    echo '<div class="pipeline-row"><span class="pipeline-label">' . e($status) . '</span>'
        . '<span class="pipeline-track"><span class="pipeline-bar" style="width:' . $barWidth . '%"></span></span>'
        . '<strong class="pipeline-total">' . $total . '</strong></div>';
}
echo '</div></section>'
    . '<section class="panel project-progress-panel"><div class="section-heading"><div><p class="eyebrow">WORK IN MOTION</p><h2>Project progress</h2><p class="muted">Progress is estimated from each project’s workflow stage.</p></div></div>';
if ($progressProjects === []) {
    echo '<p class="empty-state">No active projects to track yet.</p>';
} else {
    echo '<div class="project-progress-chart" role="list" aria-label="Progress of each project">';
    foreach ($progressProjects as $project) {
        $progress = $statusProgress[$project['status']] ?? 0;
        echo '<article class="project-progress-row" role="listitem">'
            . '<div class="project-progress-heading"><strong>' . e($project['title']) . '</strong>'
            . '<span class="project-progress-status">' . e($project['status']) . '</span></div>'
            . '<div class="project-progress-details"><span class="project-progress-track" role="progressbar"'
            . ' aria-label="Progress for ' . e($project['title']) . '" aria-valuemin="0" aria-valuemax="100"'
            . ' aria-valuenow="' . $progress . '"><span class="project-progress-bar" style="width:' . $progress . '%"></span></span>'
            . '<strong class="project-progress-percent">' . $progress . '%</strong></div></article>';
    }
    echo '</div>';
}
echo '</section>'
    . '<div class="content-grid"><section class="panel"><div class="section-heading"><div><p class="eyebrow">ON THE CALENDAR</p><h2>Upcoming events</h2></div><a href="' . e(url('events.php')) . '">View all</a></div>';
if ($upcomingEvents === []) {
    echo '<p class="empty-state">No upcoming events yet.</p>';
} else {
    echo '<div class="list-stack">';
    foreach ($upcomingEvents as $event) {
        echo '<article class="list-row"><span class="date-mark">' . e(date('d', strtotime($event['event_date']))) . '<small>' . e(strtoupper(date('M', strtotime($event['event_date'])))) . '</small></span>'
            . '<div><strong>' . e($event['event_name']) . '</strong><small>' . e($event['location']) . ' · ' . e(substr((string) $event['start_time'], 0, 5)) . '</small></div><span class="muted">' . e(format_date($event['event_date'])) . '</span></article>';
    }
    echo '</div>';
}
echo '</section><section class="panel"><div class="section-heading"><div><p class="eyebrow">WORK IN MOTION</p><h2>' . (can_manage($user) ? 'Recent projects' : 'Your assignments') . '</h2></div><a href="' . e(url('projects.php')) . '">View all</a></div>';
if ($recentProjects === []) {
    echo '<p class="empty-state">No projects to show yet.</p>';
} else {
    echo '<div class="list-stack">';
    foreach ($recentProjects as $project) {
        echo '<article class="project-row"><div><strong>' . e($project['title']) . '</strong><small>' . e($project['event_name'] ?? 'No linked event') . '</small></div>'
            . '<span class="status-badge">' . e($project['status']) . '</span></article>';
    }
    echo '</div>';
}
echo '</section></div>';
render_footer();
