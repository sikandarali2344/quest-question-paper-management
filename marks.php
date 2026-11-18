<?php
require 'includes/boot.php';
need(['teacher']);
header('Cache-Control: no-store, private');

$teacher=user();
$teacherId=(int)$teacher['id'];
$departmentId=(int)$teacher['department_id'];
$classesQuery=db()->prepare('SELECT c.id,CONCAT(a.label," — Semester ",c.semester_no) label FROM classes c JOIN academic_years a ON a.id=c.academic_year_id WHERE c.department_id=? AND c.status="active" AND EXISTS (SELECT 1 FROM subjects s WHERE s.class_id=c.id AND s.department_id=c.department_id AND s.teacher_id=? AND s.status="active") ORDER BY a.label,c.semester_no');
$classesQuery->execute([$departmentId,$teacherId]);
$classes=$classesQuery->fetchAll();
$departmentQuery=db()->prepare('SELECT name FROM departments WHERE id=?');
$departmentQuery->execute([$departmentId]);
$departmentName=(string)$departmentQuery->fetchColumn();
$savedClassOptionsQuery=db()->prepare('SELECT DISTINCT c.id,CONCAT(a.label," — Semester ",c.semester_no) label FROM teacher_marksheets m JOIN classes c ON c.id=m.class_id LEFT JOIN academic_years a ON a.id=c.academic_year_id WHERE m.teacher_id=? AND m.department_id=? ORDER BY a.label,c.semester_no');
$savedClassOptionsQuery->execute([$teacherId,$departmentId]);
$savedClassOptions=$savedClassOptionsQuery->fetchAll();
$savedSubjectOptionsQuery=db()->prepare('SELECT DISTINCT s.id,s.name FROM teacher_marksheets m JOIN subjects s ON s.id=m.subject_id WHERE m.teacher_id=? AND m.department_id=? ORDER BY s.name');
$savedSubjectOptionsQuery->execute([$teacherId,$departmentId]);
$savedSubjectOptions=$savedSubjectOptionsQuery->fetchAll();
$savedClassFilter=(int)($_GET['saved_class_id']??0);
$savedSubjectFilter=(int)($_GET['saved_subject_id']??0);
$savedFilters=['m.teacher_id=?','m.department_id=?','s.teacher_id=?'];
$savedParams=[$teacherId,$departmentId,$teacherId];
if($savedClassFilter>0){$savedFilters[]='m.class_id=?';$savedParams[]=$savedClassFilter;}
if($savedSubjectFilter>0){$savedFilters[]='m.subject_id=?';$savedParams[]=$savedSubjectFilter;}
$savedSheetsQuery=db()->prepare('SELECT m.id,m.class_id,m.batch_id,m.subject_id,m.exam_type,m.updated_at,s.name subject_name,c.semester_no,a.label year_label,b.name batch_name,(SELECT COUNT(*) FROM teacher_marksheet_students st WHERE st.marksheet_id=m.id) student_count FROM teacher_marksheets m JOIN subjects s ON s.id=m.subject_id JOIN classes c ON c.id=m.class_id JOIN academic_years a ON a.id=c.academic_year_id JOIN batches b ON b.id=m.batch_id WHERE '.implode(' AND ',$savedFilters).' ORDER BY m.updated_at DESC,m.id DESC');
$savedSheetsQuery->execute($savedParams);
$savedSheets=$savedSheetsQuery->fetchAll();
$batchesQuery=db()->prepare('SELECT id,name,grading_policy_id FROM batches WHERE department_id=? AND status="active" ORDER BY name');
$batchesQuery->execute([$departmentId]);
$batches=$batchesQuery->fetchAll();
$classId=(int)($_POST['class_id']??$_GET['class_id']??0);
$batchId=(int)($_POST['batch_id']??$_GET['batch_id']??0);
$subjectId=(int)($_POST['subject_id']??$_GET['subject_id']??0);
$examType=is_string($_POST['exam_type']??$_GET['exam_type']??null)?($_POST['exam_type']??$_GET['exam_type']??''):'';
$examTypes=['Midterm','Final','Practical'];
$gradingPolicyId=questBatchGradingPolicy();
foreach($batches as$batch)if((int)$batch['id']===$batchId)$gradingPolicyId=(int)$batch['grading_policy_id'];
$gradingBands=questGetGradingPolicyBands($gradingPolicyId);
$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='delete_sheet'){
 $deleteId=(int)($_POST['sheet_id']??0);
 $deleteQuery=db()->prepare('SELECT id,department_id,class_id,batch_id,subject_id,exam_type FROM teacher_marksheets WHERE id=? AND teacher_id=? AND department_id=?');
 $deleteQuery->execute([$deleteId,$teacherId,$departmentId]);
 $sheetToDelete=$deleteQuery->fetch();
 if(!$sheetToDelete){
  flash('danger','That marks sheet was not found in your account.');
  go('marks.php');
 }
 $pdo=db();
 try{
  $pdo->beginTransaction();
  if($sheetToDelete['exam_type']==='Midterm'){
   $finalSheetQuery=$pdo->prepare('SELECT id FROM teacher_marksheets WHERE teacher_id=? AND class_id=? AND batch_id=? AND subject_id=? AND exam_type="Final" FOR UPDATE');
   $finalSheetQuery->execute([$teacherId,$sheetToDelete['class_id'],$sheetToDelete['batch_id'],$sheetToDelete['subject_id']]);
   $finalSheetId=$finalSheetQuery->fetchColumn();
   if($finalSheetId){
    $pdo->prepare('UPDATE teacher_marksheets SET midterm_exam_max=NULL WHERE id=?')->execute([$finalSheetId]);
    $pdo->prepare('UPDATE teacher_marksheet_students SET midterm_exam_marks=NULL WHERE marksheet_id=?')->execute([$finalSheetId]);
   }
  }
  $pdo->prepare('DELETE FROM teacher_marksheets WHERE id=? AND teacher_id=?')->execute([$deleteId,$teacherId]);
  $pdo->commit();
  audit('marksheet_delete',"#{$deleteId} {$sheetToDelete['exam_type']} subject #{$sheetToDelete['subject_id']}");
  flash('success',$sheetToDelete['exam_type'].' marks sheet and its saved student marks were deleted.');
 }catch(Throwable$error){
  if($pdo->inTransaction())$pdo->rollBack();
  error_log('Could not delete teacher marks sheet: '.$error->getMessage());
  flash('danger','Could not delete the marks sheet. Please try again.');
 }
 go('marks.php');
}
$subjects=[];
if($classId){
 $subjectsQuery=db()->prepare('SELECT s.id,s.name FROM subjects s JOIN classes c ON c.id=s.class_id JOIN users t ON t.id=s.teacher_id WHERE s.class_id=? AND s.department_id=? AND s.teacher_id=? AND s.status="active" AND c.department_id=s.department_id AND c.status="active" AND t.role="teacher" AND t.status="active" AND t.department_id=s.department_id ORDER BY s.name');
 $subjectsQuery->execute([$classId,$departmentId,$teacherId]);
 $subjects=$subjectsQuery->fetchAll();
}
$selectedScope=false;
if($classId&&$batchId&&$subjectId&&in_array($examType,$examTypes,true)){
 $scopeQuery=db()->prepare('SELECT s.id FROM subjects s JOIN classes c ON c.id=s.class_id JOIN batches b ON b.department_id=c.department_id WHERE s.id=? AND c.id=? AND c.department_id=? AND c.status="active" AND s.teacher_id=? AND s.status="active" AND b.id=? AND b.status="active"');
 $scopeQuery->execute([$subjectId,$classId,$departmentId,$teacherId,$batchId]);
 $selectedScope=(bool)$scopeQuery->fetchColumn();
}
if($classId&&$batchId&&$subjectId&&$examType&&!$selectedScope)$errors[]='Choose a class, batch, assigned subject, and exam type that belong to your account.';

