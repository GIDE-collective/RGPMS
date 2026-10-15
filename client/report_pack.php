<?php
/**
 * RG PMS - Client Reporting Pack PDF
 * Generates a professional monthly-style project reporting pack.
 */
require_once __DIR__ . '/../includes/auth.php';
require_role('client');
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../assets/vendor/fpdf.php';

$id = (int)($_GET['id'] ?? 0);
if ($id < 1) { http_response_code(400); exit('Invalid project.'); }

$pdo = db();
$user = current_user();
$cs = $pdo->prepare('SELECT id FROM clients WHERE user_id=?');
$cs->execute([(int)$user['id']]);
$clientIds = array_map(fn($r)=>(int)$r['id'], $cs->fetchAll());
if (!$clientIds) { http_response_code(403); exit('403 - No client access.'); }
$in = implode(',', array_fill(0, count($clientIds), '?'));
$stmt = $pdo->prepare('SELECT p.*, c.company_name, c.contact_person, c.email AS client_email, u.full_name AS pm_name
                       FROM projects p JOIN clients c ON c.id=p.client_id
                       LEFT JOIN users u ON u.id=p.project_manager_id WHERE p.id=? AND p.client_id IN ($in)');
$stmt->execute(array_merge([$id], $clientIds));
$project = $stmt->fetch();
if (!$project) { http_response_code(404); exit('Project not found.'); }

$q = $pdo->prepare('SELECT * FROM project_meetings WHERE project_id=? ORDER BY meeting_date DESC'); $q->execute([$id]); $meetings=$q->fetchAll();
$q = $pdo->prepare('SELECT * FROM project_programme WHERE project_id=? ORDER BY COALESCE(baseline_start,forecast_start),id'); $q->execute([$id]); $programme=$q->fetchAll();
$q = $pdo->prepare('SELECT * FROM project_costs WHERE project_id=? ORDER BY cost_date DESC,id DESC'); $q->execute([$id]); $costs=$q->fetchAll();
$q = $pdo->prepare('SELECT * FROM project_cashflow WHERE project_id=? ORDER BY period_month'); $q->execute([$id]); $cashflow=$q->fetchAll();
$q = $pdo->prepare('SELECT * FROM activity_schedules WHERE project_id=? ORDER BY COALESCE(planned_date,actual_date),id'); $q->execute([$id]); $activities=$q->fetchAll();
$q = $pdo->prepare('SELECT mt.*,m.name AS material_name,m.unit FROM material_tracking mt JOIN materials m ON m.id=mt.material_id WHERE mt.project_id=? ORDER BY mt.updated_at DESC'); $q->execute([$id]); $materials=$q->fetchAll();
$q = $pdo->prepare('SELECT pi.*,u.full_name AS reporter FROM project_issues pi LEFT JOIN users u ON u.id=pi.reported_by WHERE pi.project_id=? AND pi.escalated_to_client=1 ORDER BY pi.created_at DESC'); $q->execute([$id]); $issues=$q->fetchAll();

$invoices=$pdo->prepare('SELECT * FROM invoices WHERE project_id=? ORDER BY issue_date DESC'); $invoices->execute([$id]); $invoiceList=$invoices->fetchAll();

$committed=array_sum(array_map(fn($r)=>(float)$r['committed_amount'],$costs));
$actual=array_sum(array_map(fn($r)=>(float)$r['actual_amount'],$costs));
$planned=array_sum(array_map(fn($r)=>(float)$r['planned_expenditure'],$cashflow));
$actualCash=array_sum(array_map(fn($r)=>(float)$r['actual_expenditure'],$cashflow));
$invoiced=array_sum(array_map(fn($r)=>(float)$r['amount'],$invoiceList));
$paid=array_sum(array_map(fn($r)=>(float)$r['amount'],array_filter($invoiceList,fn($r)=>$r['status']==='paid')));
$variance=(float)$project['budget']-$actual;
$burn=$planned>0?($actualCash/$planned)*100:0;
$latestMonth=$cashflow ? end($cashflow)['period_month'] : date('Y-m-d');
$reportPeriod=date('F Y',strtotime($latestMonth));

function rp_text($value): string {
    $s=(string)$value;
    $s=str_replace(["\r\n","\r"],"\n",$s);
    return iconv('UTF-8','windows-1252//TRANSLIT',$s) ?: '';
}
function rp_date($value): string { return $value ? date('d M Y',strtotime($value)) : '-'; }
function rp_money($value): string { return 'R '.number_format((float)$value,2,'.',','); }
function rp_status($value): string { return ucwords(str_replace('_',' ',(string)$value)); }

class RGReportPDF extends FPDF {
    public string $projectName='';
    function Header(): void {
        $logo=__DIR__.'/../assets/img/logo-icon.png';
        if(is_file($logo)) $this->Image($logo,16,11,13,13);
        $this->SetXY(33,11); $this->SetFont('Arial','B',14); $this->SetTextColor(31,41,55); $this->Cell(0,6,rp_text(APP_NAME),0,1);
        $this->SetX(33); $this->SetFont('Arial','',8); $this->SetTextColor(107,114,128); $this->Cell(0,5,rp_text('Construction & Civil Engineering | '.COMPANY_PHONE.' | '.COMPANY_EMAIL),0,1);
        $this->SetY(27); $this->SetDrawColor(183,121,31); $this->SetLineWidth(.7); $this->Line(16,27,194,27); $this->SetY(32);
    }
    function Footer(): void {
        $this->SetY(-15); $this->SetDrawColor(220,220,220); $this->Line(16,282,194,282);
        $this->SetY(-12); $this->SetFont('Arial','',7); $this->SetTextColor(140,140,140);
        $this->Cell(0,4,rp_text(APP_NAME.' | Project reporting pack | Confidential'),0,0,'L');
        $this->Cell(0,4,'Page '.$this->PageNo(),0,0,'R');
    }
    function Title($title,$subtitle=''): void {
        $this->SetFont('Arial','B',14); $this->SetTextColor(183,121,31); $this->Cell(0,8,rp_text($title),0,1);
        if($subtitle!==''){ $this->SetFont('Arial','',8); $this->SetTextColor(107,114,128); $this->MultiCell(0,4,rp_text($subtitle)); }
        $this->Ln(3);
    }
    function Section($title): void {
        $this->SetFont('Arial','B',10); $this->SetTextColor(31,41,55); $this->SetFillColor(245,245,245); $this->Cell(0,7,rp_text($title),0,1,'L',true); $this->Ln(2);
    }
    function LabelValue($label,$value,$x,$y,$w): void {
        $this->SetXY($x,$y); $this->SetFont('Arial','B',8); $this->SetTextColor(107,114,128); $this->Cell($w,4,rp_text($label),0,1);
        $this->SetX($x); $this->SetFont('Arial','',9); $this->SetTextColor(31,41,55); $this->MultiCell($w,4.5,rp_text($value));
    }
    function TableHeader($widths,$headers): void {
        $this->SetFont('Arial','B',7.5); $this->SetFillColor(31,41,55); $this->SetTextColor(255,255,255);
        foreach($headers as $i=>$h) $this->Cell($widths[$i],7,rp_text($h),1,0,'C',true);
        $this->Ln(); $this->SetTextColor(31,41,55); $this->SetFont('Arial','',7.5);
    }
    function Row($widths,$cells,$aligns=[],$lineH=5): void {
        $nb=1;
        foreach($cells as $i=>$c){ $lines=$this->NbLines($widths[$i],rp_text($c)); if($lines>$nb)$nb=$lines; }
        if($this->GetY()+$nb*$lineH>270){$this->AddPage();}
        $y=$this->GetY();
        foreach($cells as $i=>$c){$w=$widths[$i];$x=$this->GetX();$a=$aligns[$i]??'L';$this->Rect($x,$y,$w,$nb*$lineH);$this->MultiCell($w,$lineH,rp_text($c),0,$a);$this->SetXY($x+$w,$y);}
        $this->Ln($nb*$lineH);
    }
    function NbLines($w,$txt): int {
        $cw=$this->CurrentFont['cw']; if($w===0)$w=$this->w-$this->rMargin-$this->x; $w=($w-2*$this->cMargin)*1000/$this->FontSize; $s=str_replace("\r",'',$txt); $nb=strlen($s); if($nb>0 && $s[$nb-1]=="\n")$nb--; $sep=-1;$i=0;$j=0;$l=0;$nl=1;
        while($i<$nb){$c=$s[$i];if($c=="\n"){$i++;$sep=-1;$j=$i;$l=0;$nl++;continue;}if($c==' ')$sep=$i;$l+= $cw[$c]??600;if($l>$w){if($sep==-1){if($i==$j)$i++;}else $i=$sep+1;$sep=-1;$j=$i;$l=0;$nl++;}else $i++;}return $nl;
    }
    function KPI($x,$y,$w,$label,$value): void {
        $this->SetFillColor(248,248,248);$this->SetDrawColor(225,225,225);$this->RoundedRect($x,$y,$w,23,3,'DF');
        $this->SetXY($x+4,$y+3);$this->SetFont('Arial','',7);$this->SetTextColor(107,114,128);$this->Cell($w-8,4,rp_text($label),0,1);
        $this->SetX($x+4);$this->SetFont('Arial','B',11);$this->SetTextColor(31,41,55);$this->Cell($w-8,7,rp_text($value),0,1);
    }
    function RoundedRect($x,$y,$w,$h,$r,$style=''): void {
        $k=$this->k;$hp=$this->h; $op=($style==='F'?'f':($style==='FD'||$style==='DF'?'B':'S'));$MyArc=4/3*(sqrt(2)-1);
        $this->_out(sprintf('%.2F %.2F m',($x+$r)*$k,($hp-$y)*$k));$this->_out(sprintf('%.2F %.2F l',($x+$w-$r)*$k,($hp-$y)*$k));$this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',($x+$w-$r+$r*$MyArc)*$k,($hp-$y)*$k,($x+$w)*$k,($hp-$y+$r-$r*$MyArc)*$k,($x+$w)*$k,($hp-$y+$r)*$k));$this->_out(sprintf('%.2F %.2F l',($x+$w)*$k,($hp-($y+$h-$r))*$k));$this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',($x+$w)*$k,($hp-($y+$h-$r+$r*$MyArc))*$k,($x+$w-$r+$r*$MyArc)*$k,($hp-($y+$h))*$k,($x+$w-$r)*$k,($hp-($y+$h))*$k));$this->_out(sprintf('%.2F %.2F l',($x+$r)*$k,($hp-($y+$h))*$k));$this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',($x+$r-$r*$MyArc)*$k,($hp-($y+$h))*$k,$x*$k,($hp-($y+$h-$r+$r*$MyArc))*$k,$x*$k,($hp-($y+$h-$r))*$k));$this->_out(sprintf('%.2F %.2F l',$x*$k,($hp-($y+$r))*$k));$this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',$x*$k,($hp-($y+$r-$r*$MyArc))*$k,($x+$r-$r*$MyArc)*$k,($hp-$y)*$k,($x+$r)*$k,($hp-$y)*$k));$this->_out($op);
    }
}

