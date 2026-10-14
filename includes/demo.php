<?php
/** Public-demo policy. No remote services, cron jobs or schema changes required. */
function public_demo_mode(): bool
{
    // Disable only in a trusted server environment for local coursework tests.
    return getenv('MCKL_DEMO_MODE') !== '0';
}

function demo_email(): string
{
    if (empty($_SESSION['demo_email'])) {
        $_SESSION['demo_email'] = 'demo-' . bin2hex(random_bytes(6)) . '@example.test';
    }
    return $_SESSION['demo_email'];
}

function is_demo_email(string $email): bool
{
    return preg_match('/^demo-[a-f0-9]{12}@example\.test$/D', $email) === 1;
}

function demo_message(): string
{
    return 'This is a fictional demonstration message. No reply or service is requested.';
}

function demo_honeypot(): void
{
    if (public_demo_mode()) echo '<label hidden aria-hidden="true">Leave empty<input name="website" tabindex="-1" autocomplete="off"></label>';
}

/** Replace personal fields BEFORE validation, reflection or database writes. */
function demo_prepare_post(string $action, string $accountEmail = ''): void
{
    if (!public_demo_mode()) return;
    $email = is_demo_email($accountEmail) ? $accountEmail : demo_email();
    if ($action === 'register') {
        $_POST = array_replace($_POST, ['name'=>'Demo Visitor', 'email'=>demo_email(), 'phone'=>'']);
    } elseif ($action === 'checkout') {
        $_POST = array_replace($_POST, ['firstName'=>'Demo', 'lastName'=>'Visitor', 'email'=>$email,
            'phone'=>'0100000000', 'address'=>'10 Example Street, Demo City']);
    } elseif ($action === 'contact') {
        $_POST = array_replace($_POST, ['cFirstName'=>'Demo', 'cLastName'=>'Visitor', 'cEmail'=>demo_email(),
            'cPhone'=>'', 'cMessage'=>demo_message()]);
    }
}

function demo_stop(int $status, string $message, int $retry = 0): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    if ($retry > 0) header('Retry-After: ' . $retry);
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Demo request paused | MCKL Mart</title><main><h1>Demo request paused</h1><p>'
        . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
        . '</p><p><a href="index.php">Return to MCKL Mart</a></p></main></html>';
    exit;
}

/** One bounded file and lock, shared by PHP workers and independent of sessions. */
final class DemoWriteLease
{
    private $handle;
    private const PREFIX = "<?php http_response_code(403); exit; ?>\n";
    private const MAX_BYTES = 262144;
    private array $state;

    public function __construct(string $path)
    {
        $this->handle = @fopen($path, 'c+b');
        if (!$this->handle) throw new RuntimeException('Demo state unavailable');
        $deadline = microtime(true) + 2;
        while (!flock($this->handle, LOCK_EX | LOCK_NB)) {
            if (microtime(true) >= $deadline) throw new RuntimeException('Demo state busy');
            usleep(20000);
        }
        $size = fstat($this->handle)['size'];
        if ($size > self::MAX_BYTES) throw new RuntimeException('Demo state oversized');
        $raw = stream_get_contents($this->handle);
        if ($raw === false) throw new RuntimeException('Demo state unreadable');
        if ($raw === '') {
            $this->state = ['key'=>bin2hex(random_bytes(32)), 'buckets'=>[]];
        } else {
            if (!str_starts_with($raw, self::PREFIX)) throw new RuntimeException('Demo state invalid');
            $this->state = json_decode(substr($raw, strlen(self::PREFIX)), true, 16, JSON_THROW_ON_ERROR);
            if (!is_string($this->state['key'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $this->state['key'])
                || !is_array($this->state['buckets'] ?? null) || count($this->state['buckets']) > 1024) {
                throw new RuntimeException('Demo state invalid');
            }
        }
    }

    /** Fixed windows; rejected attempts do not extend the window. No raw IPs/emails. */
    public function consume(array $rules, int $now): int
    {
        foreach ($this->state['buckets'] as $key => $bucket) {
            if (!is_array($bucket) || !is_int($bucket['until'] ?? null) || !is_int($bucket['count'] ?? null)
                || $bucket['count'] < 0) throw new RuntimeException('Demo bucket invalid');
            if ($bucket['until'] <= $now) unset($this->state['buckets'][$key]);
        }
        $pending = [];
        $retry = 0;
        foreach ($rules as [$identity, $limit, $seconds]) {
            $key = hash_hmac('sha256', $identity . ':' . $seconds, $this->state['key']);
            $bucket = $this->state['buckets'][$key] ?? ['count'=>0, 'until'=>$now + $seconds];
            if ($bucket['count'] >= $limit) $retry = max($retry, $bucket['until'] - $now);
            $bucket['count']++;
            $pending[$key] = $bucket;
        }
        if ($retry > 0) return $retry;
        if (count(array_replace($this->state['buckets'], $pending)) > 1024) return 60;
        $this->state['buckets'] = array_replace($this->state['buckets'], $pending);
        $encoded = self::PREFIX . json_encode($this->state, JSON_THROW_ON_ERROR);
        if (strlen($encoded) > self::MAX_BYTES) throw new RuntimeException('Demo state full');
        rewind($this->handle);
        if (fwrite($this->handle, $encoded) !== strlen($encoded)
            || !ftruncate($this->handle, strlen($encoded)) || !fflush($this->handle)) {
            throw new RuntimeException('Demo state write failed');
        }
        return 0;
    }

    public function __destruct()
    {
        if (is_resource($this->handle)) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
        }
    }
}

