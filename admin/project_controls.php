<?php
/**
 * RG PMS - Project controls
 * Programme, cashflow/commercial dashboard, meetings, activity schedule,
 * material tracking and issue/decision management.
 */
require_once __DIR__ . '/../includes/layout.php';
require_role('admin');
$user = current_user();
$pdo = db();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT p.*, c.company_name, c.contact_person, c.email AS client_email, u.full_name AS pm_name
                       FROM projects p JOIN clients c ON c.id=p.client_id
                       LEFT JOIN users u ON u.id=p.project_manager_id WHERE p.id=?');
$stmt->execute([$id]);
$project = $stmt->fetch();
if (!$project) { flash_set('danger','Project not found.'); redirect('admin/projects.php'); }

$tab = $_GET['tab'] ?? 'dashboard';
$validTabs = ['dashboard','meetings','programme','costs','cashflow','activities','materials','issues'];
if (!in_array($tab, $validTabs, true)) $tab='dashboard';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_meeting') {
        $pdo->prepare('INSERT INTO project_meetings (project_id, meeting_date, meeting_type, attendees, agenda, minutes, client_present, client_action_required, created_by)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$id, $_POST['meeting_date'] ?: date('Y-m-d H:i:s'), trim($_POST['meeting_type'] ?: 'Progress meeting'),
                trim($_POST['attendees'] ?? ''), trim($_POST['agenda'] ?? ''), trim($_POST['minutes'] ?? ''),
                isset($_POST['client_present']) ? 1 : 0, trim($_POST['client_action_required'] ?? ''), $user['id']]);
        notify_client_of_project($id, 'New project meeting minutes', 'Minutes from the latest progress meeting for "' . $project['name'] . '" are available.', 'client/project_controls.php?id='.$id.'&tab=meetings');
        flash_set('success','Meeting minutes recorded and client notified.');
        redirect('admin/project_controls.php?id='.$id.'&tab=meetings');

    } elseif ($action === 'add_programme') {
        $pdo->prepare('INSERT INTO project_programme (project_id, activity, baseline_start, baseline_finish, forecast_start, forecast_finish, actual_start, actual_finish, percent_complete, status, notes)
                       VALUES (?, ?, NULLIF(?, ""), NULLIF(?, ""), NULLIF(?, ""), NULLIF(?, ""), NULLIF(?, ""), NULLIF(?, ""), ?, ?, ?)')
            ->execute([$id, trim($_POST['activity']), $_POST['baseline_start'] ?? '', $_POST['baseline_finish'] ?? '', $_POST['forecast_start'] ?? '', $_POST['forecast_finish'] ?? '',
                $_POST['actual_start'] ?? '', $_POST['actual_finish'] ?? '', max(0,min(100,(float)($_POST['percent_complete'] ?? 0))),
                $_POST['status'] ?? 'not_started', trim($_POST['notes'] ?? '')]);
        notify_client_of_project($id, 'Project programme updated', 'The programme for "' . $project['name'] . '" has been updated.', 'client/project_controls.php?id='.$id.'&tab=programme');
        flash_set('success','Programme activity added.');
        redirect('admin/project_controls.php?id='.$id.'&tab=programme');

    } elseif ($action === 'add_cost') {
        $pdo->prepare('INSERT INTO project_costs (project_id, cost_date, category, description, supplier, reference_no, committed_amount, actual_amount, created_by)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$id, $_POST['cost_date'] ?: date('Y-m-d'), trim($_POST['category']), trim($_POST['description'] ?? ''),
                trim($_POST['supplier'] ?? ''), trim($_POST['reference_no'] ?? ''), (float)($_POST['committed_amount'] ?? 0),
                (float)($_POST['actual_amount'] ?? 0), $user['id']]);
        flash_set('success','Cost report entry added.');
        redirect('admin/project_controls.php?id='.$id.'&tab=costs');

    } elseif ($action === 'add_cashflow') {
        $month = ($_POST['period_month'] ?? date('Y-m')) . '-01';
        $pdo->prepare('INSERT INTO project_cashflow (project_id, period_month, planned_expenditure, actual_expenditure, notes, created_by)
                       VALUES (?, ?, ?, ?, ?, ?)
                       ON DUPLICATE KEY UPDATE planned_expenditure=VALUES(planned_expenditure), actual_expenditure=VALUES(actual_expenditure), notes=VALUES(notes), created_by=VALUES(created_by)')
            ->execute([$id, $month, (float)($_POST['planned_expenditure'] ?? 0), (float)($_POST['actual_expenditure'] ?? 0), trim($_POST['notes'] ?? ''), $user['id']]);
        flash_set('success','Cashflow month saved.');
        redirect('admin/project_controls.php?id='.$id.'&tab=cashflow');

    } elseif ($action === 'add_activity') {
        $pdo->prepare('INSERT INTO activity_schedules (project_id, activity, planned_date, actual_date, status, notes)
                       VALUES (?, ?, NULLIF(?, ""), NULLIF(?, ""), ?, ?)')
            ->execute([$id, trim($_POST['activity']), $_POST['planned_date'] ?? '', $_POST['actual_date'] ?? '', $_POST['status'] ?? 'planned', trim($_POST['notes'] ?? '')]);
        flash_set('success','Activity schedule entry added.');
        redirect('admin/project_controls.php?id='.$id.'&tab=activities');

    } elseif ($action === 'add_material_tracking') {
        $pdo->prepare('INSERT INTO material_tracking (project_id, material_id, required_qty, ordered_qty, delivered_qty, used_qty, shortage_qty, expected_delivery, status, notes, updated_by)
                       VALUES (?, ?, ?, ?, ?, ?, ?, NULLIF(?, ""), ?, ?, ?)')
            ->execute([$id, (int)$_POST['material_id'], (float)$_POST['required_qty'], (float)$_POST['ordered_qty'], (float)$_POST['delivered_qty'],
                (float)$_POST['used_qty'], (float)$_POST['shortage_qty'], $_POST['expected_delivery'] ?? '', $_POST['status'] ?? 'planned',
                trim($_POST['notes'] ?? ''), $user['id']]);
        flash_set('success','Material tracking register updated.');
        redirect('admin/project_controls.php?id='.$id.'&tab=materials');

    } elseif ($action === 'add_issue') {
        $pdo->prepare('INSERT INTO project_issues (project_id, reported_by, issue_type, severity, title, description, impact_programme_days, impact_cost, status, mitigation, escalated_to_client, client_decision)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$id, $user['id'], $_POST['issue_type'] ?? 'other', $_POST['severity'] ?? 'medium', trim($_POST['title']),
                trim($_POST['description'] ?? ''), (int)($_POST['impact_programme_days'] ?? 0), (float)($_POST['impact_cost'] ?? 0),
                $_POST['status'] ?? 'open', trim($_POST['mitigation'] ?? ''), isset($_POST['escalated_to_client']) ? 1 : 0, trim($_POST['client_decision'] ?? '')]);
        if (!empty($_POST['escalated_to_client'])) {
            notify_client_of_project($id, 'Urgent project issue requires attention', 'An issue has been escalated for your decision on "' . $project['name'] . '".', 'client/project_controls.php?id='.$id.'&tab=issues');
        }
        flash_set('success','Project issue recorded.');
        redirect('admin/project_controls.php?id='.$id.'&tab=issues');
    }
}

$meetings = $pdo->prepare('SELECT pm.*, u.full_name AS author FROM project_meetings pm LEFT JOIN users u ON u.id=pm.created_by WHERE pm.project_id=? ORDER BY pm.meeting_date DESC');
$meetings->execute([$id]); $meetingList=$meetings->fetchAll();

$programme = $pdo->prepare('SELECT * FROM project_programme WHERE project_id=? ORDER BY COALESCE(baseline_start, forecast_start), id');
$programme->execute([$id]); $programmeList=$programme->fetchAll();

$costs = $pdo->prepare('SELECT pc.*, u.full_name AS author FROM project_costs pc LEFT JOIN users u ON u.id=pc.created_by WHERE pc.project_id=? ORDER BY pc.cost_date DESC, pc.id DESC');
$costs->execute([$id]); $costList=$costs->fetchAll();
$committedTotal=array_sum(array_map(fn($r)=>(float)$r['committed_amount'],$costList));
$actualCostTotal=array_sum(array_map(fn($r)=>(float)$r['actual_amount'],$costList));

$cashflow = $pdo->prepare('SELECT * FROM project_cashflow WHERE project_id=? ORDER BY period_month');
$cashflow->execute([$id]); $cashList=$cashflow->fetchAll();
$plannedTotal=array_sum(array_map(fn($r)=>(float)$r['planned_expenditure'],$cashList));
$actualCashTotal=array_sum(array_map(fn($r)=>(float)$r['actual_expenditure'],$cashList));
$burnRatio=$plannedTotal>0 ? ($actualCashTotal/$plannedTotal)*100 : 0;

$activities = $pdo->prepare('SELECT * FROM activity_schedules WHERE project_id=? ORDER BY COALESCE(planned_date, actual_date), id');
$activities->execute([$id]); $activityList=$activities->fetchAll();

$materials = $pdo->query('SELECT * FROM materials ORDER BY name')->fetchAll();
$mt = $pdo->prepare('SELECT mt.*, m.name AS material_name, m.unit FROM material_tracking mt JOIN materials m ON m.id=mt.material_id WHERE mt.project_id=? ORDER BY mt.updated_at DESC');
$mt->execute([$id]); $materialList=$mt->fetchAll();

$issues = $pdo->prepare('SELECT pi.*, u.full_name AS reporter FROM project_issues pi LEFT JOIN users u ON u.id=pi.reported_by WHERE pi.project_id=? ORDER BY pi.created_at DESC');
$issues->execute([$id]); $issueList=$issues->fetchAll();
$openIssues=count(array_filter($issueList,fn($x)=>!in_array($x['status'],['closed','mitigated'],true)));

$invoices=$pdo->prepare('SELECT * FROM invoices WHERE project_id=?');
$invoices->execute([$id]); $invoiceList=$invoices->fetchAll();
$invoicedTotal=array_sum(array_map(fn($i)=>(float)$i['amount'],$invoiceList));
$paidTotal=array_sum(array_map(fn($i)=>(float)$i['amount'],array_filter($invoiceList,fn($i)=>$i['status']==='paid')));

page_header('Project controls: '.$project['name'], 'projects');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <a class="small text-decoration-none" href="<?= BASE_URL ?>/admin/project_view.php?id=<?= $id ?>"><i class="bi bi-arrow-left me-1"></i>Back to project</a>
    <h4 class="fw-semibold mb-0 mt-1"><i class="bi bi-clipboard-data text-warning me-2"></i>Project control centre</h4>
    <div class="text-muted small"><?= e($project['name']) ?> · <?= e($project['company_name']) ?> · Client: <?= e($project['contact_person']) ?></div>
  </div>
  <a class="btn btn-brand btn-sm" target="_blank" href="<?= BASE_URL ?>/client/project_controls.php?id=<?= $id ?>"><i class="bi bi-eye me-1"></i>Preview client view</a>
</div>

<ul class="nav nav-tabs flex-wrap mb-4">
<?php
$labels=['dashboard'=>['Dashboard','bi-speedometer2'],'meetings'=>['Meetings / Minutes','bi-people'],'programme'=>['Programme','bi-calendar3'],'costs'=>['Cost Report','bi-cash-stack'],'cashflow'=>['Cashflow','bi-graph-up-arrow'],'activities'=>['Activity Schedule','bi-list-check'],'materials'=>['Material Tracking','bi-box-seam'],'issues'=>['Issues / Decisions ('.$openIssues.')','bi-exclamation-triangle']];
foreach($labels as $key=>$v): ?><li class="nav-item"><a class="nav-link <?= $tab===$key?'active':'' ?>" href="?id=<?= $id ?>&tab=<?= $key ?>"><i class="bi <?= $v[1] ?> me-1"></i><?= $v[0] ?></a></li><?php endforeach; ?>
</ul>

<?php if ($tab==='dashboard'): ?>
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3"><div class="card stat-tile shadow-sm h-100"><div class="card-body"><div class="value"><?= (float)$project['progress_percent'] ?>%</div><div class="label">Overall progress</div><div class="progress mt-2"><div class="progress-bar bg-warning" style="width:<?= (float)$project['progress_percent'] ?>%"></div></div></div></div></div>
  <div class="col-6 col-lg-3"><div class="card stat-tile shadow-sm h-100"><div class="card-body"><div class="value"><?= money($project['budget']) ?></div><div class="label">Approved budget</div></div></div></div>
  <div class="col-6 col-lg-3"><div class="card stat-tile shadow-sm h-100"><div class="card-body"><div class="value"><?= money($actualCostTotal) ?></div><div class="label">Actual cost captured</div></div></div></div>
  <div class="col-6 col-lg-3"><div class="card stat-tile shadow-sm h-100"><div class="card-body"><div class="value"><?= $openIssues ?></div><div class="label">Open issues</div></div></div></div>
</div>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm rounded-4 mb-4"><div class="card-body p-4">
      <h6 class="fw-semibold mb-3"><i class="bi bi-graph-up-arrow text-warning me-2"></i>Cashflow / burn-rate overview</h6>
      <?php if (!$cashList): ?><p class="text-muted small">No cashflow months have been entered yet.</p><?php else:
        $max=max(1,$plannedTotal,$actualCashTotal); $cumP=0; $cumA=0;
        $w=620; $h=230; $pad=38; $n=count($cashList); $ptsP=[]; $ptsA=[];
        foreach($cashList as $i=>$cf){$cumP+=(float)$cf['planned_expenditure'];$cumA+=(float)$cf['actual_expenditure'];$x=$pad+($n===1?0:($w-2*$pad)*$i/($n-1));$yP=$h-$pad-($cumP/$max)*($h-2*$pad);$yA=$h-$pad-($cumA/$max)*($h-2*$pad);$ptsP[]=round($x,1).','.round($yP,1);$ptsA[]=round($x,1).','.round($yA,1);}
      ?>
      <div class="table-responsive"><svg class="w-100" viewBox="0 0 <?= $w ?> <?= $h ?>" role="img" aria-label="Cumulative planned versus actual expenditure">
        <line x1="<?= $pad ?>" y1="<?= $h-$pad ?>" x2="<?= $w-$pad ?>" y2="<?= $h-$pad ?>" stroke="#adb5bd"/>
        <line x1="<?= $pad ?>" y1="<?= $pad ?>" x2="<?= $pad ?>" y2="<?= $h-$pad ?>" stroke="#adb5bd"/>
        <polyline fill="none" stroke="#b7791f" stroke-width="4" points="<?= implode(' ',$ptsP) ?>"/>
        <polyline fill="none" stroke="#198754" stroke-width="4" points="<?= implode(' ',$ptsA) ?>"/>
        <?php foreach($cashList as $i=>$cf): $x=$pad+($n===1?0:($w-2*$pad)*$i/($n-1)); ?>
          <text x="<?= $x ?>" y="<?= $h-10 ?>" font-size="11" text-anchor="middle"><?= e(date('M y',strtotime($cf['period_month']))) ?></text>
        <?php endforeach; ?>
      </svg></div>
      <div class="small d-flex gap-4 mt-2"><span><i class="bi bi-dash-lg text-warning"></i> Cumulative planned</span><span><i class="bi bi-dash-lg text-success"></i> Cumulative actual</span></div>
      <?php endif; ?>
      <div class="row small mt-3"><div class="col-4"><span class="text-muted">Planned</span><br><strong><?= money($plannedTotal) ?></strong></div><div class="col-4"><span class="text-muted">Actual</span><br><strong><?= money($actualCashTotal) ?></strong></div><div class="col-4"><span class="text-muted">Burn vs plan</span><br><strong><?= number_format($burnRatio,1) ?>%</strong></div></div>
    </div></div>
  </div>
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm rounded-4 mb-4"><div class="card-body p-4">
      <h6 class="fw-semibold mb-3"><i class="bi bi-wallet2 text-warning me-2"></i>Commercial dashboard</h6>
      <div class="d-flex justify-content-between small py-2 border-bottom"><span>Approved budget</span><strong><?= money($project['budget']) ?></strong></div>
      <div class="d-flex justify-content-between small py-2 border-bottom"><span>Committed costs</span><strong><?= money($committedTotal) ?></strong></div>
      <div class="d-flex justify-content-between small py-2 border-bottom"><span>Actual costs</span><strong><?= money($actualCostTotal) ?></strong></div>
      <div class="d-flex justify-content-between small py-2 border-bottom"><span>Invoiced</span><strong><?= money($invoicedTotal) ?></strong></div>
      <div class="d-flex justify-content-between small py-2"><span>Paid</span><strong><?= money($paidTotal) ?></strong></div>
      <?php $variance=(float)$project['budget']-$actualCostTotal; ?>
      <div class="alert <?= $variance>=0?'alert-success':'alert-danger' ?> small mt-3 mb-0"><strong><?= $variance>=0?'Remaining budget':'Over budget' ?>:</strong> <?= money(abs($variance)) ?></div>
    </div></div>
    <div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4">
      <h6 class="fw-semibold mb-3"><i class="bi bi-arrow-right-circle text-warning me-2"></i>Client communication</h6>
      <p class="small text-muted mb-2">Progress meetings include the client. Monthly programme and financial reporting can be supported by the control records on this page.</p>
      <a class="btn btn-outline-brand btn-sm" href="?id=<?= $id ?>&tab=meetings">Record meeting minutes</a>
      <a class="btn btn-outline-brand btn-sm" href="?id=<?= $id ?>&tab=issues">Escalate an issue</a>
    </div></div>
  </div>
</div>

<?php elseif ($tab==='meetings'): ?>
<div class="row g-4"><div class="col-lg-5"><div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4">
<h6 class="fw-semibold mb-3">Record progress meeting</h6>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add_meeting">
<div class="mb-2"><label class="form-label small">Date & time</label><input class="form-control" type="datetime-local" name="meeting_date" value="<?= date('Y-m-d\TH:i') ?>" required></div>
<div class="mb-2"><label class="form-label small">Meeting type</label><input class="form-control" name="meeting_type" value="Monthly progress meeting"></div>
<div class="mb-2"><label class="form-label small">Attendees</label><textarea class="form-control" name="attendees" rows="2" placeholder="Project team; client; consultants"></textarea></div>
<div class="mb-2"><label class="form-label small">Agenda</label><textarea class="form-control" name="agenda" rows="2"></textarea></div>
<div class="mb-2"><label class="form-label small required">Minutes / decisions</label><textarea class="form-control" name="minutes" rows="5" required></textarea></div>
<div class="mb-2"><label class="form-label small">Client action / decision required</label><textarea class="form-control" name="client_action_required" rows="2"></textarea></div>
<div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="client_present" id="clientPresent" checked><label class="form-check-label small" for="clientPresent">Client was present</label></div>
<button class="btn btn-brand btn-sm">Save minutes & notify client</button></form>
</div></div></div>
<div class="col-lg-7"><div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4"><h6 class="fw-semibold mb-3">Meeting minutes history</h6>
<?php foreach($meetingList as $m): ?><div class="border-bottom pb-3 mb-3"><div class="d-flex justify-content-between"><strong><?= e($m['meeting_type']) ?></strong><span class="small text-muted"><?= nice_datetime($m['meeting_date']) ?></span></div><div class="small mt-1"><?= nl2br(e($m['minutes'])) ?></div><?php if($m['client_action_required']): ?><div class="alert alert-warning small mt-2 mb-0"><strong>Client action:</strong> <?= nl2br(e($m['client_action_required'])) ?></div><?php endif; ?><div class="text-muted small mt-2"><?= $m['client_present']?'Client present':'Client not recorded as present' ?> · <?= e($m['author']??'-') ?></div></div><?php endforeach; if(!$meetingList): ?><p class="text-muted small">No meetings recorded.</p><?php endif; ?>
</div></div></div></div>

<?php elseif ($tab==='programme'): ?>
<div class="row g-4"><div class="col-lg-5"><div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4"><h6 class="fw-semibold mb-3">Add programme activity</h6>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add_programme">
<?php foreach([['activity','Activity','text'],['baseline_start','Baseline start','date'],['baseline_finish','Baseline finish','date'],['forecast_start','Forecast start','date'],['forecast_finish','Forecast finish','date'],['actual_start','Actual start','date'],['actual_finish','Actual finish','date']] as $f): ?><div class="mb-2"><label class="form-label small"><?= $f[1] ?></label><input class="form-control" name="<?= $f[0] ?>" type="<?= $f[2] ?>" <?= $f[0]==='activity'?'required':'' ?>></div><?php endforeach; ?>
<div class="mb-2"><label class="form-label small">Progress %</label><input class="form-control" name="percent_complete" type="number" min="0" max="100" step="0.01" value="0"></div>
<div class="mb-2"><label class="form-label small">Status</label><select class="form-select" name="status"><option value="not_started">Not started</option><option value="in_progress">In progress</option><option value="complete">Complete</option><option value="delayed">Delayed</option></select></div>
<div class="mb-3"><label class="form-label small">Notes</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
<button class="btn btn-brand btn-sm">Add activity</button></form></div></div></div>
<div class="col-lg-7"><div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4"><h6 class="fw-semibold mb-3">Baseline vs forecast programme</h6><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Activity</th><th>Baseline</th><th>Forecast</th><th>Progress</th><th>Status</th></tr></thead><tbody>
<?php foreach($programmeList as $r): ?><tr><td><strong><?= e($r['activity']) ?></strong><div class="small text-muted"><?= e($r['notes']??'') ?></div></td><td class="small"><?= nice_date($r['baseline_start']) ?><br><?= nice_date($r['baseline_finish']) ?></td><td class="small"><?= nice_date($r['forecast_start']) ?><br><?= nice_date($r['forecast_finish']) ?></td><td style="min-width:100px"><div class="progress"><div class="progress-bar <?= $r['status']==='delayed'?'bg-danger':'bg-warning' ?>" style="width:<?= (float)$r['percent_complete'] ?>%"></div></div><span class="small"><?= (float)$r['percent_complete'] ?>%</span></td><td><?= badge(ucwords(str_replace('_',' ',$r['status'])),$r['status']==='delayed'?'danger':($r['status']==='complete'?'success':'primary')) ?></td></tr><?php endforeach; if(!$programmeList): ?><tr><td colspan="5" class="text-muted small">No programme activities.</td></tr><?php endif; ?>
</tbody></table></div></div></div></div></div>

<?php elseif ($tab==='costs'): ?>
<div class="row g-4"><div class="col-lg-5"><div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4"><h6 class="fw-semibold mb-3">Add cost report entry</h6>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add_cost">
<div class="mb-2"><label class="form-label small">Date</label><input class="form-control" type="date" name="cost_date" value="<?= date('Y-m-d') ?>" required></div>
<div class="mb-2"><label class="form-label small">Category</label><input class="form-control" name="category" placeholder="Labour, Materials, Plant, Subcontractor..." required></div>
<div class="mb-2"><label class="form-label small">Description</label><input class="form-control" name="description"></div>
<div class="mb-2"><label class="form-label small">Supplier</label><input class="form-control" name="supplier"></div>
<div class="mb-2"><label class="form-label small">Reference</label><input class="form-control" name="reference_no"></div>
<div class="row g-2 mb-3"><div class="col-6"><label class="form-label small">Committed</label><input class="form-control" type="number" step="0.01" min="0" name="committed_amount" value="0"></div><div class="col-6"><label class="form-label small">Actual</label><input class="form-control" type="number" step="0.01" min="0" name="actual_amount" value="0"></div></div>
<button class="btn btn-brand btn-sm">Add cost</button></form></div></div></div>
<div class="col-lg-7"><div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4"><h6 class="fw-semibold mb-3">Cost report</h6><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Date</th><th>Category</th><th>Description</th><th class="text-end">Committed</th><th class="text-end">Actual</th></tr></thead><tbody>
<?php foreach($costList as $r): ?><tr><td class="small"><?= nice_date($r['cost_date']) ?></td><td><?= e($r['category']) ?></td><td class="small"><?= e($r['description']??'-') ?></td><td class="text-end small"><?= money($r['committed_amount']) ?></td><td class="text-end small"><?= money($r['actual_amount']) ?></td></tr><?php endforeach; if(!$costList): ?><tr><td colspan="5" class="text-muted small">No cost entries.</td></tr><?php endif; ?></tbody></table></div><div class="row mt-2 small"><div class="col-6">Committed: <strong><?= money($committedTotal) ?></strong></div><div class="col-6">Actual: <strong><?= money($actualCostTotal) ?></strong></div></div></div></div></div></div>

<?php elseif ($tab==='cashflow'): ?>
<div class="row g-4"><div class="col-lg-5"><div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4"><h6 class="fw-semibold mb-3">Monthly cashflow</h6>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add_cashflow"><div class="mb-2"><label class="form-label small">Month</label><input class="form-control" type="month" name="period_month" value="<?= date('Y-m') ?>" required></div><div class="row g-2 mb-2"><div class="col-6"><label class="form-label small">Planned expenditure</label><input class="form-control" type="number" step="0.01" min="0" name="planned_expenditure" required></div><div class="col-6"><label class="form-label small">Actual expenditure</label><input class="form-control" type="number" step="0.01" min="0" name="actual_expenditure" required></div></div><div class="mb-3"><label class="form-label small">Notes</label><textarea class="form-control" name="notes" rows="2"></textarea></div><button class="btn btn-brand btn-sm">Save month</button></form></div></div></div>
<div class="col-lg-7"><div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4"><h6 class="fw-semibold mb-3">Burn-rate table</h6><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Month</th><th class="text-end">Plan</th><th class="text-end">Actual</th><th class="text-end">Variance</th></tr></thead><tbody>
<?php foreach($cashList as $r): $var=(float)$r['planned_expenditure']-(float)$r['actual_expenditure']; ?><tr><td><?= nice_date($r['period_month']) ?></td><td class="text-end"><?= money($r['planned_expenditure']) ?></td><td class="text-end"><?= money($r['actual_expenditure']) ?></td><td class="text-end <?= $var<0?'text-danger':'text-success' ?>"><?= money($var) ?></td></tr><?php endforeach; if(!$cashList): ?><tr><td colspan="4" class="text-muted small">No cashflow entries.</td></tr><?php endif; ?></tbody></table></div></div></div></div></div>

<?php elseif ($tab==='activities'): ?>
<div class="row g-4"><div class="col-lg-5"><div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4"><h6 class="fw-semibold mb-3">Activity schedule</h6><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add_activity"><div class="mb-2"><label class="form-label small required">Activity</label><input class="form-control" name="activity" required></div><div class="row g-2 mb-2"><div class="col-6"><label class="form-label small">Planned date</label><input class="form-control" type="date" name="planned_date"></div><div class="col-6"><label class="form-label small">Actual date</label><input class="form-control" type="date" name="actual_date"></div></div><div class="mb-2"><label class="form-label small">Status</label><select class="form-select" name="status"><option value="planned">Planned</option><option value="in_progress">In progress</option><option value="complete">Complete</option><option value="delayed">Delayed</option></select></div><div class="mb-3"><label class="form-label small">Notes</label><textarea class="form-control" name="notes" rows="2"></textarea></div><button class="btn btn-brand btn-sm">Add activity</button></form></div></div></div>
<div class="col-lg-7"><div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4"><h6 class="fw-semibold mb-3">Activity schedule</h6><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Activity</th><th>Planned</th><th>Actual</th><th>Status</th></tr></thead><tbody><?php foreach($activityList as $r): ?><tr><td><?= e($r['activity']) ?><div class="small text-muted"><?= e($r['notes']??'') ?></div></td><td><?= nice_date($r['planned_date']) ?></td><td><?= nice_date($r['actual_date']) ?></td><td><?= badge(ucwords(str_replace('_',' ',$r['status'])),$r['status']==='delayed'?'danger':($r['status']==='complete'?'success':'primary')) ?></td></tr><?php endforeach; if(!$activityList): ?><tr><td colspan="4" class="text-muted small">No activity schedule entries.</td></tr><?php endif; ?></tbody></table></div></div></div></div></div>

<?php elseif ($tab==='materials'): ?>
<div class="row g-4"><div class="col-lg-5"><div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4"><h6 class="fw-semibold mb-3">Material tracking register</h6><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add_material_tracking"><div class="mb-2"><label class="form-label small">Material</label><select class="form-select" name="material_id" required><?php foreach($materials as $m): ?><option value="<?= $m['id'] ?>"><?= e($m['name']) ?> (<?= e($m['unit']) ?>)</option><?php endforeach; ?></select></div><div class="row g-2 mb-2"><?php foreach([['required_qty','Required'],['ordered_qty','Ordered'],['delivered_qty','Delivered'],['used_qty','Used'],['shortage_qty','Shortage']] as $f): ?><div class="col-6"><label class="form-label small"><?= $f[1] ?></label><input class="form-control" type="number" min="0" step="0.01" name="<?= $f[0] ?>" value="0"></div><?php endforeach; ?></div><div class="mb-2"><label class="form-label small">Expected delivery</label><input class="form-control" type="date" name="expected_delivery"></div><div class="mb-2"><label class="form-label small">Status</label><select class="form-select" name="status"><option value="planned">Planned</option><option value="ordered">Ordered</option><option value="part_delivered">Part delivered</option><option value="delivered">Delivered</option><option value="shortage">Shortage</option></select></div><div class="mb-3"><label class="form-label small">Notes</label><textarea class="form-control" name="notes" rows="2"></textarea></div><button class="btn btn-brand btn-sm">Save tracking entry</button></form></div></div></div>
<div class="col-lg-7"><div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4"><h6 class="fw-semibold mb-3">Material register</h6><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Material</th><th>Required</th><th>Delivered</th><th>Used</th><th>Shortage</th><th>Status</th></tr></thead><tbody><?php foreach($materialList as $r): ?><tr><td><?= e($r['material_name']) ?><div class="small text-muted"><?= $r['expected_delivery']?nice_date($r['expected_delivery']):'' ?></div></td><td><?= (float)$r['required_qty'] ?> <?= e($r['unit']) ?></td><td><?= (float)$r['delivered_qty'] ?></td><td><?= (float)$r['used_qty'] ?></td><td class="<?= (float)$r['shortage_qty']>0?'text-danger fw-semibold':'' ?>"><?= (float)$r['shortage_qty'] ?></td><td><?= badge(ucwords(str_replace('_',' ',$r['status'])),$r['status']==='shortage'?'danger':($r['status']==='delivered'?'success':'primary')) ?></td></tr><?php endforeach; if(!$materialList): ?><tr><td colspan="6" class="text-muted small">No material tracking entries.</td></tr><?php endif; ?></tbody></table></div></div></div></div></div>

<?php elseif ($tab==='issues'): ?>
<div class="row g-4"><div class="col-lg-5"><div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4"><h6 class="fw-semibold mb-3">Record / escalate issue</h6><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add_issue"><div class="mb-2"><label class="form-label small required">Title</label><input class="form-control" name="title" required></div><div class="row g-2 mb-2"><div class="col-6"><label class="form-label small">Type</label><select class="form-select" name="issue_type"><?php foreach(['delay','material_shortage','design','commercial','quality','safety','other'] as $v): ?><option value="<?= $v ?>"><?= ucwords(str_replace('_',' ',$v)) ?></option><?php endforeach; ?></select></div><div class="col-6"><label class="form-label small">Severity</label><select class="form-select" name="severity"><?php foreach(['low','medium','high','critical'] as $v): ?><option value="<?= $v ?>"><?= ucfirst($v) ?></option><?php endforeach; ?></select></div></div><div class="mb-2"><label class="form-label small">Description</label><textarea class="form-control" name="description" rows="3"></textarea></div><div class="row g-2 mb-2"><div class="col-6"><label class="form-label small">Programme impact (days)</label><input class="form-control" type="number" name="impact_programme_days" value="0"></div><div class="col-6"><label class="form-label small">Cost impact</label><input class="form-control" type="number" step="0.01" min="0" name="impact_cost" value="0"></div></div><div class="mb-2"><label class="form-label small">Mitigation / next step</label><textarea class="form-control" name="mitigation" rows="3"></textarea></div><div class="mb-2"><label class="form-label small">Client decision</label><textarea class="form-control" name="client_decision" rows="2" placeholder="Decision received or pending"></textarea></div><div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="escalated_to_client" id="escClient"><label class="form-check-label small" for="escClient">Escalate to client and send portal notification</label></div><button class="btn btn-brand btn-sm">Save issue</button></form></div></div></div>
<div class="col-lg-7"><div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4"><h6 class="fw-semibold mb-3">Issues and decisions</h6><?php foreach($issueList as $r): ?><div class="border-bottom pb-3 mb-3"><div class="d-flex flex-wrap justify-content-between gap-2"><strong><?= e($r['title']) ?></strong><?= badge(ucfirst($r['severity']),$r['severity']==='critical'||$r['severity']==='high'?'danger':($r['severity']==='medium'?'warning':'secondary')) ?></div><div class="small text-muted mt-1"><?= ucwords(str_replace('_',' ',$r['issue_type'])) ?> · <?= nice_datetime($r['created_at']) ?> · <?= e($r['reporter']??'-') ?></div><p class="small mb-1 mt-2"><?= nl2br(e($r['description']??'')) ?></p><div class="small"><strong>Impact:</strong> <?= (int)$r['impact_programme_days'] ?> day(s), <?= money($r['impact_cost']) ?></div><?php if($r['mitigation']): ?><div class="small mt-1"><strong>Mitigation:</strong> <?= nl2br(e($r['mitigation'])) ?></div><?php endif; ?><div class="mt-2"><?= badge(ucwords(str_replace('_',' ',$r['status'])),$r['status']==='closed'?'success':($r['status']==='escalated'?'danger':'primary')) ?> <?= $r['escalated_to_client']?badge('Client escalated','warning'):'' ?></div><?php if($r['client_decision']): ?><div class="alert alert-light small mt-2 mb-0"><strong>Client decision:</strong> <?= nl2br(e($r['client_decision'])) ?></div><?php endif; ?></div><?php endforeach; if(!$issueList): ?><p class="text-muted small">No project issues recorded.</p><?php endif; ?></div></div></div></div>
<?php endif; ?>
<?php page_footer(); ?>
