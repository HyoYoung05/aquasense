<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/mobile-api.php';
api_method('POST');
// Idempotent, even when the account was disabled or the token expired.
$hash = mobile_token_hash();
$pdo = db();
$pdo->beginTransaction();
$query = $pdo->prepare('SELECT user_id FROM mobile_tokens WHERE token_hash = ? FOR UPDATE');
$query->execute([$hash]);
$id = $query->fetchColumn();
$pdo->prepare('DELETE FROM mobile_tokens WHERE token_hash = ?')->execute([$hash]);
if ($id) {
    audit('MOBILE_LOGOUT', (int) $id, 'users', (int) $id);
}
$pdo->commit();
api_ok(['message' => 'Signed out.']);
