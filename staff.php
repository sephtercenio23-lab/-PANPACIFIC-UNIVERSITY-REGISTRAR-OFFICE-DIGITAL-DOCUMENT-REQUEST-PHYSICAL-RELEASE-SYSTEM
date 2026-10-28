<?php require 'includes/bootstrap.php';
$u=require_login(['Registrar','Admin']);
$admin=$u['role']==='Admin';
$nav=['dash'=>['Dashboard','staff.php'],'reqs'=>['All Requests','staff.php?p=reqs']];
if($admin){ $nav['docs']=['Document Types','staff.php?p=docs']; $nav['progs']=['Programs','staff.php?p=progs']; $nav['users']=['Staff Users','staff.php?p=users']; }
$p=$_GET['p']??'dash';
if(in_array($p,['docs','progs','users']) && !$admin) redirect('staff.php');
$msg=''; $err='';

if($_SERVER['REQUEST_METHOD']==='POST'){
  try{
    $a=$_POST['action']??'';
    if($a==='status'){
      $rid=$_POST['rid']; $new=$_POST['status']??''; $reason=trim($_POST['reason']??'');
      if(!in_array($new,STATUSES)) throw new Exception('Invalid status.');
      if(in_array($new,['Rejected','Cancelled']) && $reason==='') throw new Exception('A reason is required for Rejected/Cancelled.');
      $r=q('SELECT r.*,d.name dn FROM requests r LEFT JOIN document_types d ON d.code=r.document_code WHERE request_id=?',[$rid])->fetch();
      if(!$r) throw new Exception('Request not found.');
      if($r['status']!==$new){
        q('UPDATE requests SET status=?,status_reason=?,updated_at=NOW() WHERE request_id=?',[$new,$reason?:null,$rid]);
        audit($rid,$u['email'],'STATUS_CHANGED',$r['status'],$new,$reason);
        $m='Your '.$r['dn'].' request ('.$rid.') is now: '.$new.'.';
        if($new==='Ready for Release') $m='Good news! Your '.$r['dn'].' request ('.$rid.') is ready for release. Please visit the Registrar Office and present your Request ID and a valid ID.';
        if($reason) $m.="\nReason: ".$reason;
        notify($rid,$r['student_email'],'Status Update',$m);
      }
      $_SESSION['msg']='Request updated.'; redirect('staff.php?p=req&id='.urlencode($rid));
    } elseif($a==='doc'){
      q('INSERT INTO document_types (code,name,description,base_fee,typical_days,requirements,active) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),description=VALUES(description),base_fee=VALUES(base_fee),typical_days=VALUES(typical_days),requirements=VALUES(requirements),active=VALUES(active)',
        [strtoupper(trim($_POST['code'])),trim($_POST['name']),trim($_POST['description']),(float)$_POST['fee'],(int)$_POST['days'],trim($_POST['requirements']),isset($_POST['active'])?1:0]);
      $msg='Document saved.';
    } elseif($a==='prog'){
      q('INSERT INTO programs (code,name,active) VALUES (?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),active=VALUES(active)',[strtoupper(trim($_POST['code'])),trim($_POST['name']),isset($_POST['active'])?1:0]);
      $msg='Program saved.';
    } elseif($a==='user'){
      $em=norm($_POST['email']); if(!filter_var($em,FILTER_VALIDATE_EMAIL)) throw new Exception('Invalid email.');
      if($em===norm($u['email']) && !isset($_POST['active'])) throw new Exception('You cannot deactivate yourself.');
      $role=in_array($_POST['role'],['Registrar','Admin'])?$_POST['role']:'Registrar';
      q('INSERT INTO users (email,name,role,active) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),role=VALUES(role),active=VALUES(active)',[$em,trim($_POST['name']),$role,isset($_POST['active'])?1:0]);
      $msg='User saved.';
    }
  }catch(Exception $x){ $err=$x->getMessage(); }
}
if(!empty($_SESSION['msg'])){ $msg=$_SESSION['msg']; unset($_SESSION['msg']); }
$alerts=($err?'<div class="alert">'.e($err).'</div>':'').($msg?'<div class="success">'.e($msg).'</div>':'');
$act=$p==='req'?'reqs':$p;
page_start('Registrar',$nav,$act);
echo $alerts;
$progs=q('SELECT * FROM programs ORDER BY name')->fetchAll();
$docs=q('SELECT * FROM document_types ORDER BY name')->fetchAll();

