<?php
/**
 * RG PMS - Client project controls / reporting pack.
 * Read-only view of meetings, programme, cost report, cashflow,
 * commercial dashboard, activity schedule, material tracking and issues.
 */
require_once __DIR__ . '/../includes/layout.php';
require_role('client');
$user=current_user(); $pdo=db(); $id=(int)($_GET['id']??0);

$cstmt=$pdo->prepare('SELECT id FROM clients WHERE user_id=?'); $cstmt->execute([$user['id']]);
$clientIds=array_map(fn($r)=>(int)$r['id'],$cstmt->fetchAll());
if(!$clientIds){http_response_code(403);die('403 - No client access');}
$in=implode(',',array_fill(0,count($clientIds),'?'));
$stmt=$pdo->prepare("SELECT p.*, c.company_name, c.contact_person, u.full_name AS pm_name FROM projects p JOIN clients c ON c.id=p.client_id LEFT JOIN users u ON u.id=p.project_manager_id WHERE p.id=? AND p.client_id IN ($in)");
$stmt->execute(array_merge([$id],$clientIds)); $project=$stmt->fetch();
if(!$project){flash_set('danger','Project not found.');redirect('client/dashboard.php');}

$tab=$_GET['tab']??'dashboard';
$valid=['dashboard','meetings','programme','costs','cashflow','activities','materials','issues'];
if(!in_array($tab,$valid,true))$tab='dashboard';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'client_decision') {
        $issueId = (int)($_POST['issue_id'] ?? 0);
        $decision = trim($_POST['client_decision'] ?? '');
        if ($issueId > 0 && $decision !== '') {
            $u = $pdo->prepare('UPDATE project_issues SET client_decision=?, status=?, resolved_at=CASE WHEN ?="closed" THEN NOW() ELSE resolved_at END WHERE id=? AND project_id=? AND escalated_to_client=1');
            $newStatus = $_POST['decision_status'] ?? 'under_review';
            if (!in_array($newStatus, ['under_review','mitigated','closed'], true)) $newStatus='under_review';
            $u->execute([$decision, $newStatus, $newStatus, $issueId, $id]);
            if ($u->rowCount()) {
                if (!empty($project['project_manager_id'])) {
                    notify((int)$project['project_manager_id'], 'Client decision received', 'The client has responded to an escalated issue on "' . $project['name'] . '".', 'admin/project_controls.php?id='.$id.'&tab=issues');
                }
                flash_set('success','Your decision has been recorded and the project team has been notified.');
            }
        }
        redirect('client/project_controls.php?id='.$id.'&tab=issues');
    }
}

$q=$pdo->prepare('SELECT * FROM project_meetings WHERE project_id=? ORDER BY meeting_date DESC');$q->execute([$id]);$meetings=$q->fetchAll();
$q=$pdo->prepare('SELECT * FROM project_programme WHERE project_id=? ORDER BY COALESCE(baseline_start,forecast_start),id');$q->execute([$id]);$programme=$q->fetchAll();
$q=$pdo->prepare('SELECT * FROM project_costs WHERE project_id=? ORDER BY cost_date DESC,id DESC');$q->execute([$id]);$costs=$q->fetchAll();
$q=$pdo->prepare('SELECT * FROM project_cashflow WHERE project_id=? ORDER BY period_month');$q->execute([$id]);$cashflow=$q->fetchAll();
$q=$pdo->prepare('SELECT * FROM activity_schedules WHERE project_id=? ORDER BY COALESCE(planned_date,actual_date),id');$q->execute([$id]);$activities=$q->fetchAll();
$q=$pdo->prepare('SELECT mt.*,m.name AS material_name,m.unit FROM material_tracking mt JOIN materials m ON m.id=mt.material_id WHERE mt.project_id=? ORDER BY mt.updated_at DESC');$q->execute([$id]);$materials=$q->fetchAll();
$q=$pdo->prepare('SELECT pi.*,u.full_name AS reporter FROM project_issues pi LEFT JOIN users u ON u.id=pi.reported_by WHERE pi.project_id=? AND pi.escalated_to_client=1 ORDER BY pi.created_at DESC');$q->execute([$id]);$issues=$q->fetchAll();

