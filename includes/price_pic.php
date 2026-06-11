<?php
/** -----------------------------------------------
 *  Navbar Profile Component (Final)
 *  - Real name & email from DB
 *  - Avatar with on-image indicator dot
 *  - Clean dropdown (no icons)
 *  - Responsive + Dark mode friendly
 *  - Safe session_start + path handling
 * ----------------------------------------------*/

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Safe session start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// DB connect


// Config (define only if not defined)
if (!defined('APP_URL')) {
    define('APP_URL', 'https://umarae.com');
}
if (!defined('UPLOADS_URL')) {
    define('UPLOADS_URL', APP_URL . '/public/uploads/profile/');
}

// Auth guard
if (empty($_SESSION['user_id'])) {
    // If this component is included on public pages, you can early return instead:
    // return;
    header('Location: ' . APP_URL . '../public/login.html');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Fetch user (name, email, image)
$stmt = $conn->prepare("SELECT name, email, image FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

// Build safe values
$user_name  = isset($user['name'])  && $user['name']  !== '' ? $user['name']  : 'User';
$user_email = isset($user['email']) && $user['email'] !== '' ? $user['email'] : 'user@example.com';

// We expect DB to store only filename (e.g. 12345.png). If path came, basename() will sanitize.
$image_file = !empty($user['image']) ? basename($user['image']) : 'default.png';
$profile_image = UPLOADS_URL . $image_file;

// Small helper for escaping
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>

<!-- ================== NAVBAR PROFILE (UPGRADED) ================== -->
<div class="navbar-profile" data-profile>
  <div class="dropdown" data-dropdown>
    <a href="#" class="dropdown-toggle" data-toggle aria-haspopup="true" aria-expanded="false" aria-label="Open profile menu">
      <div class="profile-wrapper">
        <img src="<?= e($profile_image) ?>" alt="Profile" class="nav-profile-pic" loading="lazy" decoding="async">
        <span class="status-indicator" title="Online"></span>
      </div>
    </a>

    <div class="dropdown-menu" data-menu role="menu" aria-label="Profile menu">
      <!-- Dropdown Header -->
      <div class="dropdown-header">
        <img src="<?= e($profile_image) ?>" alt="Avatar" class="dropdown-avatar" loading="lazy" decoding="async">
        <div class="user-info">
          <strong><?= e($user_name) ?></strong>
          <small><?= e($user_email) ?></small>
        </div>
      </div>

      <hr>

      <!-- Menu Items -->
      <div class="menu-items">
        <a href="<?= APP_URL ?>/public/profile.php" role="menuitem">
          <i class="fas fa-user"></i> My Profile
        </a>
        <a href="<?= APP_URL ?>/user/settings.php" role="menuitem">
          <i class="fas fa-cog"></i> Settings & Security
        </a>
        <a href="<?= APP_URL ?>/includes/user_chat.php" role="menuitem">
          <i class="fas fa-question-circle"></i> Help Center
        </a>
      </div>

  <!-- Left-aligned 2FA Status -->
<div class="twofa-status">
<?php
$user_id = $_SESSION['user_id'] ?? null;
if ($user_id) {
    $stmt = $conn->prepare("SELECT twofa_enabled FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    echo "<ul class='left-2fa'>";
    if ($row && $row['twofa_enabled'] == 1) {
        // ✅ Green secured text
        echo "<li class='secured'><i class='fas fa-check-circle'></i> Account Secured</li>";
    } else {
        // 🔴 Red link (already red)
        echo "<li class='enable'><a href='../2fa/2fa_setup.php'><i class='fas fa-shield-alt'></i> Enable Security Now</a></li>";
    }
    echo "</ul>";
}
?>
</div>

      <hr>

      <!-- Logout -->
      <a href="<?= APP_URL ?>/backend/logout.php" class="logout" role="menuitem">
        <i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </div>
</div>

<style>

/* 🌙 Modern Dark Mode Overrides */ body.dark-mode{--np-bg:#0d1117;--np-text:#e6edf3;--np-muted:#9ca3af;--np-border:#1f2937;--np-hover:#161b22;--np-accent:#3b82f6;--np-shadow:0 8px 25px rgba(0,0,0,0.5);} body.dark-mode .dropdown-menu{background:var(--np-bg);color:var(--np-text);border:1px solid var(--np-border);box-shadow:0 10px 30px rgba(0,0,0,0.6);} body.dark-mode .dropdown-header{background:color-mix(in oklab,var(--np-bg) 92%,var(--np-text) 8%);} body.dark-mode .menu-items a{color:var(--np-text);} body.dark-mode .menu-items a:hover{background:var(--np-hover);} body.dark-mode .nav-profile-pic{border-color:var(--np-accent);box-shadow:0 0 10px rgba(59,130,246,0.35);} body.dark-mode .nav-profile-pic:hover{box-shadow:0 0 15px rgba(59,130,246,0.45);} body.dark-mode .left-2fa li.secured{color:#22c55e;} body.dark-mode .left-2fa li a{color:#f87171;} body.dark-mode .left-2fa li.enable a{animation:pulse-alert 1.2s infinite ease-in-out;} body.dark-mode .left-2fa li.enable a i{animation:shake-alert 0.8s infinite ease-in-out;} body.dark-mode .logout{color:#f87171;} body.dark-mode .logout:hover{background:var(--np-hover);border-radius:8px;} body.dark-mode .dropdown-menu hr{border-top:1px solid var(--np-border);} body.dark-mode .status-indicator{background:var(--np-accent);border-color:var(--np-bg);} body,body.dark-mode{transition:background 0.3s ease,color 0.3s ease,border 0.3s ease;}body.dark-mode .menu-items i {color:white;} body.dark-mode .fas{color:white;} 
     body.dark-mode .dropdown-header img {border:1px solid #E0E0E2;}


/* ====== Base Design Variables ====== */
:root {
  --np-bg: #fff;
  --np-text: #222;
  --np-muted: #666;
  --np-border: #e6e8ec;
  --np-shadow: 0 10px 25px rgba(0,0,0,0.15);
  --np-hover: #f5f6f8;
  --np-accent: #0d6efd;
}

.dark :root, .dark {
  --np-bg: #111418;
  --np-text: #e8eaed;
  --np-muted: #9aa0a6;
  --np-border: #23262b;
  --np-shadow: 0 12px 30px rgba(0,0,0,0.6);
  --np-hover: #161a20;
  --np-accent: #4da3ff;
}

/* Navbar Profile Container */
.navbar-profile {
  position: relative;
  display: flex;
  align-items: center;
  left: 15px;
  justify-content: flex-start; /* left aligned */
}

/* Trigger Avatar */
.dropdown-toggle {
  display: flex;
  align-items: center;
  text-decoration: none;
  outline: none;
}

/* Profile Wrapper */
.profile-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

/* Avatar Image */
.nav-profile-pic {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid var(--np-accent);
  cursor: pointer;
  transition: transform .2s ease, box-shadow .2s ease;
}
.nav-profile-pic:hover {
  transform: scale(1.05);
  box-shadow: 0 0 10px rgba(13,110,253,0.35);
}

.left-2fa li.secured {
    color: #28a745; /* Green text */
    font-weight: 600;
}

.left-2fa li.secured i {
    color: #28a745; /* Green icon */
    margin-right: 6px;
}

.left-2fa li a {
    color: #dc3545; /* Red text */
    text-decoration: none;
    font-weight: 600;
    display: flex;
    align-items: center;
}

.left-2fa li a i {
    color: #dc3545; /* Red icon */
    margin-right: 6px;
}
}
.left-2fa li a:hover {
    text-decoration: underline;
}

/* ================= Enable Security Alert Animation ================= */
.left-2fa li.enable a {
    color: #dc3545; /* Red text */
    text-decoration: none;
    font-weight: 600;
    display: flex;
    align-items: center;
    position: relative;
    animation: pulse-alert 1.2s infinite ease-in-out;
}

.left-2fa li.enable a i {
    color: #dc3545;
    margin-right: 6px;
    animation: shake-alert 0.8s infinite ease-in-out;
}

/* Shake animation for icon */
@keyframes shake-alert {
    0% { transform: rotate(0deg); }
    20% { transform: rotate(-10deg); }
    40% { transform: rotate(10deg); }
    60% { transform: rotate(-10deg); }
    80% { transform: rotate(10deg); }
    100% { transform: rotate(0deg); }
}

/* Pulse animation for entire link */
@keyframes pulse-alert {
    0% { transform: scale(1); color: #dc3545; }
    50% { transform: scale(1.05); color: #ff4d4d; }
    100% { transform: scale(1); color: #dc3545; }
}

/* Optional: remove hover overrides if you want continuous alert effect */
.left-2fa li.enable a:hover {
    transform: none;
    color: #dc3545;
}

/* Status Indicator Dot */
.status-indicator {
  position: absolute;
  bottom: 2px;
  right: 2px;
  margin-bottom: -4px;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: var(--np-accent);
  border: 2px solid var(--np-bg);
}

/* Dropdown Panel */
.dropdown-menu {
  position: absolute;
  top: 52px;
  right: 40px;
  background: var(--np-bg);
  color: var(--np-text);
  border: 1px solid var(--np-border);
  border-radius: 12px;
  box-shadow: var(--np-shadow);
  min-width: 260px;
  display: none;
  flex-direction: column;
  overflow: hidden;
  z-index: 1000;
  padding: 0;
  animation: np-fade .2s ease forwards;
}

/* Dropdown Header */
.dropdown-header {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  background: color-mix(in oklab, var(--np-bg) 92%, var(--np-text) 8%);
}
.dropdown-avatar {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  object-fit: cover;
  border: 1px solid var(--np-border);
}
.user-info strong {
  display: block;
  font-size: 15px;
  line-height: 1.05;
  color: var(--np-text);
}
.user-info small {
  display: block;
  font-size: 12px;
  color: var(--np-muted);
  max-width: 160px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Menu Items */
.menu-items a {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  font-size: 14px;
  text-decoration: none;
  color: var(--np-text);
  transition: background .16s ease;
}
.menu-items a:hover {
  background: var(--np-hover);
}

/* Left 2FA Status */
.left-2fa {
  list-style: none;
  padding: 0 14px;
  margin: 8px 0;
  text-align: left;
  font-size: 14px;
  font-weight: 600;
}
.left-2fa li {
  margin: 4px 0;
}
.left-2fa li a {
  color: red;
  text-decoration: none;
}
.left-2fa li a:hover {
  text-decoration: underline;
}

/* Logout Button */
.logout {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 16px;
  color: #e04b4b;
  text-decoration: none;
  font-weight: 600;
}
.logout:hover {
  background: var(--np-hover);
  border-radius: 8px;
}

/* Divider */
.dropdown-menu hr {
  margin: 0;
  border: none;
  border-top: 1px solid var(--np-border);
}

/* Show dropdown */
[data-dropdown].active .dropdown-menu {
  display: flex;
}

/* Focus ring for accessibility */
.dropdown-toggle:focus .nav-profile-pic {
  box-shadow: 0 0 0 3px color-mix(in oklab, var(--np-accent) 25%, transparent);
}

/* Fade animation */
@keyframes np-fade {
  from { opacity: 0; transform: translateY(-8px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* Responsive */
@media (max-width:768px){
  .nav-profile-pic { width: 36px; height: 36px; }
  .navbar-profile{left:0;}
  .dropdown-menu { min-width: 220px; top: 48px; right:30px;}
  
}

@media (max-width:480px){
  .nav-profile-pic { width: 32px; height: 32px; }
  .dropdown-menu { min-width: 220px; right:30px; top: 44px; }
  .navbar-profile{left:8px;}
}
</style>


<script>
(function() {
  const root = document.querySelector('[data-profile]');
  if (!root) return;

  const dropdown = root.querySelector('[data-dropdown]');
  const toggle = root.querySelector('[data-toggle]');
  const menu = root.querySelector('[data-menu]');

  if (!dropdown || !toggle || !menu) return;

  // Toggle open/close
  toggle.addEventListener('click', function(e) {
    e.preventDefault();
    const isOpen = dropdown.classList.toggle('active');
    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  });

  // Close when clicking outside
  document.addEventListener('click', function(e) {
    if (!dropdown.contains(e.target)) {
      dropdown.classList.remove('active');
      toggle.setAttribute('aria-expanded', 'false');
    }
  });

  // Close on Escape
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      dropdown.classList.remove('active');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.focus({ preventScroll: true });
    }
  });

  // Close on scroll (navbar UX)
  window.addEventListener('scroll', function() {
    if (dropdown.classList.contains('active')) {
      dropdown.classList.remove('active');
      toggle.setAttribute('aria-expanded', 'false');
    }
  }, { passive: true });
})();
</script>
