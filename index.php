<?php require 'includes/bootstrap.php';
if(!empty($_SESSION['user'])) redirect($_SESSION['user']['role']==='Student'?'portal.php':'staff.php');
if(isset($_GET['back'])){ unset($_SESSION['pending'],$_SESSION['dev_otp']); redirect('index.php'); }
$err=''; $pending=$_SESSION['pending']??null;
if($_SERVER['REQUEST_METHOD']==='POST'){
  try{
    $act=$_POST['action']??'';
    if($act==='send'){
      $p=['email'=>norm($_POST['email']??''),'name'=>trim($_POST['name']??''),'number'=>trim($_POST['number']??''),
          'type'=>in_array($_POST['type']??'',['Current Student','Alumni / Former Student'])?$_POST['type']:'Current Student','program'=>(int)($_POST['program']??0)];
      send_otp($p); $_SESSION['pending']=$p; $pending=$p;
    } elseif($act==='resend' && $pending){ send_otp($pending); }
    elseif($act==='verify' && $pending){ verify_otp($pending['email'],$_POST['code']??''); redirect(login_user($pending)); }
  }catch(Exception $x){ $err=$x->getMessage(); }
}
$programs=q('SELECT id,name FROM programs WHERE active=1 ORDER BY name')->fetchAll();
?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e(UNIVERSITY)?> | <?=e(OFFICE)?></title><link rel="stylesheet" href="assets/css/style.css"></head>
<body class="login"><div class="card login-card">
<div class="logo-tile"><img src="assets/logo.png" alt="" onerror="this.style.display='none'"></div>
<h1 class="brand"><?=e(UNIVERSITY)?></h1><p class="muted"><?=e(OFFICE)?> • Digital Service Portal</p>
<span class="pill-green">DIGITAL REQUEST • PHYSICAL RELEASE</span>
<h2 class="hero">Registrar services,<br>made simpler.</h2>
<p class="muted lead">Request your physical academic document online, track its progress, and visit the Registrar when it is ready for release.</p>
<?php if($err): ?><div class="alert"><?=e($err)?></div><?php endif; ?>
<?php if(!$pending): ?>
<form method="post"><?=csrf_field()?><input type="hidden" name="action" value="send">
<label>Email Address</label><input type="email" name="email" placeholder="student@panpacificu.edu.ph" required>
<label>Full Name</label><input name="name" placeholder="Juan Dela Cruz">
<label>Student Number <span class="opt">(optional)</span></label><input name="number" placeholder="2026-00001">
<label>Program</label><select name="program"><option value="0">Select program...</option><?php foreach($programs as $g): ?><option value="<?=$g['id']?>"><?=e($g['name'])?></option><?php endforeach; ?></select>
<label>Account Type</label><select name="type"><option>Current Student</option><option>Alumni / Former Student</option></select>
<button class="btn-primary">Continue to Secure Portal</button></form>
<?php else: ?>
<div class="infobox"><b>Verification code sent.</b><br><?=e(mask_email($pending['email']))?></div>
<?php if(DEV_MODE && !empty($_SESSION['dev_otp'])): ?><div class="alert warn">DEV MODE code: <b><?=e($_SESSION['dev_otp'])?></b></div><?php endif; ?>
<form method="post"><?=csrf_field()?><input type="hidden" name="action" value="verify">
<label>6-Digit Verification Code</label><input name="code" placeholder="000000" maxlength="6" inputmode="numeric" autocomplete="one-time-code" required autofocus>
<button class="btn-primary">Verify &amp; Enter Portal</button></form>
<div class="row-between"><a class="btn-outline" href="?back=1">Back</a>
<form method="post"><?=csrf_field()?><input type="hidden" name="action" value="resend"><button class="btn-outline">Resend</button></form></div>
<?php endif; ?>
</div></body></html>
