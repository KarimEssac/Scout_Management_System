<div class="sidebar">
  <div class="sidebar-nav">
    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>" href="index.php">
        <i class="fas fa-home"></i> Dashboard
    </a>
    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'scouts.php' ? 'active' : '' ?>" href="scouts.php">
        <i class="fas fa-users"></i> Scouts
    </a>
    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'events.php' ? 'active' : '' ?>" href="events.php">
        <i class="fas fa-calendar-alt"></i> Events
    </a>
    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'attendance.php' ? 'active' : '' ?>" href="attendance.php">
        <i class="fas fa-qrcode"></i> Attendance
    </a>
    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'bonuses.php' ? 'active' : '' ?>" href="bonuses.php">
        <i class="fas fa-star"></i> Bonuses/Penalties
    </a>
    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'apologies.php' ? 'active' : '' ?>" href="apologies.php">
        <i class="fas fa-hands-praying"></i> Apologies
    </a>
    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'engagements.php' ? 'active' : '' ?>" href="engagements.php">
        <i class="fas fa-handshake"></i> Engagements
    </a>
    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'communication.php' ? 'active' : '' ?>" href="communication.php">
        <i class="fas fa-comments"></i> Communication
    </a>
  </div>
  
  <div class="sidebar-footer">
    <div class="dropdown">
      <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
        <i class="fas fa-user-circle me-2"></i> <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?>
      </a>
      <ul class="dropdown-menu">
        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i> Profile</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
      </ul>
    </div>
  </div>
</div><div class="sidebar">
  <div class="sidebar-nav">
    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>" href="index.php">
        <i class="fas fa-home"></i> Dashboard
    </a>
    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'scouts.php' ? 'active' : '' ?>" href="scouts.php">
        <i class="fas fa-users"></i> Scouts
    </a>
    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'events.php' ? 'active' : '' ?>" href="events.php">
        <i class="fas fa-calendar-alt"></i> Events
    </a>
    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'attendance.php' ? 'active' : '' ?>" href="attendance.php">
        <i class="fas fa-qrcode"></i> Attendance
    </a>
    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'bonuses.php' ? 'active' : '' ?>" href="bonuses.php">
        <i class="fas fa-star"></i> Bonuses/Penalties
    </a>
    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'apologies.php' ? 'active' : '' ?>" href="apologies.php">
        <i class="fas fa-hands-praying"></i> Apologies
    </a>
    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'engagements.php' ? 'active' : '' ?>" href="engagements.php">
        <i class="fas fa-handshake"></i> Engagements
    </a>
    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'communication.php' ? 'active' : '' ?>" href="communication.php">
        <i class="fas fa-comments"></i> Communication
    </a>
  </div>
  
  <div class="sidebar-footer">
    <div class="dropdown">
      <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
        <i class="fas fa-user-circle me-2"></i> <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?>
      </a>
      <ul class="dropdown-menu">
        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i> Profile</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
      </ul>
    </div>
  </div>
</div>