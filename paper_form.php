<?php require 'includes/boot.php';need();$id=(int)($_GET['id']??0);$p=[];$init=null;
if($id){$s=db()->prepare('SELECT * FROM question_papers WHERE id=?');$s->execute([$id]);$p=$s->fetch();if(!canAccess($p)||$p['status']!=='draft'){http_response_code(403);die('Only your draft papers can be edited.');}
 $init=[];$ss=db()->prepare('SELECT * FROM paper_sections WHERE paper_id=? ORDER BY position');$ss->execute([$id]);
 foreach($ss->fetchAll()as$sec){$qs=db()->prepare('SELECT * FROM questions WHERE section_id=? ORDER BY position');$qs->execute([$sec['id']]);$Q=[];
  foreach($qs->fetchAll()as$q){$o=db()->prepare('SELECT body FROM question_options WHERE question_id=? ORDER BY label');$o->execute([$q['id']]);$Q[]=['id'=>(int)$q['id'],'type'=>$q['qtype']??$sec['qtype'],'text'=>$q['body'],'marks'=>$q['marks'],'clo'=>$q['clo']??'','taxonomy'=>$q['taxonomy']??'','plo'=>$q['plo']??'','image'=>$q['image_path']??null,'options'=>$o->fetchAll(PDO::FETCH_COLUMN),'correct'=>$q['correct_option']];}
  $init[]=['title'=>$sec['title'],'qtype'=>$sec['qtype'],'attempt_note'=>$sec['attempt_note'],'questions'=>$Q];}}
$deps=admin()?db()->query('SELECT id,name FROM departments WHERE status="active" ORDER BY name')->fetchAll():db()->query('SELECT id,name FROM departments WHERE id='.(int)user()['department_id'])->fetchAll();
$v=fn($k,$d='')=>e($p[$k]??$d);head($id?'Edit Paper':'Create Question Paper');?>
<form method=post enctype=multipart/form-data action=paper_save.php id=pf><?=csrf_field()?><input type=hidden name=id value=<?=$id?>><input type=hidden name=payload id=payload>
<div class="cardx mb-3"><div class=row g-2>
<div class="col-md-3 mb-2"><label class=small>Department</label><select class=form-select name=department_id id=dep required><option value="">Select</option><?php foreach($deps as$d)echo "<option value=\"{$d['id']}\" ".(($p['department_id']??null)==$d['id']?'selected':'').">".e($d['name']).'</option>'?></select></div>
<div class="col-md-3 mb-2"><label class=small>Batch</label><select class=form-select name=batch_id id=batch required><option value="">Select batch</option></select></div>
<div class="col-md-3 mb-2"><label class=small>Academic Year and Semester</label><select class=form-select name=class_id id=cls required></select></div>
<div class="col-md-3 mb-2"><label class=small for=sub>Subject</label><select class=form-select name=subject_id id=sub required><option value="">Select semester first</option></select><div class=form-text id=sub-help aria-live=polite>Select a semester to load its active subjects.</div></div>
<div class="col-md-3 mb-2"><label class=small>Exam type</label><select class=form-select name=exam_type id=et required><?php foreach(['Midterm','Final','Practical']as$x)echo '<option '.(($p['exam_type']??'')===$x?'selected':'').'>'.$x.'</option>';if(!empty($p['exam_type'])&&!in_array($p['exam_type'],['Midterm','Final','Practical'],true))echo '<option selected>'.e($p['exam_type']).'</option>'?></select><div class="form-text">Practical papers support MCQ, fill-in-the-blanks, and true/false questions.</div></div>
<div class="col-md-3 mb-2"><label class=small>Exam title</label><input class=form-control name=exam_label value="<?=$v('exam_label')?>" placeholder="e.g. Midterm Examination"></div>
<div class="col-md-2 mb-2"><label class=small>Total marks</label><input type=number min=1 class=form-control name=total_marks id=tm value="<?=$v('total_marks','60')?>" required></div>
<div class="col-md-2 mb-2"><label class=small>Duration (optional)</label><input class=form-control name=duration value="<?=$v('duration','3 Hours')?>"></div>
<div class="col-md-2 mb-2"><label class=small>Exam date (optional)</label><input type=date class=form-control name=exam_date value="<?=$v('exam_date')?>"></div>
<div class="col-12"><label class=small>General instructions (optional)</label><textarea class=form-control name=instructions rows=2><?=$v('instructions')?></textarea></div></div></div>
<div id=secs></div><button type=button class="btn btn-outline-primary mb-3" onclick="addSec()"><i class="bi bi-plus-lg"></i> Add Section</button>
<div class="cardx d-flex justify-content-between align-items-center"><div>Question marks total: <b id=sum>0</b> / <b id=tot>0</b> <span id=warn class="badge bg-warning text-dark d-none">Marks do not match</span></div><button class="btn btn-q" id=save>Save Draft</button></div></form>
<script>window.INIT=<?=json_encode($init,JSON_HEX_TAG|JSON_HEX_AMP)?>;window.PRE=<?=json_encode(['d'=>$p['department_id']??null,'b'=>$p['batch_id']??null,'c'=>$p['class_id']??null,'s'=>$p['subject_id']??null])?>;</script>
<script src="assets/js/paper.js?v=20261024-1"></script><?php foot();
