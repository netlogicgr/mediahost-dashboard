<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Auth.php';

Auth::logout();
redirect(public_url('admin/login.php'));