$pdf=new RGReportPDF('P','mm','A4');
$pdf->SetMargins(16,32,16);$pdf->SetAutoPageBreak(true,18);$pdf->AliasNbPages();$pdf->AddPage();

// Cover / executive summary
$pdf->SetY(40);$pdf->SetFont('Arial','B',22);$pdf->SetTextColor(31,41,55);$pdf->MultiCell(0,10,rp_text('PROJECT REPORTING PACK'));
$pdf->SetFont('Arial','B',16);$pdf->SetTextColor(183,121,31);$pdf->MultiCell(0,8,rp_text($project['name']));
$pdf->SetFont('Arial','',10);$pdf->SetTextColor(107,114,128);$pdf->MultiCell(0,6,rp_text('Reporting period: '.$reportPeriod));$pdf->Ln(7);
$pdf->SetDrawColor(183,121,31);$pdf->SetLineWidth(1);$pdf->Line(16,$pdf->GetY(),194,$pdf->GetY());$pdf->Ln(8);
$pdf->LabelValue('CLIENT',$project['company_name'].' - '.$project['contact_person'],16,$pdf->GetY(),82);
$pdf->LabelValue('PROJECT MANAGER',$project['pm_name']??'Not assigned',105,$pdf->GetY(),82);
$pdf->SetY(76);
$pdf->LabelValue('PROJECT TYPE',$project['build_type']??'Construction / Civil Engineering',16,$pdf->GetY(),82);
$pdf->LabelValue('LOCATION',$project['location']??'Not specified',105,$pdf->GetY(),82);
$pdf->SetY(105);$pdf->Section('Executive project position');
$pdf->KPI(16,117,42,'Overall progress',number_format((float)$project['progress_percent'],1).'%');
$pdf->KPI(62,117,42,'Approved budget',rp_money($project['budget']));
$pdf->KPI(108,117,42,'Actual cost',rp_money($actual));
$pdf->KPI(154,117,40,'Open issues',count(array_filter($issues,fn($r)=>!in_array($r['status'],['closed'],true))));
$pdf->SetY(149);$pdf->Section('Financial position');
$pdf->Row([88,90],['Measure','Position'],['L','L']);
$pdf->Row([88,90],['Committed costs',rp_money($committed)],['L','R']);
$pdf->Row([88,90],['Actual costs',rp_money($actual)],['L','R']);
$pdf->Row([88,90],['Remaining / (over) budget',rp_money(abs($variance)).($variance>=0?' remaining':' over')],['L','R']);
$pdf->Row([88,90],['Cashflow burn vs plan',number_format($burn,1).'%'],['L','R']);
$pdf->Row([88,90],['Invoiced / Paid',rp_money($invoiced).' / '.rp_money($paid)],['L','R']);
$pdf->Ln(5);$pdf->SetFont('Arial','',8);$pdf->SetTextColor(107,114,128);$pdf->MultiCell(0,4.5,rp_text('This pack consolidates the project programme, financial position, commercial dashboard, progress meeting minutes, activity schedule, material tracking and issues requiring client attention.'));

