<?php
// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
$current_user_name = $is_logged_in ? htmlspecialchars($_SESSION['user_name']) : '';
?>

<!-- ========================================== -->
<!-- PROFILE DROPDOWN CSS (FIXED WITH !important) -->
<!-- ========================================== -->
<style>
  .profile-dropdown {
    position: relative !important;
    display: inline-block !important;
  }
  .profile-dropdown .dropdown-toggle {
    color: #d4af37 !important;
    border: 1px solid rgba(212, 175, 55, 0.2) !important;
    border-radius: 50px !important;
    padding: 0.4rem 1.2rem !important;
    font-size: 0.85rem !important;
    font-weight: 600 !important;
    transition: all 0.3s ease !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 0.5rem !important;
    background: transparent !important;
    text-decoration: none !important;
    cursor: pointer !important;
    font-family: 'Inter', sans-serif !important;
    position: relative !important;
    z-index: 1001 !important;
    user-select: none !important;
    border: none !important;
    outline: none !important;
  }
  .profile-dropdown .dropdown-toggle:hover {
    background: rgba(212, 175, 55, 0.08) !important;
    border-color: #d4af37 !important;
    transform: translateY(-2px) !important;
  }
  .profile-dropdown .dropdown-toggle i {
    font-size: 1rem !important;
  }
  .profile-dropdown .dropdown-toggle .fa-chevron-down {
    font-size: 0.6rem !important;
    opacity: 0.6 !important;
    transition: transform 0.3s ease !important;
    margin-left: 0.2rem !important;
  }
  .profile-dropdown.active .dropdown-toggle .fa-chevron-down {
    transform: rotate(180deg) !important;
  }

  /* DROPDOWN MENU - FORCE HIDDEN UNLESS ACTIVE */
  .profile-dropdown .dropdown-menu {
    position: absolute !important;
    top: calc(100% + 12px) !important;
    right: 0 !important;
    min-width: 200px !important;
    background: rgba(10, 10, 10, 0.98) !important;
    backdrop-filter: blur(20px) !important;
    -webkit-backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(212, 175, 55, 0.12) !important;
    border-radius: 12px !important;
    padding: 0.5rem 0 !important;
    z-index: 99999 !important;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.8) !important;
    list-style: none !important;
    margin: 0 !important;
    
    /* DEFAULT HIDDEN */
    display: none !important;
    opacity: 0 !important;
    visibility: hidden !important;
    transform: translateY(-15px) scale(0.95) !important;
    transform-origin: top right !important;
    transition: all 0.3s cubic-bezier(0.22, 1, 0.36, 1) !important;
    pointer-events: none !important;
  }

  /* WHEN ACTIVE - SHOW */
  .profile-dropdown.active .dropdown-menu {
    display: block !important;
    opacity: 1 !important;
    visibility: visible !important;
    transform: translateY(0) scale(1) !important;
    pointer-events: auto !important;
  }

  .profile-dropdown .dropdown-menu li {
    list-style: none !important;
    display: block !important;
    padding: 0 !important;
    margin: 0 !important;
  }

  .profile-dropdown .dropdown-menu a {
    display: flex !important;
    align-items: center !important;
    gap: 0.75rem !important;
    padding: 0.65rem 1.2rem !important;
    color: rgba(255, 255, 255, 0.85) !important;
    font-size: 0.85rem !important;
    font-weight: 500 !important;
    transition: all 0.25s ease !important;
    text-decoration: none !important;
    border-bottom: none !important;
    background: transparent !important;
    cursor: pointer !important;
    white-space: nowrap !important;
  }

  .profile-dropdown .dropdown-menu a:hover {
    background: rgba(212, 175, 55, 0.06) !important;
    color: #d4af37 !important;
  }

  .profile-dropdown .dropdown-menu a i {
    width: 20px !important;
    color: #d4af37 !important;
    font-size: 0.9rem !important;
    text-align: center !important;
  }

  .profile-dropdown .dropdown-menu .dropdown-divider {
    height: 1px !important;
    background: linear-gradient(90deg, transparent, rgba(212, 175, 55, 0.12), transparent) !important;
    margin: 0.3rem 0.8rem !important;
    padding: 0 !important;
    border: none !important;
    display: block !important;
  }

  .profile-dropdown .dropdown-menu .logout-item {
    color: #ff4757 !important;
  }
  .profile-dropdown .dropdown-menu .logout-item i {
    color: #ff4757 !important;
  }
  .profile-dropdown .dropdown-menu .logout-item:hover {
    background: rgba(255, 71, 87, 0.06) !important;
    color: #ff4757 !important;
  }
</style>

