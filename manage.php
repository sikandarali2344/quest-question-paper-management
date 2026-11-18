<?php
require 'includes/boot.php';
need();
$t=$_GET['t']??'departments';
$u=user();
$dep=(int)($u['department_id']??0);
if($u['role']==='teacher'){http_response_code(403);die('Teachers can manage their own question papers, not administration records.');}
if(($_GET['t']??'')==='department_admins'&&!admin()){http_response_code(403);die('Only the Super Admin can manage department administrators.');}
if(in_array($t,['grading_policies','grade_bands'],true)&&!admin()){http_response_code(403);die('Only the Super Admin can manage grading policies.');}
$table=$t==='department_admins'?'users':($t==='grade_bands'?'grading_policy_bands':$t);
$ST=['active','inactive'];
$defs=[
 'departments'=>['Departments',['name'=>['Department name','text',true],'description'=>['Description','textarea',false],'status'=>['Status','opt',$ST]]],
 'academic_years'=>['Academic Years',['department_id'=>['Department','sel','departments',true],'label'=>['Year label','text',true]]],
 'batches'=>['Batches',['department_id'=>['Department','sel','departments',true],'name'=>['Batch name','text',true],'status'=>['Status','opt',$ST],'grading_policy_id'=>['Batch grading policy','sel','grading_policies',true]]],
 'grading_policies'=>['Grading Policies',['name'=>['Policy name','text',true],'description'=>['Description','text',false]]],
 'grade_bands'=>['Grade Bands',['policy_id'=>['Grading policy','sel','grading_policies',true],'min_percentage'=>['Minimum percentage','number',true],'grade'=>['Grade label','text',true],'grade_point'=>['Grade point','number',false]]],
 'classes'=>['Semesters',['department_id'=>['Department','sel','departments',true],'academic_year_id'=>['Academic year','sel','academic_years',true],'semester_no'=>['Semester number','opt',['1','2','3','4','5','6','7','8']],'status'=>['Status','opt',$ST]]],
 'subjects'=>['Subjects',['department_id'=>['Department','sel','departments',true],'class_id'=>['Semester','sel','classes',true],'name'=>['Subject name','text',true],'teacher_id'=>['Assigned teacher','sel','users',true],'status'=>['Status','opt',$ST]]],
 'users'=>['Teachers',['name'=>['Teacher name','text',true],'email'=>['Email','email',true],'department_id'=>['Department','sel','departments',true],'password'=>['Initial / new password','password',false],'status'=>['Status','opt',$ST]]],
 'department_admins'=>['Department Admins',['name'=>['Admin name','text',true],'email'=>['Email','email',true],'department_id'=>['Department','sel','departments',true],'password'=>['Initial / new password','password',false],'status'=>['Status','opt',$ST]]]
];
if(!isset($defs[$t])){http_response_code(404);die('Unknown management page.');}
if(in_array($t,['departments','department_admins'],true)&&!admin()){http_response_code(403);die('Forbidden');}
$fields=$defs[$t][1];
$scopeWhere=function()use($t,$dep,$u){
 if(admin())return in_array($t,['users','department_admins'],true)?[' WHERE role="'.($t==='department_admins'?'dept_admin':'teacher').'"',null]:['',null];
 if($t==='departments')return [' WHERE id=?',$dep];
 if($t==='users')return [' WHERE role="teacher" AND department_id=?',$dep];
 return [' WHERE department_id=?',$dep];
};
$canEdit=function($id)use($t,$dep){
 if($t==='departments')return admin();
 if($t==='department_admins'){
  if(!admin())return false;
  $s=db()->prepare('SELECT 1 FROM users WHERE id=? AND role="dept_admin"');$s->execute([$id]);return (bool)$s->fetchColumn();
 }
 if($t==='users'){
  $query='SELECT 1 FROM users WHERE id=? AND role="teacher"'.(admin()?'':' AND department_id=?');
  $s=db()->prepare($query);$args=[$id];if(!admin())$args[]=$dep;$s->execute($args);return (bool)$s->fetchColumn();
 }
 if(admin())return true;
 $column=$t==='academic_years'?'department_id':'department_id';
 $query=$t==='users'?'SELECT 1 FROM users WHERE id=? AND role="teacher" AND department_id=?':"SELECT 1 FROM $t WHERE id=? AND $column=?";
 $s=db()->prepare($query);$s->execute([$id,$dep]);return (bool)$s->fetchColumn();
};
$options=[];$optionDepartments=[];
foreach($fields as $key=>$f)if(($f[1]??'')==='sel'){
 $source=$f[2];$options[$key]=[];$optionDepartments[$key]=[];
 if($source==='departments'){
  $sql='SELECT id,name label FROM departments'.(admin()?'':' WHERE id='.(int)$dep).' ORDER BY name';
 }elseif($source==='academic_years'){
  $sql='SELECT id,label,department_id FROM academic_years'.(admin()?'':' WHERE department_id='.(int)$dep).' ORDER BY department_id,label';
 }elseif($source==='classes'){
  $sql='SELECT c.id,CONCAT(a.label," — Semester ",c.semester_no) label,c.department_id FROM classes c JOIN academic_years a ON a.id=c.academic_year_id'.(admin()?'':' WHERE c.department_id='.(int)$dep).' ORDER BY c.department_id,c.semester_no';
 }elseif($source==='grading_policies'){
  $sql=$key==='grading_policy_id'?'SELECT p.id,p.name label FROM grading_policies p WHERE EXISTS (SELECT 1 FROM grading_policy_bands band WHERE band.policy_id=p.id AND band.min_percentage=0) ORDER BY p.name':'SELECT id,name label FROM grading_policies ORDER BY name';
 }else{
  $sql='SELECT id,name label FROM users WHERE role="teacher" AND status="active"'.(admin()?'':' AND department_id='.(int)$dep).' ORDER BY name';
 }
 foreach(db()->query($sql) as $r){$options[$key][$r['id']]=$r['label'];if(isset($r['department_id']))$optionDepartments[$key][$r['id']]=$r['department_id'];}
}
$defaultGradingPolicyId=(int)db()->query("SELECT id FROM grading_policies WHERE policy_key='quest_2022_onward'")->fetchColumn();
if($_SERVER['REQUEST_METHOD']==='POST'){
 $id=(int)($_POST['id']??0);
 try{
  if(isset($_POST['set_status'])){
   if(!$id||!$canEdit($id)||!in_array('status',array_keys($fields),true))throw new Exception('You cannot change this record status.');
   $status=$_POST['set_status'];
   if(!in_array($status,$ST,true))throw new Exception('Choose a valid status.');
   db()->prepare("UPDATE $table SET status=? WHERE id=?")->execute([$status,$id]);
   audit('status_change',"$t#$id:$status");flash('success',$status==='inactive'?'Record deactivated. Linked papers and history are preserved.':'Record activated.');
  }elseif(isset($_POST['del'])){
   if(!$id||!$canEdit($id))throw new Exception('You cannot delete this record.');
   $pdo=db();
   if($t==='grading_policies'){
    $check=$pdo->prepare('SELECT COUNT(*) FROM batches WHERE grading_policy_id=?');$check->execute([$id]);$count=(int)$check->fetchColumn();
    if($count)throw new Exception("Cannot delete this grading policy: it is assigned to $count batch(es). Reassign those batches first.");
   }elseif($t==='grade_bands'){
    $bandQuery=$pdo->prepare('SELECT policy_id,min_percentage FROM grading_policy_bands WHERE id=?');$bandQuery->execute([$id]);$band=$bandQuery->fetch();
    if(!$band)throw new Exception('The grade band was not found.');
    if((float)$band['min_percentage']===0.0)throw new Exception('Keep the 0% grade band so every score has a matching grade.');
    $check=$pdo->prepare('SELECT COUNT(*) FROM grading_policy_bands WHERE policy_id=?');$check->execute([$band['policy_id']]);$count=(int)$check->fetchColumn();
    if($count<=1)throw new Exception('A grading policy must keep at least one grade band.');
   }elseif($t==='departments'){
    $checks=['academic years'=>'SELECT COUNT(*) FROM academic_years WHERE department_id=?','batches'=>'SELECT COUNT(*) FROM batches WHERE department_id=?','semesters'=>'SELECT COUNT(*) FROM classes WHERE department_id=?','subjects'=>'SELECT COUNT(*) FROM subjects WHERE department_id=?','teachers'=>'SELECT COUNT(*) FROM users WHERE department_id=?','papers'=>'SELECT COUNT(*) FROM question_papers WHERE department_id=?'];
    $linked=[];foreach($checks as$label=>$query){$check=$pdo->prepare($query);$check->execute([$id]);$count=(int)$check->fetchColumn();if($count)$linked[]="$count $label";}
    if($linked)throw new Exception('Cannot delete this department while it contains '.implode(', ',$linked).'. Remove unlinked records first, or deactivate the department to preserve history.');
   }elseif($t==='academic_years'){
    $check=$pdo->prepare('SELECT COUNT(*) FROM classes WHERE academic_year_id=?');$check->execute([$id]);$count=(int)$check->fetchColumn();
    if($count)throw new Exception("Cannot delete this academic year: $count semester(s) still belong to it. Remove unlinked semesters first, or keep the year to preserve the academic structure.");
   }elseif($t==='batches'){
    $check=$pdo->prepare('SELECT COUNT(*) FROM question_papers WHERE batch_id=?');$check->execute([$id]);$count=(int)$check->fetchColumn();
    if($count)throw new Exception("Cannot delete this batch: it is linked to $count saved paper(s). Deactivate the batch instead so past examination records remain intact.");
   }elseif($t==='classes'){
    $check=$pdo->prepare('SELECT COUNT(*) FROM subjects WHERE class_id=?');$check->execute([$id]);$subjects=(int)$check->fetchColumn();
    $check=$pdo->prepare('SELECT COUNT(*) FROM question_papers WHERE class_id=?');$check->execute([$id]);$papers=(int)$check->fetchColumn();
    if($subjects||$papers){$reason=[];if($subjects)$reason[]="$subjects subject(s)";if($papers)$reason[]="$papers saved paper(s)";throw new Exception('Cannot delete this semester: it is linked to '.implode(' and ',$reason).'. Delete unlinked subjects first; semesters with papers must be deactivated so the examination history stays intact.');}
   }elseif($t==='subjects'){
    $check=$pdo->prepare('SELECT COUNT(*) FROM question_papers WHERE subject_id=?');$check->execute([$id]);$count=(int)$check->fetchColumn();
    if($count)throw new Exception("Cannot delete this subject: it is linked to $count saved paper(s). Deactivate the subject instead so past examination records remain intact.");
   }elseif(in_array($t,['users','department_admins'],true)){
    $check=$pdo->prepare('SELECT COUNT(*) FROM question_papers WHERE created_by=?');$check->execute([$id]);$papers=(int)$check->fetchColumn();
    $subjects=0;
    if($t==='users'){$check=$pdo->prepare('SELECT COUNT(*) FROM subjects WHERE teacher_id=?');$check->execute([$id]);$subjects=(int)$check->fetchColumn();}
    $marksheets=0;
    if($t==='users'){$check=$pdo->prepare('SELECT COUNT(*) FROM teacher_marksheets WHERE teacher_id=?');$check->execute([$id]);$marksheets=(int)$check->fetchColumn();}
    if($papers||$subjects||$marksheets){$reason=[];if($papers)$reason[]="$papers saved paper(s)";if($subjects)$reason[]="$subjects assigned subject(s)";if($marksheets)$reason[]="$marksheets marks sheet(s)";$accountType=$t==='department_admins'?'department admin':'teacher';throw new Exception("Cannot delete this $accountType because the account is linked to ".implode(' and ',$reason).'. Deactivate the account instead; linked records will be preserved.');}
   }
   db()->prepare("DELETE FROM $table WHERE id=?")->execute([$id]);
   audit('delete',"$t#$id");flash('success','Record deleted.');
  }else{
   $v=[];
   foreach($fields as $key=>$f){
    $type=$f[1]??'text';$value=trim($_POST[$key]??'');
    if($type==='password'){if($value!=='')$v['password_hash']=password_hash($value,PASSWORD_DEFAULT);continue;}
    if($type==='opt'&&!in_array($value,$f[2],true))throw new Exception('Select a valid value for '.$f[0].'.');
    if($type==='sel'&&$value!==''&&!isset($options[$key][$value]))throw new Exception('Select a valid value for '.$f[0].'.');
    if(($f[2]??false)&&$value==='')throw new Exception($f[0].' is required.');
    $v[$key]=$value===''?null:$value;
   }
   if($t==='grading_policies'&&!$id)$v['policy_key']='custom_'.bin2hex(random_bytes(8));
   if($t==='grade_bands'){
    $minimum=$v['min_percentage']??null;$gradePoint=$v['grade_point']??null;
    if(!is_numeric($minimum)||!is_finite((float)$minimum)||(float)$minimum<0||(float)$minimum>100)throw new Exception('Minimum percentage must be from 0 to 100.');
    if(strlen((string)$v['grade'])>12)throw new Exception('Grade labels must be 12 characters or fewer.');
    if($gradePoint!==null&&(!is_numeric($gradePoint)||!is_finite((float)$gradePoint)||(float)$gradePoint<0||(float)$gradePoint>4))throw new Exception('Grade point must be from 0 to 4, or left empty for grades without points.');
    $duplicate=db()->prepare('SELECT id FROM grading_policy_bands WHERE policy_id=? AND min_percentage=?'.($id?' AND id<>?':''));
    $duplicateArgs=[$v['policy_id'],$minimum];if($id)$duplicateArgs[]=$id;$duplicate->execute($duplicateArgs);
    if($duplicate->fetchColumn())throw new Exception('This grading policy already has a band at that minimum percentage.');
    if($id){
     $currentBand=db()->prepare('SELECT min_percentage FROM grading_policy_bands WHERE id=?');$currentBand->execute([$id]);
     if((float)$currentBand->fetchColumn()===0.0&&(float)$minimum!==0.0)throw new Exception('Keep the 0% minimum band so every score has a matching grade.');
    }
   }
   if(!admin()){
    if(in_array($t,['academic_years','batches','classes','subjects','users'],true))$v['department_id']=$dep;
   }
   if($t==='departments'){
    $base=strtoupper(substr(preg_replace('/[^A-Za-z0-9]/','',$v['name']),0,12))?:'DEPT';
    $v['code']=$base;
    for($i=1;;$i++){
     $q=db()->prepare('SELECT id FROM departments WHERE code=?'.($id?' AND id<>?':''));
     $args=[$v['code']];if($id)$args[]=$id;$q->execute($args);
     if(!$q->fetchColumn())break;
     $v['code']=substr($base,0,9).$i;
    }
   }
   if($t==='academic_years'){
    if(!isset($options['department_id'][$v['department_id']]))throw new Exception('Select a department.');
   }
   if($t==='batches'){
    if(!isset($options['department_id'][$v['department_id']]))throw new Exception('Select a department.');
    if(!isset($options['grading_policy_id'][$v['grading_policy_id']]))throw new Exception('Select a valid batch grading policy.');
    $check=db()->prepare('SELECT id FROM batches WHERE department_id=? AND LOWER(name)=LOWER(?)'.($id?' AND id<>?':''));
    $args=[$v['department_id'],$v['name']];if($id)$args[]=$id;$check->execute($args);
    if($check->fetchColumn())throw new Exception('That batch already exists in the selected department.');
   }
   if($t==='classes'){
    $s=db()->prepare('SELECT 1 FROM academic_years WHERE id=? AND department_id=?');
    $s->execute([$v['academic_year_id'],$v['department_id']]);
    if(!$s->fetchColumn())throw new Exception('The academic year must belong to the selected department.');
    $v['name']='Semester '.$v['semester_no'];$v['program']=null;$v['section']=null;
    $q=db()->prepare('SELECT id FROM classes WHERE department_id=? AND semester_no=? AND academic_year_id=?'.($id?' AND id<>?':''));
    $a=[$v['department_id'],$v['semester_no'],$v['academic_year_id']];if($id)$a[]=$id;$q->execute($a);
    if($q->fetchColumn())throw new Exception('That semester already exists in the selected academic year.');
   }
   if($t==='subjects'){
    if(!$v['teacher_id'])throw new Exception('Assign an active teacher to the subject.');
    $s=db()->prepare('SELECT 1 FROM classes WHERE id=? AND department_id=? AND academic_year_id IS NOT NULL');
    $s->execute([$v['class_id'],$v['department_id']]);
    if(!$s->fetchColumn())throw new Exception('Choose a semester from the selected department.');
    if($v['teacher_id']!==null){
     $s=db()->prepare('SELECT 1 FROM users WHERE id=? AND role="teacher" AND status="active" AND department_id=?');
     $s->execute([$v['teacher_id'],$v['department_id']]);
     if(!$s->fetchColumn())throw new Exception('The teacher must belong to the selected department.');
    }
    $s=db()->prepare('SELECT id FROM subjects WHERE class_id=? AND LOWER(name)=LOWER(?)'.($id?' AND id<>?':''));
    $args=[$v['class_id'],$v['name']];if($id)$args[]=$id;$s->execute($args);
    if($s->fetchColumn())throw new Exception('A subject with that name already exists in this semester.');
    $v['code']='S'.strtoupper(bin2hex(random_bytes(5)));
   }
   if(in_array($t,['users','department_admins'],true)){
    if($t==='department_admins'&&!admin())throw new Exception('Only the Super Admin can manage department administrators.');
    $v['role']=$t==='department_admins'?'dept_admin':'teacher';
    if(!admin())$v['department_id']=$dep;
    if(!$id&&!isset($v['password_hash']))throw new Exception('A password is required for a new account.');
    if($t==='department_admins'&&isset($v['password_hash'])&&strlen($_POST['password'])<10)throw new Exception('Department administrator passwords must be at least 10 characters.');
    if($id&&!isset($v['password_hash']))unset($v['password_hash']);
   }
   if($id){
    if(!$canEdit($id))throw new Exception('You cannot edit this record.');
    if($t==='users'&&!isset($v['password_hash']))unset($v['password_hash']);
    $set=implode(',',array_map(fn($k)=>"$k=?",array_keys($v)));
    db()->prepare("UPDATE $table SET $set WHERE id=?")->execute([...array_values($v),$id]);
   }else{
    $pdo=db();
    if($t==='departments')$pdo->beginTransaction();
    $pdo->prepare("INSERT INTO $table(".implode(',',array_keys($v)).') VALUES('.implode(',',array_fill(0,count($v),'?')).')')->execute(array_values($v));
    if($t==='departments'){
     $departmentId=(int)$pdo->lastInsertId();
     foreach(['1st Year','2nd Year','3rd Year','4th Year']as$yearIndex=>$label){
      $pdo->prepare('INSERT INTO academic_years(department_id,label) VALUES(?,?)')->execute([$departmentId,$label]);$yearId=(int)$pdo->lastInsertId();
      for($semester=($yearIndex*2)+1;$semester<=($yearIndex*2)+2;$semester++)$pdo->prepare('INSERT INTO classes(department_id,name,semester_no,academic_year_id,status) VALUES(?,?,?,?,?)')->execute([$departmentId,'Semester '.$semester,$semester,$yearId,'active']);
     }
     $pdo->commit();
    }
   }
   audit('save',$t);flash('success','Record saved.');
  }
 }catch(PDOException $x){
  if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();
  error_log($x->getMessage());
  flash('danger','This record is in use or conflicts with an existing record. Existing linked data has been preserved.');
 }catch(Throwable $x){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();flash('danger',$x->getMessage());}
 go("manage.php?t=$t");
}
[$where,$scopeValue]=$scopeWhere();
$sql="SELECT * FROM $table$where ORDER BY id DESC";
$s=db()->prepare($sql);$s->execute($scopeValue===null?[]:[$scopeValue]);$rows=$s->fetchAll();
$ed=null;if(isset($_GET['edit']))foreach($rows as $r)if((int)$r['id']===(int)$_GET['edit'])$ed=$r;
head($defs[$t][0]);?>
<?php if($t==='grade_bands'):?><div class="alert alert-info">Enter each minimum percentage and its grade. Keep the 0% band so every mark has a matching grade; leave Grade point empty for grades such as F.</div><?php elseif($t==='grading_policies'):?><div class="alert alert-info">Create a grading scale, then add its percentage bands under Grade Bands. Assign a complete scale to a batch. A policy assigned to any batch cannot be deleted until those batches are reassigned.</div><?php endif?>
<div class=row><div class=col-lg-4><div class=cardx><h6><?=$ed?'Edit':'Add'?> <?=$defs[$t][0]?></h6><form method=post><?=csrf_field()?><input type=hidden name=id value="<?=$ed['id']??0?>">
<?php foreach($fields as $key=>$f):$type=$f[1]??'text';$value=$ed[$key]??($t==='batches'&&$key==='grading_policy_id'?$defaultGradingPolicyId:'');$placeholder=$t==='batches'&&$key==='name'?'e.g. 23BSCS':'';?>
<?php if($key==='grade_point'):?><div class="form-text">Leave blank for a grade with no grade point, such as F.</div><?php endif?><label class="small mt-2"><?=e($f[0])?></label>
<?php if($type==='sel'):?><select class=form-select name="<?=$key?>" <?=($f[3]??false)?'required':''?>><option value="">Select</option><?php foreach($options[$key]as$oid=>$label):?><option value="<?=$oid?>" <?php if(isset($optionDepartments[$key][$oid]))echo 'data-department-id="'.(int)$optionDepartments[$key][$oid].'" ';?><?=$value==$oid?'selected':''?>><?=e($label)?></option><?php endforeach?></select>
<?php elseif($type==='opt'):?><select class=form-select name="<?=$key?>" required><?php foreach($f[2]as$option):$optionLabel=$option;if($t==='batches'&&$key==='grading_policy')$optionLabel=$option==='quest_2022_onward'?'QUEST Revised Regulations 2022 onward':'Legacy grading scale';?><option value="<?=e($option)?>" <?=$value==$option?'selected':''?>><?=e($optionLabel)?></option><?php endforeach?></select>
<?php elseif($type==='textarea'):?><textarea class=form-control name="<?=$key?>" rows=2><?=e($value)?></textarea>
<?php else:?><input class=form-control name="<?=$key?>" type="<?=$type?>" value="<?=$type==='password'?'':e($value)?>" <?=$placeholder?'placeholder="'.e($placeholder).'"':''?> <?=$t==='batches'&&$key==='name'?'maxlength=80':''?> <?=$t==='department_admins'&&$key==='password'?'minlength=10':''?> <?=$t==='grade_bands'&&$key==='min_percentage'?'min=0 max=100 step=0.01':''?> <?=$t==='grade_bands'&&$key==='grade_point'?'min=0 max=4 step=0.1':''?> <?=($f[2]??false)&&$type!=='password'?'required':''?> <?=$type==='password'&&!$ed?'required':''?>><?php endif?><?php endforeach?>
<button class="btn btn-q mt-3">Save</button> <?php if($ed):?><a class="btn btn-light mt-3" href="manage.php?t=<?=$t?>">Cancel</a><?php endif?></form></div></div>
<div class=col-lg-8><div class=cardx><div class=table-responsive><table class=t><tr><?php foreach($fields as$key=>$f)if($key!=='password')echo '<th>'.e($f[0]).'</th>'?><?php if($t==='users')echo '<th>Assigned subjects</th>'?><th>Actions</th></tr>
<?php foreach($rows as$r):?><tr><?php foreach($fields as$key=>$f){if($key==='password')continue;$value=$r[$key]??'—';if(($f[1]??'')==='sel')$value=$options[$key][$value]??'—';if($key==='semester_no')$value='Semester '.$value;echo '<td>'.e($value).'</td>';}?><?php if($t==='users'){$assigned=db()->prepare('SELECT s.name,a.label,c.semester_no FROM subjects s JOIN classes c ON c.id=s.class_id LEFT JOIN academic_years a ON a.id=c.academic_year_id WHERE s.teacher_id=? ORDER BY a.label,c.semester_no,s.name');$assigned->execute([$r['id']]);$list=[];foreach($assigned->fetchAll()as$subject)$list[]=$subject['name'].' ('.$subject['label'].' · Sem '.$subject['semester_no'].')';echo '<td>'.e($list?implode(', ',$list):'—').'</td>';}?><td class=text-nowrap><?php if($canEdit((int)$r['id'])):?><a href="?t=<?=$t?>&edit=<?=$r['id']?>" class="btn btn-sm btn-outline-primary">Edit</a>
<?php if(in_array('status',array_keys($fields),true)):?><form method=post class=d-inline><?=csrf_field()?><input type=hidden name=id value="<?=$r['id']?>"><button class="btn btn-sm btn-outline-secondary" name=set_status value="<?=$r['status']==='active'?'inactive':'active'?>"><?=$r['status']==='active'?'Deactivate':'Activate'?></button></form><?php endif?>
<form method=post class=d-inline onsubmit="return confirm('Delete this record? Linked records cannot be removed.')"><?=csrf_field()?><input type=hidden name=id value="<?=$r['id']?>"><button name=del value=1 class="btn btn-sm btn-outline-danger">Delete</button></form><?php else:?>—<?php endif?></td></tr><?php endforeach;if(!$rows)echo '<tr><td colspan=8 class=text-muted>Nothing here yet.</td></tr>'?></table></div></div></div></div>
<?php if(in_array($t,['classes','subjects'],true)):?><script>
const departmentSelect=document.querySelector('[name="department_id"]');
const scopedSelect=document.querySelector('[name="<?= $t==='classes'?'academic_year_id':'class_id' ?>"]');
function filterDepartmentOptions(){const selectedDepartment=departmentSelect.value;let selectionValid=false;for(const option of scopedSelect.options){if(!option.value)continue;const matches=option.dataset.departmentId===selectedDepartment;option.hidden=!matches;option.disabled=!matches;if(option.value===scopedSelect.value&&matches)selectionValid=true}if(!selectionValid)scopedSelect.value=''}
departmentSelect.addEventListener('change',filterDepartmentOptions);filterDepartmentOptions();
</script><?php endif?>
<?php foot();
