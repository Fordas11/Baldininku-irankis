<?php
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

start_session();
$_SESSION = [];
session_destroy();
header('Location: index.php');
