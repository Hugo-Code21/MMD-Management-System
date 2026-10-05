<?php
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'bootstrap.php';

if (current_user() !== null) {
    redirect('dashboard.php');
}

$setupKey = app_config()['setup_key'];
$error = '';
$available = db()->query(
    'SELECT m.id, m.full_name, m.position FROM members m
     LEFT JOIN users u ON u.member_id = m.id
     WHERE u.id IS NULL AND m.status = "Active"
     ORDER BY m.full_name'
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name = post_string('full_name');
    $submittedKey = is_string($_POST['setup_key'] ?? null) ? $_POST['setup_key'] : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirmation = is_string($_POST['password_confirmation'] ?? null) ? $_POST['password_confirmation'] : '';
    $member = null;
    foreach ($available as $candidate) {
        if ($candidate['full_name'] === $name) {
            $member = $candidate;
            break;
        }
    }

    if ($setupKey === '' || !hash_equals($setupKey, $submittedKey)) {
        $error = 'Account setup could not be verified. Check the setup code with your coordinator.';
    } elseif ($member === null) {
        $error = 'Choose a name that has not already been registered.';
    } elseif (strlen($password) < 12) {
        $error = 'Choose a password with at least 12 characters.';
    } elseif ($password !== $confirmation) {
        $error = 'The passwords do not match.';
    } else {
        try {
            $statement = db()->prepare(
                'INSERT INTO users (member_id, password_hash) VALUES (:member_id, :password_hash)'
            );
            $statement->execute([
                'member_id' => $member['id'],
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }
            $error = 'This name was just registered. Choose another name or sign in instead.';
        }
        if ($error !== '') {
            $available = db()->query(
                'SELECT m.id, m.full_name, m.position FROM members m
                 LEFT JOIN users u ON u.member_id = m.id
                 WHERE u.id IS NULL AND m.status = "Active"
                 ORDER BY m.full_name'
            )->fetchAll();
        } else {
            flash('Your account is ready. You can now sign in.');
            redirect('login.php');
        }
    }
}

render_header('Set up account');
echo '<main class="auth-wrap"><section class="auth-card"><a class="brand auth-brand" href="' . e(url('login.php')) . '"><span class="brand-mark">M</span><span>MMD <small>MEDIA MANAGEMENT</small></span></a>'
    . '<p class="eyebrow">FIRST-TIME ACCOUNT SETUP</p><h1>Create your account</h1><p class="muted">Each registered member sets a private password. Ask your coordinator for the private setup code.</p>';
if ($error !== '') {
    echo '<div class="notice notice-error" role="alert">' . e($error) . '</div>';
}
if ($setupKey === '') {
    echo '<div class="notice notice-error">Account setup is not configured. Ask the site administrator to set APP_SETUP_KEY in the server-side .env file.</div>';
} elseif ($available === []) {
    echo '<div class="notice">All currently listed members have an account. New accounts can be added by an administrator in a later version.</div>';
} else {
    echo '<form method="post" action="' . e(url('register.php')) . '" class="form-stack">'
        . '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">'
        . '<label>Registered name<select name="full_name" required><option value="">Choose your name</option>';
    foreach ($available as $member) {
        echo '<option value="' . e($member['full_name']) . '">' . e($member['full_name']) . ' — ' . e($member['position']) . '</option>';
    }
    echo '</select></label><label>One-time setup code<input type="password" name="setup_key" autocomplete="off" required></label>'
        . '<label>Create password<input type="password" name="password" autocomplete="new-password" minlength="12" required><small>Use at least 12 characters.</small></label>'
        . '<label>Confirm password<input type="password" name="password_confirmation" autocomplete="new-password" minlength="12" required></label>'
        . '<button class="button button-primary button-full" type="submit">Create account</button></form>';
}
echo '<p class="auth-foot"><a href="' . e(url('login.php')) . '">Back to sign in</a></p></section></main>';
render_footer();
