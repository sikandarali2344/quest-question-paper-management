<?php
require 'includes/boot.php';
need(['teacher']);
header('Cache-Control: no-store, private');

$id=(int)($_GET['id']??0);
$query=db()->prepare('SELECT m.*,s.name subject_name,c.semester_no,a.label year_label,b.name batch_name,d.name department_name FROM teacher_marksheets m JOIN subjects s ON s.id=m.subject_id JOIN classes c ON c.id=m.class_id JOIN academic_years a ON a.id=c.academic_year_id JOIN batches b ON b.id=m.batch_id JOIN departments d ON d.id=m.department_id WHERE m.id=? AND m.teacher_id=? AND s.teacher_id=?');
$query->execute([$id,user()['id'],user()['id']]);
$sheet=$query->fetch();
if(!$sheet){http_response_code(404);die('Marks sheet not found.');}

$componentQuery=db()->prepare('SELECT id,label,maximum_marks FROM teacher_marksheet_components WHERE marksheet_id=? ORDER BY position,id');
$componentQuery->execute([$id]);
$components=$sheet['exam_type']==='Midterm'?[]:$componentQuery->fetchAll();
$studentQuery=db()->prepare('SELECT * FROM teacher_marksheet_students WHERE marksheet_id=? ORDER BY id');
$studentQuery->execute([$id]);
$students=$studentQuery->fetchAll();
$scoreQuery=db()->prepare('SELECT score.student_row_id,score.component_id,score.obtained_marks FROM teacher_marksheet_component_scores score JOIN teacher_marksheet_students student ON student.id=score.student_row_id WHERE student.marksheet_id=?');
$scoreQuery->execute([$id]);
$scores=[];
foreach($scoreQuery->fetchAll()as$score)$scores[(int)$score['student_row_id']][(int)$score['component_id']]=$score['obtained_marks'];