// Programme
$pdf->AddPage();$pdf->Title('1. Programme','Baseline and forecast dates used to monitor progress against the approved programme.');
$pdf->TableHeader([48,30,30,30,20,20],['Activity','Baseline finish','Forecast finish','Actual finish','Progress','Status']);
foreach($programme as $r){$pdf->Row([48,30,30,30,20,20],[$r['activity'],rp_date($r['baseline_finish']),rp_date($r['forecast_finish']),rp_date($r['actual_finish']),number_format((float)$r['percent_complete'],0).'%',rp_status($r['status'])],['L','C','C','C','R','C']);}
if(!$programme){$pdf->SetFont('Arial','I',8);$pdf->Cell(0,7,rp_text('No programme activities have been entered.'),0,1);}

// Cost report
$pdf->AddPage();$pdf->Title('2. Cost Report','Recorded commitments and actual expenditure against the project budget.');
$pdf->TableHeader([24,28,57,34,35],['Date','Category','Description / Supplier','Committed','Actual']);
foreach($costs as $r){$desc=$r['description']??'';if(!empty($r['supplier']))$desc.=' | '.$r['supplier'];$pdf->Row([24,28,57,34,35],[rp_date($r['cost_date']),$r['category'],$desc,rp_money($r['committed_amount']),rp_money($r['actual_amount'])],['C','L','L','R','R']);}
if(!$costs)$pdf->Cell(0,7,rp_text('No cost report entries have been entered.'),0,1);
$pdf->Ln(3);$pdf->SetFont('Arial','B',9);$pdf->Cell(0,6,rp_text('Cost summary: Committed '.rp_money($committed).' | Actual '.rp_money($actual).' | Budget '.rp_money($project['budget'])),0,1);