$marksheet=null;
$midtermSheet=null;
$components=[];
$students=[];
$examMaximum=null;
$midtermMaximum=null;
$midtermMarks=[];
if($selectedScope){
 $sheetQuery=db()->prepare('SELECT * FROM teacher_marksheets WHERE teacher_id=? AND class_id=? AND batch_id=? AND subject_id=? AND exam_type=?');
 $sheetQuery->execute([$teacherId,$classId,$batchId,$subjectId,$examType]);
 $marksheet=$sheetQuery->fetch()?:null;
 $midtermQuery=db()->prepare('SELECT * FROM teacher_marksheets WHERE teacher_id=? AND class_id=? AND batch_id=? AND subject_id=? AND exam_type="Midterm"');
 $midtermQuery->execute([$teacherId,$classId,$batchId,$subjectId]);
 $midtermSheet=$midtermQuery->fetch()?:null;
 if($marksheet){
  $examMaximum=$examType==='Midterm'?$marksheet['midterm_exam_max']:$marksheet['final_exam_max'];
  if($examType!=='Midterm'){
   $componentQuery=db()->prepare('SELECT id,label,maximum_marks,position FROM teacher_marksheet_components WHERE marksheet_id=? ORDER BY position,id');
   $componentQuery->execute([$marksheet['id']]);
   $components=$componentQuery->fetchAll();
  }
  $studentQuery=db()->prepare('SELECT st.*,score.component_id,score.obtained_marks FROM teacher_marksheet_students st LEFT JOIN teacher_marksheet_component_scores score ON score.student_row_id=st.id WHERE st.marksheet_id=? ORDER BY st.id');
  $studentQuery->execute([$marksheet['id']]);
  foreach($studentQuery->fetchAll()as$row){
   $key=strtolower($row['student_id']);
   if(!isset($students[$key]))$students[$key]=['student_id'=>$row['student_id'],'student_name'=>$row['student_name'],'exam_marks'=>$examType==='Midterm'?$row['midterm_exam_marks']:$row['final_exam_marks'],'midterm_marks'=>$row['midterm_exam_marks'],'grade'=>$row['grade'],'scores'=>[]];
   if($row['component_id']!==null)$students[$key]['scores'][(int)$row['component_id']]=$row['obtained_marks'];
  }
  $students=array_values($students);
 }elseif(!$components&&$examType!=='Midterm'){
  if($examType==='Practical')$components=[
   ['label'=>'Lab Rubric','maximum_marks'=>'30.00','position'=>1],
   ['label'=>'Mini Project / Open-ended Lab','maximum_marks'=>'10.00','position'=>2],
   ['label'=>'Objective Test','maximum_marks'=>'20.00','position'=>3]
  ];
  else $components=[
   ['label'=>'Quiz / Test','maximum_marks'=>'10.00','position'=>1],
   ['label'=>'Assignment / Project / Presentation','maximum_marks'=>'10.00','position'=>2]
  ];
 }
 if($midtermSheet){
  $midtermMaximum=$midtermSheet['midterm_exam_max'];
  $midtermRows=db()->prepare('SELECT student_id,student_name,midterm_exam_marks FROM teacher_marksheet_students WHERE marksheet_id=?');
  $midtermRows->execute([$midtermSheet['id']]);
  foreach($midtermRows->fetchAll()as$row)$midtermMarks[strtolower($row['student_id'])]=$row;
 }
 if(!$marksheet&&$examType!=='Midterm'&&$midtermMarks){
  foreach($midtermMarks as$row)$students[]=['student_id'=>$row['student_id'],'student_name'=>$row['student_name'],'exam_marks'=>null,'midterm_marks'=>$row['midterm_exam_marks'],'grade'=>null,'scores'=>[]];
 }
}

