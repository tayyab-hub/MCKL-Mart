<?php
/** Included only by the disposable local integration runner. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function run_demo_checks(PDO $db, string $base, string $second, string $temp): void
{
    $state=$temp.'/demo-state.php';
    $visitor=client(); $token=token($visitor,$base);
    $page=request($visitor,$base.'/register.php');
    preg_match('/value="(demo-[a-f0-9]{12}@example\.test)"/', $page['body'],$match);
    $email=$match[1]??'';
    check($email!=='' && str_contains($page['body'],'fictional details only'), 'Demo registration displays a generated identity and privacy notice');
    check(str_contains($page['body'],'readonly') && str_contains($page['body'],'name="website"'), 'Personal fields are read-only and forms contain the spam trap');
    $headersClient=client(); curl_setopt($headersClient,CURLOPT_HTTPHEADER,['Host: mcklmart.site.je']);
    $r=request($headersClient,$base.'/index.php');
    check(preg_match('/Set-Cookie: MCKLMARTSESSID=[^\r\n]*; secure; HttpOnly; SameSite=Lax/i',$r['headers'])===1, 'Known public host always receives a Secure, HttpOnly, SameSite cookie');
    $spoof=client(); curl_setopt($spoof,CURLOPT_HTTPHEADER,['X-Forwarded-Proto: https']);
    $r=request($spoof,$base.'/index.php');
    check(!preg_match('/Set-Cookie: [^\r\n]*; secure/i',$r['headers']), 'Untrusted forwarded scheme does not change local cookie security decisions');
    check(request($visitor,$base.'/register.php',['csrf_token'=>$token,'padding'=>str_repeat('x',17000)])['status']===413, 'Oversized demo POST rejected before database work');
    foreach(['login.php','register.php','contact.php','checkout.php'] as $path) check(request($visitor,$base.'/'.$path,[])['status']===403, 'Public demo retains CSRF protection: '.$path);
    check(!is_file($state), 'Invalid CSRF and oversized requests create no rate-limit state');

    add($visitor,$base,$token,2);
    $r=request($visitor,$base.'/checkout.php',array_replace(checkoutData($token),['firstName'=>'Unexpected','lastName'=>'Person','email'=>'person@example.invalid','phone'=>'0123456789','fulfillment'=>'delivery','address'=>'999 Unexpected Address','payment'=>'card']));
    check($r['status']===302, 'Public demo guest checkout succeeds');
    $order=$db->query('SELECT * FROM orders ORDER BY order_id DESC LIMIT 1')->fetch();
    check($order['customer_name']==='Demo Visitor' && $order['customer_phone']==='0100000000' && $order['customer_address']==='10 Example Street, Demo City' && $order['customer_email']===$email, 'Forged personal checkout fields are replaced before storage');
    check(str_contains(request($visitor,$base.'/'.$r['location'])['body'],'No real payment or delivery'), 'Demo confirmation explicitly labels simulated fulfillment');
    $outsider=client(); check(request($outsider,$base.'/checkout.php?order='.$order['order_id'])['status']===404,'Demo order confirmation remains session-owned');
    $reg=['csrf_token'=>$token,'name'=>'Unexpected Person','email'=>'person@example.invalid','phone'=>'0123456789','password'=>'Temporary123!','confirm_password'=>'Temporary123!'];
    $r=request($visitor,$base.'/register.php',$reg);
    check($r['status']===302, 'Public demo registration succeeds');
    $user=$db->query('SELECT * FROM users ORDER BY user_id DESC LIMIT 1')->fetch();
    check($user['name']==='Demo Visitor' && $user['phone']===null && $user['email']===$email && password_verify('Temporary123!',$user['password']), 'Demo account stores sample identity and a password hash');
    check((int)$db->query('SELECT user_id FROM orders WHERE order_id='.(int)$order['order_id'])->fetchColumn()===(int)$user['user_id'], 'Generated guest identity still links its order at signup');
    check(str_contains(request($visitor,$base.'/order_history.php')['body'],'MM-'),'Demo account can view its order history');
    request($visitor,$base.'/logout.php',['csrf_token'=>$token]);
    check(request($visitor,$base.'/order_history.php')['status']===302,'Demo logout removes history access');
    $token=token($visitor,$base);
    $r=request($visitor,$base.'/login.php',['csrf_token'=>$token,'email'=>$email,'password'=>'Temporary123!']);
    check($r['status']===302,'Generated demo identity can log in again');
    request($visitor,$base.'/logout.php',['csrf_token'=>$token]);
    for($i=0;$i<9;$i++) {
        $attempt=client(); $attemptToken=token($attempt,$base);
        $r=request($attempt,$base.'/login.php',['csrf_token'=>$attemptToken,'email'=>$email,'password'=>'wrong']);
        check($r['status']===200,'Login attempt '.($i+2).' remains within the ten-attempt window');
    }
    $attempt=client(); curl_setopt($attempt,CURLOPT_HTTPHEADER,['X-Forwarded-For: 198.51.100.200','Client-IP: 198.51.100.201']);
    $attemptToken=token($attempt,$base);
    $r=request($attempt,$base.'/login.php',['csrf_token'=>$attemptToken,'email'=>$email,'password'=>'Temporary123!']);
    check($r['status']===429 && str_contains($r['headers'],'Retry-After:'),'Login limit survives new sessions and spoofed client IP headers');
    check(request($attempt,$base.'/products.php')['status']===200,'Rate limits preserve read-only catalogue access');
    unlink($state); // Only this newly created test environment, after requests finish.

    $c=client(); $ct=token($c,$base);
    $contact=['csrf_token'=>$ct,'cSubject'=>'feedback','cFirstName'=>'Unexpected','cLastName'=>'Person','cEmail'=>'person@example.invalid','cPhone'=>'0123456789','cMessage'=>'Unexpected confidential text'];
    check(request($c,$base.'/contact.php',array_replace($contact,['website'=>'spam']))['status']===422,'Filled spam trap is rejected');
    check((int)$db->query('SELECT COUNT(*) FROM contact_messages')->fetchColumn()===0,'Spam trap creates no database row');
    check(request($c,$base.'/contact.php',$contact)['status']===302,'Sample contact submission succeeds');
    $message=$db->query('SELECT * FROM contact_messages LIMIT 1')->fetch();
    check($message['name']==='Demo Visitor' && $message['phone']===null && str_contains($message['email'],'@example.test') && $message['message']==='This is a fictional demonstration message. No reply or service is requested.','Contact never persists forged personal fields or free text');
    for($i=0;$i<3;$i++) request($c,$base.'/contact.php',$contact);
    $before=(int)$db->query('SELECT COUNT(*) FROM contact_messages')->fetchColumn();
    check(request($c,$base.'/contact.php',$contact)['status']===429 && (int)$db->query('SELECT COUNT(*) FROM contact_messages')->fetchColumn()===$before,'Contact attempts stop at the persistent limit without another row');
    unlink($state);

    $buyer=client(); $bt=token($buyer,$base); add($buyer,$base,$bt,2);
    request($buyer,$base.'/cart_action.php',['csrf_token'=>$bt,'action'=>'update','product_id'=>2,'quantity'=>4,'ajax'=>'1']);
    check(request($buyer,$base.'/checkout.php',checkoutData($bt))['status']===422,'Public demo rejects quantities above three per product');
    request($buyer,$base.'/cart_action.php',['csrf_token'=>$bt,'action'=>'update','product_id'=>2,'quantity'=>1,'ajax'=>'1']);
    for($i=0;$i<9;$i++) request($buyer,$base.'/checkout.php',array_replace(checkoutData($bt),['payment'=>'invalid']));
    check(request($buyer,$base.'/checkout.php',checkoutData($bt))['status']===429,'Checkout attempts are persistently rate limited');
    unlink($state);

    $account=client(); $at=token($account,$base);
    request($account,$base.'/login.php',['csrf_token'=>$at,'email'=>$email,'password'=>'Temporary123!']);
    $insert=$db->prepare("INSERT INTO orders (user_id,customer_name,customer_phone,payment_method,total) VALUES (?,'Demo Visitor','0100000000','cash',0)");
    for($i=1;$i<20;$i++) $insert->execute([$user['user_id']]);
    add($account,$base,$at,2);
    check(request($account,$base.'/checkout.php',checkoutData($at))['status']===429 && (int)$db->query('SELECT COUNT(*) FROM orders')->fetchColumn()===20,'Per-account order cap stops repeated authenticated creation');
    $insert=$db->prepare("INSERT INTO order_details (order_id,product_id,product_name,unit_price,quantity,subtotal) VALUES (?,2,'Sample item',1,1,1)");
    $db->beginTransaction();
    for($i=1;$i<5000;$i++) $insert->execute([$order['order_id']]);
    $db->commit();
    check(request($buyer,$base.'/checkout.php',checkoutData($bt))['status']===503 && (int)$db->query('SELECT COUNT(*) FROM order_details')->fetchColumn()===5000,'Order-line storage cap stops writes before the order table is full');
    unlink($state);

    // Two real web workers compete for the last remaining account slot.
    $insert=$db->prepare('INSERT INTO users (name,email,password) VALUES (?,?,?)');
    for($i=1;$i<199;$i++) $insert->execute(['Capacity Fixture','capacity-'.$i.'@example.test',$user['password']]);
    $a=client(); $b=client(); $ta=token($a,$base); $tb=token($b,$second);
    prepareRequest($a,$base.'/register.php',array_replace($reg,['csrf_token'=>$ta]));
    prepareRequest($b,$second.'/register.php',array_replace($reg,['csrf_token'=>$tb]));
    $multi=curl_multi_init(); curl_multi_add_handle($multi,$a); curl_multi_add_handle($multi,$b);
    do { curl_multi_exec($multi,$running); if($running) curl_multi_select($multi,.05); } while($running);
    $ra=response($a,curl_multi_getcontent($a)); $rb=response($b,curl_multi_getcontent($b));
    curl_multi_remove_handle($multi,$a); curl_multi_remove_handle($multi,$b); curl_multi_close($multi);
    check(($ra['status']===302?1:0)+($rb['status']===302?1:0)===1 && ($ra['status']===503 || $rb['status']===503),'Concurrent signup respects the last available storage slot');
    check((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()===200,'Account table cannot grow beyond the 200-record cap');
    unlink($state);
    $db->exec("INSERT INTO orders (user_id,customer_name,customer_phone,customer_email,payment_method,fulfillment,total) SELECT NULL,'Demo Visitor','0100000000','capacity@example.test','cash','pickup',0 FROM users");
    $count=(int)$db->query('SELECT COUNT(*) FROM orders')->fetchColumn();
    $insert=$db->prepare("INSERT INTO orders (customer_name,customer_phone,payment_method,total) VALUES ('Demo Visitor','0100000000','cash',0)");
    for($i=$count;$i<500;$i++) $insert->execute();
    check(request($buyer,$base.'/checkout.php',checkoutData($bt))['status']===503 && (int)$db->query('SELECT COUNT(*) FROM orders')->fetchColumn()===500,'Order storage cap rejects writes without deleting existing records');
    $insert=$db->prepare("INSERT INTO contact_messages (name,email,subject,message) VALUES ('Demo Visitor','capacity@example.test','Feedback','Sample message')");
    for($i=(int)$db->query('SELECT COUNT(*) FROM contact_messages')->fetchColumn();$i<200;$i++) $insert->execute();
    check(request($c,$base.'/contact.php',$contact)['status']===503 && (int)$db->query('SELECT COUNT(*) FROM contact_messages')->fetchColumn()===200,'Contact storage cap rejects writes without deleting existing records');
    file_put_contents($state,'deliberately corrupt test state');
    $r=request($attempt,$base.'/login.php',['csrf_token'=>$attemptToken,'email'=>$email,'password'=>'wrong']);
    check($r['status']===503 && !str_contains($r['body'],'corrupt') && !str_contains($r['body'],$temp),'Broken limiter fails closed with a generic response');
    check(request(client(),$base.'/index.php')['status']===200,'Broken submission protection leaves public browsing available');
}