$committed=array_sum(array_map(fn($r)=>(float)$r['committed_amount'],$costs));
$actual=array_sum(array_map(fn($r)=>(float)$r['actual_amount'],$costs));
$planned=array_sum(array_map(fn($r)=>(float)$r['planned_expenditure'],$cashflow));
$actualCash=array_sum(array_map(fn($r)=>(float)$r['actual_expenditure'],$cashflow));
$invoices=$pdo->prepare('SELECT * FROM invoices WHERE project_id=? ORDER BY issue_date DESC');$invoices->execute([$id]);$invoiceList=$invoices->fetchAll();
$invoiced=array_sum(array_map(fn($r)=>(float)$r['amount'],$invoiceList));
$paid=array_sum(array_map(fn($r)=>(float)$r['amount'],array_filter($invoiceList,fn($r)=>$r['status']==='paid')));
$burn=$planned>0?($actualCash/$planned)*100:0;

page_header('Project reports: '.$project['name'],'dashboard');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
<div><a class="small text-decoration-none" href="<?= BASE_URL ?>/client/project_view.php?id=<?= $id ?>"><i class="bi bi-arrow-left me-1"></i>Back to project</a><h4 class="fw-semibold mb-0 mt-1"><i class="bi bi-file-earmark-bar-graph text-warning me-2"></i>Project reporting pack</h4><div class="text-muted small"><?= e($project['name']) ?> · Project Manager: <?= e($project['pm_name']??'-') ?></div></div>
</div>
<div class="alert alert-light border small"><i class="bi bi-info-circle me-1 text-warning"></i>This portal brings together the programme, monthly financial position, commercial dashboard, meeting minutes, activity schedule and material tracking information issued as part of project reporting.</div>
<ul class="nav nav-tabs flex-wrap mb-4">
<?php $labels=['dashboard'=>['Dashboard','bi-speedometer2'],'meetings'=>['Meeting minutes','bi-people'],'programme'=>['Programme','bi-calendar3'],'costs'=>['Cost report','bi-cash-stack'],'cashflow'=>['Cashflow','bi-graph-up-arrow'],'activities'=>['Activity schedule','bi-list-check'],'materials'=>['Material tracking','bi-box-seam'],'issues'=>['Client decisions','bi-exclamation-triangle']]; foreach($labels as $k=>$v): ?><li class="nav-item"><a class="nav-link <?= $tab===$k?'active':'' ?>" href="?id=<?= $id ?>&tab=<?= $k ?>"><i class="bi <?= $v[1] ?> me-1"></i><?= $v[0] ?></a></li><?php endforeach; ?>
</ul>

