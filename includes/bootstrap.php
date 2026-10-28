<?php
if (!file_exists(__DIR__.'/../config.php')) die('Missing config.php. Copy config.sample.php to config.php.');
require __DIR__.'/../config.php';
date_default_timezone_set('Asia/Manila');
session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax']);
session_start();

const PURPOSES = ['Employment','Further Studies','Transfer','Government Requirement','Licensure / Examination','Personal Record','Other'];
const RELEASE = ['Office Pickup','Authorized Representative'];
const STATUSES = ['Submitted','Processing','Ready for Release','Released','Rejected','Cancelled'];

function db(){ static $p; if(!$p){ $p=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]); $p->exec("SET time_zone='+08:00'"); } return $p; }
function q($sql,$a=[]){ $s=db()->prepare($sql); $s->execute($a); return $s; }
function e($v){ return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function redirect($u){ header('Location: '.$u); exit; }
function norm($s){ return strtolower(trim((string)$s)); }
function csrf(){ if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrf_field(){ return '<input type="hidden" name="csrf" value="'.csrf().'">'; }
if($_SERVER['REQUEST_METHOD']==='POST' && !hash_equals($_SESSION['csrf']??'',$_POST['csrf']??'')) die('Invalid or expired form. Go back and reload the page.');

function fdate($v){ return $v?date('M j, Y',strtotime($v)):''; }
function fdt($v){ return $v?date('M j, Y g:i A',strtotime($v)):''; }
function mask_email($m){ [$l,$d]=explode('@',$m); return (strlen($l)<=2?$l[0].'*':substr($l,0,2).'***').'@'.$d; }
function add_business_days($date,$days){ $d=new DateTime($date); $c=0; while($c<$days){ $d->modify('+1 day'); if(!in_array((int)$d->format('w'),[0,6])) $c++; } return $d->format('Y-m-d'); }

function require_login($roles){
  $u=$_SESSION['user']??null;
  if(!$u) redirect('index.php');
  if(!in_array($u['role'],$roles)) redirect($u['role']==='Student'?'portal.php':'staff.php');
  return $u;
}
function audit($rid,$actor,$action,$old,$new,$details=''){
  q('INSERT INTO audit_logs (request_id,actor_email,action,old_value,new_value,details,created_at) VALUES (?,?,?,?,?,?,NOW())',[$rid,$actor,$action,$old,$new,$details]);
}

function send_mail($to,$subject,$body){
  $driver=MAIL_DRIVER;
  if($driver==='brevo'||$driver==='resend'){
    if($driver==='brevo'){
      $url='https://api.brevo.com/v3/smtp/email'; $h=['api-key: '.MAIL_API_KEY,'Content-Type: application/json','accept: application/json'];
      $data=['sender'=>['name'=>MAIL_FROM_NAME,'email'=>MAIL_FROM],'to'=>[['email'=>$to]],'subject'=>$subject,'textContent'=>$body];
    } else {
      $url='https://api.resend.com/emails'; $h=['Authorization: Bearer '.MAIL_API_KEY,'Content-Type: application/json'];
      $data=['from'=>MAIL_FROM_NAME.' <'.MAIL_FROM.'>','to'=>[$to],'subject'=>$subject,'text'=>$body];
    }
    $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>$h,CURLOPT_POSTFIELDS=>json_encode($data),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15]);
    curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    return $code>=200 && $code<300;
  }
  $headers="From: ".MAIL_FROM_NAME." <".MAIL_FROM.">\r\nContent-Type: text/plain; charset=UTF-8";
  return @mail($to,$subject,$body,$headers);
}
function notify($rid,$email,$type,$message){
  $subject=UNIVERSITY.' Registrar • '.$rid;
  $ok=send_mail($email,$subject,$message);
  q('INSERT INTO notifications (request_id,recipient_email,type,subject,message,sent_at,status) VALUES (?,?,?,?,?,NOW(),?)',[$rid,$email,$type,$subject,$message,$ok?'Sent':'Failed']);
}

