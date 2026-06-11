
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// ✅ Agar user already session me hai, kuch na karo
if (isset($_SESSION['user_id'])) {
    return;
}

// ✅ Agar remember_token cookie hai
if (isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];

    $stmt = $conn->prepare("SELECT user_id, expires_at FROM login_tokens WHERE token=? LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (strtotime($row['expires_at']) > time()) {
            // ✅ Valid token → auto login
            $userId = $row['user_id'];

            $user_stmt = $conn->prepare("SELECT id, name, twofa_enabled, twofa_secret FROM users WHERE id=?");
            $user_stmt->bind_param("i", $userId);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();

            if ($user_data = $user_result->fetch_assoc()) {
                $_SESSION['user_id'] = $user_data['id'];
                $_SESSION['user_name'] = ucwords(strtolower($user_data['name']));

                // ✅ Always mark 2FA verified for valid remember_token
                $_SESSION['2fa_verified'] = true;

                // ✅ Refresh cookies for another 15 days (auto renewal)
                setcookie('remember_token', $token, time() + (15 * 24 * 60 * 60), '/', '', false, true);
                setcookie('trusted_2fa', 'yes', time() + (15 * 24 * 60 * 60), '/', '', false, true);
            }

            $user_stmt->close();
        } else {
            // ❌ Token expired → delete cookies
            setcookie("remember_token", "", time() - 3600, "/", "", false, true);
            setcookie("trusted_2fa", "", time() - 3600, "/", "", false, true);
        }
    }

    $stmt->close();
}
?>