if($_SERVER['REQUEST_METHOD']==='POST'&&$selectedScope){
 $postedComponents=$examType!=='Midterm'&&is_array($_POST['components']??null)?array_slice($_POST['components'],0,12):[];
 $cleanComponents=[];
 $componentNames=[];
 foreach($postedComponents as$position=>$component){
  if(!is_array($component))continue;
  $label=trim(is_string($component['label']??null)?$component['label']:'');
  $maximum=$component['maximum']??'';
  if($label===''||strlen($label)>40){$errors[]='Each sessional component needs a name (up to 40 characters).';continue;}
  if(!is_numeric($maximum)||!is_finite((float)$maximum)||(float)$maximum<=0||(float)$maximum>9999.99){$errors[]='Each sessional component needs maximum marks between 0.01 and 9,999.99.';continue;}
  $normalized=strtolower($label);
  if(isset($componentNames[$normalized])){$errors[]='Sessional component names must be unique.';continue;}
  $componentNames[$normalized]=true;
  $cleanComponents[]=['label'=>$label,'maximum'=>(float)$maximum,'position'=>$position+1];
 }
 if($examType!=='Midterm'&&!$cleanComponents)$errors[]='Add at least one sessional component.';
 $rawExamMaximum=$_POST['exam_maximum']??'';
 $hasValidExamMaximum=is_numeric($rawExamMaximum)&&is_finite((float)$rawExamMaximum)&&(float)$rawExamMaximum>0&&(float)$rawExamMaximum<=9999.99;
 if(!$hasValidExamMaximum)$errors[]='Enter valid maximum marks for the selected exam.';
 else $examMaximum=(float)$rawExamMaximum;
 if($examType==='Final'&&$hasValidExamMaximum){
  $sessionalMaximum=0.0;
  foreach($cleanComponents as$component)$sessionalMaximum+=(float)$component['maximum'];
  $midtermExamMaximum=(float)($midtermMaximum??0);
  $isHundredMarkTheory=abs($sessionalMaximum-20)<0.005&&abs($midtermExamMaximum-20)<0.005&&abs((float)$examMaximum-60)<0.005;
  $isFiftyMarkTheory=abs($sessionalMaximum-10)<0.005&&abs($midtermExamMaximum-10)<0.005&&abs((float)$examMaximum-30)<0.005;
  if(!$isHundredMarkTheory&&!$isFiftyMarkTheory)$errors[]='Theory marks must use either sessional 20 + midterm 20 + final 60 = 100, or sessional 10 + midterm 10 + final 30 = 50. Check the sessional components and saved Midterm maximum.';
 }

 $rawStudents=$_POST['students']??[];
 if(!is_array($rawStudents)){$errors[]='Student rows are invalid. Please reload the marks sheet and try again.';$postedStudents=[];}
 else $postedStudents=array_slice($rawStudents,0,300);
 if(is_array($rawStudents)&&count($rawStudents)>300)$errors[]='A marks sheet can contain at most 300 students.';
 $cleanStudents=[];
 $seenIds=[];
 foreach($postedStudents as$row){
  if(!is_array($row))continue;
  $studentNumber=trim(is_string($row['student_id']??null)?$row['student_id']:'');
  $studentName=trim(is_string($row['student_name']??null)?$row['student_name']:'');
  $examRaw=$row['exam_marks']??'';
  $examMarks=null;
  if($examRaw!==''&&$examRaw!==null){
   if(!is_numeric($examRaw)||!is_finite((float)$examRaw)||(float)$examRaw<0||(float)$examRaw>$examMaximum){$errors[]='Exam marks must be between zero and the exam maximum.';continue;}
   $examMarks=(float)$examRaw;
  }
  $grade=null;
  $rawScores=is_array($row['scores']??null)?$row['scores']:[];
  $scores=[];
  foreach($cleanComponents as$index=>$component){
   $raw=$rawScores[(string)$index]??'';
   if($raw===''||$raw===null){$scores[$index]=null;continue;}
   if(!is_numeric($raw)||!is_finite((float)$raw)||(float)$raw<0||(float)$raw>$component['maximum']){$errors[]='Sessional marks must be between zero and that component maximum.';continue 2;}
   $scores[$index]=(float)$raw;
  }
  if($studentNumber===''&&$studentName===''&&$examMarks===null&&!array_filter($scores,static fn($score)=>$score!==null))continue;
  if($studentNumber===''){$errors[]='Each student row needs a roll number.';continue;}
  if(strlen($studentNumber)>64||strlen($studentName)>150||strlen((string)$grade)>12){$errors[]='Student roll number, name, or grade is too long.';continue;}
  $idKey=strtolower($studentNumber);
  if(isset($seenIds[$idKey])){$errors[]='Each student roll number must appear only once.';continue;}
  $seenIds[$idKey]=true;
  if($examType==='Midterm')$linkedMidterm=$examMarks;
  elseif($examType==='Final')$linkedMidterm=$midtermSheet?(array_key_exists($idKey,$midtermMarks)?$midtermMarks[$idKey]['midterm_exam_marks']:null):($row['midterm_marks']??null);
  else $linkedMidterm=null;
  $cleanStudents[]=['student_id'=>$studentNumber,'student_name'=>$studentName,'exam_marks'=>$examMarks,'midterm_marks'=>$linkedMidterm,'grade'=>$grade?:null,'scores'=>$scores];
 }
 if(in_array($examType,['Final','Practical'],true)){
 foreach($cleanStudents as &$studentRow){
  $total=0.0;$maximum=0.0;
  foreach($cleanComponents as$component){$maximum+=(float)$component['maximum'];}
  foreach($studentRow['scores']as$score){if($score!==null)$total+=(float)$score;}
  if($studentRow['midterm_marks']!==null&&$studentRow['midterm_marks']!=='' ){$total+=(float)$studentRow['midterm_marks'];}
  if($studentRow['exam_marks']!==null&&$studentRow['exam_marks']!==''){$total+=(float)$studentRow['exam_marks'];}
  $maximum+=(float)($examMaximum??0.0);
  if($examType==='Final')$maximum+=(float)($midtermMaximum??0.0);
  $allMarksEntered=$studentRow['exam_marks']!==null;
  foreach($studentRow['scores']as$score)if($score===null)$allMarksEntered=false;
  if($examType==='Final'&&$studentRow['midterm_marks']===null)$allMarksEntered=false;
  $studentRow['grade']=$allMarksEntered&&$maximum>0?questGradeFromPercentage(($total/$maximum)*100,$gradingPolicyId)['grade']:null;
 }
 unset($studentRow);
 }
 if(!$errors){
  $pdo=db();
  try{
   $pdo->beginTransaction();
   $find=$pdo->prepare('SELECT id FROM teacher_marksheets WHERE teacher_id=? AND class_id=? AND batch_id=? AND subject_id=? AND exam_type=? FOR UPDATE');
   $find->execute([$teacherId,$classId,$batchId,$subjectId,$examType]);
   $sheetId=$find->fetchColumn();
   $examField=$examType==='Midterm'?'midterm_exam_max':'final_exam_max';
   if($sheetId){
    if($examType==='Final')$pdo->prepare('UPDATE teacher_marksheets SET final_exam_max=?,midterm_exam_max=? WHERE id=? AND teacher_id=?')->execute([$examMaximum,$midtermMaximum,$sheetId,$teacherId]);
    elseif($examType==='Midterm')$pdo->prepare('UPDATE teacher_marksheets SET midterm_exam_max=? WHERE id=? AND teacher_id=?')->execute([$examMaximum,$sheetId,$teacherId]);
    else $pdo->prepare('UPDATE teacher_marksheets SET final_exam_max=?,midterm_exam_max=NULL WHERE id=? AND teacher_id=?')->execute([$examMaximum,$sheetId,$teacherId]);
    $pdo->prepare('DELETE FROM teacher_marksheet_students WHERE marksheet_id=?')->execute([$sheetId]);
    $pdo->prepare('DELETE FROM teacher_marksheet_components WHERE marksheet_id=?')->execute([$sheetId]);
   }else{
    if($examType==='Final')$pdo->prepare('INSERT INTO teacher_marksheets(teacher_id,department_id,class_id,batch_id,subject_id,exam_type,midterm_exam_max,final_exam_max) VALUES(?,?,?,?,?,?,?,?)')->execute([$teacherId,$departmentId,$classId,$batchId,$subjectId,$examType,$midtermMaximum,$examMaximum]);
    else $pdo->prepare('INSERT INTO teacher_marksheets(teacher_id,department_id,class_id,batch_id,subject_id,exam_type,'.$examField.') VALUES(?,?,?,?,?,?,?)')->execute([$teacherId,$departmentId,$classId,$batchId,$subjectId,$examType,$examMaximum]);
    $sheetId=(int)$pdo->lastInsertId();
   }
   $insertComponent=$pdo->prepare('INSERT INTO teacher_marksheet_components(marksheet_id,label,maximum_marks,position) VALUES(?,?,?,?)');
   $componentIds=[];
   foreach($cleanComponents as$index=>$component){$insertComponent->execute([$sheetId,$component['label'],$component['maximum'],$component['position']]);$componentIds[$index]=(int)$pdo->lastInsertId();}
   $insertStudent=$pdo->prepare('INSERT INTO teacher_marksheet_students(marksheet_id,student_id,student_name,midterm_exam_marks,final_exam_marks,grade) VALUES(?,?,?,?,?,?)');
   $insertScore=$pdo->prepare('INSERT INTO teacher_marksheet_component_scores(student_row_id,component_id,obtained_marks) VALUES(?,?,?)');
   foreach($cleanStudents as$row){
    $insertStudent->execute([$sheetId,$row['student_id'],$row['student_name'],$row['midterm_marks'],$examType==='Midterm'?null:$row['exam_marks'],$row['grade']]);
    $studentRowId=(int)$pdo->lastInsertId();
    foreach($row['scores']as$index=>$score)$insertScore->execute([$studentRowId,$componentIds[$index],$score]);
   }
   if($examType==='Midterm'){
    $finalSheetQuery=$pdo->prepare('SELECT id FROM teacher_marksheets WHERE teacher_id=? AND class_id=? AND batch_id=? AND subject_id=? AND exam_type="Final" FOR UPDATE');
    $finalSheetQuery->execute([$teacherId,$classId,$batchId,$subjectId]);
    $finalSheetId=$finalSheetQuery->fetchColumn();
    if($finalSheetId){
     $pdo->prepare('UPDATE teacher_marksheets SET midterm_exam_max=? WHERE id=?')->execute([$examMaximum,$finalSheetId]);
     $pdo->prepare('UPDATE teacher_marksheet_students SET midterm_exam_marks=NULL WHERE marksheet_id=?')->execute([$finalSheetId]);
     $syncMidterm=$pdo->prepare('UPDATE teacher_marksheet_students SET midterm_exam_marks=? WHERE marksheet_id=? AND LOWER(student_id)=LOWER(?)');
     foreach($cleanStudents as$row)$syncMidterm->execute([$row['exam_marks'],$finalSheetId,$row['student_id']]);
    }
   }
   $pdo->commit();
   flash('success',$examType==='Midterm'?'Midterm marks saved permanently.':'Marks and sessional components saved permanently.');
   go('marks.php?'.http_build_query(['class_id'=>$classId,'batch_id'=>$batchId,'subject_id'=>$subjectId,'exam_type'=>$examType]));
  }catch(Throwable$error){
   if($pdo->inTransaction())$pdo->rollBack();
   error_log('Could not save teacher marks sheet: '.$error->getMessage());
   $errors[]='Could not save the marks sheet. Please try again.';
  }
 }
 $components=[];
 foreach($postedComponents as$component)if(is_array($component))$components[]=['label'=>is_scalar($component['label']??null)?(string)$component['label']:'','maximum_marks'=>is_scalar($component['maximum']??null)?(string)$component['maximum']:'','position'=>count($components)+1];
 $students=[];
 foreach($postedStudents as$row){
  if(!is_array($row))continue;
  $number=is_scalar($row['student_id']??null)?(string)$row['student_id']:'';
  $key=strtolower($number);
  $students[]=['student_id'=>$number,'student_name'=>is_scalar($row['student_name']??null)?(string)$row['student_name']:'','exam_marks'=>is_scalar($row['exam_marks']??null)?(string)$row['exam_marks']:'','midterm_marks'=>$midtermMarks[$key]['midterm_exam_marks']??null,'grade'=>is_scalar($row['grade']??null)?(string)$row['grade']:'','scores'=>is_array($row['scores']??null)?$row['scores']:[]];
 }
}

