<?php
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'bootstrap.php';
redirect(current_user() === null ? 'login.php' : 'dashboard.php');
