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
$safeCell=static function($value){$value=(string)$value;return preg_match('/^[=+\-@\t\r]/',$value)?"'".$value:$value;};
$filename=preg_replace('/[^A-Za-z0-9_-]+/','-',strtolower($sheet['subject_name'].'-'.$sheet['exam_type'].'-marks'))?:'student-marks';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filename.'.csv"');
header('X-Content-Type-Options: nosniff');
$output=fopen('php://output','w');
fwrite($output,"\xEF\xBB\xBF");
fputcsv($output,[$safeCell('Quaid-e-Awam University of Engineering, Science & Technology')]);
fputcsv($output,[$safeCell($sheet['department_name']),'Subject: '.$safeCell($sheet['subject_name']),'Year: '.$safeCell($sheet['year_label']),'Semester: '.$sheet['semester_no'],'Batch: '.$safeCell($sheet['batch_name']),'Exam: '.$safeCell($sheet['exam_type'])]);
$header=['S. No.','Roll No.','Student Name'];
foreach($components as$component)$header[]=$safeCell($component['label']).' (Max '.(float)$component['maximum_marks'].')';
if($isFinal)$header[]='Midterm Marks (Max '.$midtermMaximum.')';
$examLabel=$sheet['exam_type']==='Midterm'?'Midterm Exam':($isFinal?'Final Exam':'Practical Exam');
$header[]=$examLabel.' (Max '.$examMaximum.')';
$header[]='Total (Max '.$totalMaximum.')';
if($showGrade)$header=array_merge($header,['Percentage','Grade','Grade Point']);
fputcsv($output,$header);
$number=0;
foreach($students as$student){
 $number++;
 $row=[$number,$safeCell($student['student_id']),$safeCell($student['student_name'])];
 $total=0;
 foreach($components as$component){
  $value=$scores[(int)$student['id']][(int)$component['id']]??null;
  $row[]=$value;
  if($value!==null)$total+=(float)$value;
 }
 if($isFinal){$studentKey=strtolower($student['student_id']);$midtermMarks=$hasMidtermSheet?($linkedMidtermMarks[$studentKey]??null):$student['midterm_exam_marks'];$row[]=$midtermMarks;if($midtermMarks!==null)$total+=(float)$midtermMarks;}
 $row[]=$student[$examField];
 if($student[$examField]!==null)$total+=(float)$student[$examField];
 $row[]=number_format($total,2,'.','');
 if($showGrade){
  $allMarksEntered=$student[$examField]!==null;
  foreach($components as$component)if(($scores[(int)$student['id']][(int)$component['id']]??null)===null)$allMarksEntered=false;
  if($isFinal&&$midtermMarks===null)$allMarksEntered=false;
  $percentage=$totalMaximum>0?($total/$totalMaximum)*100:0;
  $gradeInfo=$allMarksEntered&&$totalMaximum>0?questGradeFromPercentage($percentage,$gradingPolicy):null;
  $row[]=$gradeInfo?number_format($percentage,2,'.','').'%':'';
  $row[]=$gradeInfo?$safeCell($gradeInfo['grade']):'';
  $row[]=$gradeInfo&&$gradeInfo['grade_point']!==null?number_format($gradeInfo['grade_point'],1,'.',''):'—';
 }
 fputcsv($output,$row);
}
fclose($output);
