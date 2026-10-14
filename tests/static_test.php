<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$checks = 0;
function check(bool $ok, string $label): void {
    global $checks;
    if (!$ok) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
    $checks++; echo "PASS: $label\n";
}
$normalize = static fn(string $s): string => str_replace("\r\n", "\n", $s);
$schema = $normalize(file_get_contents($root.'/database/schema.sql'));
$seed = $normalize(file_get_contents($root.'/database/seed.sql'));
$setup = $normalize(file_get_contents($root.'/database/setup.sql'));
check($setup === $schema . "\n" . $seed, 'Combined setup exactly matches schema plus seed');
check(!preg_match('/\b(?:DROP|TRUNCATE|DELETE|REPLACE)\b/i', preg_replace('/^--.*$/m','',$seed)), 'Seed has no destructive statements');
foreach (['schema.sql','seed.sql','setup.sql'] as $file) {
    $sql = file_get_contents($root.'/database/'.$file);
    check(!preg_match('/INSERT\s+INTO\s+`?(users|orders|order_details|contact_messages)`?/i',$sql), 'No private rows in '.$file);
    check(!preg_match('/\$(?:2[aby]\$|argon2)/',$sql), 'No password hashes in '.$file);
    check(!preg_match('/\b(?:DROP\s+(?:TABLE|DATABASE)|TRUNCATE|CREATE\s+USER|GRANT)\b/i',$sql), 'No reset or credential commands in '.$file);
}
preg_match_all("/'(Products\/[^']+)'/",$seed,$images);
check(count(array_unique($images[1])) === 17, '17 distinct public product images');
foreach ($images[1] as $path) {
    // Case-sensitive filename check also catches failures on Linux hosting.
    check(in_array(basename($path),scandir($root.'/Products'),true), 'Exact image filename: '.basename($path));
}
$ignore=file_get_contents($root.'/.gitignore');
check(str_contains($ignore,'includes/config.local.php') && str_contains($ignore,'.local/'), 'Private config and test artifacts covered by ignore rules');
check(!str_contains($ignore,'!database/*.sql'), 'Only explicit public SQL files are re-included');
$example=require $root.'/includes/config.example.php';
check($example['user']==='mckl_mart_app' && $example['name']==='mckl_mart_portfolio' && $example['password']==='REPLACE_WITH_YOUR_LOCAL_PASSWORD', 'Public config contains only a dedicated account and placeholder');
check(!is_file($root.'/includes/config.example.php.php'), 'Misnamed duplicate configuration removed');
echo "RESULT: $checks checks passed.\n";
