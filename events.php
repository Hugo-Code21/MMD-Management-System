<?php
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'bootstrap.php';
$user = require_login();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_manager($user);
    require_csrf();
    $name = post_string('event_name');
    $date = post_string('event_date');
    $start = post_string('start_time');
    $end = post_string('end_time');
    $location = post_string('location');
    $description = post_string('description');
    if ($name === '' || $location === '' || !is_valid_date($date)
        || !is_valid_time($start) || !is_valid_time($end) || $end <= $start) {
        $error = 'Enter an event name, valid date, start and end times (end must be later), and location.';
    } else {
        $statement = db()->prepare(
            'INSERT INTO events (event_name, event_date, start_time, end_time, location, description, created_by)
             VALUES (:event_name, :event_date, :start_time, :end_time, :location, :description, :created_by)'
        );
        $statement->execute([
            'event_name' => $name, 'event_date' => $date, 'start_time' => $start, 'end_time' => $end,
            'location' => $location, 'description' => $description, 'created_by' => $user['id'],
        ]);
        flash('Event created.');
        redirect('events.php');
    }
}

$events = db()->query(
    'SELECT e.event_name, e.event_date, e.start_time, e.end_time, e.location, e.description, m.full_name AS creator
     FROM events e LEFT JOIN users u ON u.id = e.created_by
     LEFT JOIN members m ON m.id = u.member_id
     ORDER BY e.event_date DESC, e.start_time DESC'
)->fetchAll();
render_header('Events', $user);
show_flash();
echo '<div class="page-heading"><div><p class="eyebrow">PLAN THE MOMENTS THAT MATTER</p><h1>Events</h1><p class="muted">Keep the team aligned around school activities.</p></div></div>';
if (can_manage($user)) {
    echo '<section class="panel form-panel"><div class="section-heading"><div><p class="eyebrow">ADD TO THE CALENDAR</p><h2>Create event</h2></div></div>';
    if ($error !== '') {
        echo '<div class="notice notice-error">' . e($error) . '</div>';
    }
    echo '<form method="post" class="form-grid"><input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">'
        . '<label>Event name<input name="event_name" maxlength="160" required></label><label>Date<input type="date" name="event_date" required></label>'
        . '<label>Start time<input type="time" name="start_time" required></label><label>End time<input type="time" name="end_time" required></label>'
        . '<label>Location<input name="location" maxlength="160" required></label><label class="span-two">Description<textarea name="description" rows="3" maxlength="2000"></textarea></label>'
        . '<div class="span-two"><button class="button button-primary" type="submit">Save event</button></div></form></section>';
}
echo '<section class="panel table-panel"><div class="section-heading"><div><p class="eyebrow">SCHEDULE</p><h2>All events</h2></div></div>';
if ($events === []) {
    echo '<p class="empty-state">No events have been added yet.</p>';
} else {
    echo '<div class="table-wrap"><table><thead><tr><th>Event</th><th>Date & time</th><th>Location</th><th>Created by</th></tr></thead><tbody>';
    foreach ($events as $event) {
        echo '<tr><td class="strong-cell">' . e($event['event_name']) . '<small class="table-subtext">' . e($event['description']) . '</small></td>'
            . '<td>' . e(format_date($event['event_date'])) . '<small class="table-subtext">' . e(substr((string) $event['start_time'], 0, 5)) . '–' . e(substr((string) $event['end_time'], 0, 5)) . '</small></td>'
            . '<td>' . e($event['location']) . '</td><td>' . e($event['creator'] ?? '—') . '</td></tr>';
    }
    echo '</tbody></table></div>';
}
echo '</section>';
render_footer();