<?php if($tab==='dashboard'): ?>
<div class="row g-3 mb-4">
<?php foreach([['Overall progress',(float)$project['progress_percent'].'%','bi-graph-up'],['Approved budget',money($project['budget']),'bi-wallet2'],['Actual cost',money($actual),'bi-cash-stack'],['Cash burn vs plan',number_format($burn,1).'%', 'bi-bar-chart-line']] as $x): ?><div class="col-6 col-lg-3"><div class="card stat-tile shadow-sm h-100"><div class="card-body"><div class="value"><?= e($x[1]) ?></div><div class="label"><i class="bi <?= $x[2] ?> me-1"></i><?= e($x[0]) ?></div></div></div></div><?php endforeach; ?>
</div>
<div class="row g-4">
<div class="col-lg-7"><div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4"><h6 class="fw-semibold mb-3">Cumulative expenditure</h6>
<?php if(!$cashflow): ?><p class="small text-muted mb-0">No cashflow data has been issued yet.</p><?php else:$max=max(1,$planned,$actualCash);$w=620;$h=230;$pad=38;$n=count($cashflow);$pp=[];$pa=[];$cp=0;$ca=0;foreach($cashflow as $i=>$cf){$cp+=(float)$cf['planned_expenditure'];$ca+=(float)$cf['actual_expenditure'];$x=$pad+($n===1?0:($w-2*$pad)*$i/($n-1));$yp=$h-$pad-($cp/$max)*($h-2*$pad);$ya=$h-$pad-($ca/$max)*($h-2*$pad);$pp[]=round($x,1).','.round($yp,1);$pa[]=round($x,1).','.round($ya,1);} ?>
<svg class="w-100" viewBox="0 0 <?= $w ?> <?= $h ?>" aria-label="Cumulative planned versus actual expenditure"><line x1="<?= $pad ?>" y1="<?= $h-$pad ?>" x2="<?= $w-$pad ?>" y2="<?= $h-$pad ?>" stroke="#adb5bd"/><line x1="<?= $pad ?>" y1="<?= $pad ?>" x2="<?= $pad ?>" y2="<?= $h-$pad ?>" stroke="#adb5bd"/><polyline fill="none" stroke="#b7791f" stroke-width="4" points="<?= implode(' ',$pp) ?>"/><polyline fill="none" stroke="#198754" stroke-width="4" points="<?= implode(' ',$pa) ?>"/><?php foreach($cashflow as $i=>$cf):$x=$pad+($n===1?0:($w-2*$pad)*$i/($n-1));?><text x="<?= $x ?>" y="<?= $h-10 ?>" font-size="11" text-anchor="middle"><?= e(date('M y',strtotime($cf['period_month']))) ?></text><?php endforeach;?></svg>
<div class="small d-flex gap-4 mt-2"><span><i class="bi bi-dash-lg text-warning"></i> Planned</span><span><i class="bi bi-dash-lg text-success"></i> Actual</span></div><?php endif;?>
</div></div></div>
<div class="col-lg-5"><div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4"><h6 class="fw-semibold mb-3">Commercial dashboard</h6>
<div class="d-flex justify-content-between py-2 border-bottom small"><span>Approved budget</span><strong><?= money($project['budget']) ?></strong></div><div class="d-flex justify-content-between py-2 border-bottom small"><span>Committed</span><strong><?= money($committed) ?></strong></div><div class="d-flex justify-content-between py-2 border-bottom small"><span>Actual cost</span><strong><?= money($actual) ?></strong></div><div class="d-flex justify-content-between py-2 border-bottom small"><span>Invoiced</span><strong><?= money($invoiced) ?></strong></div><div class="d-flex justify-content-between py-2 small"><span>Paid</span><strong><?= money($paid) ?></strong></div>
<?php $variance=(float)$project['budget']-$actual; ?><div class="alert <?= $variance>=0?'alert-success':'alert-danger' ?> small mt-3 mb-0"><strong><?= $variance>=0?'Remaining budget':'Over budget' ?>:</strong> <?= money(abs($variance)) ?></div>
</div></div></div></div>

<?php elseif($tab==='meetings'): ?><div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4"><h6 class="fw-semibold mb-3">Progress meeting minutes</h6><?php foreach($meetings as $m): ?><div class="border-bottom pb-3 mb-3"><div class="d-flex justify-content-between"><strong><?= e($m['meeting_type']) ?></strong><span class="small text-muted"><?= nice_datetime($m['meeting_date']) ?></span></div><div class="small mt-2"><strong>Attendees:</strong> <?= e($m['attendees']??'-') ?></div><div class="small mt-1"><?= nl2br(e($m['minutes'])) ?></div><?php if($m['client_action_required']): ?><div class="alert alert-warning small mt-2 mb-0"><strong>Action / decision required:</strong> <?= nl2br(e($m['client_action_required'])) ?></div><?php endif;?></div><?php endforeach;if(!$meetings):?><p class="small text-muted mb-0">No meeting minutes have been issued yet.</p><?php endif;?></div></div>

