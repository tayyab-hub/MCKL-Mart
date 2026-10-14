<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
session_save_path(sys_get_temp_dir());
// Small no-database regression checks; production integration still needs XAMPP/MariaDB.
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/functions.php';
function check(bool $condition, string $description): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: $description\n");
        exit(1);
    }
    echo "PASS: $description\n";
}
check(e('<script>') === '&lt;script&gt;', 'Output escaping');
check(money(3.5) === '3.50', 'Money formatting');
check(validateMalaysiaPhone('+60123456789') === '0123456789', 'Malaysia phone normalisation');
check(validateMalaysiaPhone('12345') === false, 'Invalid phone rejected');
check(!valid_csrf_token('bad-token'), 'Invalid CSRF denied');
$token = csrf_token();
check(strlen($token) === 64 && valid_csrf_token($token), 'Valid CSRF accepted');
check(!valid_csrf_token(''), 'Empty CSRF denied');
check(!valid_csrf_token(['bad']), 'Array CSRF denied');
check(input_string(['unexpected']) === '', 'Array fields rejected safely');
check(input_string("bad\0value") === '', 'NUL input rejected');
check(input_string("\xff") === '', 'Malformed UTF-8 input rejected');
check(str_contains(e("\xff"), "\xef\xbf\xbd"), 'Malformed output substituted safely');
check(validateName('Dr. Example'), 'Name punctuation accepted');
check(!validateName("Example\nName"), 'Newline in a name rejected');
check(!validateEmail(str_repeat('a', 64) . '@' . str_repeat('b', 63) . '.' . str_repeat('c', 30) . '.test'), 'Database email length enforced');
check(validatePassword('Example123!'), 'Strong password accepted');
check(!validatePassword('weak'), 'Weak password rejected');
check(!validatePassword('Example123!' . str_repeat('a', 72)), 'bcrypt truncation prevented');
check(safe_redirect('https://evil.example/checkout.php') === 'index.php', 'External redirect denied');
check(safe_redirect(['checkout.php']) === 'index.php', 'Array redirect denied');
check(safe_redirect('checkout.php') === 'checkout.php', 'Local allowlisted redirect accepted');