function send_otp($p){
  $email=$p['email'];
  if(!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new Exception('Please enter a valid email address.');
  $staff=q('SELECT id FROM users WHERE email=? AND active=1',[$email])->fetch();
  $known=q('SELECT id FROM students WHERE email=?',[$email])->fetch();
  if(!preg_match('/@panpacificu\.edu\.ph$/i',$email) && !$staff && !$known)
    throw new Exception('Use your Panpacific University email. Alumni/former students may use a personal email only if it is already registered with the Registrar.');
  if(!$staff && $p['name']==='' && !$known) throw new Exception('Please enter your full name.');
  $last=q('SELECT created_at FROM otp_codes WHERE email=? ORDER BY id DESC LIMIT 1',[$email])->fetch();
  if($last && time()-strtotime($last['created_at'])<60) throw new Exception('Please wait before requesting another code.');
  $code=(string)random_int(100000,999999);
  q('UPDATE otp_codes SET used=1 WHERE email=?',[$email]);
  q('INSERT INTO otp_codes (email,code_hash,expires_at,created_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 10 MINUTE),NOW())',[$email,password_hash($code,PASSWORD_DEFAULT)]);
  $ok=send_mail($email,UNIVERSITY.' Registrar Portal Verification Code',
    "Your Registrar Portal verification code is: $code\n\nThis code expires in 10 minutes.\n\nIf you did not request access, ignore this message.\n\n".UNIVERSITY."\n".OFFICE);
  q('INSERT INTO notifications (request_id,recipient_email,type,subject,message,sent_at,status) VALUES (?,?,?,?,?,NOW(),?)',['',$email,'OTP','Verification Code','(code hidden)',$ok?'Sent':'Failed']);
  if(DEV_MODE) $_SESSION['dev_otp']=$code;
  elseif(!$ok) throw new Exception('The verification email could not be sent. Please try again later.');
}
function verify_otp($email,$code){
  $r=q('SELECT * FROM otp_codes WHERE email=? AND used=0 ORDER BY id DESC LIMIT 1',[$email])->fetch();
  if(!$r || strtotime($r['expires_at'])<time()) throw new Exception('The verification code has expired. Please request a new one.');
  if($r['attempts']>=5) throw new Exception('Too many attempts. Please request a new code.');
  q('UPDATE otp_codes SET attempts=attempts+1 WHERE id=?',[$r['id']]);
  if(!password_verify(trim($code),$r['code_hash'])) throw new Exception('Incorrect verification code.');
  q('UPDATE otp_codes SET used=1 WHERE id=?',[$r['id']]);
}
function login_user($p){
  $email=$p['email'];
  $staff=q('SELECT * FROM users WHERE email=? AND active=1',[$email])->fetch();
  session_regenerate_id(true);
  unset($_SESSION['pending'],$_SESSION['dev_otp']);
  if($staff){
    $_SESSION['user']=['id'=>$staff['id'],'email'=>$email,'name'=>$staff['name'],'role'=>$staff['role']];
    return 'staff.php';
  }
  $s=q('SELECT * FROM students WHERE email=?',[$email])->fetch();
  $prog=$p['program']?:null;
  if(!$s){
    q('INSERT INTO students (student_number,full_name,email,student_type,program_id,created_at,updated_at) VALUES (?,?,?,?,?,NOW(),NOW())',[$p['number'],$p['name'],$email,$p['type'],$prog]);
    $s=q('SELECT * FROM students WHERE email=?',[$email])->fetch();
  } else {
    q('UPDATE students SET full_name=?,student_number=IF(?="",student_number,?),student_type=?,program_id=COALESCE(?,program_id),updated_at=NOW() WHERE id=?',[$p['name']?:$s['full_name'],$p['number'],$p['number'],$p['type'],$prog,$s['id']]);
    $s=q('SELECT * FROM students WHERE id=?',[$s['id']])->fetch();
  }
  $_SESSION['user']=['id'=>$s['id'],'email'=>$email,'name'=>$s['full_name'],'role'=>'Student','student_number'=>$s['student_number'],'student_type'=>$s['student_type'],'program_id'=>$s['program_id']];
  return 'portal.php';
}

function page_start($title,$links,$active){
  echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($title).' | '.e(UNIVERSITY).'</title><link rel="stylesheet" href="assets/css/style.css"></head><body class="app">';
  echo '<header class="topbar"><button class="burger" onclick="document.body.classList.toggle(\'nav-open\')">&#9776;</button><img class="mini-logo" src="assets/logo.png" alt="" onerror="this.style.display=\'none\'"><div><b>'.e(OFFICE).'</b><small>Digital Service Portal</small></div><form method="post" action="logout.php" class="signout">'.csrf_field().'<button class="btn-outline">Sign Out</button></form></header>';
  echo '<div class="shell"><nav class="side">';
  foreach($links as $k=>$l) echo '<a href="'.e($l[1]).'" class="'.($k===$active?'on':'').'">'.e($l[0]).'</a>';
  echo '</nav><main class="main">';
}
function page_end(){ echo '</main></div></body></html>'; }