$subjectNames=[];foreach($subjects as$subject)$subjectNames[(int)$subject['id']]=$subject['name'];
$batchNames=[];foreach($batches as$batch)$batchNames[(int)$batch['id']]=$batch['name'];
$classNames=[];foreach($classes as$class)$classNames[(int)$class['id']]=$class['label'];
$effectiveExamMaximum=(float)($examMaximum??($examType==='Midterm'?20:($examType==='Practical'?40:60)));
$finalTotalMaximum=$effectiveExamMaximum;
foreach($components as$component)$finalTotalMaximum+=(float)$component['maximum_marks'];
$linkMidterm=$examType==='Final';
$showGrade=$examType==='Final'||$examType==='Practical';
if($linkMidterm&&(float)$midtermMaximum>0)$finalTotalMaximum+=(float)$midtermMaximum;
$midtermMarkValues=[];foreach($midtermMarks as$key=>$row)$midtermMarkValues[$key]=$row['midterm_exam_marks'];
head('Student Marks');?>
<div class="marks-university cardx mb-3">
 <img src="assets/images/quest_logo.png?v=quest-cyan-20261023" alt="QUEST University logo">
 <div><div class=marks-university-kicker>QUAID-E-AWAM UNIVERSITY OF ENGINEERING, SCIENCE &amp; TECHNOLOGY</div><h5>Student Marks Register</h5><div class=marks-university-detail>NAWABSHAH · <?=e($teacher['name'])?> · <?=e($teacher['role']==='teacher'?'Teacher':'')?></div></div>
