<?php
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'bootstrap.php';
$user = require_login();
$members = db()->query(
    'SELECT m.full_name, m.position, m.team_role, m.class_name, m.access_role, m.status, u.id AS user_id
     FROM members m LEFT JOIN users u ON u.member_id = m.id
     ORDER BY CASE WHEN u.id IS NULL THEN 1 ELSE 0 END, m.full_name'
)->fetchAll();

render_header('Members', $user);
echo '<div class="page-heading"><div><p class="eyebrow">THE PEOPLE BEHIND THE WORK</p><h1>Members</h1><p class="muted">The current registered MMD roster.</p></div></div>'
    . '<section class="panel table-panel"><div class="table-wrap"><table><thead><tr><th>Name</th><th>Class</th><th>Team role</th><th>System access</th><th>Account</th></tr></thead><tbody>';
foreach ($members as $member) {
    echo '<tr><td class="strong-cell">' . e($member['full_name']) . '</td><td>' . e($member['class_name'] ?: '—') . '</td>'
        . '<td>' . e($member['team_role'] ?: $member['position']) . '</td><td>' . e(role_label($member['access_role'])) . '</td>'
        . '<td><span class="account-state ' . ($member['user_id'] ? 'is-ready' : 'is-pending') . '">' . ($member['user_id'] ? 'Registered' : 'Setup pending') . '</span></td></tr>';
}
echo '</tbody></table></div></section>';
render_footer();
