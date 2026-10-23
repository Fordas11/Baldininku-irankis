<?php
// Naudojimas: php tools/hash-password.php <slaptažodis>
// Išvestą eilutę įrašyk į config.local.php: return ['admin_password_hash' => '...'];
if ($argc < 2 || $argv[1] === '') {
    fwrite(STDERR, "Naudojimas: php tools/hash-password.php <slaptažodis>\n");
    exit(1);
}
echo password_hash($argv[1], PASSWORD_DEFAULT), PHP_EOL;
