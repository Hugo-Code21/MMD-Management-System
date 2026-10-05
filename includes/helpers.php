<?php
declare(strict_types=1);

function app_config(): array
{
    global $config;
    return $config;
}

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $database = app_config()['database'];
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $database['host'],
        $database['port'],
        $database['name']
    );
    $pdo = new PDO($dsn, $database['user'], $database['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path): string
{
    $baseUrl = app_config()['base_url'];
    return $baseUrl . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function post_string(string $name, string $default = ''): string
{
    $value = $_POST[$name] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function is_valid_date(string $value): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    $errors = DateTime::getLastErrors();
    return $date !== false
        && $date->format('Y-m-d') === $value
        && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
}

function is_valid_time(string $value): bool
{
    if (!preg_match('/^\d{2}:\d{2}$/', $value)) {
        return false;
    }
    $time = DateTime::createFromFormat('!H:i', $value);
    $errors = DateTime::getLastErrors();
    return $time !== false
        && $time->format('H:i') === $value
        && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
}

function require_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(403);
        exit('Request could not be verified. Refresh the page and try again.');
    }
}

function current_user(): ?array
{
    static $checked = false;
    static $user = null;
    if ($checked) {
        return $user;
    }
    $checked = true;

    if (!isset($_SESSION['user_id']) || !is_int($_SESSION['user_id'])) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT u.id, u.member_id, m.full_name, m.position, m.access_role
         FROM users u JOIN members m ON m.id = u.member_id
         WHERE u.id = :id AND m.status = "Active"'
    );
    $statement->execute(['id' => $_SESSION['user_id']]);
    $user = $statement->fetch() ?: null;
    if ($user === null) {
        unset($_SESSION['user_id']);
    }
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        redirect('login.php');
    }
    return $user;
}

function can_manage(array $user): bool
{
    return in_array($user['access_role'], ['head_coordinator', 'coordinator', 'assistant_coordinator', 'super_admin'], true);
}

function require_manager(array $user): void
{
    if (!can_manage($user)) {
        http_response_code(403);
        render_header('Access denied', $user);
        echo '<section class="panel"><h1>Access denied</h1><p>Your account cannot manage this information.</p></section>';
        render_footer();
        exit;
    }
}

function role_label(string $role): string
{
    return match ($role) {
        'head_coordinator' => 'Head coordinator',
        'coordinator' => 'Coordinator',
        'assistant_coordinator' => 'Assistant coordinator',
        'super_admin' => 'Super admin',
        default => 'Member',
    };
}

function render_header(string $title, ?array $user = null): void
{
    $pageTitle = e($title);
    $photoUrl = 'https://smkmahardhika.sch.id/link-foto/';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="color-scheme" content="dark"><title>' . $pageTitle . ' · MMD</title>'
        . '<link rel="stylesheet" href="' . e(url('assets/css/app.css')) . '"></head><body>';
    if ($user !== null) {
        echo '<div class="app-shell"><aside class="sidebar"><a class="brand" href="' . e(url('dashboard.php')) . '"><span class="brand-mark">M</span><span>MMD <small>MEDIA MANAGEMENT</small></span></a>'
            . '<nav aria-label="Main navigation"><a href="' . e(url('dashboard.php')) . '">Dashboard</a>'
            . '<a href="' . e(url('members.php')) . '">Members</a><a href="' . e(url('events.php')) . '">Events</a>'
            . '<a href="' . e(url('projects.php')) . '">Projects</a>'
            . '<a href="' . e($photoUrl) . '" target="_blank" rel="noopener noreferrer">Link Foto ↗</a></nav>'
            . '<div class="sidebar-bottom"><span>' . e($user['full_name']) . '<small>' . e(role_label($user['access_role'])) . '</small></span>'
            . '<form action="' . e(url('logout.php')) . '" method="post"><input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '"><button class="nav-logout" type="submit">Log out</button></form></div></aside>'
            . '<main class="main-content"><header class="topbar"><span>Media Mahardhika Division</span><span class="user-chip">' . e($user['full_name']) . '</span></header><div class="page-content">';
    }
}

function render_footer(): void
{
    if (current_user() !== null) {
        echo '</div></main></div>';
    }
    echo '</body></html>';
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function show_flash(): void
{
    if (!isset($_SESSION['flash'])) {
        return;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    echo '<div class="notice notice-' . e($flash['type']) . '">' . e($flash['message']) . '</div>';
}

function format_date(?string $date): string
{
    if ($date === null || $date === '') {
        return 'Not set';
    }
    return date('j M Y', strtotime($date));
}
