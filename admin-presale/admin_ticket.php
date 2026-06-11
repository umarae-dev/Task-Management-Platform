
<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// Database connection file include (Matches your dashboard paths)


// // ---- Admin auth guard (Checks if admin is logged in) ----
// if (!isset($_SESSION['admin_id'])) {
//     // Redirect to login if not authenticated
//     header('Location: admin_login.php');
//     exit;
// }

$message = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ticketId = (int) ($_POST['ticket_id'] ?? 0);
    $action   = $_POST['action'] ?? '';

    if ($ticketId && in_array($action, ['approve', 'reject', 'revoke'])) {

        $stmtT = $conn->prepare("SELECT * FROM tickets_purchased WHERE id = ? LIMIT 1");
        $stmtT->bind_param("i", $ticketId);
        $stmtT->execute();
        $ticket = $stmtT->get_result()->fetch_assoc();
        $stmtT->close();

        if (!$ticket) {
            $message = 'Ticket not found.';
            $msgType = 'error';

        } elseif ($action === 'revoke') {
            // ============================================================
            // REVOKE: Delete ticket, reset user to Standard, deduct 3000 UQX
            // Works on ANY status (Approved, Pending, Rejected)
            // ============================================================
            $conn->begin_transaction();
            try {
                $revokeUserId = (int)$ticket['user_id'];

                // 1. Reset user back to standard + clear cooldown
                $s1 = $conn->prepare("UPDATE users SET user_type = 'standard', last_withdrawal_at = NULL WHERE id = ?");
                $s1->bind_param("i", $revokeUserId);
                $s1->execute();
                $s1->close();

                // 2. Deduct 3000 UQX bonus (won't go below 0)
                $s2 = $conn->prepare("UPDATE uqx_wallet SET balance = GREATEST(0, balance - 3000) WHERE user_id = ?");
                $s2->bind_param("i", $revokeUserId);
                $s2->execute();
                $s2->close();

                // 3. Delete ticket record
                $s3 = $conn->prepare("DELETE FROM tickets_purchased WHERE id = ?");
                $s3->bind_param("i", $ticketId);
                $s3->execute();
                $s3->close();

                $conn->commit();
                $message = "🔄 Ticket revoked. User #{$revokeUserId} is now Standard — 3,000 UQX deducted, cooldown cleared, 30-day withdrawal rule active.";
                $msgType = 'success';

            } catch (Exception $e) {
                $conn->rollback();
                $message = 'Revoke failed: ' . htmlspecialchars($e->getMessage());
                $msgType = 'error';
            }

        } elseif ($ticket['status'] !== 'Pending') {
            $message = 'This ticket has already been processed.';
            $msgType = 'error';

        } elseif ($action === 'approve') {

            // Safe Database Transaction
            $conn->begin_transaction();
            try {
                // Lock the sequence row to prevent race conditions & duplicates
                $seqCheck = $conn->query("SELECT last_number FROM ticket_sequence WHERE id = 1 FOR UPDATE");
                $seqRow = $seqCheck->fetch_assoc();

                if (!$seqRow) {
                    // Initialize if missing
                    $conn->query("INSERT IGNORE INTO ticket_sequence (id, last_number) VALUES (1, 1000)");
                    $newNum = 1001;
                } else {
                    $newNum = (int)$seqRow['last_number'] + 1;
                }

                $ticketNumber = 'UQX-' . $newNum;

                // Update sequence table
                $stmtUpSeq = $conn->prepare("UPDATE ticket_sequence SET last_number = ? WHERE id = 1");
                $stmtUpSeq->bind_param("i", $newNum);
                $stmtUpSeq->execute();
                $stmtUpSeq->close();

                // Update tickets table to approved status
                $stmtUpT = $conn->prepare("UPDATE tickets_purchased SET status = 'Approved', ticket_number = ? WHERE id = ?");
                $stmtUpT->bind_param("si", $ticketNumber, $ticketId);
                $stmtUpT->execute();
                $stmtUpT->close();

                // Upgrade user to VIP and credit 3,000 UQX instantly (Main Users Table)
                $stmtUpU = $conn->prepare("UPDATE users SET user_type = 'ticket_holder', uqx_balance = uqx_balance + 3000 WHERE id = ?");
                $stmtUpU->bind_param("i", $ticket['user_id']);
                $stmtUpU->execute();
                $stmtUpU->close();

                // UPDATE UQX WALLET (Mining Dashboard relies on this table)
                $walletCheck = $conn->query("SELECT balance FROM uqx_wallet WHERE user_id = " . intval($ticket['user_id']));
                if ($walletCheck && $walletCheck->num_rows > 0) {
                    $conn->query("UPDATE uqx_wallet SET balance = balance + 3000 WHERE user_id = " . intval($ticket['user_id']));
                } else {
                    $conn->query("INSERT INTO uqx_wallet (user_id, balance) VALUES (" . intval($ticket['user_id']) . ", 3000)");
                }

                // ═══════════════════════════════════════════════════════════════
                // ✅ TICKET REFERRAL COMMISSION (300 PKR) — NEW CODE INSERT HERE
                // ═══════════════════════════════════════════════════════════════
                // Check if user was referred for tickets
                $ref_stmt = $conn->prepare("SELECT ticket_referrer_id FROM users WHERE id = ? AND ticket_referrer_id IS NOT NULL");
                $ref_stmt->bind_param("i", $ticket['user_id']);
                $ref_stmt->execute();
                $ref_stmt->bind_result($ticket_referrer_id);
                $ref_stmt->fetch();
                $ref_stmt->close();

                if ($ticket_referrer_id && $ticket_referrer_id != $ticket['user_id']) {
                    // Get live USD rate
                    $rate_row = $conn->query("SELECT rate FROM currency_rate_cache WHERE currency_from='USD' AND currency_to='PKR' LIMIT 1")->fetch_assoc();
                    $usd_rate = $rate_row ? floatval($rate_row['rate']) : 280.00;
                    
                    $commission_pkr = 300.00;
                    $commission_usd = round($commission_pkr / $usd_rate, 4);
                    
                    // Add to referrer's available balance
                    $conn->query("UPDATE users SET available_balance = available_balance + $commission_usd WHERE id = $ticket_referrer_id");
                    
                    // Log in ticket_referral_earnings
                    $log_stmt = $conn->prepare("INSERT INTO ticket_referral_earnings 
                        (referrer_id, referred_id, ticket_id, amount_pkr, amount_usd, usd_rate, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, 'approved', NOW())");
                    $log_stmt->bind_param("iiiddd", $ticket_referrer_id, $ticket['user_id'], $ticketId, $commission_pkr, $commission_usd, $usd_rate);
                    $log_stmt->execute();
                    $log_stmt->close();
                }
                // ═══════════════════════════════════════════════════════════════
                // END NEW CODE
                // ═══════════════════════════════════════════════════════════════

                $conn->commit();
                $message = "✅ Ticket #{$ticketNumber} approved successfully! User is now VIP & credited with 3,000 UQX.";
                $msgType = 'success';

            } catch (Exception $e) {
                $conn->rollback();
                $message = 'Database transaction failed: ' . htmlspecialchars($e->getMessage());
                $msgType = 'error';
            }

        } elseif ($action === 'reject') {
            // Reject action
            $stmtRej = $conn->prepare("UPDATE tickets_purchased SET status = 'Rejected' WHERE id = ?");
            $stmtRej->bind_param("i", $ticketId);
            $stmtRej->execute();
            $stmtRej->close();

            $message = 'Ticket request rejected successfully.';
            $msgType = 'warning';
        }
    }
}

