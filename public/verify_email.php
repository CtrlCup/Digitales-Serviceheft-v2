<?php
// Legacy alias for compatibility: redirect underscore URL to dash URL, keeping query string
$qs = $_SERVER['QUERY_STRING'] ?? '';
$location = '/verify-email/' . ($qs !== '' ? ('?' . $qs) : '');
header('Location: ' . $location, true, 302);
exit;
