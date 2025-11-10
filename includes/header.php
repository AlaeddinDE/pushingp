<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . '/db.php';

// Kassensaldo
$saldo = 0.0;
if ($res = $conn->query("SELECT IFNULL(SUM(betrag),0) AS s FROM transaktionen")) {
  $r = $res->fetch_assoc(); $saldo = (float)$r['s'];
  $res->free();
}

// Live-Shift-Ticker (wer JETZT in Schicht laut Modus/Uhrzeit?)
$now = date('H:i:s');
$stmt = $conn->prepare("
SELECT id,name,shift_mode,shift_start,shift_end
FROM users
WHERE shift_enabled=1
");
$stmt->execute();
$stmt->bind_result($mid,$mname,$mmode,$sstart,$send);
$onShift=[];
while($stmt->fetch()){
  // einfache Logik: fixed window (custom) oder Presets
  $st = $sstart ?: '00:00:00';
  $en = $send  ?: '00:00:00';
  if ($mmode==='early'){ $st='06:00:00'; $en='14:00:00';}
  if ($mmode==='late'){  $st='14:00:00'; $en='22:00:00';}
  if ($mmode==='night'){ $st='22:00:00'; $en='06:00:00';}
  // Nacht über Mitternacht:
  $isOn = false;
  if ($st < $en) { $isOn = ($now >= $st && $now <= $en); }
  else { $isOn = ($now >= $st || $now <= $en); }
  if ($isOn) $onShift[] = $mname;
}
$stmt->close();
?>
<header style="background:#0b0f12;color:#e9f3f7;padding:14px 20px;display:flex;justify-content:space-between;align-items:center;gap:12px;border-bottom:1px solid rgba(255,255,255,.06);">
  <nav style="display:flex;gap:14px;align-items:center;">
    <a href="/index.php" style="color:#7ee0ff;text-decoration:none;font-weight:700;font-size:18px;">PushingP</a>
    <a href="/kasse.php" style="color:#cfe7ee;text-decoration:none;">Kasse</a>
    <a href="/events.php" style="color:#cfe7ee;text-decoration:none;">Events</a>
    <a href="/leaderboard.php" style="color:#cfe7ee;text-decoration:none;">🏆 Leaderboard</a>
    <a href="/settings.php" style="color:#cfe7ee;text-decoration:none;">Einstellungen</a>
    <?php if(isset($_SESSION['role']) && $_SESSION['role']==='admin'): ?>
      <a href="/admin_kasse.php" style="color:#ffe27e;text-decoration:none;">Admin</a>
      <a href="/admin_xp.php" style="color:#ffe27e;text-decoration:none;">⚙️ XP Admin</a>
    <?php endif; ?>
  </nav>
  <div style="display:flex;align-items:center;gap:14px;">
    <div style="font-size:15px;opacity:.9;">Saldo: <b><?= number_format($saldo,2,',','.') ?> €</b></div>
    <div style="font-size:14px;white-space:nowrap;max-width:48vw;overflow:hidden;text-overflow:ellipsis;">
      <span style="opacity:.7;margin-right:6px;">Shift:</span>
      <?php if(count($onShift)): ?>
        <span><?= htmlspecialchars(implode(' • ',$onShift)) ?></span>
      <?php else: ?>
        <span style="opacity:.6;">keiner aktiv</span>
      <?php endif; ?>
    </div>
    <?php if(isset($_SESSION['mitglied_name'])): ?>
      <span style="opacity:.8;">👤 <?= htmlspecialchars($_SESSION['mitglied_name']) ?></span>
      <a href="/logout.php" style="color:#ff9aa2;text-decoration:none;">Logout</a>
    <?php else: ?>
      <a href="/login.php" style="color:#a2ffb1;text-decoration:none;">Login</a>
    <?php endif; ?>
  </div>
</header>