// Cashflow dashboard
$pdf->AddPage();$pdf->Title('3. Commercial Dashboard & Cashflow','The cashflow comparison highlights whether actual expenditure is tracking ahead of or behind the planned burn rate.');
$pdf->KPI(16,50,55,'Approved budget',rp_money($project['budget']));$pdf->KPI(75,50,55,'Committed',rp_money($committed));$pdf->KPI(134,50,60,'Actual cost',rp_money($actual));
$pdf->SetY(80);$pdf->Section('Cashflow table');$pdf->TableHeader([38,46,46,48],['Month','Planned','Actual','Variance']);
foreach($cashflow as $r){$v=(float)$r['planned_expenditure']-(float)$r['actual_expenditure'];$pdf->Row([38,46,46,48],[date('M Y',strtotime($r['period_month'])),rp_money($r['planned_expenditure']),rp_money($r['actual_expenditure']),rp_money($v)],['C','R','R','R']);}
if(!$cashflow)$pdf->Cell(0,7,rp_text('No monthly cashflow entries have been entered.'),0,1);
$pdf->Ln(5);$pdf->Section('Burn-rate interpretation');
$pdf->SetFont('Arial','',9);$pdf->SetTextColor(31,41,55);$message=$burn>105?'Actual expenditure is running materially ahead of the planned cashflow. Review commitments, programme changes and mitigation actions.':($burn>100?'Actual expenditure is slightly ahead of planned cashflow and should be monitored closely.':'Actual expenditure is at or below the cumulative planned cashflow position.');$pdf->MultiCell(0,5,rp_text($message));

