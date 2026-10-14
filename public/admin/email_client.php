<?php
declare(strict_types=1);

/**
 * Admin Email Client
 * Read emails from info@aleppogift.com (IMAP) and compose/send via SMTP
 *
 * IMAP:  imap.hostinger.com:993 SSL
 * SMTP:  smtp.hostinger.com:465 SSL
 */

$root_dir = dirname(dirname(__DIR__));
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/session_helper.php');

require_admin_login();

require_once $root_dir . '/includes/Database.php';
require_once $root_dir . '/includes/functions.php';
require_once $root_dir . '/includes/store_features.php';
$mailDb = new Database();
$savedPassword = store_setting($mailDb, 'smtp_password', '');
if (!defined('EMAIL_ACCOUNT')) {
    define('EMAIL_ACCOUNT', store_setting($mailDb, 'smtp_username', 'info@aleppogift.com'));
}
if (!defined('EMAIL_PASSWORD')) {
    define('EMAIL_PASSWORD', $savedPassword !== '' ? $savedPassword : (defined('SMTP_PASSWORD') ? SMTP_PASSWORD : ''));
}
if (!defined('IMAP_HOST')) {
    define('IMAP_HOST', store_setting($mailDb, 'imap_host', 'imap.hostinger.com'));
}
if (!defined('IMAP_PORT')) {
    define('IMAP_PORT', (int)store_setting($mailDb, 'imap_port', '993'));
}
if (!defined('SMTP_HOST_MAIL')) {
    define('SMTP_HOST_MAIL', store_setting($mailDb, 'smtp_host', defined('SMTP_HOST') ? SMTP_HOST : 'smtp.hostinger.com'));
}
if (!defined('SMTP_PORT_MAIL')) {
    define('SMTP_PORT_MAIL', (int)store_setting($mailDb, 'smtp_port', defined('SMTP_PORT') ? (string)SMTP_PORT : '465'));
}

$imap_available = function_exists('imap_open');

