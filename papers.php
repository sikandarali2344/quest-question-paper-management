<?php
require 'includes/boot.php';
need();
$u=user();$filters=[];$params=[];
if(!admin()){$filters[]='p.department_id=?';$params[]=$u['department_id'];if($u['role']==='teacher'){$filters[]='p.created_by=?';$params[]=$u['id'];}}
foreach(['status'=>'p.status','department_id'=>'p.department_id','batch_id'=>'p.batch_id','academic_year_id'=>'a.id','class_id'=>'p.class_id','subject_id'=>'p.subject_id']as$key=>$column){
 if(($_GET[$key]??'')!==''){$filters[]="$column=?";$params[]=$_GET[$key];}
}
if(($_GET['exam_date']??'')!==''){$filters[]='p.exam_date=?';$params[]=$_GET['exam_date'];}
$examType=$_GET['exam_type']??'';
if($examType==='Midterm'){$filters[]='p.exam_type=?';$params[]='Midterm';}
elseif($examType==='Final'){$filters[]='p.exam_type IN (?,?)';array_push($params,'Final','Final Term');}
elseif($examType==='Practical'){$filters[]='p.exam_type=?';$params[]='Practical';}
$query=trim($_GET['q']??'');
if($query!==''){$filters[]='(s.name LIKE ? OR p.exam_label LIKE ? OR b.name LIKE ?)';array_push($params,"%$query%","%$query%","%$query%");}
$where=$filters?' WHERE '.implode(' AND ',$filters):'';
$joins=' FROM question_papers p JOIN subjects s ON s.id=p.subject_id JOIN classes c ON c.id=p.class_id JOIN departments d ON d.id=p.department_id LEFT JOIN academic_years a ON a.id=c.academic_year_id LEFT JOIN batches b ON b.id=p.batch_id';
$count=db()->prepare('SELECT COUNT(*)'.$joins.$where);$count->execute($params);$total=(int)$count->fetchColumn();
$page=max(1,(int)($_GET['pg']??1));$perPage=15;
$sort=in_array($_GET['sort']??'',['p.exam_date','p.updated_at','s.name'],true)?$_GET['sort']:'p.updated_at';
$statement=db()->prepare('SELECT p.*,s.name subject_name,c.semester_no,d.name department_name,a.label year_label,b.name batch_name'.$joins.$where.' ORDER BY '.$sort.' DESC LIMIT '.$perPage.' OFFSET '.(($page-1)*$perPage));$statement->execute($params);$rows=$statement->fetchAll();
$departments=admin()?db()->query('SELECT id,name FROM departments WHERE status="active" ORDER BY name')->fetchAll():[];
$batchSql='SELECT id,department_id,name FROM batches'.(admin()?'':' WHERE department_id='.(int)$u['department_id']).' ORDER BY name';
$batches=db()->query($batchSql)->fetchAll();
$yearSql='SELECT id,label,department_id FROM academic_years'.(admin()?'':' WHERE department_id='.(int)$u['department_id']).' ORDER BY label';
$years=db()->query($yearSql)->fetchAll();
$semesterSql='SELECT c.id,c.semester_no,c.department_id,a.id academic_year_id,a.label FROM classes c LEFT JOIN academic_years a ON a.id=c.academic_year_id'.(admin()?'':' WHERE c.department_id='.(int)$u['department_id']).' ORDER BY a.label,c.semester_no';
$semesters=db()->query($semesterSql)->fetchAll();
$subjectSql='SELECT DISTINCT s.id,s.name,s.department_id FROM subjects s'.(admin()?'':' WHERE s.department_id='.(int)$u['department_id']).($u['role']==='teacher'?(admin()?' WHERE ':' AND ').'s.teacher_id='.(int)$u['id'].' AND s.status="active"':'').' ORDER BY s.name';
$subjectOptions=db()->query($subjectSql)->fetchAll();
head('Saved Papers');$queryString=http_build_query(array_diff_key($_GET,['pg'=>1]));?>
<form class="cardx mb-3 row g-2">
<div class=col-md-3><input class=form-control name=q placeholder="Search subject or exam title" value="<?=e($query)?>"></div>
<?php if(admin()):?><div class=col-md-2><select class=form-select name=department_id id=department-filter><option value="">All departments</option><?php foreach($departments as$d):?><option value="<?=$d['id']?>" <?=($_GET['department_id']??'')==$d['id']?'selected':''?>><?=e($d['name'])?></option><?php endforeach?></select></div><?php endif?>
<div class=col-md-3><select class=form-select name=subject_id><option value="">All subjects</option><?php foreach($subjectOptions as$subjectOption):?><option value="<?=$subjectOption['id']?>" <?=($_GET['subject_id']??'')==$subjectOption['id']?'selected':''?>><?=e($subjectOption['name'])?></option><?php endforeach?></select></div>
<div class=col-md-2><select class=form-select name=batch_id id=batch-filter><option value="">All batches</option><?php foreach($batches as$batch):?><option value="<?=$batch['id']?>" data-department-id="<?=$batch['department_id']?>" <?=($_GET['batch_id']??'')==$batch['id']?'selected':''?>><?=e($batch['name'])?></option><?php endforeach?></select></div>
<div class=col-md-2><select class=form-select name=academic_year_id id=year-filter><option value="">All years</option><?php foreach($years as$year):?><option value="<?=$year['id']?>" data-department-id="<?=$year['department_id']?>" <?=($_GET['academic_year_id']??'')==$year['id']?'selected':''?>><?=e($year['label'])?></option><?php endforeach?></select></div>
<div class=col-md-2><select class=form-select name=class_id id=semester-filter><option value="">All semesters</option><?php foreach($semesters as$semester):?><option value="<?=$semester['id']?>" data-department-id="<?=$semester['department_id']?>" data-year-id="<?=$semester['academic_year_id']?>" <?=($_GET['class_id']??'')==$semester['id']?'selected':''?>><?=e(($semester['label']?:'Academic year').' — Semester '.$semester['semester_no'])?></option><?php endforeach?></select></div>
<div class=col-md-2><select class=form-select name=exam_type><option value="">All exam types</option><?php foreach(['Midterm','Final','Practical']as$type):?><option value="<?=$type?>" <?=($_GET['exam_type']??'')===$type?'selected':''?>><?=$type?></option><?php endforeach?></select></div>
<div class=col-md-2><input type=date class=form-control name=exam_date value="<?=e($_GET['exam_date']??'')?>"></div>
<div class=col-md-2><select class=form-select name=status><option value="">Any status</option><?php foreach(['draft','final','archived']as$status):?><option value="<?=$status?>" <?=($_GET['status']??'')===$status?'selected':''?>><?=ucfirst($status)?></option><?php endforeach?></select></div>
<div class=col-md-2><select class=form-select name=sort><option value=p.updated_at>Last updated</option><option value=p.exam_date <?=$sort==='p.exam_date'?'selected':''?>>Exam date</option><option value=s.name <?=$sort==='s.name'?'selected':''?>>Subject</option></select></div>
<div class=col-md-1><button class="btn btn-q">Search</button></div><div class="col-12"><a href=papers.php class=small>Reset filters</a></div></form>
<div class=cardx><div class=table-responsive><table class=t><tr><th>Department<th>Batch<th>Academic year<th>Semester<th>Subject<th>Exam<th>Date<th>Marks<th>Status<th>Actions</tr>
<?php foreach($rows as$paper):$action=function($name,$label,$color)use($paper){return '<form method=post action=paper_action.php class=d-inline '.($name==='delete'?'onsubmit="return confirm(\'Delete this paper?\')"':'').'>'.csrf_field().'<input type=hidden name=id value="'.$paper['id'].'"><input type=hidden name=a value="'.$name.'"><button class="btn btn-sm btn-outline-'.$color.'">'.$label.'</button></form> ';};?>
<tr><td><?=e($paper['department_name'])?><td><?=e($paper['batch_name']?:'—')?><td><?=e($paper['year_label']?:'—')?><td>Semester <?=e($paper['semester_no']??'—')?><td><a href="<?=e('papers.php?'.http_build_query(['subject_id'=>$paper['subject_id']]))?>"><?=e($paper['subject_name'])?></a><td><?=e($paper['exam_label']?:$paper['exam_type'])?><td><?=e($paper['exam_date']?:'—')?><td><?=e($paper['total_marks'])?><td><span class="badge bg-<?=['draft'=>'warning','final'=>'success','archived'=>'secondary'][$paper['status']]?>"><?=e(ucfirst($paper['status']))?></span><td class=text-nowrap><a class="btn btn-sm btn-outline-primary" target=_blank href="paper_print.php?id=<?=$paper['id']?>">Preview / Print</a> <?php if($paper['status']==='draft')echo '<a class="btn btn-sm btn-outline-primary" href="paper_form.php?id='.$paper['id'].'">Edit</a> '.$action('finalize','Finalize','success').$action('delete','Delete','danger');else echo $action('archive','Archive','secondary');echo $action('duplicate','Duplicate','dark')?></tr>
<?php endforeach;if(!$rows):?><tr><td colspan=10 class=text-muted>No papers found.</td></tr><?php endif?></table></div>
<?php for($i=1;$i<=ceil($total/$perPage);$i++):?><a class="btn btn-sm <?=$i===$page?'btn-q':'btn-light'?>" href="?<?=e($queryString)?>&pg=<?=$i?>"><?=$i?></a> <?php endfor?></div>
<script>
const departmentFilter=document.querySelector('#department-filter'),yearFilter=document.querySelector('#year-filter'),semesterFilter=document.querySelector('#semester-filter'),batchFilter=document.querySelector('#batch-filter');
function filterAcademicOptions(){const department=departmentFilter?departmentFilter.value:'';let yearValid=false;for(const option of yearFilter.options){if(!option.value)continue;const visible=!department||option.dataset.departmentId===department;option.hidden=!visible;option.disabled=!visible;if(option.value===yearFilter.value&&visible)yearValid=true}if(!yearValid)yearFilter.value='';const year=yearFilter.value;let semesterValid=false;for(const option of semesterFilter.options){if(!option.value)continue;const visible=(!department||option.dataset.departmentId===department)&&(!year||option.dataset.yearId===year);option.hidden=!visible;option.disabled=!visible;if(option.value===semesterFilter.value&&visible)semesterValid=true}if(!semesterValid)semesterFilter.value='';let batchValid=false;for(const option of batchFilter.options){if(!option.value)continue;const visible=!department||option.dataset.departmentId===department;option.hidden=!visible;option.disabled=!visible;if(option.value===batchFilter.value&&visible)batchValid=true}if(!batchValid)batchFilter.value=''}
if(departmentFilter)departmentFilter.addEventListener('change',filterAcademicOptions);yearFilter.addEventListener('change',filterAcademicOptions);filterAcademicOptions();
</script><?php foot();
