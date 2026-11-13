<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || !in_array('--allow-local-fixtures', $argv, true)) {
    exit("Usage: php tests/website-account.php --allow-local-fixtures\n");
}
require dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/website-account.php';
if ($config['environment'] !== 'development') throw new RuntimeException('Development only.');
$pdo = db(); $ids = []; $prefix = 'settings-' . bin2hex(random_bytes(5));
$assert = static function(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    echo 'PASS: ' . $label . PHP_EOL;
};
$reject = static function(callable $action, string $message) use ($assert): void {
    try { $action(); } catch (InvalidArgumentException $e) {
        $assert(str_contains($e->getMessage(), $message), $message); return;
    }
    throw new RuntimeException('Expected rejection: ' . $message);
};
try {
    $roles = $pdo->query('SELECT slug,id FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach (['system_admin','administrator','environmental_staff','owner'] as $role) {
        $pdo->prepare('INSERT INTO users(role_id,full_name,email,password_hash) VALUES(?,?,?,?)')
            ->execute([$roles[$role], 'Settings fixture', $prefix . '-' . $role . '@example.test', password_hash('Original!2026', PASSWORD_DEFAULT)]);
        $ids[$role] = (int) $pdo->lastInsertId();
    }
    $id = $ids['environmental_staff'];
    $contact = ['section'=>'contact','email'=>'','contact_number'=>'099' . random_int(10000000,99999999),'current_password'=>'Original!2026'];
    $reject(fn()=>website_account_update($pdo,$id,array_replace($contact,['current_password'=>'wrong'])), 'current password is incorrect');
    $reject(fn()=>website_account_update($pdo,$id,array_replace($contact,['contact_number'=>''])), 'contact number');
    $reject(fn()=>website_account_update($pdo,$id,array_replace($contact,['email'=>'invalid'])), 'valid email');
    website_account_update($pdo,$id,$contact);
    $q=$pdo->prepare('SELECT email,contact_number,password_hash FROM users WHERE id=?');$q->execute([$id]);$saved=$q->fetch();
    $assert($saved['email']===null && $saved['contact_number']===$contact['contact_number'], 'Optional email and required contact save for staff');
    $reject(fn()=>website_account_update($pdo,$ids['administrator'],$contact), 'already in use');
    $reject(fn()=>website_account_update($pdo,$ids['owner'],$contact), 'no longer available');
    $password=['section'=>'password','current_password'=>'Original!2026','new_password'=>'Updated!2026','password_confirmation'=>'different'];
    $reject(fn()=>website_account_update($pdo,$id,$password), 'do not match');
    $reject(fn()=>website_account_update($pdo,$id,array_replace($password,['new_password'=>'short'])), '8 to 128');
    website_account_update($pdo,$id,array_replace($password,['password_confirmation'=>'Updated!2026','user_id'=>$ids['system_admin']]));
    $q->execute([$id]);$assert(password_verify('Updated!2026',$q->fetch()['password_hash']), 'Password changed for signed-in staff');
    $q->execute([$ids['system_admin']]);$assert(password_verify('Original!2026',$q->fetch()['password_hash']), 'Submitted target ID cannot change another account');
    for($i=0;$i<4;$i++)audit('WEBSITE_ACCOUNT_VERIFY_FAILED',$ids['administrator'],'users',$ids['administrator']);
    $reject(fn()=>website_account_update($pdo,$ids['administrator'],array_replace($contact,['current_password'=>'wrong'])), 'current password is incorrect');
    $reject(fn()=>website_account_update($pdo,$ids['administrator'],$contact), 'Too many password checks');
} finally {
    foreach($ids as $id){$pdo->prepare('DELETE FROM audit_logs WHERE user_id=?')->execute([$id]);$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);}
}