<!-- NAVBAR -->
<nav class="navbar" id="navbar">
  <a href="index.php" class="logo">LUXORA<span>.</span></a>
  <ul class="nav-links">
    <li><a href="index.php" class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">Home</a></li>
    <li><a href="about.php" class="<?= basename($_SERVER['PHP_SELF']) == 'about.php' ? 'active' : '' ?>">About</a></li>
    <li><a href="services.php" class="<?= basename($_SERVER['PHP_SELF']) == 'services.php' ? 'active' : '' ?>">Services</a></li>
    <li><a href="pricing.php" class="<?= basename($_SERVER['PHP_SELF']) == 'pricing.php' ? 'active' : '' ?>">Pricing</a></li>
    <li><a href="faq.php" class="<?= basename($_SERVER['PHP_SELF']) == 'faq.php' ? 'active' : '' ?>">FAQ</a></li>
    <li><a href="contact.php" class="<?= basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'active' : '' ?>">Contact</a></li>
    
    <?php if ($is_logged_in): ?>
      <!-- Logged In - Show Profile Dropdown -->
      <li class="profile-dropdown" id="profileDropdown">
        <button class="dropdown-toggle" id="dropdownToggle" type="button" aria-haspopup="true" aria-expanded="false">
          <i class="fas fa-user-circle"></i>
          <?= $current_user_name ?>
          <i class="fas fa-chevron-down"></i>
        </button>
        <ul class="dropdown-menu" id="dropdownMenu" role="menu">
          <li><a href="profile.php" role="menuitem">
            <i class="fas fa-user"></i> View Profile
          </a></li>
          <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
          <li><a href="admin/dashboard.php" role="menuitem">
            <i class="fas fa-tachometer-alt"></i> Admin Dashboard
          </a></li>
          <?php endif; ?>
          <li class="dropdown-divider" role="separator"></li>
          <li><a href="logout.php" class="logout-item" role="menuitem">
            <i class="fas fa-sign-out-alt"></i> Logout
          </a></li>
        </ul>
      </li>
    <?php else: ?>
      <!-- Logged Out - Show Sign In/Sign Up -->
      <li class="auth-buttons">
        <a href="sign-in.php" class="btn btn-sm btn-outline-gold">
          <i class="fas fa-sign-in-alt me-2"></i> Sign In
        </a>
        <a href="sign-up.php" class="btn btn-sm btn-gold-solid">
          <i class="fas fa-user-plus me-2"></i> Sign Up
        </a>
      </li>
    <?php endif; ?>
  </ul>
  <button class="mobile-toggle" id="mobileToggle" aria-label="Toggle menu">
    <span></span><span></span><span></span>
  </button>
</nav>

<!-- MOBILE MENU -->
<div class="mobile-menu" id="mobileMenu">
  <button class="mobile-close" id="mobileClose"><i class="fas fa-times"></i></button>
  <a href="index.php" class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">Home</a>
  <a href="about.php" class="<?= basename($_SERVER['PHP_SELF']) == 'about.php' ? 'active' : '' ?>">About</a>
  <a href="services.php" class="<?= basename($_SERVER['PHP_SELF']) == 'services.php' ? 'active' : '' ?>">Services</a>
  <a href="pricing.php" class="<?= basename($_SERVER['PHP_SELF']) == 'pricing.php' ? 'active' : '' ?>">Pricing</a>
  <a href="faq.php" class="<?= basename($_SERVER['PHP_SELF']) == 'faq.php' ? 'active' : '' ?>">FAQ</a>
  <a href="contact.php" class="<?= basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'active' : '' ?>">Contact</a>
  
  <?php if ($is_logged_in): ?>
    <!-- Logged In - Mobile Profile -->
    <div class="mobile-profile-btn">
      <a href="profile.php" class="profile-link">
        <i class="fas fa-user-circle me-2"></i> <?= $current_user_name ?>
      </a>
      <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
      <a href="admin/dashboard.php" class="profile-link" style="border-color: rgba(212,175,55,0.2);">
        <i class="fas fa-tachometer-alt me-2"></i> Admin Dashboard
      </a>
      <?php endif; ?>
      <a href="logout.php" class="logout-link">
        <i class="fas fa-sign-out-alt me-2"></i> Logout
      </a>
    </div>
  <?php else: ?>
    <!-- Logged Out - Show Sign In/Sign Up -->
    <div class="mobile-auth">
      <a href="sign-in.php" class="btn btn-outline-gold">
        <i class="fas fa-sign-in-alt me-2"></i> Sign In
      </a>
      <a href="sign-up.php" class="btn btn-gold-solid">
        <i class="fas fa-user-plus me-2"></i> Sign Up
      </a>
    </div>
  <?php endif; ?>
</div>

<!-- ===== PROFILE DROPDOWN JAVASCRIPT (FIXED, IMMEDIATE) ===== -->
<script>
(function() {
  // Wait for DOM to be ready
  function initDropdown() {
    var dropdown = document.getElementById('profileDropdown');
    if (!dropdown) return;

    var toggle = dropdown.querySelector('.dropdown-toggle');
    if (!toggle) return;

    // Ensure dropdown is closed on load
    dropdown.classList.remove('active');
    toggle.setAttribute('aria-expanded', 'false');

    // TOGGLE CLICK HANDLER
    toggle.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();

      if (dropdown.classList.contains('active')) {
        dropdown.classList.remove('active');
        toggle.setAttribute('aria-expanded', 'false');
      } else {
        // Close any other open dropdowns
        document.querySelectorAll('.profile-dropdown.active').forEach(function(el) {
          if (el !== dropdown) {
            el.classList.remove('active');
            var otherToggle = el.querySelector('.dropdown-toggle');
            if (otherToggle) otherToggle.setAttribute('aria-expanded', 'false');
          }
        });

        // Open this dropdown
        dropdown.classList.add('active');
        toggle.setAttribute('aria-expanded', 'true');
      }
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
      if (e.key === 'Escape' && dropdown.classList.contains('active')) {
        dropdown.classList.remove('active');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });

    // Close when clicking a menu item
    dropdown.querySelectorAll('.dropdown-menu a').forEach(function(link) {
      link.addEventListener('click', function() {
        dropdown.classList.remove('active');
        toggle.setAttribute('aria-expanded', 'false');
      });
    });
  }

  // Run immediately if DOM is already loaded, otherwise wait
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDropdown);
  } else {
    initDropdown();
  }
})();
</script>