// Meetings
$pdf->AddPage();$pdf->Title('4. Progress Meeting Minutes','Project progress meetings provide the main forum for team and client updates, actions and decisions.');
foreach($meetings as $m){$pdf->Section($m['meeting_type'].' - '.rp_date($m['meeting_date']));$pdf->SetFont('Arial','B',8);$pdf->Cell(25,5,'Attendees',0,0);$pdf->SetFont('Arial','',8);$pdf->MultiCell(0,5,rp_text($m['attendees']??'-'));$pdf->SetFont('Arial','B',8);$pdf->Cell(25,5,'Agenda',0,0);$pdf->SetFont('Arial','',8);$pdf->MultiCell(0,5,rp_text($m['agenda']??'-'));$pdf->SetFont('Arial','B',8);$pdf->Cell(25,5,'Minutes',0,0);$pdf->SetFont('Arial','',8);$pdf->MultiCell(0,5,rp_text($m['minutes']??'-'));if(!empty($m['client_action_required'])){$pdf->SetFillColor(255,247,230);$pdf->SetFont('Arial','B',8);$pdf->Cell(25,6,'Client action',0,0,'L',true);$pdf->SetFont('Arial','',8);$pdf->MultiCell(0,6,rp_text($m['client_action_required']),0,'L',true);}$pdf->Ln(3);}
if(!$meetings)$pdf->Cell(0,7,rp_text('No meeting minutes have been recorded.'),0,1);

// Activities/materials
$pdf->AddPage();$pdf->Title('5. Activity Schedule & Material Tracking','Operational tracking of planned activities and construction materials.');
$pdf->Section('Activity schedule');$pdf->TableHeader([76,35,35,32],['Activity','Planned','Actual','Status']);
foreach($activities as $r)$pdf->Row([76,35,35,32],[$r['activity'],rp_date($r['planned_date']),rp_date($r['actual_date']),rp_status($r['status'])],['L','C','C','C']);
if(!$activities)$pdf->Cell(0,7,rp_text('No activity schedule entries have been entered.'),0,1);
$pdf->Ln(5);$pdf->Section('Material tracking register');$pdf->TableHeader([49,25,25,25,25,29],['Material','Required','Ordered','Delivered','Shortage','Status']);
foreach($materials as $r)$pdf->Row([49,25,25,25,25,29],[$r['material_name'],(float)$r['required_qty'].' '.$r['unit'],(float)$r['ordered_qty'],(float)$r['delivered_qty'],(float)$r['shortage_qty'],rp_status($r['status'])],['L','R','R','R','R','C']);
if(!$materials)$pdf->Cell(0,7,rp_text('No material tracking entries have been entered.'),0,1);

