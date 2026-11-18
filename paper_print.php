<?php
require 'includes/boot.php';
need();
$id=(int)($_GET['id']??0);
$s=db()->prepare('SELECT p.*,s.name subject_name,c.name semester_name,c.semester_no,d.name department_name,a.label year_label,b.name batch_name FROM question_papers p JOIN subjects s ON s.id=p.subject_id JOIN classes c ON c.id=p.class_id JOIN departments d ON d.id=p.department_id LEFT JOIN academic_years a ON a.id=c.academic_year_id LEFT JOIN batches b ON b.id=p.batch_id WHERE p.id=?');
$s->execute([$id]);$paper=$s->fetch();
if(!canAccess($paper)){http_response_code(403);die('Forbidden');}
$sections=db()->prepare('SELECT * FROM paper_sections WHERE paper_id=? ORDER BY position');$sections->execute([$id]);
$examTitle=$paper['exam_label']?:$paper['exam_type'].' Examination';$number=0;$isPractical=$paper['exam_type']==='Practical';
$practicalGroups=[];
if($isPractical){
 $questionQuery=db()->prepare("SELECT q.* FROM questions q JOIN paper_sections ps ON ps.id=q.section_id WHERE ps.paper_id=? ORDER BY FIELD(q.qtype,'blank','tf','mcq'),ps.position,q.position");
 $questionQuery->execute([$id]);
 $groupLabels=['blank'=>'FILL IN THE BLANKS','tf'=>'TRUE / FALSE','mcq'=>'MULTIPLE CHOICE QUESTIONS (MCQs)'];
 foreach($questionQuery->fetchAll()as$q){
  $type=$q['qtype'];
  if(!isset($practicalGroups[$type]))$practicalGroups[$type]=['title'=>$groupLabels[$type]??strtoupper($type),'marks'=>0,'questions'=>[]];
  $optionQuery=db()->prepare('SELECT label,body FROM question_options WHERE question_id=? ORDER BY label');$optionQuery->execute([$q['id']]);
  $q['choices']=$optionQuery->fetchAll();
  $practicalGroups[$type]['marks']+=(float)$q['marks'];
  $practicalGroups[$type]['questions'][]=$q;
 }
}
$ordinal=static function($value){$value=(int)$value;$lastTwo=$value%100;if($lastTwo>=11&&$lastTwo<=13)return $value.'th';return $value.(['th','st','nd','rd','th','th','th','th','th','th'][$value%10]??'th');};
?>
<!doctype html><html><head><meta charset=utf-8><meta name=viewport content="width=device-width,initial-scale=1"><title><?=e($examTitle)?> | QUEST</title><style>
@page{size:A4 portrait;margin:12mm}
*{box-sizing:border-box}body{font:11pt "Times New Roman",serif;color:#000;margin:0}.bar{background:#0a2a6b;padding:10px;text-align:center}.bar button{padding:8px 20px;border:0;border-radius:6px;background:#fff;cursor:pointer}.paper{max-width:186mm;margin:auto;padding:10px}.head{display:flex;align-items:center;gap:4mm;border-bottom:1.5px solid #000;padding-bottom:2mm}.head img{width:18mm;height:18mm;object-fit:contain}.head div{flex:1;text-align:center}.head h1{margin:0;font-size:13pt;line-height:1.2;font-weight:bold}.head .department{margin:2px 0 0;font-size:10pt;font-weight:bold}.exam-title{text-align:center;margin:2.5mm 0 1mm;font-weight:bold;font-size:10.5pt;text-transform:uppercase}.subject{text-align:center;margin:1mm 0 2mm;font-size:11pt}.meta{display:flex;justify-content:space-between;gap:8px;border-bottom:1px solid #000;padding:0 0 1.5mm;font-weight:bold}.instructions{margin:2mm 0;padding-bottom:1.5mm;border-bottom:1px solid #000}.instructions p{margin:0 0 1mm}.exam-table{width:100%;border-collapse:collapse;table-layout:fixed;font-size:10pt}.exam-table th,.exam-table td{border:1px solid #555;padding:1.5mm 1.2mm;vertical-align:top}.exam-table th{text-align:center;font-size:8.5pt;font-weight:bold}.exam-table .qno{width:8%;text-align:center}.exam-table .question-cell{width:56%;text-align:left}.exam-table .clo{width:8%;text-align:center}.exam-table .taxonomy{width:10%;text-align:center}.exam-table .plo{width:8%;text-align:center}.exam-table .marks{width:10%;text-align:center;white-space:nowrap}.exam-table.practical .qno{width:10%}.exam-table.practical .question-cell{width:80%}.exam-table.practical .marks{width:10%}.question-row{break-inside:avoid;page-break-inside:avoid}.question-text{white-space:normal;overflow-wrap:anywhere}.options{display:grid;grid-template-columns:1fr 1fr;gap:2px 10px;margin-top:2mm}.question-image{display:block;max-width:100%;width:auto;max-height:85mm;object-fit:contain;margin:2mm auto 0;break-inside:avoid;page-break-inside:avoid}.section-row td{font-weight:bold;background:#f5f5f5}.end{text-align:center;margin-top:5mm;font-weight:bold;font-style:italic}
.practical-table .practical-heading td{background:#edf3fb;border-top:2px solid #173a69;padding:2.5mm 3mm;font-size:10.5pt}.practical-heading-content{display:flex;justify-content:space-between;gap:8px;align-items:center}.practical-heading-content strong{letter-spacing:.2px}.practical-qno{width:12%;text-align:center;font-weight:bold}.practical-question{width:76%}.practical-marks{width:12%;text-align:center;white-space:nowrap;font-weight:bold}.answer-options{display:grid;grid-template-columns:1fr 1fr;gap:2mm 8mm;margin-top:2mm}.answer-option{padding:1mm 0}.choice-label{font-weight:bold;text-transform:lowercase;margin-right:1mm}.tf-options{display:flex;gap:10mm;margin-top:3mm}.tf-option{display:inline-flex;align-items:center;gap:2mm}.answer-box{display:inline-block;width:4mm;height:4mm;border:1px solid #000;vertical-align:middle;flex:0 0 4mm}
@media print{.bar{display:none}.paper{padding:0;max-width:none}.exam-table th{background:#fff!important}.section-row td{background:#fff!important}.question-row{break-inside:avoid;page-break-inside:avoid}}
</style></head><body><div class=bar><button onclick="window.print()">Print / Save as PDF</button></div><main class=paper>
<header class=head><img src="assets/images/quest_logo.png?v=quest-cyan-20261023" alt="QUEST official logo"><div><h1>QUAID-E-AWAM UNIVERSITY OF ENGINEERING, SCIENCE &amp; TECHNOLOGY, NAWABSHAH</h1><p class=department>DEPARTMENT OF <?=e(strtoupper($paper['department_name']))?></p></div></header>
<p class=exam-title><?=e(strtoupper($examTitle))?><?php if($paper['year_label']):?> OF <?=e(strtoupper($paper['year_label']))?><?php endif?> (<?=e($ordinal($paper['semester_no']))?> SEMESTER)<?php if($paper['batch_name']):?> · BATCH <?=e(strtoupper($paper['batch_name']))?><?php endif?><?php if($paper['exam_date']):?> · <?=e(date('Y',strtotime($paper['exam_date'])))?><?php endif?></p>
<p class=subject><b>SUBJECT: <u><?=e(strtoupper($paper['subject_name']))?></u></b></p>
<div class=meta><span>Date: <?=$paper['exam_date']?e(date('d.m.Y',strtotime($paper['exam_date']))):'________'?></span><span>Maximum Marks: <?=e($paper['total_marks'])?></span><span>Time Allowed: <?=e($paper['duration']?:'________')?></span></div>
<div class=instructions><?php if($paper['title']):?><p><b><?=e($paper['title'])?></b></p><?php endif?><?php if($paper['instructions']):?><p><?=nl2br(e($paper['instructions']))?></p><?php endif?></div>
<?php if($isPractical):?>
<table class="exam-table practical-table"><thead><tr><th class=practical-qno>Q. No.</th><th class=practical-question>QUESTION</th><th class=practical-marks>MARKS</th></tr></thead><tbody>
<?php $sectionLetter='A';foreach($practicalGroups as$group):?>
<tr class=practical-heading><td colspan=3><div class=practical-heading-content><strong>SECTION <?=$sectionLetter++?> — <?=e($group['title'])?></strong><span>Total Marks: <?=e($group['marks'])?></span></div></td></tr>
<?php foreach($group['questions']as$questionIndex=>$q):$number=$questionIndex+1;?>
<tr class=question-row><td class=practical-qno><?=$number?>.</td><td class=practical-question><div class=question-text><?=nl2br(e($q['body']))?></div>
<?php if($q['qtype']==='tf'):?><div class=tf-options><span class=tf-option><span class=answer-box aria-hidden=true></span> True</span><span class=tf-option><span class=answer-box aria-hidden=true></span> False</span></div><?php elseif($q['qtype']==='mcq'&&$q['choices']):?><div class=answer-options><?php foreach($q['choices']as$choice):?><span class=answer-option><span class=answer-box aria-hidden=true></span> <span class=choice-label>(<?=e(strtolower($choice['label']))?>)</span><?=e($choice['body'])?></span><?php endforeach?></div><?php endif?>
<?php if($q['image_path']):if(is_file(__DIR__.'/'.$q['image_path'])):?><img class=question-image src="<?=e($q['image_path'])?>" alt="Question image"><?php else:error_log('Missing question image: '.$q['image_path']);endif;endif?>
</td><td class=practical-marks><?=e((float)$q['marks'])?></td></tr>
<?php endforeach?><?php endforeach?>
</tbody></table>
<?php else:?>
<table class="exam-table"><thead><tr><th class=qno>Q. No.</th><th class=question-cell>QUESTION</th><th class=clo>CLO</th><th class=taxonomy>Tax. Level</th><th class=plo>PLO</th><th class=marks>Marks</th></tr></thead><tbody>
<?php foreach($sections->fetchAll()as$section):?>
<?php if($section['title']!=='Questions'||$section['attempt_note']):?><tr class=section-row><td colspan=6><?=($section['title']!=='Questions'?e($section['title']):'')?><?=$section['title']!=='Questions'&&$section['attempt_note']?' — ':''?><?=$section['attempt_note']?e($section['attempt_note']):''?></td></tr><?php endif?>
<?php $questions=db()->prepare('SELECT * FROM questions WHERE section_id=? ORDER BY position');$questions->execute([$section['id']]);foreach($questions->fetchAll()as$q):$number++;$options=db()->prepare('SELECT label,body FROM question_options WHERE question_id=? ORDER BY label');$options->execute([$q['id']]);$choices=$options->fetchAll();?>
<tr class=question-row><td class=qno><?=sprintf('%02d',$number)?></td><td class=question-cell><div class=question-text><?=nl2br(e($q['body']))?></div>
<?php if($q['qtype']==='tf'&&!$choices):?><div class=options><span>( ) True</span><span>( ) False</span></div><?php elseif($choices):?><div class=options><?php foreach($choices as$choice):?><span>(<?=e($choice['label'])?>) <?=e($choice['body'])?></span><?php endforeach?></div><?php endif?>
<?php if($q['image_path']):if(is_file(__DIR__.'/'.$q['image_path'])):?><img class=question-image src="<?=e($q['image_path'])?>" alt="Image for question <?=$number?>"><?php else:error_log('Missing question image: '.$q['image_path']);endif;endif?>
</td><td class=clo><?=e($q['clo']?:'—')?></td><td class=taxonomy><?=e($q['taxonomy']?:'—')?></td><td class=plo><?=e($q['plo']?:'—')?></td><td class=marks><?=e((float)$q['marks'])?></td></tr>
<?php endforeach?><?php endforeach?>
</tbody></table><?php endif?>
<p class=end><b><u>Good Luck</u></b></p></main></body></html>
