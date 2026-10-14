<?php
/** CLI-only rate limiter, concurrency and privacy regressions. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/includes/demo.php';
if (($argv[1] ?? '') === '--worker') {
    $lease = new DemoWriteLease($argv[2]);
    echo $lease->consume([['concurrent',3,60]],1000) === 0 ? 'allowed' : 'denied';
    exit;
}
$passed = 0;
function demo_check(bool $ok, string $message): void {
    global $passed;
    if (!$ok) throw new RuntimeException($message);
    $passed++; echo "PASS: $message\n";
}
$temp = dirname(__DIR__) . '/.local/demo-unit-' . bin2hex(random_bytes(6));
mkdir($temp,0700,true);
$exit=0;
try {
    putenv('MCKL_DEMO_MODE=1'); $_SESSION=[];
    $email=demo_email();
    demo_check(is_demo_email($email) && demo_email()===$email, 'Generated reserved-domain identity is stable per session');
    $_SESSION=[]; demo_check(demo_email()!==$email, 'Separate visitors receive different identities');
    $_POST=['name'=>'Unexpected Person','email'=>'person@example.invalid','phone'=>'0123456789','password'=>'Temporary123!'];
    demo_prepare_post('register');
    demo_check($_POST['name']==='Demo Visitor' && $_POST['phone']==='' && is_demo_email($_POST['email']) && $_POST['password']==='Temporary123!', 'Registration replaces personal fields and preserves password workflow');
    $_POST=['address'=>'Unexpected address','phone'=>'0123456789','email'=>'person@example.invalid'];
    demo_prepare_post('checkout',$email);
    demo_check($_POST['address']==='10 Example Street, Demo City' && $_POST['phone']==='0100000000' && $_POST['email']===$email, 'Checkout uses fixed contact details and account demo identity');
    $_POST=['cMessage'=>'Unexpected confidential text']; demo_prepare_post('contact');
    demo_check($_POST['cMessage']===demo_message(), 'Contact message cannot store free-form visitor text');
    putenv('MCKL_DEMO_MODE=0'); $_POST=['name'=>'Coursework Example']; demo_prepare_post('register');
    demo_check($_POST===['name'=>'Coursework Example'], 'Explicit local coursework mode preserves original validation inputs');
    putenv('MCKL_DEMO_MODE=1');

    $path=$temp.'/state.php'; $lease=new DemoWriteLease($path);
    demo_check($lease->consume([['ip:192.0.2.10',2,10]],100)===0, 'First attempt allowed'); unset($lease);
    $lease=new DemoWriteLease($path);
    demo_check($lease->consume([['ip:192.0.2.10',2,10]],101)===0, 'Counter persists across independent instances'); unset($lease);
    $lease=new DemoWriteLease($path);
    demo_check($lease->consume([['ip:192.0.2.10',2,10]],102)===8, 'Exhausted bucket returns remaining wait');
    demo_check($lease->consume([['ip:192.0.2.10',2,10]],109)===1, 'Denied attempts do not extend the wait');
    demo_check($lease->consume([['ip:192.0.2.10',2,10]],110)===0, 'Expired window recovers automatically'); unset($lease);
    $raw=file_get_contents($path);
    demo_check(str_starts_with($raw,'<?php http_response_code(403); exit; ?>') && !str_contains($raw,'192.0.2.10'), 'State has PHP denial wrapper and stores no raw IP address');

    $lease=new DemoWriteLease($temp.'/accounts.php');
    for($i=0;$i<10;$i++) demo_check($lease->consume(demo_rate_rules('login','192.0.2.'.$i,'demo@example.test'),1000)===0, 'Account limiter permits attempt '.($i+1));
    demo_check($lease->consume(demo_rate_rules('login','198.51.100.1','DEMO@example.test'),1001)>0, 'Account limiter survives IP changes and email case changes'); unset($lease);
    demo_check(!str_contains(file_get_contents($temp.'/accounts.php'),'demo@example.test'), 'State stores no raw email address');
    foreach(['register'=>[3,86400], 'contact'=>[5,3600], 'checkout'=>[10,3600]] as $action=>[$limit,$seconds]) {
        $lease=new DemoWriteLease($temp.'/'.$action.'.php');
        for($i=0;$i<$limit;$i++) $allowed=$lease->consume(demo_rate_rules($action,'192.0.2.1'),1000);
        demo_check($allowed===0 && $lease->consume(demo_rate_rules($action,'192.0.2.1'),1001)===$seconds-1, "$action uses its configured persistent limit"); unset($lease);
    }
    $lease=new DemoWriteLease($temp.'/bounded.php'); $rules=[];
    for($i=0;$i<1025;$i++) $rules[]=['key-'.$i,1,60];
    demo_check($lease->consume($rules,1000)===60, 'New identities cannot exceed the 1024-bucket storage cap'); unset($lease);
    file_put_contents($temp.'/corrupt.php','invalid'); $failed=false;
    try { new DemoWriteLease($temp.'/corrupt.php'); } catch(Throwable $e) { $failed=true; }
    demo_check($failed,'Corrupt state fails closed instead of resetting counters');
    file_put_contents($temp.'/large.php',str_repeat('x',262145)); $failed=false;
    try { new DemoWriteLease($temp.'/large.php'); } catch(Throwable $e) { $failed=true; }
    demo_check($failed,'Oversized state is refused without reading unbounded data');
    $failed=false; try { new DemoWriteLease($temp.'/missing/state.php'); } catch(Throwable $e) { $failed=true; }
    demo_check($failed,'Unavailable storage cannot silently disable protection');

    $processes=[];
    for($i=0;$i<8;$i++) {
        $proc=proc_open([PHP_BINARY,__FILE__,'--worker',$temp.'/concurrent.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,null,['bypass_shell'=>true,'create_no_window'=>true]);
        if (!is_resource($proc)) throw new RuntimeException('Cannot launch concurrency test');
        fclose($pipes[0]); $processes[]=[$proc,$pipes];
    }
    $allowed=0;
    foreach($processes as [$proc,$pipes]) {
        $result=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); $code=proc_close($proc);
        if($code!==0 || $error!=='') throw new RuntimeException('Concurrent worker failed');
        if($result==='allowed') $allowed++;
    }
    demo_check($allowed===3,'Eight concurrent PHP processes cannot exceed a three-attempt quota');
    echo "RESULT: $passed demo checks passed.\n";
} catch(Throwable $e) { fwrite(STDERR,'FAIL: '.$e->getMessage()."\n"); $exit=1; }
finally {
    foreach(glob($temp.'/*.php') as $file) unlink($file);
    rmdir($temp);
}
exit($exit);
