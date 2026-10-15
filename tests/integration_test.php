<?php
/** CLI-only integration suite. Creates and drops ONLY new random test databases. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!in_array('--run', $argv, true)) {
    exit("Usage: set MCKL_TEST_DB_USER / MCKL_TEST_DB_PASSWORD privately, then php tests/integration_test.php --run [--serve]\nCreates new mckl_mart_test_* databases; never imports into existing databases.\n");
}
date_default_timezone_set('Asia/Kuala_Lumpur');
$root = dirname(__DIR__);
$host = getenv('MCKL_TEST_DB_HOST') ?: '127.0.0.1';
$port = getenv('MCKL_TEST_DB_PORT') ?: '3306';
$user = getenv('MCKL_TEST_DB_USER');
$password = getenv('MCKL_TEST_DB_PASSWORD') ?: '';
if (!in_array($host, ['localhost', '127.0.0.1'], true) || !$user) {
    fwrite(STDERR, "Use a local database and explicitly set MCKL_TEST_DB_USER.\n"); exit(1);
}
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];
$created = [];
$servers = [];
$clients = [];
$passed = 0;
$temp = $root . '/.local/test-' . bin2hex(random_bytes(6));
mkdir($temp, 0700, true);
function check(bool $condition, string $label): void {
    global $passed;
    if (!$condition) throw new RuntimeException($label);
    $passed++;
    echo "PASS: $label\n";
}
function importSql(PDO $db, string $path): void {
    // The shipped files contain only simple DDL/DML, no stored routines.
    $sql = preg_replace('/^--.*$/m', '', file_get_contents($path));
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $statement) {
        if (trim($statement) !== '') $db->exec($statement);
    }
}
function client(): CurlHandle {
    global $clients;
    $c = curl_init();
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEFILE => '', CURLOPT_TIMEOUT => 20]);
    $clients[] = $c;
    return $c;
}
function prepareRequest(CurlHandle $c, string $url, ?array $data = null): void {
    curl_setopt($c, CURLOPT_URL, $url);
    curl_setopt($c, CURLOPT_POST, $data !== null);
    if ($data !== null) curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($data));
    else curl_setopt($c, CURLOPT_HTTPGET, true);
}
function response(CurlHandle $c, string $raw): array {
    $headers = substr($raw, 0, curl_getinfo($c, CURLINFO_HEADER_SIZE));
    $body = substr($raw, strlen($headers));
    preg_match('/^Location:\s*(.+)$/mi', $headers, $location);
    if (preg_match('/Fatal error:|Warning:|Stack trace:|SQLSTATE\[/', $body)) throw new RuntimeException('Internal error leaked in response');
    return ['status' => curl_getinfo($c, CURLINFO_RESPONSE_CODE), 'body' => $body, 'headers' => $headers, 'location' => trim($location[1] ?? '')];
}
function request(CurlHandle $c, string $url, ?array $data = null): array {
    prepareRequest($c, $url, $data);
    $raw = curl_exec($c);
    if ($raw === false) throw new RuntimeException('Local HTTP request failed');
    return response($c, $raw);
}
function token(CurlHandle $c, string $base): string {
    $r = request($c, $base . '/index.php');
    if (!preg_match('/name="csrf-token" content="([a-f0-9]{64})"/', $r['body'], $m)) throw new RuntimeException('CSRF token missing');
    return $m[1];
}
function add(CurlHandle $c, string $base, string $token, int $id): array {
    return json_decode(request($c, $base . '/cart_action.php', ['action'=>'add','product_id'=>$id,'ajax'=>'1','csrf_token'=>$token])['body'], true);
}
function checkoutData(string $token, string $email = 'guest@example.test'): array {
    return ['csrf_token'=>$token,'firstName'=>'Demo','lastName'=>'Customer','email'=>$email,'phone'=>'0100000000','fulfillment'=>'pickup','pickupDate'=>date('Y-m-d', strtotime('+1 day')),'pickupTime'=>'12:00','payment'=>'cash'];
}
function expectDbReject(PDO $db, string $sql, string $label): void {
    $rejected = false;
    $db->beginTransaction();
    try { $db->exec($sql); } catch (PDOException $error) { $rejected = true; }
    finally { $db->rollBack(); }
    check($rejected, $label);
}
function startServer(string $dbName, string $suffix, bool $demo = false): string {
    global $root, $temp, $host, $port, $user, $password, $servers;
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if (!$socket) throw new RuntimeException('Cannot reserve local test port');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $env = array_merge(getenv(), ['MCKL_DB_HOST'=>$host,'MCKL_DB_PORT'=>$port,'MCKL_DB_NAME'=>$dbName,'MCKL_DB_USER'=>$user,'MCKL_DB_PASSWORD'=>$password,'MCKL_TEST_EMPTY_PASSWORD'=>$password === '' ? '1' : '0',
        'MCKL_DEMO_MODE'=>$demo ? '1' : '0', 'MCKL_DEMO_STATE_PATH'=>$temp.'/demo-state.php']);
    $proc = proc_open([PHP_BINARY, '-d', 'session.save_path='.$temp, '-d', 'display_errors=0', '-S', $address, '-t', $root, $root.'/tests/router.php'],
        [0=>['pipe','r'],1=>['file',$temp.'/server-'.$suffix.'.log','a'],2=>['file',$temp.'/server-'.$suffix.'.log','a']], $pipes, $root, $env, ['bypass_shell'=>true, 'create_no_window'=>true]);
    if (!is_resource($proc)) throw new RuntimeException('Could not start PHP test server');
    fclose($pipes[0]);
    $servers[] = $proc;
    $base = 'http://' . $address;
    for ($i=0; $i<50; $i++) {
        $c = curl_init($base.'/index.php');
        curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT_MS=>100,CURLOPT_TIMEOUT_MS=>500]);
        curl_exec($c); $status=curl_getinfo($c,CURLINFO_RESPONSE_CODE); curl_close($c);
        if ($status === 200) return $base;
        usleep(100000);
    }
    throw new RuntimeException('Test server did not become ready');
}
$exit = 0;
try {
    $admin = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $password, $options);
    foreach (['combined','split'] as $kind) {
        $name = 'mckl_mart_test_' . bin2hex(random_bytes(8));
        // No IF NOT EXISTS: a collision must fail, not reuse someone else's database.
        $admin->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $created[] = $name;
        file_put_contents($temp.'/created-databases.json',json_encode($created,JSON_PRETTY_PRINT));
        $db = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $user, $password, $options);
        foreach ($kind === 'combined' ? ['setup.sql'] : ['schema.sql','seed.sql'] as $file) importSql($db, $root.'/database/'.$file);
        check((int)$db->query('SELECT COUNT(*) FROM categories')->fetchColumn()===4 && (int)$db->query('SELECT COUNT(*) FROM products')->fetchColumn()===17, "$kind import: 4 categories and 17 products");
        check((int)$db->query('SELECT (SELECT COUNT(*) FROM users)+(SELECT COUNT(*) FROM orders)+(SELECT COUNT(*) FROM order_details)+(SELECT COUNT(*) FROM contact_messages)')->fetchColumn()===0, "$kind import: no private records");
        check((int)$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND ENGINE='InnoDB'")->fetchColumn()===6, "$kind import: six InnoDB tables");
        if ($kind === 'combined') $catalogue = $db->query('SELECT * FROM products ORDER BY product_id')->fetchAll();
        else check($catalogue === $db->query('SELECT * FROM products ORDER BY product_id')->fetchAll(), 'Combined and split imports match');
    }
    $dbName = $created[1];
    check((int)$db->query("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE()")->fetchColumn()===4, 'Four foreign key relationships');
    expectDbReject($db, 'UPDATE products SET category_id=9999 WHERE product_id=1', 'Foreign key rejects missing category');
    expectDbReject($db, "INSERT INTO categories (name,slug) VALUES ('Duplicate','drinks')", 'Unique category slug enforced');
    expectDbReject($db, 'UPDATE products SET stock=-1 WHERE product_id=1', 'Negative inventory rejected by schema');
    expectDbReject($db, 'UPDATE products SET price=-1 WHERE product_id=1', 'Negative prices rejected by schema');
    foreach ($catalogue as $p) check(is_file($root.'/'.$p['image']), 'Product image exists: '.$p['product_id']);
    $base = startServer($dbName,'one');
    $baseTwo = startServer($dbName,'two');
    $a=client(); $b=client(); $guest=client();
    $ta=token($a,$base); $tb=token($b,$base); $tg=token($guest,$base);
    $cookieResponse=request(client(),$base.'/index.php');
    check(str_contains($cookieResponse['headers'],'MCKLMARTSESSID=') && stripos($cookieResponse['headers'],'HttpOnly')!==false && stripos($cookieResponse['headers'],'SameSite=Lax')!==false, 'Session cookie has dedicated name, HttpOnly and SameSite');
    $linkedPaths=[];
    foreach (['index.php','products.php','contact.php','cart.php','checkout.php','login.php','register.php','product_details.php?id=1'] as $page) {
        $pageResponse=request($a,$base.'/'.$page);
        check($pageResponse['status']===200, 'Page loads: '.$page);
        preg_match_all('~(?:href|src)="([^"]+)"~',$pageResponse['body'],$references);
        foreach ($references[1] as $reference) {
            $reference=html_entity_decode($reference,ENT_QUOTES,'UTF-8');
            if ($reference==='' || str_starts_with($reference,'#') || preg_match('~^(?:[a-z]+:|//)~i',$reference)) continue;
            $parts=parse_url($reference);
            $encodedPath=implode('/',array_map('rawurlencode',explode('/',rawurldecode($parts['path']??''))));
            $linkedPaths[$encodedPath.(isset($parts['query'])?'?'.$parts['query']:'')]=true;
        }
    }
    $broken=[];
    foreach (array_keys($linkedPaths) as $path) {
        if (request($a,$base.'/'.$path)['status']!==200) $broken[]=$path;
    }
    check(!$broken, 'All '.count($linkedPaths).' local linked pages and assets return HTTP 200');
    $home=request($a,$base.'/index.php');
    check(str_contains($home['headers'],'HttpOnly') || count(curl_getinfo($a,CURLINFO_COOKIELIST))>0, 'Session cookie issued');
    check(str_contains(strtolower($home['headers']), 'cache-control: no-store'), 'Personalized pages not cached');
    check(substr_count(request($a,$base.'/products.php')['body'],'<article class="product-card"')===17, 'Catalogue renders all 17 products');
    check(substr_count(request($a,$base.'/products.php?category=drinks')['body'],'<article class="product-card"')===5, 'Category filter works');
    check(substr_count(request($a,$base.'/products.php?q=green%20tea')['body'],'<article class="product-card"')===1, 'Product search works');
    check(str_contains(request($a,$base.'/products.php?q=no-such-product')['body'],'No products found'), 'Empty search feedback');
    check(request($a,$base.'/products.php?q%5B%5D=x&category%5B%5D=x')['status']===200, 'Array search parameters do not crash');
    check(request($a,$base.'/product_details.php?id=99999')['status']===404, 'Missing product returns 404');
    check(request($a,$base.'/product_details.php?id=1%20OR%201=1')['status']===404, 'Product SQL injection rejected');
    $xss=request($a,$base.'/products.php?q='.rawurlencode('<script>alert(1)</script>'));
    check(!str_contains($xss['body'],'<script>alert(1)</script>') && str_contains($xss['body'],'&lt;script&gt;'), 'Search output escaped');
    check(request($a,$base.'/order_history.php')['status']===302, 'Guest order history blocked');
    foreach (['cart_action.php','logout.php'] as $page) check(request($a,$base.'/'.$page)['status']===405,'GET mutation denied: '.$page);
    foreach (['login.php','register.php','contact.php','checkout.php','cart_action.php','logout.php'] as $page) check(request($a,$base.'/'.$page,[])['status']===403,'CSRF denied: '.$page);
    check(request($a,$base.'/login.php',['csrf_token'=>$ta,'email'=>['bad'],'password'=>['bad']])['status']===200, 'Array login inputs return validation feedback');
    $reg=['csrf_token'=>$ta,'name'=>'Demo Alpha','email'=>'alpha@example.test','password'=>'DemoTesting123!','confirm_password'=>'DemoTesting123!','redirect'=>'https://evil.example/'];
    $before=curl_getinfo($a,CURLINFO_COOKIELIST);
    $r=request($a,$base.'/register.php',$reg);
    check($r['status']===302 && $r['location']==='order_history.php', 'Registration succeeds with safe redirect');
    check($before!==curl_getinfo($a,CURLINFO_COOKIELIST), 'Registration rotates session ID');
    $hash=$db->query("SELECT password FROM users WHERE email='alpha@example.test'")->fetchColumn();
    check(password_verify('DemoTesting123!', $hash) && $hash!=='DemoTesting123!', 'Password stored as a verifiable hash');
    $r=request($b,$base.'/register.php',array_replace($reg,['csrf_token'=>$tb]));
    check($r['status']===200 && str_contains($r['body'],'already exists'), 'Duplicate registration is handled');
    $regRaceA=client(); $regRaceB=client();
    $regTokenA=token($regRaceA,$base); $regTokenB=token($regRaceB,$baseTwo);
    // Pause both inserts after their preliminary email checks to exercise the
    // unique-key race rather than only the ordinary duplicate-email branch.
    $db->exec('CREATE TRIGGER test_pause_signup BEFORE INSERT ON users FOR EACH ROW DO SLEEP(0.4)');
    try {
        prepareRequest($regRaceA,$base.'/register.php',array_replace($reg,['csrf_token'=>$regTokenA,'email'=>'signup-race@example.test']));
        prepareRequest($regRaceB,$baseTwo.'/register.php',array_replace($reg,['csrf_token'=>$regTokenB,'email'=>'signup-race@example.test']));
        $signupMulti=curl_multi_init(); curl_multi_add_handle($signupMulti,$regRaceA); curl_multi_add_handle($signupMulti,$regRaceB);
        $overlapped=false;
        do {
            curl_multi_exec($signupMulti,$running);
            $inserts=(int)$admin->query("SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE DB='$dbName' AND (INFO LIKE '%INSERT INTO users%' OR STATE='User sleep')")->fetchColumn();
            $overlapped=$overlapped || $inserts>=2;
            if ($running) curl_multi_select($signupMulti,.02);
        } while ($running);
        $signupA=response($regRaceA,curl_multi_getcontent($regRaceA)); $signupB=response($regRaceB,curl_multi_getcontent($regRaceB));
        curl_multi_remove_handle($signupMulti,$regRaceA); curl_multi_remove_handle($signupMulti,$regRaceB); curl_multi_close($signupMulti);
    } finally { $db->exec('DROP TRIGGER test_pause_signup'); }
    check($overlapped, 'Concurrent registration inserts overlap');
    check(($signupA['status']===302?1:0)+($signupB['status']===302?1:0)===1 && str_contains($signupA['body'].$signupB['body'],'already exists'), 'Duplicate-email race returns one success and one validation error');
    check((int)$db->query("SELECT COUNT(*) FROM users WHERE email='signup-race@example.test'")->fetchColumn()===1, 'Duplicate-email race creates exactly one account');
    check(request($a,$base.'/logout.php',['csrf_token'=>$ta])['status']===302, 'POST logout succeeds');
    check(request($a,$base.'/order_history.php')['status']===302, 'Logout removes account access');
    $ta=token($a,$base);
    check(str_contains(request($a,$base.'/login.php',['csrf_token'=>$ta,'email'=>'alpha@example.test','password'=>'wrong'])['body'],'Incorrect email or password'), 'Wrong password rejected');
    $r=request($a,$base.'/login.php',['csrf_token'=>$ta,'email'=>'alpha@example.test','password'=>'DemoTesting123!','redirect'=>['bad']]);
    check($r['status']===302 && $r['location']==='order_history.php', 'Login accepts valid credentials and safe session destination');
    check(add($a,$base,$ta,1)['cartCount']===1, 'Add to cart');
    check(!add($a,$base,$ta,3)['success'], 'Out-of-stock add denied');
    check(!add($a,$base,$ta,9999)['success'], 'Unknown product add denied');
    $cartData=['csrf_token'=>$ta,'action'=>'update','product_id'=>1,'quantity'=>3,'ajax'=>'1'];
    check(json_decode(request($a,$base.'/cart_action.php',$cartData)['body'],true)['cartCount']===3, 'Cart quantity update');
    foreach (['0','-1','1.5','bad', ['bad']] as $bad) {
        $r=json_decode(request($a,$base.'/cart_action.php',array_replace($cartData,['quantity'=>$bad]))['body'],true);
        check(!$r['success'] && $r['cartCount']===3, 'Invalid quantity preserves the cart');
    }
    $db->exec('UPDATE products SET stock=2 WHERE product_id=1');
    check(json_decode(request($a,$base.'/cart_action.php',$cartData)['body'],true)['cartCount']===2, 'Quantity capped at current stock');
    $db->exec('UPDATE products SET stock=200 WHERE product_id=1');
    check(json_decode(request($a,$base.'/cart_action.php',array_replace($cartData,['quantity'=>999]))['body'],true)['cartCount']===99, 'Cart capped at 99 per product');
    $db->exec('UPDATE products SET stock=2 WHERE product_id=1');
    check(add($a,$base,$ta,1)['cartCount']===2, '99-item cart rechecks reduced stock when adding');
    check(json_decode(request($a,$base.'/cart_action.php',array_replace($cartData,['action'=>'remove']))['body'],true)['cartCount']===0, 'Remove cart item');
    add($a,$base,$ta,2);
    check(json_decode(request($a,$base.'/cart_action.php',array_replace($cartData,['action'=>'clear']))['body'],true)['cartCount']===0, 'Clear cart');
    add($a,$base,$ta,17);
    $db->exec('DELETE FROM products WHERE product_id=17');
    check(str_contains(request($a,$base.'/cart.php')['body'],'Your cart is empty'), 'Deleted product removed from cart view');
    check(json_decode(request($a,$base.'/cart_action.php',array_replace($cartData,['action'=>'clear']))['body'],true)['cartCount']===0, 'Deleted product leaves no ghost quantity');
    add($a,$base,$ta,1);
    $order=checkoutData($ta,'alpha@example.test');
    foreach (['pickupDate'=>'2020-01-01','pickupTime'=>'03:12','payment'=>'invalid','fulfillment'=>['pickup'],'firstName'=>str_repeat('A',100),'email'=>str_repeat('a',151).'@example.test'] as $field=>$bad) {
        $r=request($a,$base.'/checkout.php',array_replace($order,[$field=>$bad]));
        check($r['status']===200 && (int)$db->query('SELECT COUNT(*) FROM orders')->fetchColumn()===0, 'Invalid checkout denied: '.$field);
    }
    $db->exec('UPDATE products SET price=7.23 WHERE product_id=1');
    $r=request($a,$base.'/checkout.php',array_replace($order,['price'=>'0.01','total'=>'0.01']));
    check($r['status']===302 && str_starts_with($r['location'],'checkout.php?order='), 'Authenticated checkout succeeds');
    $oid=(int)substr($r['location'],strlen('checkout.php?order='));
    check($db->query("SELECT total FROM orders WHERE order_id=$oid")->fetchColumn()==='7.23', 'Current server price overrides client totals');
    check((int)$db->query('SELECT stock FROM products WHERE product_id=1')->fetchColumn()===1, 'Checkout deducts inventory once');
    check((int)$db->query("SELECT COUNT(*) FROM order_details WHERE order_id=$oid AND subtotal=7.23")->fetchColumn()===1, 'Order line total consistent');
    request($a,$base.'/checkout.php',$order);
    check((int)$db->query('SELECT COUNT(*) FROM orders')->fetchColumn()===1, 'Repeated checkout POST cannot buy an emptied cart');
    check(request($a,$base.'/checkout.php?order='.$oid)['status']===200, 'Owner sees confirmation');
    check(request($b,$base.'/checkout.php?order='.$oid)['status']===404, 'Other session cannot see confirmation');
    check(str_contains(request($a,$base.'/order_history.php')['body'],'MM-'.str_pad((string)$oid,6,'0',STR_PAD_LEFT)), 'Order history contains own order');
    request($b,$base.'/register.php',array_replace($reg,['csrf_token'=>$tb,'name'=>'Demo Beta','email'=>'beta@example.test']));
    check(!str_contains(request($b,$base.'/order_history.php')['body'],'MM-'.str_pad((string)$oid,6,'0',STR_PAD_LEFT)), 'Second account cannot see first account history');
    add($guest,$base,$tg,2);
    $r=request($guest,$base.'/checkout.php',array_replace(checkoutData($tg),['fulfillment'=>'delivery','address'=>'10 Demo Road, Test City','payment'=>'ewallet']));
    check($r['status']===302, 'Guest delivery checkout succeeds');
    $goid=(int)substr($r['location'],strlen('checkout.php?order='));
    check($db->query("SELECT user_id FROM orders WHERE order_id=$goid")->fetchColumn()===null, 'Guest order initially has no account');
    check(request($guest,$base.'/'.$r['location'])['status']===200, 'Guest sees own confirmation');
    request($guest,$base.'/register.php',array_replace($reg,['csrf_token'=>$tg,'name'=>'Demo Guest','email'=>'guest@example.test']));
    check((int)$db->query("SELECT user_id FROM orders WHERE order_id=$goid")->fetchColumn()>0, 'Same-session matching-email signup links guest order');
    $contact=['csrf_token'=>$ta,'cFirstName'=>'Demo','cLastName'=>'Tester','cEmail'=>'contact@example.test','cSubject'=>'feedback','cMessage'=>'Fictional integration test message.'];
    check(request($a,$base.'/contact.php',array_replace($contact,['cSubject'=>['bad']]))['status']===200, 'Malformed contact subject handled');
    check(request($a,$base.'/contact.php',array_replace($contact,['cLastName'=>str_repeat('A',100)]))['status']===200 && (int)$db->query('SELECT COUNT(*) FROM contact_messages')->fetchColumn()===0, 'Oversized combined contact name rejected');
    check(request($a,$base.'/contact.php',$contact)['status']===302 && (int)$db->query('SELECT COUNT(*) FROM contact_messages')->fetchColumn()===1, 'Contact saved locally');
    check(str_contains(request($a,$base.'/contact.php?sent=1')['body'],'contact-success show'), 'Contact success shown after save');
    check(!str_contains(request($a,$base.'/contact.php?sent=1')['body'],'contact-success show'), 'Forged success URL does not claim a new save');

    // Inject a real database failure AFTER the stock update; the entire order must roll back.
    $rollback=client(); $tr=token($rollback,$base); add($rollback,$base,$tr,4);
    $stockBefore=(int)$db->query('SELECT stock FROM products WHERE product_id=4')->fetchColumn();
    $ordersBefore=(int)$db->query('SELECT COUNT(*) FROM orders')->fetchColumn();
    $detailsBefore=(int)$db->query('SELECT COUNT(*) FROM order_details')->fetchColumn();
    $db->exec("CREATE TRIGGER test_fail_detail BEFORE INSERT ON order_details FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected test failure'");
    try { $r=request($rollback,$base.'/checkout.php',checkoutData($tr)); }
    finally { $db->exec('DROP TRIGGER test_fail_detail'); }
    check(str_contains($r['body'],'we could not place your order') && !str_contains($r['body'],'Injected test failure'), 'Database failure returns generic checkout error');
    check((int)$db->query('SELECT COUNT(*) FROM orders')->fetchColumn()===$ordersBefore && (int)$db->query('SELECT COUNT(*) FROM order_details')->fetchColumn()===$detailsBefore && (int)$db->query('SELECT stock FROM products WHERE product_id=4')->fetchColumn()===$stockBefore, 'Failure rolls back order, details and stock');

    // Two real PHP processes, two sessions, one locked final item.
    $raceA=client(); $raceB=client(); $t1=token($raceA,$base); $t2=token($raceB,$baseTwo);
    $db->exec('UPDATE products SET stock=1 WHERE product_id=5');
    add($raceA,$base,$t1,5); add($raceB,$baseTwo,$t2,5);
    $db->beginTransaction(); $db->query('SELECT product_id FROM products WHERE product_id=5 FOR UPDATE')->fetch();
    prepareRequest($raceA,$base.'/checkout.php',checkoutData($t1,'race-a@example.test'));
    prepareRequest($raceB,$baseTwo.'/checkout.php',checkoutData($t2,'race-b@example.test'));
    $multi=curl_multi_init(); curl_multi_add_handle($multi,$raceA); curl_multi_add_handle($multi,$raceB);
    $deadline=microtime(true)+8; $bothWaiting=false;
    do {
        curl_multi_exec($multi,$running);
        // This MariaDB build reports the blocked prepared SELECT in PROCESSLIST
        // while INNODB_LOCK_WAITS remains empty. Require both real web workers
        // to reach their locking query before releasing our transaction.
        $waiting=(int)$admin->query("SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE DB='$dbName' AND INFO LIKE 'SELECT product_id, name, price, stock FROM products WHERE product_id = %FOR UPDATE'")->fetchColumn();
        if ($waiting>=2) { $bothWaiting=true; break; }
        usleep(20000);
    } while ($running && microtime(true)<$deadline);
    $db->commit();
    do { curl_multi_exec($multi,$running); if ($running) curl_multi_select($multi,.1); } while ($running);
    $r1=response($raceA,curl_multi_getcontent($raceA)); $r2=response($raceB,curl_multi_getcontent($raceB));
    curl_multi_remove_handle($multi,$raceA); curl_multi_remove_handle($multi,$raceB); curl_multi_close($multi);
    check($bothWaiting, 'Two independent checkout requests overlap at the locking SELECT');
    check(($r1['status']===302 ? 1:0)+($r2['status']===302 ? 1:0)===1, 'Final-item race: exactly one checkout succeeds');
    check(str_contains($r1['body'].$r2['body'],'no longer available'), 'Final-item loser receives stock feedback');
    check((int)$db->query('SELECT stock FROM products WHERE product_id=5')->fetchColumn()===0 && (int)$db->query('SELECT COUNT(*) FROM order_details WHERE product_id=5')->fetchColumn()===1, 'Final-item race: no oversell or partial order');
    echo "BASELINE RESULT: $passed checks passed.\n";
    $demoBase = startServer($created[0], 'demo', true);
    $demoBaseTwo = startServer($created[0], 'demo-two', true);
    $demoDb = new PDO("mysql:host=$host;port=$port;dbname=".$created[0].";charset=utf8mb4", $user, $password, $options);
    require __DIR__ . '/demo_scenarios.php';
    run_demo_checks($demoDb, $demoBase, $demoBaseTwo, $temp);
    echo "RESULT: $passed checks passed including public-demo protections.\n";
    if (in_array('--serve',$argv,true)) {
        if (in_array('--demo-preview',$argv,true)) {
            // Only this invocation's disposable schema and state are reset for
            // visual QA, after the cap tests. Existing databases are untouched.
            $demoDb->exec('DELETE FROM orders');
            $demoDb->exec('DELETE FROM users');
            $demoDb->exec('DELETE FROM contact_messages');
            unlink($temp.'/demo-state.php');
            $base = $demoBase;
        }
        file_put_contents($temp.'/browser-url.txt',$base);
        echo "BROWSER_URL=$base\nTEST_DIRECTORY=$temp\nCreate $temp/stop to stop the preview and clean up.\n";
        // A stop file also works when the calling terminal has no open stdin.
        // Bound the preview lifetime so an abandoned terminal cannot run forever.
        $deadline=time()+1800;
        while (!is_file($temp.'/stop') && time()<$deadline) {
            clearstatcache(true,$temp.'/stop');
            usleep(250000);
        }
    }
} catch (Throwable $error) {
    // Do not dump connection exception messages or credentials.
    fwrite(STDERR,'FAIL: '.($error instanceof PDOException ? 'Database operation failed (code '.$error->getCode().')' : $error->getMessage())."\n");
    $exit=1;
} finally {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    foreach ($servers as $proc) { proc_terminate($proc); proc_close($proc); }
    foreach ($clients as $c) curl_close($c);
    foreach ($created as $name) {
        if (!preg_match('/^mckl_mart_test_[a-f0-9]{16}$/D',$name)) continue;
        try { $admin->exec("DROP DATABASE `$name`"); echo "CLEANUP: removed newly created test database.\n"; }
        catch (Throwable $error) { fwrite(STDERR,"Cleanup needed for $name\n"); $exit=1; }
    }
}
exit($exit);
