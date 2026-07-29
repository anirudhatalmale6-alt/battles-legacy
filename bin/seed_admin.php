<?php
/**
 * Create the first Admin account.
 * Usage: php bin/seed_admin.php "Full Name" email@example.com "password"
 */
require __DIR__ . '/../src/db.php';

$name  = $argv[1] ?? null;
$email = $argv[2] ?? null;
$pass  = $argv[3] ?? null;
if (!$name || !$email || !$pass) { fwrite(STDERR, "Usage: php bin/seed_admin.php \"Name\" email password\n"); exit(1); }

$email = strtolower(trim($email));
$exists = one("SELECT id FROM users WHERE email=?", [$email]);
$hash = password_hash($pass, PASSWORD_DEFAULT);
if ($exists) {
    q("UPDATE users SET name=?, pass_hash=?, role='admin', status='active' WHERE id=?", [$name, $hash, $exists['id']]);
    echo "Updated existing user to admin: $email\n";
} else {
    q("INSERT INTO users (name,email,pass_hash,role,status) VALUES (?,?,?, 'admin','active')", [$name, $email, $hash]);
    echo "Created admin: $email\n";
}