if($p==='dash'){
  $c=q('SELECT status,COUNT(*) c FROM requests GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
  echo '<h1>Welcome, '.e($u['name']).'</h1><p class="muted">Registrar overview of all document requests.</p><div class="stats">';
  foreach(STATUSES as $s) echo '<a class="card stat" href="staff.php?p=reqs&status='.urlencode($s).'"><b>'.($c[$s]??0).'</b><span>'.e($s).'</span></a>';
  echo '</div><div class="card"><h3>Total requests: '.array_sum($c).'</h3><p class="muted">Open a status above to see the matching requests. Changing a status emails the requester and is recorded in the audit log.</p></div>';
} elseif($p==='reqs'){
  $w=[];$a=[];
  if(!empty($_GET['status'])){$w[]='r.status=?';$a[]=$_GET['status'];}
  if(!empty($_GET['doc'])){$w[]='r.document_code=?';$a[]=$_GET['doc'];}
  if(!empty($_GET['prog'])){$w[]='r.program_id=?';$a[]=(int)$_GET['prog'];}
  if(!empty($_GET['from'])){$w[]='DATE(r.submitted_at)>=?';$a[]=$_GET['from'];}
  if(!empty($_GET['to'])){$w[]='DATE(r.submitted_at)<=?';$a[]=$_GET['to'];}
  if(!empty($_GET['s'])){$w[]='(r.request_id LIKE ? OR r.student_name LIKE ? OR r.student_email LIKE ? OR r.student_number LIKE ?)';$l='%'.$_GET['s'].'%';array_push($a,$l,$l,$l,$l);}
  $rows=q('SELECT r.*,d.name dn,g.code pc FROM requests r LEFT JOIN document_types d ON d.code=r.document_code LEFT JOIN programs g ON g.id=r.program_id'.($w?' WHERE '.implode(' AND ',$w):'').' ORDER BY r.submitted_at DESC LIMIT 500',$a)->fetchAll();
  $g=fn($k)=>$_GET[$k]??'';
  echo '<h1>All Requests</h1><p class="muted">Search and filter document requests.</p><div class="card"><form method="get" class="filters"><input type="hidden" name="p" value="reqs"><input name="s" placeholder="Search ID, name, email" value="'.e($g('s')).'"><select name="status"><option value="">All statuses</option>';
  foreach(STATUSES as $s) echo '<option'.($g('status')===$s?' selected':'').'>'.e($s).'</option>';
  echo '</select><select name="doc"><option value="">All documents</option>';
  foreach($docs as $d) echo '<option value="'.e($d['code']).'"'.($g('doc')===$d['code']?' selected':'').'>'.e($d['name']).'</option>';
  echo '</select><select name="prog"><option value="">All programs</option>';
  foreach($progs as $d) echo '<option value="'.$d['id'].'"'.($g('prog')==$d['id']?' selected':'').'>'.e($d['code']).'</option>';
  echo '</select><input type="date" name="from" value="'.e($g('from')).'"><input type="date" name="to" value="'.e($g('to')).'"><button class="btn-primary inline">Filter</button></form>
  <div class="tw"><table><tr><th>REQUEST ID</th><th>REQUESTER</th><th>PROGRAM</th><th>DOCUMENT</th><th>STATUS</th><th>SUBMITTED</th><th>DUE</th></tr>';
  foreach($rows as $r) echo '<tr><td><a href="staff.php?p=req&id='.urlencode($r['request_id']).'"><b>'.e($r['request_id']).'</b></a></td><td>'.e($r['student_name']).'<br><small class="muted">'.e($r['student_email']).'</small></td><td>'.e($r['pc']).'</td><td>'.e($r['dn']).'</td><td><span class="pill">'.e($r['status']).'</span></td><td>'.fdt($r['submitted_at']).'</td><td>'.fdate($r['due_date']).'</td></tr>';
  if(!$rows) echo '<tr><td colspan="7" class="muted">No requests found.</td></tr>';
  echo '</table></div></div>';
} elseif($p==='req'){
  $r=q('SELECT r.*,d.name dn,d.base_fee,d.requirements,g.name pn FROM requests r LEFT JOIN document_types d ON d.code=r.document_code LEFT JOIN programs g ON g.id=r.program_id WHERE request_id=?',[$_GET['id']??''])->fetch();
  if(!$r){ echo '<h1>Not found</h1>'; }
  else {
    echo '<h1>'.e($r['request_id']).'</h1><p class="muted"><a href="staff.php?p=reqs">← Back to all requests</a></p><div class="card"><div class="grid2">';
    foreach([['Requester',$r['student_name'].' ('.$r['student_email'].')'],['Student Number',$r['student_number']?:'—'],['Program',$r['pn']?:'—'],['Document',$r['dn']],['Purpose',$r['purpose']],['Release Method',$r['release_method']],
      ['Representative',$r['rep_name']?$r['rep_name'].' ('.$r['rep_relation'].')':'—'],['Base Fee','₱'.number_format($r['base_fee'],2)],['Requirements',str_replace(';',', ',$r['requirements'])],['Submitted',fdt($r['submitted_at'])],['Due',fdate($r['due_date'])],['Notes',$r['notes']?:'—']] as $x)
      echo '<div><small class="muted">'.e($x[0]).'</small><br>'.e($x[1]).'</div>';
    echo '</div></div><div class="card"><h3>Update status</h3><p>Current: <span class="pill">'.e($r['status']).'</span></p><form method="post">'.csrf_field().'<input type="hidden" name="action" value="status"><input type="hidden" name="rid" value="'.e($r['request_id']).'">
    <label>New status</label><select name="status">';
    foreach(STATUSES as $s) echo '<option'.($s===$r['status']?' selected':'').'>'.e($s).'</option>';
    echo '</select><label>Reason <span class="opt">(required for Rejected / Cancelled)</span></label><textarea name="reason" rows="3">'.e($r['status_reason']).'</textarea><div class="right"><button class="btn-primary inline">Save &amp; Notify Requester</button></div></form></div>';
    $logs=q('SELECT * FROM audit_logs WHERE request_id=? ORDER BY id DESC',[$r['request_id']])->fetchAll();
    echo '<div class="card"><h3>Audit trail</h3><div class="tw"><table><tr><th>WHEN</th><th>ACTOR</th><th>ACTION</th><th>CHANGE</th><th>DETAILS</th></tr>';
    foreach($logs as $l) echo '<tr><td>'.fdt($l['created_at']).'</td><td>'.e($l['actor_email']).'</td><td>'.e($l['action']).'</td><td>'.e($l['old_value']).' → '.e($l['new_value']).'</td><td>'.e($l['details']).'</td></tr>';
    echo '</table></div></div>';
  }
} elseif($p==='docs'){
  echo '<h1>Document Types</h1><p class="muted">Manage fees, typical days and requirements. Use a new code to add a document.</p><div class="card"><div class="tw"><table><tr><th>CODE</th><th>NAME</th><th>DESCRIPTION</th><th>FEE</th><th>DAYS</th><th>REQUIREMENTS</th><th>ACTIVE</th><th></th></tr>';
  foreach(array_merge($docs,[['code'=>'','name'=>'','description'=>'','base_fee'=>'0','typical_days'=>'3','requirements'=>'','active'=>1,'new'=>1]]) as $d)
    echo '<tr><form method="post">'.csrf_field().'<input type="hidden" name="action" value="doc"><td><input name="code" value="'.e($d['code']).'" size="6" '.(isset($d['new'])?'placeholder="NEW"':'readonly').' required></td><td><input name="name" value="'.e($d['name']).'" required></td><td><input name="description" value="'.e($d['description']).'"></td><td><input name="fee" value="'.e($d['base_fee']).'" size="6"></td><td><input name="days" value="'.e($d['typical_days']).'" size="3"></td><td><input name="requirements" value="'.e($d['requirements']).'"></td><td><input type="checkbox" name="active" '.($d['active']?'checked':'').'></td><td><button class="btn-outline">Save</button></td></form></tr>';
  echo '</table></div></div>';
} elseif($p==='progs'){
  echo '<h1>Programs</h1><p class="muted">The program list shown on the login form. Replace the placeholders with the official list.</p><div class="card"><div class="tw"><table><tr><th>CODE</th><th>NAME</th><th>ACTIVE</th><th></th></tr>';
  foreach(array_merge($progs,[['code'=>'','name'=>'','active'=>1,'new'=>1]]) as $d)
    echo '<tr><form method="post">'.csrf_field().'<input type="hidden" name="action" value="prog"><td><input name="code" value="'.e($d['code']).'" size="8" '.(isset($d['new'])?'placeholder="NEW"':'readonly').' required></td><td><input name="name" value="'.e($d['name']).'" size="40" required></td><td><input type="checkbox" name="active" '.($d['active']?'checked':'').'></td><td><button class="btn-outline">Save</button></td></form></tr>';
  echo '</table></div></div>';
} elseif($p==='users'){
  $us=q('SELECT * FROM users ORDER BY name')->fetchAll();
  echo '<h1>Staff Users</h1><p class="muted">Registrar/Admin accounts that sign in with email OTP.</p><div class="card"><div class="tw"><table><tr><th>EMAIL</th><th>NAME</th><th>ROLE</th><th>ACTIVE</th><th></th></tr>';
  foreach(array_merge($us,[['email'=>'','name'=>'','role'=>'Registrar','active'=>1,'new'=>1]]) as $d)
    echo '<tr><form method="post">'.csrf_field().'<input type="hidden" name="action" value="user"><td><input name="email" value="'.e($d['email']).'" '.(isset($d['new'])?'placeholder="new staff email"':'readonly').' required></td><td><input name="name" value="'.e($d['name']).'" required></td><td><select name="role"><option'.($d['role']==='Registrar'?' selected':'').'>Registrar</option><option'.($d['role']==='Admin'?' selected':'').'>Admin</option></select></td><td><input type="checkbox" name="active" '.($d['active']?'checked':'').'></td><td><button class="btn-outline">Save</button></td></form></tr>';
  echo '</table></div></div>';
}
page_end();
