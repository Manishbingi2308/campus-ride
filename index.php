<?php
/**
 * CampusRide - Landing Page
 */
$pageTitle = 'Campus Ride Sharing System';
require_once __DIR__ . '/includes/header.php';
?>

<header class="hero">
  <div class="container">
    <h1>Share Your Ride. Save Money. Travel Together.</h1>
    <p>
      An easy, reliable way for students and faculty to connect, share empty seats, 
      split travel expenses, and reduce campus carbon footprint every single day.
    </p>
    <div class="actions">
      <a class="btn" href="<?= BASE_URL ?>/find-ride.php">🔍 Find a Ride</a>
      <?php if ($isLoggedIn): ?>
        <a class="btn outline" href="<?= BASE_URL ?>/post-ride.php">🚗 Offer a Ride</a>
      <?php else: ?>
        <a class="btn outline" href="<?= BASE_URL ?>/register.php">🎓 Join CampusRide</a>
      <?php endif; ?>
    </div>
    <div class="road"></div>
  </div>
</header>

<section id="features">
  <div class="container">
    <h2 class="title">Why CampusRide?</h2>
    <p class="sub">Practical benefits designed specifically for college students and faculty.</p>
    <div class="grid">
      <div class="card">
        <div class="icon">💰</div>
        <h3>Save Commute Costs</h3>
        <p>Split fuel and travel expenses transparently on your daily campus route.</p>
      </div>
      <div class="card">
        <div class="icon">🚦</div>
        <h3>Reduce Campus Traffic</h3>
        <p>Fewer private vehicles around campus gates and parking lots means smoother peak hours.</p>
      </div>
      <div class="card">
        <div class="icon">🌱</div>
        <h3>Eco-Friendly Travel</h3>
        <p>Shared vehicle journeys actively cut carbon emissions and foster sustainability.</p>
      </div>
      <div class="card">
        <div class="icon">🤝</div>
        <h3>Verified Campus Community</h3>
        <p>Share rides only with verified students and faculty carrying valid college IDs.</p>
      </div>
    </div>
  </div>
</section>

<section id="how" style="background:#f9fbfb; border-top:1px solid var(--line); border-bottom:1px solid var(--line);">
  <div class="container">
    <h2 class="title">How CampusRide Works</h2>
    <p class="sub">Four effortless steps from campus sign-up to shared commute.</p>
    <div class="grid">
      <div class="card step">
        <div class="n">01</div>
        <h3>Register &amp; Verify</h3>
        <p>Sign up with your verified college ID, institutional email, and select your department.</p>
      </div>
      <div class="card step">
        <div class="n">02</div>
        <h3>Search or Post Rides</h3>
        <p>Looking for a lift? Search routes by origin, time, or cost. Driving? Offer your empty seats.</p>
      </div>
      <div class="card step">
        <div class="n">03</div>
        <h3>Request &amp; Confirm</h3>
        <p>Passengers send seat requests; drivers confirm with real-time seat lock and instant notifications.</p>
      </div>
      <div class="card step">
        <div class="n">04</div>
        <h3>Meet &amp; Travel</h3>
        <p>Meet at the designated campus pickup point, share the journey, and travel safely together.</p>
      </div>
    </div>
  </div>
</section>

<section id="safety">
  <div class="container">
    <h2 class="title">Campus Safety &amp; Community Guidelines</h2>
    <p class="sub">Your security and peace of mind are our highest priorities.</p>
    <div class="grid">
      <div class="card">
        <div class="icon">🆔</div>
        <h3>Verified Members Only</h3>
        <p>All drivers and passengers register with unique College Roll Numbers/IDs and official emails.</p>
      </div>
      <div class="card">
        <div class="icon">📍</div>
        <h3>Official Campus Pickup Spots</h3>
        <p>Pickups and drops take place at well-lit, recognized campus spots, gates, and transit stations.</p>
      </div>
      <div class="card">
        <div class="icon">🔔</div>
        <h3>Real-time Updates</h3>
        <p>Instant in-app alerts ensure both drivers and riders are always informed about booking statuses.</p>
      </div>
    </div>
  </div>
</section>

<section id="about">
  <div class="container">
    <div class="about-box">
      <h2>About CampusRide</h2>
      <p>
        The Campus Ride Sharing System is an academic initiative engineered to simplify campus mobility. 
        By coordinating shared trips among students and faculty members belonging to the same institution, 
        CampusRide drastically lowers travel expenses, alleviates parking shortages, and creates a greener, 
        more connected collegiate ecosystem.
      </p>
      <div style="margin-top:24px;">
        <?php if (!$isLoggedIn): ?>
          <a class="btn" href="<?= BASE_URL ?>/register.php" style="background:#fff; color:var(--brand-dark); border-color:#fff;">
            Get Started with College ID
          </a>
        <?php else: ?>
          <a class="btn" href="<?= BASE_URL ?>/dashboard.php" style="background:#fff; color:var(--brand-dark); border-color:#fff;">
            Go to Your Dashboard &rarr;
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