</div>
<?php foreach($errors as$error):?><div class="alert alert-danger"><?=e($error)?></div><?php endforeach?>
<div class="cardx mb-3"><h5>Choose a marks sheet</h5><p class=text-muted>Choose your semester to show subjects assigned to you. Each department, subject, batch and exam has its own saved sheet. Choose an existing sheet below to edit it, or select a new combination to start another.</p>
<form method=get class="row g-2 align-items-end">
<div class=col-md-3><label class=form-label>Academic year / semester</label><select name=class_id class=form-select required onchange="this.form.elements.subject_id.value='';this.form.submit()"><option value="">Select semester</option><?php foreach($classes as$class):?><option value="<?=$class['id']?>" <?=$classId===(int)$class['id']?'selected':''?>><?=e($class['label'])?></option><?php endforeach?></select></div>
<div class=col-md-2><label class=form-label>Batch</label><select name=batch_id class=form-select required><option value="">Select batch</option><?php foreach($batches as$batch):?><option value="<?=$batch['id']?>" <?=$batchId===(int)$batch['id']?'selected':''?>><?=e($batch['name'])?></option><?php endforeach?></select></div>
<div class=col-md-3><label class=form-label>Subject</label><select name=subject_id id=marks-subject class=form-select <?=$subjects?'required':''?>><option value="">Select subject</option><?php foreach($subjects as$subject):?><option value="<?=$subject['id']?>" <?=$subjectId===(int)$subject['id']?'selected':''?>><?=e($subject['name'])?></option><?php endforeach?><?php if($classId&&!$subjects):?><option value="" disabled>No active subjects are assigned to you for this semester</option><?php endif?></select></div>
<div class=col-md-2><label class=form-label>Exam type</label><select name=exam_type class=form-select required><option value="">Select exam</option><?php foreach($examTypes as$type):?><option <?=$examType===$type?'selected':''?>><?=$type?></option><?php endforeach?></select></div>
<div class=col-md-2><button class="btn btn-q w-100">Open marks sheet</button></div></form><div id=subject-load-error class="alert alert-danger mt-3 mb-0 d-none" role=alert></div></div>
<section class="cardx mb-3 marks-saved-sheets"><div class=marks-section-heading><div><span class=marks-section-kicker>YOUR SAVED DATA</span><h6>Saved marks sheets</h6><p>Each marks register is separate by subject, semester, batch and exam. Filter the list to open the right register.</p></div><span class="badge text-bg-primary"><?=count($savedSheets)?> shown</span></div>
<form method=get class="row g-2 align-items-end mb-3"><div class=col-md-5><label class=form-label>Filter by semester / class</label><select class=form-select name=saved_class_id><option value="">All classes</option><?php foreach($savedClassOptions as$savedClass):?><option value="<?=$savedClass['id']?>" <?=$savedClassFilter===(int)$savedClass['id']?'selected':''?>><?=e($savedClass['label'])?></option><?php endforeach?></select></div><div class=col-md-5><label class=form-label>Filter by subject</label><select class=form-select name=saved_subject_id><option value="">All subjects</option><?php foreach($savedSubjectOptions as$savedSubject):?><option value="<?=$savedSubject['id']?>" <?=$savedSubjectFilter===(int)$savedSubject['id']?'selected':''?>><?=e($savedSubject['name'])?></option><?php endforeach?></select></div><div class=col-md-2><button class="btn btn-outline-primary w-100">Show sheets</button></div><div class=col-12><a href="marks.php" class=small>Clear filters</a></div></form>
<?php if($savedSheets):?><div class=table-responsive><table class="table table-bordered align-middle marks-saved-table mb-0"><thead><tr><th>Department</th><th>Subject</th><th>Semester · Batch</th><th>Exam</th><th>Students</th><th>Last saved</th><th>Actions</th></tr></thead><tbody>
<?php foreach($savedSheets as$saved):?><tr><td><?=e($departmentName)?></td><td><?=e($saved['subject_name'])?></td><td><?=e($saved['year_label'])?> · Sem <?=e($saved['semester_no'])?><br><small><?=e($saved['batch_name'])?></small></td><td><span class="badge <?=e($saved['exam_type']==='Midterm'?'text-bg-info':($saved['exam_type']==='Final'?'text-bg-primary':'text-bg-secondary'))?>"><?=e($saved['exam_type'])?></span></td><td><?=e($saved['student_count'])?></td><td><?=e(date('d M Y, H:i',strtotime($saved['updated_at'])))?></td><td><div class=marks-saved-actions><a class="btn btn-sm btn-outline-primary" href="<?=e('marks.php?'.http_build_query(['class_id'=>$saved['class_id'],'batch_id'=>$saved['batch_id'],'subject_id'=>$saved['subject_id'],'exam_type'=>$saved['exam_type']]))?>">Edit</a><a class="btn btn-sm btn-outline-success" href="marks_export.php?id=<?=$saved['id']?>">CSV</a><a class="btn btn-sm btn-outline-secondary" target=_blank href="marks_print.php?id=<?=$saved['id']?>">Print</a><form method=post class=d-inline onsubmit="return confirm('Delete this saved sheet and all its student marks? This cannot be undone.')"><?=csrf_field()?><input type=hidden name=action value=delete_sheet><input type=hidden name=sheet_id value="<?=$saved['id']?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></div></td></tr><?php endforeach?>
</tbody></table></div><?php else:?><div class="alert alert-light border mb-0">No marks sheets match these filters. Each saved register remains separate by subject and class.</div><?php endif?></section>
<?php if($selectedScope):?>
<div class="cardx mb-3 marks-config"><div><span class=marks-section-kicker><?=e($examType)?> EXAM REGISTER</span><h5 class=mb-1><?=e($subjectNames[$subjectId]??'Subject')?></h5><div class=text-muted><?=e($classNames[$classId]??'Semester')?> · <?=e($batchNames[$batchId]??'Batch')?> · <?=e($departmentName)?></div></div>
<div class=marks-actions><?php if($marksheet):?><a class="btn btn-outline-success" href="marks_export.php?id=<?=$marksheet['id']?>">Download Excel CSV</a><a class="btn btn-outline-primary" target=_blank href="marks_print.php?id=<?=$marksheet['id']?>">Print / Save PDF</a><?php endif?></div></div>
<?php if($examType!=='Midterm'&&!$midtermSheet):?><div class="alert alert-info">Save the Midterm sheet for this same subject, semester and batch first. Final marks will automatically include each student's saved Midterm marks.</div><?php endif?>
<form method=post id=marks-form><?=csrf_field()?><input type=hidden name=class_id value="<?=$classId?>"><input type=hidden name=batch_id value="<?=$batchId?>"><input type=hidden name=subject_id value="<?=$subjectId?>"><input type=hidden name=exam_type value="<?=e($examType)?>">
<?php if($examType!=='Midterm'):?><section class="cardx mb-3"><div class=marks-section-heading><div><span class=marks-section-kicker>01 · CONTINUOUS ASSESSMENT</span><h6><?=$examType==='Practical'?'Practical Assessment Components':'Sessional Marks'?></h6><p><?=$examType==='Practical'?'Practical sheets default to Lab Rubric 30, Mini Project 10 and Objective Test 20; set Practical/Viva maximum to 40 or adjust the assessment maxima as required.':'Split the sessional maximum across quizzes/tests, assignments, projects or presentations. Theory totals must be 20/100 or 10/50 marks.'?></p></div><button class="btn btn-sm btn-outline-primary" type=button id=add-component>+ Add component</button></div>
<div class=table-responsive><table class="table table-bordered marks-component-editor"><thead><tr><th>Sessional component</th><th>Maximum marks</th><th></th></tr></thead><tbody id=component-rows><?php foreach($components as$index=>$component):?><tr><td><input class=form-control name="components[<?=$index?>][label]" value="<?=e($component['label'])?>" maxlength=40 required placeholder="e.g. Assignment"></td><td><input class=form-control type=number name="components[<?=$index?>][maximum]" value="<?=e($component['maximum_marks'])?>" min=0.01 max=9999.99 step=0.01 required></td><td><button type=button class="btn btn-sm btn-outline-danger remove-component" aria-label="Remove component">Remove</button></td></tr><?php endforeach?></tbody></table></div></section>
<?php endif?>
<section class="cardx mb-3 marks-exam-config"><div><span class=marks-section-kicker><?=e($examType==='Midterm'?'01':'02')?> · EXAM MARKS</span><h6 class=mb-1><?=e($examType==='Midterm'?'Midterm Exam Marks':($examType==='Final'?'Final Exam Marks':'Practical / Viva Marks'))?></h6><p class="small text-muted mb-0">Enter maximum marks for this exam. Student totals update automatically.</p></div><div class=marks-maximum><label for=exam-maximum class=form-label>Maximum exam marks</label><input id=exam-maximum class=form-control type=number name=exam_maximum value="<?=e($examMaximum??$effectiveExamMaximum)?>" min=0.01 max=9999.99 step=0.01 required></div><?php if($linkMidterm):?><div class=marks-midterm-info><strong>Midterm marks are linked automatically</strong><span><?php if($midtermSheet):?>Midterm maximum: <?=e($midtermMaximum??'Not set')?><?php else:?>Midterm sheet not saved yet<?php endif?></span></div><?php endif?></section>
<section class="cardx marks-table-card"><div class=marks-table-actions><div><span class=marks-section-kicker><?=e($examType==='Midterm'?'02':'03')?> · STUDENT REGISTER</span><h6 class=mb-0>Roll number, optional name and marks</h6><small class=text-muted><?= $marksheet?'Editing saved marks.':'New sheet — not saved yet.'?></small></div><div><button type=button class="btn btn-sm btn-outline-primary" id=add-row>Add student</button> <button type=button class="btn btn-sm btn-outline-secondary" id=add-ten>Add 10 rows</button></div></div>
<div class=table-responsive><table class="table table-bordered align-middle marks-entry-table"><thead><tr><th>S. No.</th><th>Roll No.</th><th>Student Name <small>(optional)</small></th><?php foreach($components as$index=>$component):?><th class="sessional-column sessional-column-<?=$index?>"><span><?=e($component['label'])?></span><small>Max <?=e((float)$component['maximum_marks'])?></small></th><?php endforeach?><?php if($linkMidterm):?><th class=midterm-header>Midterm Marks<small>Auto-loaded</small></th><?php endif?><th class=exam-header><?=e($examType==='Midterm'?'Midterm Obtained':($examType==='Final'?'Final Obtained':'Practical Obtained'))?><small>Max <span id=exam-maximum-label><?=e($effectiveExamMaximum)?></span></small></th><th>Total<small>Max <span id=total-maximum-header><?=e($finalTotalMaximum)?></span></small></th><?php if($showGrade):?><th>Percentage</th><th>Grade</th><th>Grade Point</th><?php endif?><th></th></tr></thead>
<tbody id=student-rows><?php if(!$students)$students=array_fill(0,10,['student_id'=>'','student_name'=>'','exam_marks'=>'','midterm_marks'=>null,'grade'=>'','scores'=>[]]);foreach($students as$studentIndex=>$student):$scoreValues=$student['scores']??[];$studentKey=strtolower($student['student_id']??'');$midtermStudent=$midtermMarks[$studentKey]??null;$displayMidterm=$midtermSheet?($midtermStudent['midterm_exam_marks']??null):($student['midterm_marks']??null);?>
<tr><td class=row-number><?=($studentIndex+1)?></td><td><input class="form-control student-id" name="students[<?=$studentIndex?>][student_id]" value="<?=e($student['student_id']??'')?>" maxlength=64></td><td><input class="form-control student-name" name="students[<?=$studentIndex?>][student_name]" value="<?=e($student['student_name']??'')?>" maxlength=150></td>
<?php foreach($components as$componentIndex=>$component):?><td class="sessional-column sessional-column-<?=$componentIndex?>"><input class="form-control marks-value sessional-score" type=number name="students[<?=$studentIndex?>][scores][<?=$componentIndex?>]" value="<?=e($scoreValues[(int)($component['id']??-1)]??$scoreValues[(string)$componentIndex]??'')?>" min=0 max="<?=e($component['maximum_marks'])?>" step=0.01></td><?php endforeach?>
<?php if($linkMidterm):?><td class=midterm-mark><?=e($displayMidterm!==null?(float)$displayMidterm:'')?></td><?php endif?>
<td><input class="form-control marks-value exam-score" type=number name="students[<?=$studentIndex?>][exam_marks]" value="<?=e($student['exam_marks']??'')?>" min=0 max="<?=e($effectiveExamMaximum)?>" step=0.01></td><td class=student-total>0</td><?php if($showGrade):?><td class=student-percentage></td><td class=student-grade><?=e($student['grade']??'')?></td><td class=student-grade-point></td><?php endif?><td><button type=button class="btn btn-sm btn-outline-danger remove-row" aria-label="Remove student">×</button></td></tr>
<?php endforeach?></tbody></table></div><div class=marks-save-row><span>Overall maximum: <strong id=sheet-max><?=e($finalTotalMaximum)?></strong><small class="d-block text-muted">Changes are written to this <?=e($examType)?> sheet when saved.</small></span><button class="btn btn-q"><i class="bi bi-floppy me-1"></i><?= $marksheet?'Save changes':'Save '.$examType.' sheet'?></button></div></section></form>
<script>
(()=>{const body=document.querySelector('#student-rows'),componentBody=document.querySelector('#component-rows'),examMaximum=document.querySelector('#exam-maximum'),examType=<?=json_encode($examType)?>,gradingBands=<?=json_encode($gradingBands,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,showGrade=<?=json_encode($showGrade)?>,midtermMarksById=<?=json_encode($midtermMarkValues,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;let componentCount=componentBody?componentBody.querySelectorAll('tr').length:0;const totalMaximum=document.querySelector('#sheet-max');
function renumber(){body.querySelectorAll('tr').forEach((row,index)=>{row.querySelector('.row-number').textContent=index+1;row.querySelectorAll('input').forEach(input=>input.name=input.name.replace(/students\[\d+\]/,'students['+index+']'))})}
function renumberComponents(){const headers=document.querySelectorAll('.marks-entry-table thead .sessional-column');headers.forEach((cell,index)=>cell.className='sessional-column sessional-column-'+index);componentBody.querySelectorAll('tr').forEach((row,index)=>{row.querySelectorAll('input').forEach(input=>input.name=input.name.replace(/components\[\d+\]/,'components['+index+']'));body.querySelectorAll('tr').forEach((studentRow,studentIndex)=>{const cell=studentRow.querySelectorAll('.sessional-column')[index];if(cell){cell.className='sessional-column sessional-column-'+index;cell.querySelector('input').name='students['+studentIndex+'][scores]['+index+']'}})});componentCount=componentBody.querySelectorAll('tr').length}
function gradeForPercentage(percentage){const rounded=Math.round(percentage);return gradingBands.find(band=>rounded>=Number(band.min_percentage))}
function recalculate(){let maximum=Number(examMaximum.value)||0;if(componentBody)componentBody.querySelectorAll('tr').forEach(row=>maximum+=Number(row.querySelector('input[type=number]').value)||0);if(examType==='Final')maximum+=Number(<?=json_encode((float)($midtermMaximum??0))?>)||0;const formattedMaximum=maximum.toFixed(2).replace(/\.00$/,'');totalMaximum.textContent=formattedMaximum;document.querySelector('#total-maximum-header').textContent=formattedMaximum;document.querySelector('#exam-maximum-label').textContent=Number(examMaximum.value).toFixed(2).replace(/\.00$/,'');body.querySelectorAll('tr').forEach(row=>{let total=0;row.querySelectorAll('.sessional-score,.exam-score').forEach(input=>total+=Number(input.value)||0);const midterm=Number(row.querySelector('.midterm-mark')?.textContent)||0;if(examType==='Final')total+=midterm;row.querySelector('.student-total').textContent=total.toFixed(2).replace(/\.00$/,'');if(showGrade){const marksInputs=[...row.querySelectorAll('.marks-value')],allMarksEntered=marksInputs.length>0&&marksInputs.every(input=>input.value.trim()!=='')&&(examType!=='Final'||row.querySelector('.midterm-mark')?.textContent.trim()!=='');const percentage=maximum>0?total/maximum*100:0;const grade=allMarksEntered&&maximum>0?gradeForPercentage(percentage):null;row.querySelector('.student-percentage').textContent=grade?percentage.toFixed(2)+'%':'';row.querySelector('.student-grade').textContent=grade?grade.grade:'';row.querySelector('.student-grade-point').textContent=grade?(grade.grade_point===null?'—':Number(grade.grade_point).toFixed(1)):''}})}
function addComponent(label='',maximum='10'){if(componentCount>=12)return;const index=componentCount++,row=document.createElement('tr');row.innerHTML='<td><input class="form-control" name="components['+index+'][label]" maxlength="40" required placeholder="e.g. Presentation" value="'+String(label).replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]))+'"></td><td><input class="form-control" type="number" name="components['+index+'][maximum]" min="0.01" max="9999.99" step="0.01" required value="'+maximum+'"></td><td><button type="button" class="btn btn-sm btn-outline-danger remove-component" aria-label="Remove component">Remove</button></td>';componentBody.append(row);const heading=document.createElement('th'),labelNode=document.createElement('span'),maximumNode=document.createElement('small');heading.className='sessional-column sessional-column-'+index;labelNode.textContent=label;maximumNode.textContent='Max '+maximum;heading.append(labelNode,maximumNode);const reference=document.querySelector('.midterm-header')||document.querySelector('.exam-header');reference.parentElement.insertBefore(heading,reference);body.querySelectorAll('tr').forEach((studentRow,studentIndex)=>{const cell=document.createElement('td'),input=document.createElement('input');cell.className='sessional-column sessional-column-'+index;input.className='form-control marks-value sessional-score';input.type='number';input.name='students['+studentIndex+'][scores]['+index+']';input.min='0';input.max=maximum;input.step='0.01';cell.append(input);studentRow.insertBefore(cell,studentRow.querySelector('.midterm-mark')?.parentElement||studentRow.querySelector('.exam-score').parentElement)});renumberComponents();recalculate()}
function addStudent(){if(body.querySelectorAll('tr').length>=300)return;const index=body.querySelectorAll('tr').length,row=document.createElement('tr');row.innerHTML='<td class="row-number"></td><td><input class="form-control student-id" name="students['+index+'][student_id]" maxlength="64"></td><td><input class="form-control student-name" name="students['+index+'][student_name]" maxlength="150"></td>'+[...(componentBody?.querySelectorAll('tr')??[])].map((component,componentIndex)=>'<td class="sessional-column sessional-column-'+componentIndex+'"><input class="form-control marks-value sessional-score" type="number" name="students['+index+'][scores]['+componentIndex+']" min="0" max="'+component.querySelector('input[type=number]').value+'" step="0.01"></td>').join('')+(examType==='Final'?'<td class="midterm-mark"></td>':'')+'<td><input class="form-control marks-value exam-score" type="number" name="students['+index+'][exam_marks]" min="0" max="'+examMaximum.value+'" step="0.01"></td><td class="student-total">0</td>'+(showGrade?'<td class="student-percentage"></td><td class="student-grade"></td><td class="student-grade-point"></td>':'')+'<td><button type="button" class="btn btn-sm btn-outline-danger remove-row" aria-label="Remove student">×</button></td>';body.append(row);renumber();recalculate();row.querySelector('.student-id').focus()}
if(componentBody){
document.querySelector('#add-component').addEventListener('click',()=>{if(componentCount<12)addComponent()});
componentBody.addEventListener('input',event=>{if(event.target.matches('input[name$="[label]"]')){const index=[...componentBody.children].indexOf(event.target.closest('tr')),header=document.querySelector('.marks-entry-table thead .sessional-column-'+index);if(header)header.querySelector('span').textContent=event.target.value}if(event.target.matches('input[name$="[maximum]"]')){const index=[...componentBody.children].indexOf(event.target.closest('tr')),header=document.querySelector('.marks-entry-table thead .sessional-column-'+index);if(header)header.querySelector('small').textContent='Max '+event.target.value;body.querySelectorAll('.sessional-column-'+index+' input').forEach(input=>input.max=event.target.value)}recalculate()});
componentBody.addEventListener('click',event=>{if(!event.target.matches('.remove-component'))return;const row=event.target.closest('tr'),index=[...componentBody.children].indexOf(row);if([...body.querySelectorAll('.sessional-column-'+index)].some(cell=>cell.querySelector('input')?.value)&&!window.confirm('Removing this component also removes its entered marks. Continue?'))return;body.querySelectorAll('.sessional-column-'+index).forEach(cell=>cell.remove());document.querySelectorAll('.marks-entry-table thead .sessional-column-'+index).forEach(cell=>cell.remove());row.remove();renumberComponents();recalculate()});
}
document.querySelector('#add-row').addEventListener('click',addStudent);
document.querySelector('#add-ten').addEventListener('click',()=>{for(let index=0;index<10;index++)addStudent()});
body.addEventListener('input',event=>{if(event.target.matches('.student-id')&&examType==='Final'){const row=event.target.closest('tr'),midtermValue=midtermMarksById[event.target.value.trim().toLowerCase()];row.querySelector('.midterm-mark').textContent=midtermValue??'';recalculate()}if(event.target.matches('.marks-value'))recalculate()});
body.addEventListener('click',event=>{if(event.target.matches('.remove-row')){const row=event.target.closest('tr'),hasData=[...row.querySelectorAll('input')].some(input=>input.value.trim()!=='');if(hasData&&!window.confirm('Remove this student? The deletion is applied to the saved sheet when you save changes.'))return;row.remove();renumber();recalculate()}});
examMaximum.addEventListener('input',()=>{body.querySelectorAll('.exam-score').forEach(input=>input.max=examMaximum.value);recalculate()});
recalculate();
})();
</script>
<?php endif?>
<?php foot();