$isFinal=$sheet['exam_type']==='Final';
$showGrade=in_array($sheet['exam_type'],['Final','Practical'],true);
$gradingPolicy=questBatchGradingPolicy((int)$sheet['batch_id']);
$examField=$sheet['exam_type']==='Midterm'?'midterm_exam_marks':'final_exam_marks';
$examMaximum=(float)($sheet['exam_type']==='Midterm'?$sheet['midterm_exam_max']:$sheet['final_exam_max']);
$midtermMaximum=(float)($sheet['midterm_exam_max']??0);
$linkedMidtermMarks=[];
$hasMidtermSheet=false;
if($isFinal){
 $midtermSheetQuery=db()->prepare('SELECT id,midterm_exam_max FROM teacher_marksheets WHERE teacher_id=? AND class_id=? AND batch_id=? AND subject_id=? AND exam_type="Midterm"');
 $midtermSheetQuery->execute([$sheet['teacher_id'],$sheet['class_id'],$sheet['batch_id'],$sheet['subject_id']]);
 $midtermSheet=$midtermSheetQuery->fetch();
 if($midtermSheet){
  $hasMidtermSheet=true;
  $midtermMaximum=(float)$midtermSheet['midterm_exam_max'];
  $midtermStudentsQuery=db()->prepare('SELECT student_id,midterm_exam_marks FROM teacher_marksheet_students WHERE marksheet_id=?');
  $midtermStudentsQuery->execute([$midtermSheet['id']]);
  foreach($midtermStudentsQuery->fetchAll()as$midtermStudent)$linkedMidtermMarks[strtolower($midtermStudent['student_id'])]=$midtermStudent['midterm_exam_marks'];
 }
}
$totalMaximum=$examMaximum;
foreach($components as$component)$totalMaximum+=(float)$component['maximum_marks'];
if($isFinal)$totalMaximum+=$midtermMaximum;
?><!doctype html><html lang=en><head><meta charset=utf-8><meta name=viewport content="width=device-width,initial-scale=1"><title><?=e($sheet['subject_name'])?> Marks | QUEST</title><style>
*{box-sizing:border-box}body{margin:0;padding:18px;color:#152b49;font:13px "Segoe UI",Arial,sans-serif}.toolbar{display:flex;justify-content:space-between;gap:12px;margin-bottom:16px}.toolbar button{padding:9px 16px;border:0;border-radius:6px;background:#123d74;color:#fff;font-weight:600;cursor:pointer}.heading{text-align:center;border-bottom:2px solid #123d74;padding-bottom:12px;margin-bottom:12px}.heading img{width:58px;height:58px;object-fit:contain;vertical-align:middle;margin-right:12px}.heading h1{display:inline-block;margin:0;vertical-align:middle;font-size:20px;color:#123d74}.heading .department{margin-top:7px;color:#287b9b;font-weight:700;letter-spacing:.08em}.details{display:grid;grid-template-columns:repeat(3,1fr);gap:7px;margin:12px 0 16px}.details strong{color:#123d74}.marks{width:100%;border-collapse:collapse}.marks th,.marks td{border:1px solid #5b6b7f;padding:7px 8px;text-align:center}.marks th{background:#eaf1ff;color:#102c53}.marks th:nth-child(2),.marks th:nth-child(3),.marks td:nth-child(2),.marks td:nth-child(3){text-align:left}.marks tbody tr{height:34px}.footer{text-align:right;margin-top:14px;color:#52647c;font-size:12px}@page{size:A4 landscape;margin:12mm}@media print{body{padding:0}.toolbar{display:none}.marks th{background:#eaf1ff!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}.marks tr{break-inside:avoid}}
</style></head><body><div class=toolbar><span>Print this sheet or choose “Save as PDF”.</span><button onclick="window.print()">Print / Save PDF</button></div>
<header class=heading><img src="assets/images/quest_logo.png?v=quest-cyan-20261023" alt="QUEST logo"><h1>QUAID-E-AWAM UNIVERSITY OF ENGINEERING, SCIENCE &amp; TECHNOLOGY</h1><div>NAWABSHAH · STUDENT MARKS REGISTER</div><div class=department><?=e($sheet['department_name'])?> DEPARTMENT</div></header>
<section class=details><div><strong>Subject:</strong> <?=e($sheet['subject_name'])?></div><div><strong>Exam type:</strong> <?=e($sheet['exam_type'])?></div><div><strong>Maximum total:</strong> <?=e($totalMaximum)?></div><div><strong>Academic year:</strong> <?=e($sheet['year_label'])?></div><div><strong>Semester:</strong> <?=e($sheet['semester_no'])?></div><div><strong>Batch:</strong> <?=e($sheet['batch_name'])?></div></section>
<table class=marks><thead><tr><th>S. No.</th><th>Roll No.</th><th>Student Name</th><?php foreach($components as$component):?><th><?=e($component['label'])?><br>Max: <?=e((float)$component['maximum_marks'])?></th><?php endforeach?><?php if($isFinal):?><th>Midterm Marks<br>Max: <?=e($midtermMaximum)?></th><?php endif?><th><?=e($sheet['exam_type']==='Midterm'?'Midterm Exam':($isFinal?'Final Exam':'Practical Exam'))?><br>Max: <?=e($examMaximum)?></th><th>Total<br>Max: <?=e($totalMaximum)?></th><?php if($showGrade):?><th>Percentage</th><th>Grade</th><th>Grade Point</th><?php endif?></tr></thead><tbody>
<?php $number=0;foreach($students as$student):$number++;$total=0;$allMarksEntered=$student[$examField]!==null;?><tr><td><?=$number?></td><td><?=e($student['student_id'])?></td><td><?=e($student['student_name'])?></td><?php foreach($components as$component):$value=$scores[(int)$student['id']][(int)$component['id']]??null;if($value!==null)$total+=(float)$value;else $allMarksEntered=false;?><td><?=$value===null?'':e((float)$value)?></td><?php endforeach?><?php if($isFinal):$studentKey=strtolower($student['student_id']);$midterm=$hasMidtermSheet?($linkedMidtermMarks[$studentKey]??null):$student['midterm_exam_marks'];if($midterm!==null)$total+=(float)$midterm;else $allMarksEntered=false;?><td><?=$midterm===null?'':e((float)$midterm)?></td><?php endif?><?php $examMarks=$student[$examField];if($examMarks!==null)$total+=(float)$examMarks;$percentage=$totalMaximum>0?($total/$totalMaximum)*100:0;$gradeInfo=$showGrade&&$allMarksEntered&&$totalMaximum>0?questGradeFromPercentage($percentage,$gradingPolicy):null;?><td><?=$examMarks===null?'':e((float)$examMarks)?></td><td><?=e(number_format($total,2,'.',''))?></td><?php if($showGrade):?><td><?=$gradeInfo?e(number_format($percentage,2,'.','').'%'):''?></td><td><?=$gradeInfo?e($gradeInfo['grade']):''?></td><td><?=$gradeInfo&&$gradeInfo['point']!==null?e(number_format($gradeInfo['point'],1,'.','')):'—'?></td><?php endif?></tr><?php endforeach?></tbody></table>
<p class=footer>Prepared by: <?=e(user()['name'])?> · Printed <?=e(date('d M Y'))?></p></body></html>
