<?php
// Redirect legacy underscore directory to the canonical dash URL, preserving query string
$qs = $_SERVER['QUERY_STRING'] ?? '';
$location = '/verify-email/' . ($qs !== '' ? ('?' . $qs) : '');
header('Location: ' . $location, true, 302);
exit;
