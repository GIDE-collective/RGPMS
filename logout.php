<?php
/**
 * RG PMS - Sign out
 */
require_once __DIR__ . '/includes/layout.php';

logout_user();

// Fallback in case the destroy helper didn't already set this
setcookie('rgpms_session_end', 'logout', time() + 60, '/', '', false, false);

redirect('login.php');