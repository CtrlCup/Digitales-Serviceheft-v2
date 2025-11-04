<?php
// Legacy underscore path at web root: redirect to canonical /verify-email/
$qs = $_SERVER['QUERY_STRING'] ?? '';
$location = '/verify-email/' . ($qs !== '' ? ('?' . $qs) : '');
header('Location: ' . $location, true, 302);
exit;
