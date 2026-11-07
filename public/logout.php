<?php
require __DIR__ . '/../app/bootstrap.php';
// Logout is POST-only (with CSRF token) so a link on another site can't log you out.
if (!is_post()) redirect('dashboard.php');
if (uid()) audit('Logged out');
logout_user();
session_start();
flash('success', 'You have been logged out safely.');
redirect('login.php');
