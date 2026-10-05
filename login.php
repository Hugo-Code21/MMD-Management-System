<?php
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'bootstrap.php';

if (current_user() !== null) {
    redirect('dashboard.php');
}

$error = '';
$name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name = post_string('full_name');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $statement = db()->prepare(
        'SELECT u.id, u.password_hash FROM users u
         JOIN members m ON m.id = u.member_id
         WHERE m.full_name = :name AND m.status = "Active"'
    );
    $statement->execute(['name' => $name]);
    $account = $statement->fetch();

    if ($account && password_verify($password, $account['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $account['id'];
        unset($_SESSION['csrf_token']);
        $updateLogin = db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $updateLogin->execute(['id' => $account['id']]);
        redirect('dashboard.php');
    }
    $error = 'The registered name or password is incorrect.';
}

render_header('Sign in');
echo '<main class="auth-wrap"><section class="auth-card">' . brand_link('login.php', 'auth-brand')
    . '<p class="eyebrow">MEDIA MAHARDHIKA DIVISION</p><h1>Welcome back</h1><p class="muted">Sign in with your registered name and personal password.</p>';
show_flash();
if ($error !== '') {
    echo '<div class="notice notice-error" role="alert">' . e($error) . '</div>';
}
echo '<form method="post" action="' . e(url('login.php')) . '" class="form-stack">'
    . '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">'
    . '<label>Registered name<input name="full_name" autocomplete="username" maxlength="120" value="' . e($name) . '" required></label>'
    . '<label>Password<input type="password" name="password" autocomplete="current-password" required></label>'
    . '<button class="button button-primary button-full" type="submit">Sign in</button></form>'
    . '<p class="auth-foot">First time here? <a href="' . e(url('register.php')) . '">Set up your account</a></p></section></main>';
render_footer();
