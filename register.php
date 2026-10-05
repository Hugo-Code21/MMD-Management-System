<?php
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'bootstrap.php';

if (current_user() !== null) {
    redirect('dashboard.php');
}

$setupKey = app_config()['setup_key'];
$error = '';
$name = '';
$className = '';
$birthDate = '';
$teamRole = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name = post_string('full_name');
    $className = post_string('class_name');
    $birthDate = post_string('birth_date');
    $teamRole = post_string('team_role');
    $submittedKey = is_string($_POST['setup_key'] ?? null) ? $_POST['setup_key'] : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirmation = is_string($_POST['password_confirmation'] ?? null) ? $_POST['password_confirmation'] : '';
    $parsedBirthDate = DateTime::createFromFormat('!d/m/y', $birthDate);
    $birthDateErrors = DateTime::getLastErrors();
    $validBirthDate = preg_match('/\A\d{2}\/\d{2}\/\d{2}\z/', $birthDate) === 1
        && $parsedBirthDate !== false
        && ($birthDateErrors === false
            || ($birthDateErrors['warning_count'] === 0 && $birthDateErrors['error_count'] === 0))
        && $parsedBirthDate->format('d/m/y') === $birthDate
        && $parsedBirthDate <= new DateTime('today');

    if ($setupKey === '' || !hash_equals($setupKey, $submittedKey)) {
        $error = 'Account setup could not be verified. Check the setup code with your coordinator.';
    } elseif (preg_match('/\A.{1,120}\z/us', $name) !== 1
        || preg_match('/\A.{1,80}\z/us', $className) !== 1
        || preg_match('/\A.{1,80}\z/us', $teamRole) !== 1) {
        $error = 'Enter your name, class, and team role within the listed character limits.';
    } elseif (!$validBirthDate) {
        $error = 'Enter a valid birth date in DD/MM/YY format that is not in the future.';
    } elseif (strlen($password) < 12) {
        $error = 'Choose a password with at least 12 characters.';
    } elseif ($password !== $confirmation) {
        $error = 'The passwords do not match.';
    } else {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $statement = $pdo->prepare('SELECT id, status FROM members WHERE full_name = :name');
            $statement->execute(['name' => $name]);
            $member = $statement->fetch();

            if ($member !== false) {
                if ($member['status'] !== 'Active') {
                    $error = 'This member is not currently active. Ask a coordinator for help.';
                } else {
                    $accountCheck = $pdo->prepare('SELECT 1 FROM users WHERE member_id = :member_id');
                    $accountCheck->execute(['member_id' => $member['id']]);
                    if ($accountCheck->fetchColumn()) {
                        $error = 'An account already exists for this name. Sign in instead.';
                    } else {
                        $updateMember = $pdo->prepare(
                            'UPDATE members SET class_name = :class_name, birth_date = :birth_date,
                             team_role = :team_role WHERE id = :id'
                        );
                        $updateMember->execute([
                            'class_name' => $className,
                            'birth_date' => $parsedBirthDate->format('Y-m-d'),
                            'team_role' => $teamRole,
                            'id' => $member['id'],
                        ]);
                    }
                }
            } else {
                $createMember = $pdo->prepare(
                    'INSERT INTO members (full_name, position, access_role, class_name, birth_date, team_role)
                     VALUES (:full_name, "Member", "member", :class_name, :birth_date, :team_role)'
                );
                $createMember->execute([
                    'full_name' => $name,
                    'class_name' => $className,
                    'birth_date' => $parsedBirthDate->format('Y-m-d'),
                    'team_role' => $teamRole,
                ]);
                $member = ['id' => $pdo->lastInsertId(), 'status' => 'Active'];
            }

            if ($error !== '') {
                $pdo->rollBack();
            } else {
                $createAccount = $pdo->prepare(
                    'INSERT INTO users (member_id, password_hash) VALUES (:member_id, :password_hash)'
                );
                $createAccount->execute([
                    'member_id' => $member['id'],
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ]);
                $pdo->commit();
                flash('Your account is ready. You can now sign in.');
                redirect('login.php');
            }
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }
            $error = 'An account already exists for this name. Check the spelling or sign in instead.';
        }
    }
}

