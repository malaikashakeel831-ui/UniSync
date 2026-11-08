<?php
require __DIR__ . '/../app/bootstrap.php';
$me = current_user();
$pageTitle = 'Smart University Life Organizer';
require __DIR__ . '/../app/views/guest_header.php';
?>
<div class="landing">
  <nav class="lnav">
    <a class="brand" href="<?= e(url('index.php')) ?>"><img src="<?= e(asset('img/logo.svg')) ?>" width="34" height="34" alt=""> <span>Uni<b>Sync</b></span></a>
    <div class="row-gap">
      <button class="icon-btn" data-toggle-theme aria-label="Toggle dark mode"><i class="bi bi-moon-stars theme-dark-icon"></i><i class="bi bi-sun theme-light-icon"></i></button>
      <?php if ($me): ?>
        <a class="btn btn-primary" href="<?= e(url($me['role'] === 'admin' ? 'admin/dashboard.php' : 'dashboard.php')) ?>">Open dashboard <i class="bi bi-arrow-right"></i></a>
      <?php else: ?>
        <a class="btn btn-ghost" href="<?= e(url('login.php')) ?>">Log in</a>
        <a class="btn btn-primary" href="<?= e(url('register.php')) ?>">Get started</a>
      <?php endif; ?>
    </div>
  </nav>

  <section class="lhero">
    <div>
      <span class="eyebrow"><i class="bi bi-stars"></i> Built for university students</span>
      <h1>Your whole semester,<br><em>in perfect sync.</em></h1>
      <p class="lead">Courses, timetables, deadlines, GPA, study plans and group projects — organised in one secure dashboard that actually helps you plan.</p>
      <div class="row-gap mt-3">
        <a class="btn btn-primary btn-lg" href="<?= e(url('register.php')) ?>"><i class="bi bi-rocket-takeoff"></i> Create free account</a>
        <a class="btn btn-ghost btn-lg" href="#features">See features</a>
      </div>
      <div class="row-gap mt-3 muted" style="font-size:.88rem">
        <span><i class="bi bi-shield-check"></i> Private & secure</span>
        <span><i class="bi bi-calculator"></i> Accurate GPA / CGPA</span>
        <span><i class="bi bi-lightning-charge"></i> Smart study planner</span>
      </div>
    </div>
    <div class="mock" aria-hidden="true">
      <div class="mock-bar"><span></span><span></span><span></span></div>
      <div class="mock-grid">
        <div class="mock-stat"><small class="muted">Current GPA</small><b style="color:var(--primary)">3.67</b></div>
        <div class="mock-stat"><small class="muted">Due this week</small><b style="color:var(--accent)">5</b></div>
        <div class="mock-stat"><small class="muted">Study streak</small><b style="color:var(--success)">6🔥</b></div>
      </div>
      <div class="mock-list">
        <div class="mock-item" style="--c:#ef4444"><i class="bi bi-mortarboard"></i><span>Database Systems — Midterm</span><small class="muted">Tomorrow</small></div>
        <div class="mock-item" style="--c:#5b5bd6"><i class="bi bi-journal-text"></i><span>Web Eng. — Assignment 3</span><small class="muted">Fri</small></div>
        <div class="mock-item" style="--c:#10b981"><i class="bi bi-patch-question"></i><span>Statistics — Quiz 2</span><small class="muted">Mon</small></div>
      </div>
      <div class="mock-bars"><i style="height:40%"></i><i style="height:65%"></i><i style="height:52%"></i><i style="height:85%"></i><i style="height:70%"></i><i style="height:95%"></i><i style="height:60%"></i></div>
    </div>
  </section>

  <section class="lsection" id="features">
    <h2>Everything a student juggles — handled</h2>
    <p class="sub">UniSync doesn't just store your data. It calculates, predicts and plans so you always know what to do next.</p>
    <div class="features">
      <?php
      $features = [
        ['bi-grid-1x2', 'tone-primary', 'Smart dashboard', 'Deadlines this week, overdue alerts, GPA, workload meter and study streak at a glance.'],
        ['bi-check2-square', 'tone-accent', 'Deadline tracker', 'Assignments, quizzes, exams and projects with priorities, filters and one-click completion.'],
        ['bi-calendar3-week', 'tone-info', 'Timetable & calendar', 'A weekly class grid plus a month calendar that flags heavy days with 2+ deadlines.'],
        ['bi-award', 'tone-success', 'GPA & CGPA planner', 'Weighted course grades, semester GPA, CGPA, what-if marks and "marks needed for an A".'],
        ['bi-lightning-charge', 'tone-warning', 'Smart study planner', 'Ranks your tasks by urgency and importance and fits them into your free hours.'],
        ['bi-people', 'tone-primary', 'Group projects', 'Invite teammates, assign tasks and move them across a drag-and-drop Kanban board.'],
        ['bi-graph-up-arrow', 'tone-danger', 'Performance analytics', 'Charts for completion, workload per course, weekly study hours and GPA trend.'],
        ['bi-shield-lock', 'tone-info', 'Secure by design', 'Hashed passwords, CSRF tokens, prepared statements and strict per-student privacy.'],
      ];
      foreach ($features as [$icon, $tone, $title, $text]): ?>
        <div class="feature"><i class="bi <?= $icon ?> <?= $tone ?>"></i><h3><?= e($title) ?></h3><p><?= e($text) ?></p></div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="lsection">
    <h2>Up and running in minutes</h2>
    <p class="sub">Three steps and your semester is organised.</p>
    <div class="steps">
      <div class="step"><h3>Add your semester & courses</h3><p class="muted mb-0">Enter course codes, credit hours, instructors and pick a colour for each.</p></div>
      <div class="step"><h3>Log deadlines and marks</h3><p class="muted mb-0">Track every assignment, quiz and exam, and record marks as results come in.</p></div>
      <div class="step"><h3>Let UniSync plan your week</h3><p class="muted mb-0">Get a study schedule, GPA forecast and warnings before a week gets overloaded.</p></div>
    </div>
  </section>

  <section class="cta">
    <h2>Stop juggling. Start syncing.</h2>
    <p>Join UniSync and take control of your university life today.</p>
    <a class="btn btn-lg" style="background:#fff;color:#3d3db8" href="<?= e(url('register.php')) ?>">Create your account <i class="bi bi-arrow-right"></i></a>
  </section>

  <section class="lsection" style="padding-top:0">
    <h2 style="font-size:1.3rem">Built by</h2>
    <div class="team mt-2">
      <div class="card"><div class="avatar">SL</div><div><strong>Syeda Laraib</strong><br><small>BCSF24A002</small></div></div>
      <div class="card"><div class="avatar">KS</div><div><strong>Khadija Shakeel</strong><br><small>BCSF24A034</small></div></div>
    </div>
  </section>

  <footer class="lfoot">UniSync &middot; Smart University Life Organizer &middot; PHP · MySQL · HTML5 · CSS3 · JavaScript</footer>
</div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
