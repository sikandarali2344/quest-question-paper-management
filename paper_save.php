<?php
require 'includes/boot.php';
need();
$u=user();$id=(int)($_POST['id']??0);$P=fn($key)=>is_string($_POST[$key]??null)?trim($_POST[$key]):'';
$payload=is_string($_POST['payload']??null)?$_POST['payload']:'[]';$sections=json_decode($payload,true);
$types=['descriptive','mcq','blank','tf'];
$examTypes=['Midterm','Final','Final Term','Quiz','Class Test','Practical','Supplementary','Other'];
$errors=[];
$department=(int)$P('department_id');$batch=(int)$P('batch_id');$semester=(int)$P('class_id');$subject=(int)$P('subject_id');
if(!$department||!$batch||!$semester||!$subject)$errors[]='Choose a department, batch, academic year/semester, and subject.';
if(!in_array($P('exam_type'),$examTypes,true))$errors[]='Choose a valid exam type.';
if((int)$P('total_marks')<1)$errors[]='Total marks must be greater than zero.';
if($P('exam_date')!==''&&(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$P('exam_date'))||!DateTime::createFromFormat('!Y-m-d',$P('exam_date'))||DateTime::createFromFormat('!Y-m-d',$P('exam_date'))->format('Y-m-d')!==$P('exam_date')))$errors[]='Enter a valid exam date.';
$scope=db()->prepare('SELECT c.department_id FROM classes c JOIN academic_years a ON a.id=c.academic_year_id WHERE c.id=? AND a.department_id=c.department_id');
$scope->execute([$semester]);$semesterDepartment=$scope->fetchColumn();
if(!$semesterDepartment||(int)$semesterDepartment!==$department)$errors[]='The selected semester does not belong to the department.';
$batchCheck=db()->prepare('SELECT id FROM batches WHERE id=? AND department_id=? AND (status="active" OR id=(SELECT batch_id FROM question_papers WHERE id=? AND status="draft"))');
$batchCheck->execute([$batch,$department,$id]);
if(!$batchCheck->fetchColumn())$errors[]='Choose an active batch belonging to the selected department.';
$subjectQuery='SELECT id FROM subjects WHERE id=? AND class_id=? AND department_id=? AND status="active"'.($u['role']==='teacher'?' AND teacher_id=?':'');
$check=db()->prepare($subjectQuery);$args=[$subject,$semester,$department];if($u['role']==='teacher')$args[]=$u['id'];$check->execute($args);
if(!$check->fetchColumn())$errors[]='The subject is not available to your account.';
if(!admin()&&(int)$u['department_id']!==$department)$errors[]='You cannot create papers for this department.';
if(!is_array($sections)||!$sections)$errors[]='Add at least one question.';
$questionCount=0;$marksSum=0;
if(is_array($sections)){
 foreach($sections as$section){
  if(!is_array($section)||!is_array($section['questions']??null)){$errors[]='Invalid question data.';continue;}
  if(!is_string($section['title']??'')||!is_string($section['attempt_note']??''))$errors[]='Invalid section details.';
  foreach($section['questions'] as$q){
   if(!is_array($q)){$errors[]='Invalid question data.';continue;}
   $questionCount++;
   if(!is_string($q['text']??null)||trim($q['text'])==='')$errors[]='Every question needs question text.';
   if(!in_array($q['type']??'descriptive',$types,true))$errors[]='Choose a valid question type.';
   if($P('exam_type')==='Practical'&&!in_array($q['type']??'',['mcq','blank','tf'],true))$errors[]='Practical papers support MCQ, fill-in-the-blanks, and true/false questions only.';
   if(!is_numeric($q['marks']??'')||(float)$q['marks']<=0)$errors[]='Each question must have marks greater than zero.';
   else $marksSum+=(float)$q['marks'];
   if($P('exam_type')!=='Practical')foreach(['clo','taxonomy','plo']as$field){
    if(!is_string($q[$field]??'')||strlen(trim($q[$field]??''))>10)$errors[]='CLO, Taxonomy and PLO values must be text up to 10 characters.';
   }
   if(($q['type']??'')==='mcq'){
    $options=array_slice(is_array($q['options']??null)?$q['options']:[],0,4);
    $filled=array_filter($options,fn($option)=>is_string($option)&&trim($option)!=='');
    if(count($filled)<2)$errors[]='Each MCQ needs at least two answer options.';
    $correct=$q['correct']??'';
    if(!in_array($correct,['A','B','C','D'],true))$errors[]='Choose the correct option for each MCQ.';
    else{
     $correctIndex=array_search($correct,str_split('ABCD'),true);
     if(!isset($options[$correctIndex])||!is_string($options[$correctIndex])||trim($options[$correctIndex])==='')$errors[]='The correct MCQ answer must have an option text.';
    }
   }
  }
 }
}
if($questionCount===0)$errors[]='Add at least one question.';
if($errors){flash('danger',implode(' ',array_unique($errors)));go($id?"paper_form.php?id=$id":'paper_form.php');}
$pdo=db();$oldImages=[];$ownedImages=[];
if($id){
 $s=$pdo->prepare('SELECT * FROM question_papers WHERE id=?');$s->execute([$id]);$original=$s->fetch();
 if(!canAccess($original)||$original['status']!=='draft'){http_response_code(403);die('Only your draft papers can be edited.');}
 $s=$pdo->prepare('SELECT q.id,q.image_path FROM questions q JOIN paper_sections ps ON ps.id=q.section_id WHERE ps.paper_id=?');$s->execute([$id]);
 foreach($s->fetchAll()as$row){if($row['image_path'])$oldImages[]=$row['image_path'];$ownedImages[(int)$row['id']]=$row['image_path'];}
}
$newFiles=[];$uploaded=[];
try{
 $files=$_FILES['question_images']??null;
 if($files){
  foreach($files['name']as$key=>$name){
   $error=(int)$files['error'][$key];
   if($error===UPLOAD_ERR_NO_FILE)continue;
   if($error!==UPLOAD_ERR_OK)throw new RuntimeException('An image upload failed. Please choose the image again.');
   if((int)$files['size'][$key]>5*1024*1024)throw new RuntimeException('Question images must be 5 MB or smaller.');
   $tmp=$files['tmp_name'][$key];
   $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
   $extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
   if(!isset($extensions[$mime])||!getimagesize($tmp))throw new RuntimeException('Images must be valid JPG, PNG, or WebP files.');
   $uploadDir=__DIR__.'/uploads/questions';
   if(!is_dir($uploadDir)&&!mkdir($uploadDir,0755,true)&&!is_dir($uploadDir))throw new RuntimeException('Could not create the question image folder.');
   $filename=bin2hex(random_bytes(20)).'.'.$extensions[$mime];
   $relative='uploads/questions/'.$filename;
   if(!move_uploaded_file($tmp,__DIR__.'/'.$relative))throw new RuntimeException('Could not save the uploaded question image.');
   $newFiles[]=$relative;$uploaded[(string)$key]=$relative;
  }
 }
 $sum=0;
 $pdo->beginTransaction();
 $paperNote=$id?($original['title']??null):null;
 $values=[$department,$semester,$subject,$batch,$P('exam_type'),$P('exam_label')?:null,$P('exam_date')?:null,null,(int)$P('total_marks'),$P('duration')?:null,$paperNote,$P('instructions')?:null];
 if($id){
  $s=$pdo->prepare('SELECT * FROM question_papers WHERE id=? FOR UPDATE');$s->execute([$id]);$paper=$s->fetch();
  if(!canAccess($paper)||$paper['status']!=='draft')throw new RuntimeException('Only your draft papers can be edited.');
  $pdo->prepare('UPDATE question_papers SET department_id=?,class_id=?,subject_id=?,batch_id=?,exam_type=?,exam_label=?,exam_date=?,examiner=?,total_marks=?,duration=?,title=?,instructions=? WHERE id=?')->execute([...$values,$id]);
  $pdo->prepare('DELETE FROM paper_sections WHERE paper_id=?')->execute([$id]);
 }else{
  $pdo->prepare('INSERT INTO question_papers(department_id,class_id,subject_id,batch_id,exam_type,exam_label,exam_date,examiner,total_marks,duration,title,instructions,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([...$values,$u['id']]);
  $id=(int)$pdo->lastInsertId();
  $pdo->prepare('UPDATE question_papers SET ref_no=? WHERE id=?')->execute(['QP-'.date('Y').'-'.str_pad((string)$id,5,'0',STR_PAD_LEFT),$id]);
 }
 $questionNumber=0;$usedImages=[];
 foreach($sections as$sectionIndex=>$section){
  $questions=$section['questions'];$pdo->prepare('INSERT INTO paper_sections(paper_id,position,title,qtype,attempt_note) VALUES(?,?,?,?,?)')->execute([$id,$sectionIndex+1,trim($section['title']??'')?:'Questions','descriptive',trim($section['attempt_note']??'')?:null]);$sectionId=(int)$pdo->lastInsertId();
  foreach($questions as$position=>$q){
   $questionNumber++;$questionType=$q['type']??'descriptive';
   $oldId=(int)($q['id']??0);$image=isset($ownedImages[$oldId])?$ownedImages[$oldId]:null;
   if(!empty($q['remove_image']))$image=null;
   $uploadKey=$q['upload']??null;
   if($uploadKey!==null&&!isset($uploaded[(string)$uploadKey]))throw new RuntimeException('A selected question image was not uploaded.');
   if($uploadKey!==null)$image=$uploaded[(string)$uploadKey];
   if($image)$usedImages[]=$image;
   $marks=(float)$q['marks'];$sum+=$marks;
   $correct=($questionType==='mcq'&&in_array($q['correct']??'',['A','B','C','D'],true))?$q['correct']:null;
   $mapping=$P('exam_type')==='Practical'?[null,null,null]:[trim($q['clo']??'')?:null,trim($q['taxonomy']??'')?:null,trim($q['plo']??'')?:null];
   $pdo->prepare('INSERT INTO questions(section_id,position,qtype,body,marks,clo,taxonomy,plo,correct_option,image_path) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([$sectionId,$position+1,$questionType,trim($q['text']),$marks,...$mapping,$correct,$image]);
   $questionId=(int)$pdo->lastInsertId();
   if($questionType==='mcq')foreach(array_slice($q['options']??[],0,4)as$index=>$option)if(trim($option)!=='')$pdo->prepare('INSERT INTO question_options(question_id,label,body) VALUES(?,?,?)')->execute([$questionId,'ABCD'[$index],trim($option)]);
  }
 }
 audit('paper_save',"#$id");$pdo->commit();
 foreach(array_unique(array_merge(array_diff($oldImages,$usedImages),array_diff($newFiles,$usedImages)))as$path){
  $reference=$pdo->prepare('SELECT 1 FROM questions WHERE image_path=? LIMIT 1');$reference->execute([$path]);
  if(!$reference->fetchColumn()&&is_file(__DIR__.'/'.$path))unlink(__DIR__.'/'.$path);
 }
 flash(abs($sum-(float)$P('total_marks'))<0.001?'success':'warning',abs($sum-(float)$P('total_marks'))<0.001?'Draft saved.':"Draft saved; question marks total $sum, not {$P('total_marks')}.");
 go('papers.php');
}catch(Throwable $x){
 if($pdo->inTransaction())$pdo->rollBack();
 foreach($newFiles as$path)if(is_file(__DIR__.'/'.$path))unlink(__DIR__.'/'.$path);
 error_log($x->getMessage());flash('danger',$x instanceof RuntimeException?$x->getMessage():'Could not save the paper. Check the details and try again.');
 go($id?"paper_form.php?id=$id":'paper_form.php');
}