// Issues
$pdf->AddPage();$pdf->Title('6. Issues, Mitigation & Client Decisions','Issues are assessed for programme, cost and delivery impact before escalation where a client decision is required.');
foreach($issues as $r){$pdf->Section($r['title'].' - '.rp_status($r['severity']));$pdf->SetFont('Arial','',8);$pdf->MultiCell(0,4.5,rp_text('Type: '.rp_status($r['issue_type']).' | Status: '.rp_status($r['status']).' | Reported: '.rp_date($r['created_at'])));$pdf->Ln(1);$pdf->SetFont('Arial','B',8);$pdf->Cell(38,5,'Description / impact',0,0);$pdf->SetFont('Arial','',8);$pdf->MultiCell(0,5,rp_text(($r['description']??'').' Programme impact: '.(int)$r['impact_programme_days'].' day(s). Cost impact: '.rp_money($r['impact_cost'])));if($r['mitigation']){$pdf->SetFont('Arial','B',8);$pdf->Cell(38,5,'Mitigation',0,0);$pdf->SetFont('Arial','',8);$pdf->MultiCell(0,5,rp_text($r['mitigation']));}if($r['client_decision']){$pdf->SetFont('Arial','B',8);$pdf->Cell(38,5,'Client decision',0,0);$pdf->SetFont('Arial','',8);$pdf->MultiCell(0,5,rp_text($r['client_decision']));}$pdf->Ln(3);}
if(!$issues)$pdf->Cell(0,7,rp_text('No project issues have been recorded.'),0,1);

// Sign-off
$pdf->AddPage();$pdf->Title('7. Client Reporting & Sign-off','This section provides a simple record that the reporting pack was issued for review at the project progress meeting.');
$pdf->SetFont('Arial','',9);$pdf->MultiCell(0,5,rp_text('RG Trade and Projects issues project programme and financial reporting to the client on a monthly basis. Meeting minutes, commercial dashboards and supporting project controls are included in this pack. Urgent matters requiring client attention should also be communicated by email and recorded in the project issue register.'));
$pdf->Ln(12);$pdf->Section('Client acknowledgement');
$pdf->LabelValue('Client / Organisation',$project['company_name'],16,$pdf->GetY(),82);$pdf->LabelValue('Reporting period',$reportPeriod,105,$pdf->GetY(),82);$pdf->SetY(100);
$pdf->Line(16,125,95,125);$pdf->Line(105,125,194,125);$pdf->SetY(127);$pdf->SetFont('Arial','',8);$pdf->Cell(79,5,'Client representative signature / acknowledgement',0,0);$pdf->Cell(10,5,'',0,0);$pdf->Cell(89,5,'Date',0,1);
$pdf->Ln(25);$pdf->Line(16,165,95,165);$pdf->Line(105,165,194,165);$pdf->SetY(167);$pdf->Cell(79,5,'RG Trade and Projects representative',0,0);$pdf->Cell(10,5,'',0,0);$pdf->Cell(89,5,'Date',0,1);
$pdf->Ln(15);$pdf->SetFont('Arial','I',8);$pdf->SetTextColor(107,114,128);$pdf->MultiCell(0,4.5,rp_text('Prepared by RG PMS. This report is generated from the project control information recorded in the system at the time of generation.'));

$filename=preg_replace('/[^A-Za-z0-9_-]+/','-',(string)$project['name']).'-Reporting-Pack-'.date('Y-m-d').'.pdf';
$pdf->Output('D',$filename);exit;