<?php elseif($tab==='programme'): ?><div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4"><h6 class="fw-semibold mb-3">Programme - baseline vs forecast</h6><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Activity</th><th>Baseline</th><th>Forecast</th><th>Actual</th><th>Progress</th><th>Status</th></tr></thead><tbody><?php foreach($programme as $r):?><tr><td><?= e($r['activity']) ?><div class="small text-muted"><?= e($r['notes']??'') ?></div></td><td><?= nice_date($r['baseline_start']) ?> – <?= nice_date($r['baseline_finish']) ?></td><td><?= nice_date($r['forecast_start']) ?> – <?= nice_date($r['forecast_finish']) ?></td><td><?= nice_date($r['actual_start']) ?> – <?= nice_date($r['actual_finish']) ?></td><td style="min-width:100px"><div class="progress"><div class="progress-bar" style="width:<?= (float)$r['percent_complete'] ?>%"></div></div><span class="small"><?= (float)$r['percent_complete'] ?>%</span></td><td><?= badge(ucwords(str_replace('_',' ',$r['status'])),$r['status']==='delayed'?'danger':($r['status']==='complete'?'success':'primary')) ?></td></tr><?php endforeach;if(!$programme):?><tr><td colspan="6" class="text-muted small">No programme has been issued yet.</td></tr><?php endif;?></tbody></table></div></div></div>

<?php elseif($tab==='costs'): ?><div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4"><h6 class="fw-semibold mb-3">Cost report</h6><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Date</th><th>Category</th><th>Description</th><th class="text-end">Committed</th><th class="text-end">Actual</th></tr></thead><tbody><?php foreach($costs as $r):?><tr><td><?= nice_date($r['cost_date']) ?></td><td><?= e($r['category']) ?></td><td><?= e($r['description']??'-') ?></td><td class="text-end"><?= money($r['committed_amount']) ?></td><td class="text-end"><?= money($r['actual_amount']) ?></td></tr><?php endforeach;if(!$costs):?><tr><td colspan="5" class="text-muted small">No cost report entries issued.</td></tr><?php endif;?></tbody></table></div><div class="small mt-2">Committed: <strong><?= money($committed) ?></strong> · Actual: <strong><?= money($actual) ?></strong></div></div></div>

<?php elseif($tab==='cashflow'): ?><div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4"><h6 class="fw-semibold mb-3">Monthly cashflow</h6><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Month</th><th class="text-end">Planned</th><th class="text-end">Actual</th><th class="text-end">Variance</th></tr></thead><tbody><?php foreach($cashflow as $r):$v=(float)$r['planned_expenditure']-(float)$r['actual_expenditure'];?><tr><td><?= nice_date($r['period_month']) ?></td><td class="text-end"><?= money($r['planned_expenditure']) ?></td><td class="text-end"><?= money($r['actual_expenditure']) ?></td><td class="text-end <?= $v<0?'text-danger':'text-success' ?>"><?= money($v) ?></td></tr><?php endforeach;if(!$cashflow):?><tr><td colspan="4" class="text-muted small">No cashflow entries issued.</td></tr><?php endif;?></tbody></table></div><p class="small text-muted mb-0">The burn-rate comparison helps identify when actual expenditure is running ahead of the planned cashflow.</p></div></div>

<?php elseif($tab==='activities'): ?><div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4"><h6 class="fw-semibold mb-3">Activity schedule</h6><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Activity</th><th>Planned</th><th>Actual</th><th>Status</th></tr></thead><tbody><?php foreach($activities as $r):?><tr><td><?= e($r['activity']) ?><div class="small text-muted"><?= e($r['notes']??'') ?></div></td><td><?= nice_date($r['planned_date']) ?></td><td><?= nice_date($r['actual_date']) ?></td><td><?= badge(ucwords(str_replace('_',' ',$r['status'])),$r['status']==='delayed'?'danger':($r['status']==='complete'?'success':'primary')) ?></td></tr><?php endforeach;if(!$activities):?><tr><td colspan="4" class="text-muted small">No activity schedule entries issued.</td></tr><?php endif;?></tbody></table></div></div></div>