$filter = $_GET['filter'] ?? 'Pending';
$allowedFilters = ['Pending','Approved','Rejected','all'];
if (!in_array($filter, $allowedFilters)) {
    $filter = 'Pending';
}

// Fetch all tickets depending on filter
$tickets = [];
if ($filter === 'all') {
    $stmtFetch = $conn->prepare("
        SELECT t.*, u.email, u.wallet_address, COALESCE(w.balance, u.uqx_balance, 0) as uqx_balance
        FROM tickets_purchased t
        JOIN users u ON u.id = t.user_id
        LEFT JOIN uqx_wallet w ON w.user_id = t.user_id
        ORDER BY t.id DESC
    ");
} else {
    $stmtFetch = $conn->prepare("
        SELECT t.*, u.email, u.wallet_address, COALESCE(w.balance, u.uqx_balance, 0) as uqx_balance
        FROM tickets_purchased t
        JOIN users u ON u.id = t.user_id
        LEFT JOIN uqx_wallet w ON w.user_id = t.user_id
        WHERE t.status = ?
        ORDER BY t.id DESC
    ");
    $stmtFetch->bind_param("s", $filter);
}

if ($stmtFetch->execute()) {
    $resFetch = $stmtFetch->get_result();
    while ($row = $resFetch->fetch_assoc()) {
        $tickets[] = $row;
    }
}
$stmtFetch->close();

$countsQuery = $conn->query("SELECT status, COUNT(*) as cnt FROM tickets_purchased GROUP BY status");
$counts = ['Pending' => 0, 'Approved' => 0, 'Rejected' => 0];
if ($countsQuery) {
    while ($row = $countsQuery->fetch_assoc()) {
        $counts[$row['status']] = (int)$row['cnt'];
    }
}
$pendingCount  = $counts['Pending'];
$approvedCount = $counts['Approved'];
$rejectedCount = $counts['Rejected'];
?>