render_header('Set up account');
echo '<main class="auth-wrap"><section class="auth-card">' . brand_link('login.php', 'auth-brand')
    . '<p class="eyebrow">FIRST-TIME ACCOUNT SETUP</p><h1>Create your account</h1><p class="muted">Enter your details and choose a private password. Ask your coordinator for the setup code.</p>';
if ($error !== '') {
    echo '<div class="notice notice-error" role="alert">' . e($error) . '</div>';
}
if ($setupKey === '') {
    echo '<div class="notice notice-error">Account setup is not configured. Ask the site administrator to set APP_SETUP_KEY in the server-side .env file.</div>';
} else {
    echo '<form method="post" action="' . e(url('register.php')) . '" class="form-stack">'
        . '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">'
        . '<label>Your name<input name="full_name" autocomplete="name" maxlength="120" value="' . e($name) . '" required></label>'
        . '<label>Class<input name="class_name" maxlength="80" value="' . e($className) . '" placeholder="For example, XII RPL 1" required></label>'
        . '<label>Birth date<input id="birth-date" type="text" name="birth_date" inputmode="numeric" autocomplete="bday" pattern="(0[1-9]|[12][0-9]|3[01])/(0[1-9]|1[0-2])/[0-9]{2}" maxlength="8" placeholder="DD/MM/YY" value="' . e($birthDate) . '" aria-describedby="birth-date-help" required><small id="birth-date-help">Enter the day, month, and year; slashes are added automatically.</small></label>'
        . '<label>Team role<input name="team_role" maxlength="80" value="' . e($teamRole) . '" placeholder="For example, photographer or video editor" required></label>'
        . '<label>One-time setup code<input type="password" name="setup_key" autocomplete="off" required></label>'
        . '<label>Create password<input type="password" name="password" autocomplete="new-password" minlength="12" required><small>Use at least 12 characters.</small></label>'
        . '<label>Confirm password<input type="password" name="password_confirmation" autocomplete="new-password" minlength="12" required></label>'
        . '<button class="button button-primary button-full" type="submit">Create account</button></form>'
        . '<script>
            const birthDateInput = document.getElementById("birth-date");
            const formatBirthDate = digits => {
                if (digits.length < 2) return digits;
                if (digits.length < 4) return digits.slice(0, 2) + "/" + digits.slice(2);
                return digits.slice(0, 2) + "/" + digits.slice(2, 4) + "/" + digits.slice(4);
            };
            const setBirthDate = (digits, digitPosition) => {
                const formatted = formatBirthDate(digits);
                const caret = Math.min(
                    digitPosition + (digitPosition >= 2 ? 1 : 0) + (digitPosition >= 4 ? 1 : 0),
                    formatted.length
                );
                birthDateInput.value = formatted;
                birthDateInput.setSelectionRange(caret, caret);
            };
            birthDateInput.addEventListener("input", () => {
                const start = birthDateInput.selectionStart || 0;
                const digitPosition = birthDateInput.value.slice(0, start).replace(/\\D/g, "").length;
                const digits = birthDateInput.value.replace(/\\D/g, "").slice(0, 6);
                setBirthDate(digits, digitPosition);
            });
            birthDateInput.addEventListener("keydown", event => {
                const caret = birthDateInput.selectionStart;
                if (event.key !== "Backspace" || caret !== birthDateInput.selectionEnd
                    || caret === 0 || birthDateInput.value[caret - 1] !== "/") return;
                event.preventDefault();
                const digits = birthDateInput.value.replace(/\\D/g, "");
                const digitPosition = birthDateInput.value.slice(0, caret).replace(/\\D/g, "").length;
                setBirthDate(
                    digits.slice(0, digitPosition - 1) + digits.slice(digitPosition),
                    digitPosition - 1
                );
            });
        </script>';
}
echo '<p class="auth-foot"><a href="' . e(url('login.php')) . '">Back to sign in</a></p></section></main>';
render_footer();
