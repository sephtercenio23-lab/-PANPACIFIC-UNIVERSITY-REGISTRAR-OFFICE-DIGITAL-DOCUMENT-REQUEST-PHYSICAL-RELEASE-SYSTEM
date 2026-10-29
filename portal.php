<?php require 'includes/bootstrap.php';
$u=require_login(['Student']);
$nav=['dash'=>['Dashboard','portal.php'],'req'=>['Request Document','portal.php?p=req'],'my'=>['My Requests','portal.php?p=my']];
$p=$_GET['p']??'dash';
$docs=q('SELECT * FROM document_types WHERE active=1 ORDER BY name')->fetchAll();
$docs=array_values(array_filter($docs,fn($d)=>true));
// order docs like original list
$order=['TOR','COE','COG','COGRA','GM','DIP','HD','CTC','COC','COA','COU','GWA','CCG','CMI','CORANK','CNR','DUPDIP','AUTH','COURSE','COE','COG','STUDSTATUS','OTHERS'];
usort($docs,fn($a,$b)=>(array_search($a['code'],$order)===false?99:array_search($a['code'],$order))<=>(array_search($b['code'],$order)===false?99:array_search($b['code'],$order)));

if($_SERVER['REQUEST_METHOD']==='POST' && $p==='req'){
  try{
    $doc=null; foreach($docs as $d) if($d['code']===($_POST['document']??'')) $doc=$d;
    if(!$doc) throw new Exception('Please select a document.');
    $purpose=$_POST['purpose']??''; if(!in_array($purpose,PURPOSES)) throw new Exception('Please select the purpose of your request.');
    $rel=$_POST['release']??''; if(!in_array($rel,RELEASE)) throw new Exception('Please select a release method.');
    $rn=trim($_POST['rep_name']??''); $rr=trim($_POST['rep_relation']??'');
    if($rel==='Authorized Representative' && ($rn===''||$rr==='')) throw new Exception('Please provide the representative name and relationship.');
    if($rel!=='Authorized Representative'){ $rn=''; $rr=''; }
    $s=q('SELECT program_id FROM students WHERE id=?',[$u['id']])->fetch();
    do{ $rid='PU-REQ-'.date('Y').'-'.random_int(100000,999999); }while(q('SELECT 1 FROM requests WHERE request_id=?',[$rid])->fetch());
    $due=add_business_days(date('Y-m-d'),(int)$doc['typical_days']);
    q('INSERT INTO requests (request_id,student_id,student_number,student_email,student_name,program_id,document_code,purpose,release_method,rep_name,rep_relation,status,submitted_at,updated_at,due_date,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,"Submitted",NOW(),NOW(),?,?)',
      [$rid,$u['id'],$u['student_number']??'',$u['email'],$u['name'],$s['program_id'],$doc['code'],$purpose,$rel,$rn,$rr,$due,trim($_POST['notes']??'')]);
    audit($rid,$u['email'],'REQUEST_CREATED','','Submitted',$doc['name']);
    notify($rid,$u['email'],'Request Submitted','Your '.$doc['name'].' request has been received by the Registrar Office.');
    $_SESSION['flash']=['rid'=>$rid,'doc'=>$doc['name'],'due'=>fdate($due),'fee'=>number_format($doc['base_fee'],2)];
    redirect('portal.php?p=req');
  }catch(Exception $x){ $err=$x->getMessage(); }
}
page_start('Portal',$nav,$p);
if($p==='dash'){
  $r=q('SELECT status,COUNT(*) c FROM requests WHERE student_email=? GROUP BY status',[$u['email']])->fetchAll(PDO::FETCH_KEY_PAIR);
  $total=array_sum($r); $active=($r['Submitted']??0)+($r['Processing']??0);
  echo '<h1>Welcome, '.e($u['name']).'</h1><p class="muted">Your Registrar requests, all in one place.</p><div class="stats">';
  foreach([[$total,'Total Requests'],[$active,'Active Requests'],[$r['Ready for Release']??0,'Ready for Release'],[$r['Released']??0,'Released']] as $s) echo '<div class="card stat"><b>'.$s[0].'</b><span>'.$s[1].'</span></div>';
  echo '</div><div class="card"><h3>How the service works</h3><p class="muted">Select your document → submit your request → track processing → receive a notification → visit the Registrar only when your physical document is ready for release.</p></div>';
} elseif($p==='req'){
  $flash=$_SESSION['flash']??null; unset($_SESSION['flash']);
  echo '<h1>Request a Document</h1><p class="muted">Select the type of document you need and complete the request information.</p><div class="card"><form method="post">'.csrf_field();
  if(!empty($err)) echo '<div class="alert">'.e($err).'</div>';
  echo '<label>Type of Document</label><select name="document" required><option value="">Select a document...</option>';
  foreach($docs as $d) echo '<option value="'.e($d['code']).'">'.e($d['name']).'</option>';
  echo '</select><label>Purpose of Request</label><select name="purpose" required><option value="">Select purpose...</option>';
  foreach(PURPOSES as $x) echo '<option>'.e($x).'</option>';
  echo '</select><label>Release Method</label><select name="release" id="release">';
  foreach(RELEASE as $x) echo '<option>'.e($x).'</option>';
  echo '</select><div id="rep" style="display:none"><label>Representative Name</label><input name="rep_name"><label>Relationship</label><input name="rep_relation"></div>
  <label>Additional Notes <span class="opt">(optional)</span></label><textarea name="notes" rows="4" placeholder="Additional information for the Registrar..."></textarea>';
  if($flash) echo '<div class="success"><b>Request submitted successfully.</b><br>Request ID: <b>'.e($flash['rid']).'</b><br>Document: '.e($flash['doc']).'<br>Estimated completion: '.e($flash['due']).'<br>Base fee: ₱'.e($flash['fee']).'<br><br><span class="muted">Keep your Request ID for tracking and physical release verification.</span></div>';
  echo '<div class="right"><button class="btn-primary inline">Submit Request</button></div></form></div>
  <script>var r=document.getElementById("release"),b=document.getElementById("rep");r.onchange=function(){b.style.display=r.value==="Authorized Representative"?"block":"none"};r.onchange();</script>';
} else {
  $rows=q('SELECT r.*,d.name dn FROM requests r LEFT JOIN document_types d ON d.code=r.document_code WHERE student_email=? ORDER BY submitted_at DESC,request_id DESC',[$u['email']])->fetchAll();
  echo '<h1>My Requests</h1><p class="muted">View your submitted Registrar document requests.</p><div class="card"><div class="tw"><table><tr><th>REQUEST ID</th><th>DOCUMENT</th><th>STATUS</th><th>SUBMITTED</th><th>DUE</th></tr>';
  foreach($rows as $r) echo '<tr><td><b>'.e($r['request_id']).'</b></td><td>'.e($r['dn']?:$r['document_code']).'</td><td><span class="pill">'.e($r['status']).'</span></td><td>'.fdt($r['submitted_at']).'</td><td>'.fdate($r['due_date']).'</td></tr>';
  if(!$rows) echo '<tr><td colspan="5" class="muted">No requests yet.</td></tr>';
  echo '</table></div></div>';
}
page_end();
