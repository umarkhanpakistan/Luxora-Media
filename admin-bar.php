<?php
// admin-bar.php - Floating admin panel for logged-in admins

// Check if user is logged in and is admin
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
?>
<style>
  /* ── FLOATING ADMIN BAR ── */
  .admin-float-bar {
    position: fixed;
    bottom: 2rem;
    left: 2rem;
    z-index: 99999;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
  }
  
  .admin-float-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 1.2rem;
    background: rgba(16, 16, 16, 0.95);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(212, 175, 55, 0.2);
    border-radius: 50px;
    color: #d4af37;
    font-size: 0.85rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.4s cubic-bezier(0.22, 1, 0.36, 1);
    box-shadow: 0 8px 40px rgba(0, 0, 0, 0.6);
    cursor: pointer;
    font-family: 'Inter', sans-serif;
    position: relative;
    overflow: hidden;
  }
  
  .admin-float-btn::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 50px;
    background: linear-gradient(135deg, rgba(212, 175, 55, 0.1), rgba(245, 200, 76, 0.05));
    opacity: 0;
    transition: opacity 0.4s ease;
  }
  
  .admin-float-btn:hover::before {
    opacity: 1;
  }
  
  .admin-float-btn:hover {
    transform: translateX(8px) scale(1.05);
    border-color: rgba(212, 175, 55, 0.4);
    box-shadow: 0 8px 50px rgba(212, 175, 55, 0.2);
  }
  
  .admin-float-btn i {
    font-size: 1.1rem;
    color: #d4af37;
    transition: transform 0.3s ease;
  }
  
  .admin-float-btn:hover i {
    transform: rotate(-10deg) scale(1.1);
  }
  
  .admin-float-btn .badge-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #2ed573;
    display: inline-block;
    margin-left: 0.3rem;
    animation: pulse-dot 2s ease-in-out infinite;
  }
  
  @keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(0.8); }
  }
  
  .admin-float-btn .admin-label {
    background: linear-gradient(135deg, #d4af37, #f5c84c);
    color: #050505;
    font-size: 0.55rem;
    font-weight: 700;
    padding: 0.1rem 0.5rem;
    border-radius: 50px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
  }
  
  /* Expandable admin menu */
  .admin-float-menu {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    margin-left: 0.5rem;
    opacity: 0;
    transform: translateX(-20px) scale(0.9);
    pointer-events: none;
    transition: all 0.4s cubic-bezier(0.22, 1, 0.36, 1);
    transform-origin: bottom left;
  }
  
  .admin-float-menu.show {
    opacity: 1;
    transform: translateX(0) scale(1);
    pointer-events: auto;
  }
  
  .admin-float-menu .admin-float-btn {
    padding: 0.5rem 1rem;
    font-size: 0.8rem;
    background: rgba(16, 16, 16, 0.9);
  }
  
  .admin-float-menu .admin-float-btn i {
    font-size: 0.9rem;
  }
  
  /* Toggle button */
  .admin-toggle-btn {
    background: none;
    border: none;
    color: #d4af37;
    font-size: 0.7rem;
    padding: 0.2rem 0.5rem;
    cursor: pointer;
    transition: transform 0.3s ease;
  }
  
  .admin-toggle-btn:hover {
    transform: rotate(180deg);
  }
  
  /* Responsive */
  @media (max-width: 768px) {
    .admin-float-bar {
      bottom: 1rem;
      left: 1rem;
    }
    .admin-float-btn {
      padding: 0.5rem 1rem;
      font-size: 0.75rem;
    }
    .admin-float-btn i {
      font-size: 0.9rem;
    }
    .admin-float-btn .admin-label {
      font-size: 0.5rem;
      padding: 0.05rem 0.4rem;
    }
    .admin-float-menu .admin-float-btn {
      padding: 0.4rem 0.8rem;
      font-size: 0.7rem;
    }
  }
</style>

<div class="admin-float-bar" id="adminFloatBar">
  <!-- Main Admin Button -->
  <button class="admin-float-btn" id="adminMainBtn" onclick="toggleAdminMenu()">
    <i class="fas fa-crown"></i>
    <span>Admin Panel</span>
    <span class="badge-dot"></span>
    <span class="admin-label">Online</span>
    <i class="fas fa-chevron-down" style="font-size:0.6rem; opacity:0.5;"></i>
  </button>
  
  <!-- Admin Menu -->
  <div class="admin-float-menu" id="adminFloatMenu">
    <a href="/luxora-media/admin/dashboard.php" class="admin-float-btn">
      <i class="fas fa-tachometer-alt"></i> Dashboard
    </a>
    <a href="/luxora-media/admin/admin-services.php" class="admin-float-btn">
      <i class="fas fa-cubes"></i> Services
    </a>
    <a href="/luxora-media/admin/admin-clients.php" class="admin-float-btn">
      <i class="fas fa-users"></i> Clients
    </a>
    <a href="/luxora-media/admin/admin-projects.php" class="admin-float-btn">
      <i class="fas fa-file-invoice"></i> Projects
    </a>
    <a href="/luxora-media/admin/admin-inquiries.php" class="admin-float-btn">
      <i class="fas fa-envelope"></i> Inquiries
    </a>
    <hr style="width:100%; border-color: rgba(255,255,255,0.05); margin: 0.2rem 0;">
    <a href="/luxora-media/admin/dashboard.php" class="admin-float-btn" style="color: #ff4757;">
      <i class="fas fa-sign-out-alt"></i> Back to Admin
    </a>
  </div>
</div>

<script>
  // Toggle admin menu
  function toggleAdminMenu() {
    const menu = document.getElementById('adminFloatMenu');
    const btn = document.getElementById('adminMainBtn');
    menu.classList.toggle('show');
    const chevron = btn.querySelector('.fa-chevron-down');
    if (chevron) {
      chevron.style.transform = menu.classList.contains('show') ? 'rotate(180deg)' : 'rotate(0deg)';
    }
  }
  
  // Close menu when clicking outside
  document.addEventListener('click', function(e) {
    const bar = document.getElementById('adminFloatBar');
    if (bar && !bar.contains(e.target)) {
      const menu = document.getElementById('adminFloatMenu');
      if (menu) {
        menu.classList.remove('show');
        const chevron = document.getElementById('adminMainBtn')?.querySelector('.fa-chevron-down');
        if (chevron) {
          chevron.style.transform = 'rotate(0deg)';
        }
      }
    }
  });
  
  // Close menu on Escape key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      const menu = document.getElementById('adminFloatMenu');
      if (menu && menu.classList.contains('show')) {
        menu.classList.remove('show');
        const chevron = document.getElementById('adminMainBtn')?.querySelector('.fa-chevron-down');
        if (chevron) {
          chevron.style.transform = 'rotate(0deg)';
        }
      }
    }
  });
</script>
<?php
}
?>