// ─── CSRF token ───────────────────────────────────────────────────────────────
if (empty($_SESSION['email_csrf'])) {
    $_SESSION['email_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['email_csrf'];

// ─── Helpers ─────────────────────────────────────────────────────────────────
function imap_connect_inbox(): mixed {
    if (!function_exists('imap_open')) {
        return false;
    }
    $mailbox = '{' . IMAP_HOST . ':' . IMAP_PORT . '/imap/ssl/novalidate-cert}INBOX';
    $conn = @imap_open($mailbox, EMAIL_ACCOUNT, EMAIL_PASSWORD);
    if (!$conn) {
        return false;
    }
    return $conn;
}

function decode_header_value(string $value): string {
    $decoded = imap_mime_header_decode($value);
    $result  = '';
    foreach ($decoded as $part) {
        $charset = strtolower($part->charset ?? 'default');
        if ($charset === 'default' || $charset === 'us-ascii') {
            $result .= $part->text;
        } else {
            $converted = @mb_convert_encoding($part->text, 'UTF-8', $charset);
            $result   .= ($converted !== false) ? $converted : $part->text;
        }
    }
    return $result;
}

function get_email_body(mixed $conn, int $msgno): string {
    $structure = imap_fetchstructure($conn, $msgno);
    $body      = '';

    if ($structure->type === 0) {
        // plain text only
        $raw = imap_fetchbody($conn, $msgno, '1');
        $raw = decode_transfer_encoding($raw, $structure->encoding ?? 0);
        $body = nl2br(htmlspecialchars($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    } elseif ($structure->type === 1 && isset($structure->parts)) {
        // multipart
        foreach ($structure->parts as $idx => $part) {
            $section = (string)($idx + 1);
            $raw     = imap_fetchbody($conn, $msgno, $section);
            $raw     = decode_transfer_encoding($raw, $part->encoding ?? 0);
            if ($part->type === 0) {
                $subtype = strtolower($part->subtype ?? 'plain');
                if ($subtype === 'html') {
                    // sanitise but keep HTML
                    $body = purify_html($raw);
                    break;
                }
                if ($body === '') {
                    $body = nl2br(htmlspecialchars($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
                }
            }
        }
    } else {
        $raw  = imap_body($conn, $msgno);
        $raw  = decode_transfer_encoding($raw, $structure->encoding ?? 0);
        $body = nl2br(htmlspecialchars($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }
    return $body;
}

function decode_transfer_encoding(string $data, int $encoding): string {
    return match ($encoding) {
        1  => imap_utf8($data),        // 8bit
        3  => base64_decode($data),    // BASE64
        4  => quoted_printable_decode($data), // QP
        default => $data,
    };
}

/**
 * Minimal HTML purifier – strips dangerous tags/attributes.
 */
function purify_html(string $html): string {
    // Remove script, object, embed, iframe, form, style blocks, and on* attributes
    $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
    $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
    $html = preg_replace('/<(object|embed|iframe|form|input|button|textarea|select)[^>]*>.*?<\/\1>/is', '', $html);
    $html = preg_replace('/<(object|embed|iframe|form|input|button|textarea|select)[^>]*\/?>/is', '', $html);
    // Remove on* event attributes
    $html = preg_replace('/\s+on\w+="[^"]*"/i', '', $html);
    $html = preg_replace('/\s+on\w+=\'[^\']*\'/i', '', $html);
    // Remove javascript: hrefs/srcs
    $html = preg_replace('/\b(href|src|action)\s*=\s*["\']?\s*javascript:/i', '$1="#', $html);
    return $html;
}

function send_smtp(string $to, string $to_name, string $subject, string $body_html, string $body_text, array $cc = []): bool|string {
    $root = dirname(dirname(dirname(__FILE__)));
    $autoload = $root . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        return 'PHPMailer not found. Cannot send email.';
    }
    require_once $autoload;

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host        = SMTP_HOST_MAIL;
        $mail->SMTPAuth    = true;
        $mail->Username    = EMAIL_ACCOUNT;
        $mail->Password    = EMAIL_PASSWORD;
        $mail->SMTPSecure  = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port        = SMTP_PORT_MAIL;
        $mail->CharSet     = 'UTF-8';

        $mail->setFrom(EMAIL_ACCOUNT, 'AleppoGift Info');
        $mail->addAddress($to, $to_name);
        foreach ($cc as $cc_addr) {
            if (filter_var(trim($cc_addr), FILTER_VALIDATE_EMAIL)) {
                $mail->addCC(trim($cc_addr));
            }
        }
        $mail->addReplyTo(EMAIL_ACCOUNT, 'AleppoGift Info');

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body_html;
        $mail->AltBody = $body_text ?: strip_tags($body_html);

        $mail->send();
        return true;
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        return $mail->ErrorInfo;
    }
}

// ─── Action dispatcher ───────────────────────────────────────────────────────
$action   = $_GET['action'] ?? 'inbox';
$message  = '';
$msg_type = '';

// Handle send
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
    // CSRF check
    if (!hash_equals($csrf, $_POST['csrf_token'] ?? '')) {
        $message  = 'Security token mismatch. Please try again.';
        $msg_type = 'danger';
    } else {
        $to      = filter_var(trim($_POST['to'] ?? ''), FILTER_VALIDATE_EMAIL);
        $to_name = htmlspecialchars(strip_tags(trim($_POST['to_name'] ?? '')));
        $subject = htmlspecialchars(strip_tags(trim($_POST['subject'] ?? '')));
        $body    = trim($_POST['body'] ?? '');
        $cc_raw  = array_filter(array_map('trim', explode(',', $_POST['cc'] ?? '')));

        if (!$to) {
            $message  = 'Invalid recipient email address.';
            $msg_type = 'danger';
        } elseif ($subject === '') {
            $message  = 'Subject is required.';
            $msg_type = 'danger';
        } elseif ($body === '') {
            $message  = 'Email body is required.';
            $msg_type = 'danger';
        } else {
            $body_html = nl2br(htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
            $result    = send_smtp($to, $to_name, $subject, $body_html, $body, $cc_raw);
            if ($result === true) {
                $message  = "Email sent successfully to {$to}.";
                $msg_type = 'success';
                $action   = 'inbox';
            } else {
                $message  = 'Send failed: ' . htmlspecialchars((string)$result);
                $msg_type = 'danger';
            }
        }
    }
}

// Handle delete
if ($action === 'delete' && isset($_GET['msgno']) && $imap_available) {
    if (!hash_equals($csrf, $_GET['csrf'] ?? '')) {
        $message = 'Invalid CSRF token.';
        $msg_type = 'danger';
    } else {
        $conn = imap_connect_inbox();
        if ($conn) {
            $msgno = (int)$_GET['msgno'];
            imap_delete($conn, (string)$msgno);
            imap_expunge($conn);
            imap_close($conn);
            $message  = 'Email deleted.';
            $msg_type = 'success';
        }
    }
    $action = 'inbox';
}

// ─── Load inbox ──────────────────────────────────────────────────────────────
$emails       = [];
$imap_error   = '';
$total_emails = 0;
$per_page     = 20;
$page_num     = max(1, (int)($_GET['pg'] ?? 1));

if ($action === 'inbox' && $imap_available) {
    $conn = imap_connect_inbox();
    if ($conn === false) {
        $imap_error = 'Could not connect to mailbox. ' . imap_last_error();
    } else {
        $total_emails = imap_num_msg($conn);
        // Newest first: start from the highest msgno
        $start = $total_emails - ($page_num - 1) * $per_page;
        $end   = max(1, $start - $per_page + 1);
        if ($start >= 1) {
            $msglist = range($start, $end);
            foreach ($msglist as $msgno) {
                $header = imap_headerinfo($conn, $msgno);
                if (!$header) {
                    continue;
                }
                $from_obj = $header->from[0] ?? null;
                $from     = $from_obj ? decode_header_value($from_obj->personal ?? $from_obj->mailbox ?? 'Unknown') : 'Unknown';
                $from_email = $from_obj ? ($from_obj->mailbox . '@' . ($from_obj->host ?? '')) : '';
                $emails[] = [
                    'msgno'    => $msgno,
                    'from'     => $from,
                    'from_email' => $from_email,
                    'subject'  => decode_header_value($header->subject ?? '(no subject)'),
                    'date'     => $header->date ?? '',
                    'seen'     => (bool)($header->Seen ?? false),
                    'deleted'  => (bool)($header->Deleted ?? false),
                ];
            }
        }
        imap_close($conn);
    }
}

// ─── Load single email ───────────────────────────────────────────────────────
$email_body    = '';
$email_detail  = null;
if ($action === 'view' && isset($_GET['msgno']) && $imap_available) {
    $conn = imap_connect_inbox();
    if ($conn) {
        $msgno = (int)$_GET['msgno'];
        // Mark as read
        imap_setflag_full($conn, (string)$msgno, '\\Seen');
        $header = imap_headerinfo($conn, $msgno);
        if ($header) {
            $from_obj = $header->from[0] ?? null;
            $email_detail = [
                'msgno'      => $msgno,
                'from'       => decode_header_value($from_obj->personal ?? $from_obj->mailbox ?? 'Unknown'),
                'from_email' => $from_obj ? ($from_obj->mailbox . '@' . ($from_obj->host ?? '')) : '',
                'subject'    => decode_header_value($header->subject ?? '(no subject)'),
                'date'       => $header->date ?? '',
            ];
        }
        $email_body = get_email_body($conn, $msgno);
        imap_close($conn);
    }
}

// ─── Page setup ──────────────────────────────────────────────────────────────
$page_title = 'Email Client — info@aleppogift.com';
require __DIR__ . '/includes/header.php';
?>

<style>
.email-toolbar { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:18px; }
.email-list { border-radius:8px; overflow:hidden; border:1px solid #e9ecef; }
.email-row { display:grid; grid-template-columns:2fr 3fr 1.5fr auto; align-items:center;
    padding:12px 16px; border-bottom:1px solid #f0f0f0; gap:12px; transition:background .15s; }
.email-row:last-child { border-bottom:none; }
.email-row:hover { background:#f8f9fa; }
.email-row.unread .email-subject-link { font-weight:700; }
.email-from { font-weight:600; font-size:.875rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.email-from-addr { font-size:.75rem; color:#6c757d; }
.email-subject-link { font-size:.875rem; color:#333; text-decoration:none; }
.email-subject-link:hover { color:#d4af37; text-decoration:underline; }
.email-date { font-size:.75rem; color:#6c757d; white-space:nowrap; }
.compose-card { max-width:780px; }
.email-view-body { background:#fff; border:1px solid #dee2e6; border-radius:8px; padding:20px;
    min-height:200px; font-size:.9rem; line-height:1.7; }
.pagination-bar { display:flex; gap:8px; align-items:center; margin-top:16px; }
.imap-notice { background:#fff3cd; border:1px solid #ffc107; border-radius:6px; padding:14px 18px; color:#856404; }
.badge-unread { background:#d4af37; color:#fff; border-radius:20px; padding:2px 8px; font-size:.7rem; }
</style>

<main class="admin-content">
<?php if ($message): ?>
    <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!$imap_available): ?>
    <div class="imap-notice mb-4">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>IMAP extension not enabled</strong> on this PHP installation — email reading is unavailable in the local environment.
        On the <strong>Hostinger server</strong>, the <code>imap</code> extension is enabled and the inbox will display here.
        You can still <strong>compose and send</strong> emails below.
    </div>
<?php endif; ?>

<?php if ($action === 'view' && $email_detail): ?>
    <!-- ── VIEW EMAIL ─────────────────────────────────────────────────── -->
    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-envelope-open me-2"></i><?php echo htmlspecialchars($email_detail['subject']); ?></span>
            <a href="email_client.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back to Inbox</a>
        </div>
        <div class="card-body">
            <table class="table table-sm table-borderless mb-4">
                <tr><th style="width:80px">From</th><td><?php echo htmlspecialchars($email_detail['from']); ?> &lt;<?php echo htmlspecialchars($email_detail['from_email']); ?>&gt;</td></tr>
                <tr><th>Date</th><td><?php echo htmlspecialchars($email_detail['date']); ?></td></tr>
                <tr><th>Subject</th><td><?php echo htmlspecialchars($email_detail['subject']); ?></td></tr>
            </table>
            <div class="email-view-body"><?php echo $email_body; ?></div>
            <div class="mt-3 d-flex gap-2">
                <!-- Reply button pre-fills compose -->
                <a href="email_client.php?action=compose&to=<?php echo urlencode($email_detail['from_email']); ?>&to_name=<?php echo urlencode($email_detail['from']); ?>&subject=<?php echo urlencode('Re: ' . $email_detail['subject']); ?>"
                   class="btn btn-primary btn-sm"><i class="fas fa-reply me-1"></i>Reply</a>
                <a href="email_client.php?action=delete&msgno=<?php echo $email_detail['msgno']; ?>&csrf=<?php echo $csrf; ?>"
                   class="btn btn-outline-danger btn-sm"
                   onclick="return confirm('Delete this email?')"><i class="fas fa-trash me-1"></i>Delete</a>
                <a href="email_client.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-inbox me-1"></i>Inbox</a>
            </div>
        </div>
    </div>

<?php elseif ($action === 'compose'): ?>
    <!-- ── COMPOSE ───────────────────────────────────────────────────── -->
    <div class="card shadow-sm compose-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-pen me-2"></i>Compose New Email</span>
            <a href="email_client.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-inbox me-1"></i>Inbox</a>
        </div>
        <div class="card-body">
            <form method="POST" action="email_client.php?action=compose" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                <input type="hidden" name="send_email" value="1">

                <div class="mb-3 row">
                    <label class="col-sm-2 col-form-label">From</label>
                    <div class="col-sm-10">
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars(EMAIL_ACCOUNT); ?>" disabled>
                    </div>
                </div>
                <div class="mb-3 row">
                    <label class="col-sm-2 col-form-label">To <span class="text-danger">*</span></label>
                    <div class="col-sm-10">
                        <input type="email" class="form-control" name="to" required
                               value="<?php echo htmlspecialchars($_GET['to'] ?? ''); ?>"
                               placeholder="recipient@example.com">
                    </div>
                </div>
                <div class="mb-3 row">
                    <label class="col-sm-2 col-form-label">Name</label>
                    <div class="col-sm-10">
                        <input type="text" class="form-control" name="to_name"
                               value="<?php echo htmlspecialchars($_GET['to_name'] ?? ''); ?>"
                               placeholder="Recipient Name (optional)">
                    </div>
                </div>
                <div class="mb-3 row">
                    <label class="col-sm-2 col-form-label">CC</label>
                    <div class="col-sm-10">
                        <input type="text" class="form-control" name="cc" placeholder="cc1@example.com, cc2@example.com (comma-separated)">
                    </div>
                </div>
                <div class="mb-3 row">
                    <label class="col-sm-2 col-form-label">Subject <span class="text-danger">*</span></label>
                    <div class="col-sm-10">
                        <input type="text" class="form-control" name="subject" required
                               value="<?php echo htmlspecialchars($_GET['subject'] ?? ''); ?>">
                    </div>
                </div>
                <div class="mb-3 row">
                    <label class="col-sm-2 col-form-label">Body <span class="text-danger">*</span></label>
                    <div class="col-sm-10">
                        <textarea class="form-control" name="body" rows="12" required
                                  placeholder="Write your message here…"></textarea>
                    </div>
                </div>
                <div class="text-end">
                    <a href="email_client.php" class="btn btn-outline-secondary me-2">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-1"></i>Send Email
                    </button>
                </div>
            </form>
        </div>
    </div>

<?php else: ?>
    <!-- ── INBOX ─────────────────────────────────────────────────────── -->
    <div class="email-toolbar">
        <a href="email_client.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-sync-alt me-1"></i>Refresh</a>
        <a href="email_client.php?action=compose" class="btn btn-sm btn-primary"><i class="fas fa-pen me-1"></i>Compose</a>
        <?php if ($imap_available && $total_emails > 0): ?>
            <span class="ms-auto text-muted" style="font-size:.85rem;">
                <?php echo $total_emails; ?> email<?php echo $total_emails !== 1 ? 's' : ''; ?> in inbox
            </span>
        <?php endif; ?>
    </div>

    <?php if ($imap_error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($imap_error); ?></div>
    <?php endif; ?>

    <?php if ($imap_available && !$imap_error): ?>
        <?php if (empty($emails)): ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-inbox fa-3x mb-3"></i><br>Inbox is empty
            </div>
        <?php else: ?>
            <div class="card shadow-sm">
                <div class="card-header"><i class="fas fa-inbox me-2"></i>Inbox — <?php echo htmlspecialchars(EMAIL_ACCOUNT); ?></div>
                <div class="card-body p-0">
                    <div class="email-list">
                        <?php foreach ($emails as $em): ?>
                            <div class="email-row <?php echo !$em['seen'] ? 'unread' : ''; ?>">
                                <div>
                                    <div class="email-from"><?php echo htmlspecialchars($em['from']); ?></div>
                                    <div class="email-from-addr"><?php echo htmlspecialchars($em['from_email']); ?></div>
                                </div>
                                <div>
                                    <a href="email_client.php?action=view&msgno=<?php echo $em['msgno']; ?>" class="email-subject-link">
                                        <?php if (!$em['seen']): ?><span class="badge-unread me-1">NEW</span><?php endif; ?>
                                        <?php echo htmlspecialchars($em['subject']); ?>
                                    </a>
                                </div>
                                <div class="email-date"><?php echo htmlspecialchars($em['date']); ?></div>
                                <div>
                                    <a href="email_client.php?action=delete&msgno=<?php echo $em['msgno']; ?>&csrf=<?php echo $csrf; ?>"
                                       class="btn btn-outline-danger btn-sm py-0"
                                       onclick="return confirm('Delete this email?')" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <?php
            $total_pages = (int)ceil($total_emails / $per_page);
            if ($total_pages > 1): ?>
                <div class="pagination-bar mt-3">
                    <?php if ($page_num > 1): ?>
                        <a href="?pg=<?php echo $page_num - 1; ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-chevron-left"></i> Prev</a>
                    <?php endif; ?>
                    <span class="text-muted" style="font-size:.85rem;">Page <?php echo $page_num; ?> / <?php echo $total_pages; ?></span>
                    <?php if ($page_num < $total_pages): ?>
                        <a href="?pg=<?php echo $page_num + 1; ?>" class="btn btn-sm btn-outline-secondary">Next <i class="fas fa-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-body text-center text-muted py-5">
                <i class="fas fa-envelope fa-3x mb-3 d-block"></i>
                Email reading requires the PHP IMAP extension (enabled on Hostinger production server).<br>
                You can still use <a href="email_client.php?action=compose">Compose</a> to send emails from this page.
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
