<?php
require __DIR__ . '/config/bootstrap.php';
require_login();

$user = current_user($pdo);
if (!$user) {
    header('Location: /logout.php');
    exit;
}
$stmt = $pdo->prepare('SELECT id, title, product_name, status, created_at FROM campaigns WHERE user_id = ? ORDER BY id DESC LIMIT 20');
$stmt->execute([$user['id']]);
$campaigns = $stmt->fetchAll();
$summary = ['draft' => 0, 'in_progress' => 0, 'ready' => 0, 'completed' => 0];
foreach ($campaigns as $row) {
    $key = (string) $row['status'];
    if (!isset($summary[$key])) {
        $key = 'draft';
    }
    $summary[$key]++;
}
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>RATEB AI — Dashboard</title>
<link rel="stylesheet" href="/public/assets/css/theme.css">
<link rel="stylesheet" href="/public/assets/css/app.css">
</head>
<body>
<header class="topbar">
  <div class="brand">RATEB <span>AI</span></div>
  <nav>
    <a href="/dashboard.php" data-i18n="dashboard">Dashboard</a>
    <a href="/campaign-new.php" data-i18n="new_campaign">New Campaign</a>
    <a href="/brand.php" data-i18n="brand_kit">Brand kit</a>
    <a href="/logout.php" data-i18n="logout">Logout</a>
    <button type="button" id="lang" class="icon">عربي</button>
    <button type="button" id="theme" class="icon">☾</button>
  </nav>
</header>
<main class="container">
  <div class="hero">
    <div>
      <p class="eyebrow" data-i18n="workspace">AI CAMPAIGN WORKSPACE</p>
      <h1><span data-i18n="welcome">Welcome</span>, <?= e($user['name']) ?></h1>
      <p class="muted" data-i18n="manage_lead">Create and manage your marketing campaigns.</p>
    </div>
    <a class="button" href="/campaign-new.php" data-i18n="new_campaign_button">+ New Campaign</a>
  </div>
  <div class="dash-kpis">
    <?php foreach ($summary as $key => $count): ?>
      <article class="kpi">
        <span class="badge status-<?= e($key) ?>" data-i18n="status_<?= e($key) ?>"><?= e($key) ?></span>
        <strong><?= (int) $count ?></strong>
      </article>
    <?php endforeach; ?>
  </div>

  <section class="card">
    <div class="section-head"><h2 data-i18n="your_campaigns">Your campaigns</h2></div>
    <?php if (!$campaigns): ?>
      <div class="empty">
        <h3 data-i18n="no_campaigns">No campaigns yet</h3>
        <p class="muted" data-i18n="no_campaigns_lead">Create your first campaign to get started.</p>
        <a class="button" href="/campaign-new.php" data-i18n="create_campaign">Create Campaign</a>
      </div>
    <?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr>
          <th data-i18n="col_title">Title</th>
          <th data-i18n="col_product">Product</th>
          <th data-i18n="col_status">Status</th>
          <th data-i18n="col_created">Created</th>
        </tr></thead>
        <tbody>
        <?php foreach ($campaigns as $campaign): ?>
          <tr>
            <td><a href="/campaign.php?id=<?= (int) $campaign['id'] ?>"><?= e($campaign['title']) ?></a></td>
            <td><?= e($campaign['product_name']) ?></td>
            <td><span class="badge status-<?= e(in_array((string) $campaign['status'], campaign_status_values(), true) ? (string) $campaign['status'] : 'draft') ?>" data-i18n="status_<?= e(in_array((string) $campaign['status'], campaign_status_values(), true) ? (string) $campaign['status'] : 'draft') ?>"><?= e($campaign['status']) ?></span></td>
            <td><?= e($campaign['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </section>
</main>
<script src="/public/assets/js/app.js"></script>
</body>
</html>