function demo_rate_rules(string $action, string $ip, string $email = ''): array
{
    // REMOTE_ADDR only: client-supplied forwarding headers cannot bypass a limit.
    $rules = match ($action) {
        'login' => [["login:ip:$ip",10,900], ['login:global',200,900]],
        'register' => [["register:ip:$ip",3,86400], ['register:global',50,86400]],
        'contact' => [["contact:ip:$ip",5,3600], ['contact:global',100,86400]],
        'checkout' => [["checkout:ip:$ip",10,3600], ["checkout:day:$ip",20,86400], ['checkout:global',100,86400]],
        default => throw new InvalidArgumentException('Unknown demo action'),
    };
    if ($action === 'login' && $email !== '') $rules[] = ['login:account:' . strtolower(substr($email,0,150)),10,900];
    return $rules;
}

/** Hold this lease through commit: concurrent requests cannot race record caps. */
function demo_guard(PDO $pdo, string $action, string $email = ''): ?DemoWriteLease
{
    if (!public_demo_mode()) return null;
    try {
        $path = getenv('MCKL_DEMO_STATE_PATH') ?: __DIR__ . '/.demo-state.php';
        $lease = new DemoWriteLease($path);
        $retry = $lease->consume(demo_rate_rules($action, $_SERVER['REMOTE_ADDR'] ?? 'unknown', $email), time());
        if ($retry > 0) demo_stop(429, 'Too many demo attempts. Please try again after the waiting period.', $retry);
        if (!empty($_POST['website'])) demo_stop(422, 'The demonstration form could not be accepted.');
        $capacity = match ($action) {
            'register' => ['users',200], 'checkout' => ['orders',500], 'contact' => ['contact_messages',200], default => null,
        };
        // Table names come only from this fixed allowlist, never from a request.
        if ($capacity && (int)$pdo->query('SELECT COUNT(*) FROM ' . $capacity[0])->fetchColumn() >= $capacity[1]) {
            demo_stop(503, 'Demo storage is full. Browsing remains available; the owner needs to review demo records.', 3600);
        }
        if ($action === 'checkout') {
            $cart = $_SESSION['cart'] ?? [];
            if (count($cart) > 10 || array_filter($cart, static fn($qty) => !is_int($qty) || $qty < 1 || $qty > 3)) {
                demo_stop(422, 'Demo orders allow up to 10 different products, with 1–3 of each. Please update your cart.');
            }
            if ((int)$pdo->query('SELECT COUNT(*) FROM order_details')->fetchColumn() + count($cart) > 5000) {
                demo_stop(503, 'Demo order storage is full. Please try browsing the catalogue instead.', 3600);
            }
            if (isset($_SESSION['user_id'])) {
                $count = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id = ?');
                $count->execute([$_SESSION['user_id']]);
                if ((int)$count->fetchColumn() >= 20) demo_stop(429, 'This demo account has reached its 20-order limit.');
            }
        }
        return $lease;
    } catch (Throwable $error) {
        error_log('MCKL Mart: demo protection unavailable');
        demo_stop(503, 'Demo submissions are temporarily unavailable. Please try browsing the catalogue instead.', 60);
    }
}