<?php elseif($tab==='materials'): ?><div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4"><h6 class="fw-semibold mb-3">Material tracking register</h6><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Material</th><th>Required</th><th>Ordered</th><th>Delivered</th><th>Used</th><th>Shortage</th><th>Status</th></tr></thead><tbody><?php foreach($materials as $r):?><tr><td><?= e($r['material_name']) ?><div class="small text-muted"><?= $r['expected_delivery']? 'Expected '.nice_date($r['expected_delivery']):'' ?></div></td><td><?= (float)$r['required_qty'] ?> <?= e($r['unit']) ?></td><td><?= (float)$r['ordered_qty'] ?></td><td><?= (float)$r['delivered_qty'] ?></td><td><?= (float)$r['used_qty'] ?></td><td class="<?= (float)$r['shortage_qty']>0?'text-danger fw-semibold':'' ?>"><?= (float)$r['shortage_qty'] ?></td><td><?= badge(ucwords(str_replace('_',' ',$r['status'])),$r['status']==='shortage'?'danger':($r['status']==='delivered'?'success':'primary')) ?></td></tr><?php endforeach;if(!$materials):?><tr><td colspan="7" class="text-muted small">No material tracking entries issued.</td></tr><?php endif;?></tbody></table></div></div></div>

<?php elseif($tab==='issues'): ?><div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4"><h6 class="fw-semibold mb-3">Issues escalated for client attention</h6><?php foreach($issues as $r):?><div class="border-bottom pb-3 mb-3"><div class="d-flex justify-content-between"><strong><?= e($r['title']) ?></strong><?= badge(ucfirst($r['severity']),in_array($r['severity'],['high','critical'],true)?'danger':'warning') ?></div><div class="small text-muted"><?= ucwords(str_replace('_',' ',$r['issue_type'])) ?> · <?= nice_datetime($r['created_at']) ?></div><p class="small mt-2 mb-1"><?= nl2br(e($r['description']??'')) ?></p><div class="small"><strong>Programme impact:</strong> <?= (int)$r['impact_programme_days'] ?> day(s) · <strong>Cost impact:</strong> <?= money($r['impact_cost']) ?></div><?php if($r['mitigation']):?><div class="small mt-1"><strong>Mitigation:</strong> <?= nl2br(e($r['mitigation'])) ?></div><?php endif;?><?php if($r['client_decision']):?><div class="alert alert-light small mt-2 mb-0"><strong>Client decision:</strong> <?= nl2br(e($r['client_decision'])) ?></div><?php else:?><div class="alert alert-warning small mt-2 mb-2">Client decision / acknowledgement pending.</div>
<form method="post" class="mt-2"><?= csrf_field() ?><input type="hidden" name="action" value="client_decision"><input type="hidden" name="issue_id" value="<?= (int)$r['id'] ?>">
<textarea class="form-control form-control-sm mb-2" name="client_decision" rows="2" placeholder="Enter your decision or acknowledgement" required></textarea>
<div class="d-flex gap-2"><select class="form-select form-select-sm" name="decision_status"><option value="under_review">Under review</option><option value="mitigated">Mitigation accepted</option><option value="closed">Close issue</option></select><button class="btn btn-brand btn-sm">Submit decision</button></div></form><?php endif;?></div><?php endforeach;if(!$issues):?><p class="text-muted small mb-0">No issues have been escalated for client attention.</p><?php endif;?></div></div>
<?php endif; ?>
<?php page_footer(); ?>
