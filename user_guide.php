<?php
/**
 * RG PMS - Role-aware user guide
 */
require_once __DIR__ . '/includes/layout.php';
require_login();

$user = current_user();
$role = $user['role_name']; // 'admin' | 'personnel' | 'client'

// --- Build the guide content based on role ---
$guides = [
    'admin' => [
        'title'  => 'Administrator Guide',
        'intro'  => 'As an Administrator you have full access to every module in the system. This guide walks you through each area of the platform.',
        'accent' => 'primary',
        'icon'   => 'bi-shield-check',
        'sections' => [
            [
                'id'    => 'dashboard',
                'title' => 'Dashboard',
                'icon'  => 'bi-grid-1x2',
                'body'  => '
                    <p>The Dashboard is your landing page and gives a snapshot of everything happening across the company.</p>
                    <ul>
                        <li><strong>Stat tiles</strong> - counts of active projects, clients, personnel, and open quotations/invoices.</li>
                        <li><strong>Project status chart</strong> - a visual breakdown of projects by status (active, on hold, completed, cancelled).</li>
                        <li><strong>Budget vs. spend chart</strong> - compares total budgets to actual spend across all projects.</li>
                        <li><strong>Latest updates</strong> - recent notifications across the whole system.</li>
                    </ul>
                    <p class="mb-0">Use it as a quick health-check each morning.</p>
                ',
            ],
            [
                'id'    => 'projects',
                'title' => 'Projects',
                'icon'  => 'bi-kanban',
                'body'  => '
                    <p>Projects are the heart of the system. Everything else - quotations, materials, invoices, photos - links back to a project.</p>
                    <p><strong>Creating a project</strong></p>
                    <ol>
                        <li>Go to <em>Projects - New project</em>.</li>
                        <li>Fill in the project name, client, location, target completion date, and starting budget.</li>
                        <li>Save - the project is created in "Enquiry" state.</li>
                    </ol>
                    <p><strong>Project view</strong> - every project has a detail page with tabs:</p>
                    <ul>
                        <li><strong>Overview</strong> - key details, progress bar, client info.</li>
                        <li><strong>Project controls</strong> - programme, cashflow, commercial dashboard, meeting minutes.</li>
                        <li><strong>Materials</strong> - requests logged against this project.</li>
                        <li><strong>Photos</strong> - site photographs uploaded by personnel.</li>
                        <li><strong>Invoices</strong> - all invoices issued for this project.</li>
                    </ul>
                    <p><strong>Updating progress</strong> - click <em>Edit</em> on a project to adjust the progress percentage, status, or budget.</p>
                ',
            ],
            [
                'id'    => 'clients',
                'title' => 'Clients',
                'icon'  => 'bi-people',
                'body'  => '
                    <p>Manage your client list and their portal access here.</p>
                    <ul>
                        <li><strong>Add a client</strong> - capture their company name, contact person, email, and phone.</li>
                        <li><strong>Portal access</strong> - when you add a client, an account is created so they can log in and view their own projects only.</li>
                        <li><strong>View a client</strong> - see every project linked to that client, plus their quotations and invoices.</li>
                    </ul>
                ',
            ],
            [
                'id'    => 'quotations',
                'title' => 'Quotations',
                'icon'  => 'bi-file-earmark-text',
                'body'  => '
                    <p>Quotations are sent to clients for approval before work begins.</p>
                    <ol>
                        <li>Go to <em>Quotations - New quotation</em>.</li>
                        <li>Select the project and client.</li>
                        <li>Add line items (description, quantity, rate). Totals calculate automatically.</li>
                        <li>Save as draft, then click <strong>Send to client</strong>.</li>
                    </ol>
                    <p>The client sees the quotation on their portal and can approve or request changes. You\'ll be notified when they respond.</p>
                ',
            ],
            [
                'id'    => 'personnel',
                'title' => 'Personnel',
                'icon'  => 'bi-person-badge',
                'body'  => '
                    <p>Manage your team - project managers, quantity surveyors, administrators, and subcontractors.</p>
                    <ul>
                        <li><strong>Add personnel</strong> - name, email, phone, and role.</li>
                        <li><strong>Assign to projects</strong> - link team members to specific projects so they see only what\'s relevant to them.</li>
                        <li><strong>Login credentials</strong> - each person gets portal access with the <em>personnel</em> role.</li>
                    </ul>
                ',
            ],
            [
                'id'    => 'materials',
                'title' => 'Materials',
                'icon'  => 'bi-box-seam',
                'body'  => '
                    <p>Track material stock and requests.</p>
                    <ul>
                        <li><strong>Materials list</strong> - master catalogue of all materials and current stock levels.</li>
                        <li><strong>Requests</strong> - personnel submit requests per project; you review and approve/reject them here.</li>
                        <li><strong>Stock levels</strong> - adjust quantities when materials are received or issued.</li>
                    </ul>
                ',
            ],
            [
                'id'    => 'invoices',
                'title' => 'Invoices',
                'icon'  => 'bi-receipt',
                'body'  => '
                    <p>Issue invoices and track payments.</p>
                    <ol>
                        <li>Go to <em>Invoices - New invoice</em>.</li>
                        <li>Select the project and client.</li>
                        <li>Add line items and due date.</li>
                        <li>Save, then click <strong>Mark as sent</strong> when it goes out.</li>
                        <li>When paid, click <strong>Mark as paid</strong> to update the dashboard totals.</li>
                    </ol>
                ',
            ],
            [
                'id'    => 'security',
                'title' => 'Your profile &amp; security',
                'icon'  => 'bi-shield-lock',
                'body'  => '
                    <p>From <em>Profile</em> (bottom of the sidebar) you can:</p>
                    <ul>
                        <li>Update your name and phone number.</li>
                        <li><strong>Change your password</strong> - the system shows you a security code (captcha-style) that you must re-enter on the next page to confirm it\'s really you.</li>
                        <li>Switch between light and dark themes using the moon/sun icon in the top-right.</li>
                        <li>Translate the interface into any of South Africa\'s 11 official languages via the globe icon.</li>
                    </ul>
                ',
            ],
            [
                'id'    => 'tips',
                'title' => 'Tips &amp; best practices',
                'icon'  => 'bi-lightbulb',
                'body'  => '
                    <ul>
                        <li>Keep project progress percentages up to date - they drive the dashboard charts.</li>
                        <li>Attach a client to every project - otherwise the client portal won\'t show it.</li>
                        <li>Review material requests daily so site work doesn\'t stall.</li>
                        <li>Send quotations with clear descriptions - it speeds up client approval.</li>
                        <li>Use dark mode when working late - click the moon icon.</li>
                    </ul>
                ',
            ],
        ],
    ],

    'personnel' => [
        'title'  => 'Team Member Guide',
        'intro'  => 'This guide covers what you can do in RG PMS as a team member - your projects, progress updates, material requests, and photos.',
        'accent' => 'info',
        'icon'   => 'bi-person-badge',
        'sections' => [
            [
                'id'    => 'dashboard',
                'title' => 'My Projects dashboard',
                'icon'  => 'bi-grid-1x2',
                'body'  => '
                    <p>Your dashboard shows only the projects you\'ve been assigned to.</p>
                    <ul>
                        <li><strong>Stat tiles</strong> - how many projects you\'re on, how many are active, and how many are complete.</li>
                        <li><strong>Project cards</strong> - click any card to open the project.</li>
                        <li><strong>Progress bars</strong> - see at a glance how far along each project is.</li>
                    </ul>
                ',
            ],
            [
                'id'    => 'project',
                'title' => 'Working on a project',
                'icon'  => 'bi-kanban',
                'body'  => '
                    <p>Click a project card to open its detail view. You can:</p>
                    <ul>
                        <li><strong>Update progress</strong> - enter a new percentage to reflect actual site work completed.</li>
                        <li><strong>View programme</strong> - see baseline dates and forecast dates for activities.</li>
                        <li><strong>View project controls</strong> - commercial dashboard, cashflow, and meeting minutes (read-only for most team members).</li>
                        <li><strong>Log material requests</strong> - request materials for your project.</li>
                        <li><strong>Upload photos</strong> - capture site progress visually.</li>
                    </ul>
                ',
            ],
            [
                'id'    => 'materials',
                'title' => 'Material requests',
                'icon'  => 'bi-box-seam',
                'body'  => '
                    <p>When you need materials on site, log a request from the project view.</p>
                    <ol>
                        <li>Open the project - <em>Materials</em> tab.</li>
                        <li>Click <strong>New request</strong>.</li>
                        <li>Choose the material, quantity, and add a short note about urgency.</li>
                        <li>Submit - the administrator/manager will approve or reject it.</li>
                    </ol>
                    <p>You\'ll get a notification when your request is reviewed.</p>
                ',
            ],
            [
                'id'    => 'photos',
                'title' => 'Uploading site photos',
                'icon'  => 'bi-camera',
                'body'  => '
                    <p>Photos help keep the client and managers informed about site progress.</p>
                    <ul>
                        <li>Open the project - <em>Photos</em> tab.</li>
                        <li>Click <strong>Upload</strong> and select one or more images.</li>
                        <li>Add a caption describing the work shown.</li>
                        <li>Save - the photo becomes visible to your manager and (if approved) to the client.</li>
                    </ul>
                    <p class="mb-0"><strong>Tip:</strong> Take photos in good lighting and show clear progress points (foundation laid, walls up, roof complete).</p>
                ',
            ],
            [
                'id'    => 'notifications',
                'title' => 'Notifications',
                'icon'  => 'bi-bell',
                'body'  => '
                    <p>You\'ll receive notifications when:</p>
                    <ul>
                        <li>You\'re assigned to a new project.</li>
                        <li>Your material request is approved or rejected.</li>
                        <li>Your uploaded photo is reviewed.</li>
                        <li>A project deadline changes.</li>
                    </ul>
                    <p>Click the bell icon in the header to see recent notifications, or <em>All notifications</em> in your profile menu.</p>
                ',
            ],
            [
                'id'    => 'security',
                'title' => 'Your profile &amp; security',
                'icon'  => 'bi-shield-lock',
                'body'  => '
                    <p>From <em>Profile</em> (bottom of the sidebar) you can:</p>
                    <ul>
                        <li>Update your name and phone number.</li>
                        <li>Change your password - the system shows you a security code you re-enter on the next page.</li>
                        <li>Switch light/dark theme (moon/sun icon).</li>
                        <li>Translate the interface (globe icon).</li>
                    </ul>
                ',
            ],
        ],
    ],

    'client' => [
        'title'  => 'Client Guide',
        'intro'  => 'Welcome to your client portal. This guide shows you how to track your projects, approve quotations, and view invoices.',
        'accent' => 'success',
        'icon'   => 'bi-people',
        'sections' => [
            [
                'id'    => 'dashboard',
                'title' => 'Your dashboard',
                'icon'  => 'bi-grid-1x2',
                'body'  => '
                    <p>Your dashboard gives you a single view of everything happening on your projects.</p>
                    <ul>
                        <li><strong>Stat tiles</strong> - number of projects, total budget, total spent, and open quotations.</li>
                        <li><strong>Status chart</strong> - how your projects are spread across active, on hold, and completed.</li>
                        <li><strong>Latest site image</strong> - a recent photograph from site.</li>
                        <li><strong>Project cards</strong> - click any card to open the full project.</li>
                    </ul>
                ',
            ],
            [
                'id'    => 'project',
                'title' => 'Your projects',
                'icon'  => 'bi-kanban',
                'body'  => '
                    <p>Click a project card to see everything about it:</p>
                    <ul>
                        <li><strong>Overview</strong> - location, target completion date, and current progress percentage.</li>
                        <li><strong>Programme</strong> - the timeline of activities with baseline and forecast dates.</li>
                        <li><strong>Commercial dashboard</strong> - your project\'s budget, spend to date, and remaining budget.</li>
                        <li><strong>Photos</strong> - approved site photographs showing progress.</li>
                        <li><strong>Invoices</strong> - all invoices issued to you for this project.</li>
                        <li><strong>Meeting minutes</strong> - notes from progress meetings (where you were present).</li>
                    </ul>
                ',
            ],
            [
                'id'    => 'quotations',
                'title' => 'Quotations',
                'icon'  => 'bi-file-earmark-text',
                'body'  => '
                    <p>When RG Trade sends you a quotation, it appears on your dashboard with a yellow alert.</p>
                    <ol>
                        <li>Click the quotation link to open it.</li>
                        <li>Review the line items, quantities, and rates.</li>
                        <li>Click <strong>Approve</strong> to accept, or <strong>Request changes</strong> to ask for adjustments.</li>
                    </ol>
                    <p>The RG team is notified immediately and will follow up.</p>
                ',
            ],
            [
                'id'    => 'invoices',
                'title' => 'Invoices',
                'icon'  => 'bi-receipt',
                'body'  => '
                    <p>All invoices issued to you are visible from your project view or the dashboard.</p>
                    <ul>
                        <li>Click an invoice to see full details - line items, totals, and due date.</li>
                        <li>Download a PDF copy for your records.</li>
                        <li>Payment status (paid / outstanding) is shown next to each invoice.</li>
                    </ul>
                ',
            ],
            [
                'id'    => 'notifications',
                'title' => 'Notifications &amp; updates',
                'icon'  => 'bi-bell',
                'body'  => '
                    <p>You\'ll be notified when:</p>
                    <ul>
                        <li>A new quotation is sent to you.</li>
                        <li>An invoice is issued.</li>
                        <li>A project milestone is reached.</li>
                        <li>The RG team needs an urgent decision from you.</li>
                    </ul>
                    <p>Click the bell icon in the header to see recent updates, or the "View all" link for the full list.</p>
                ',
            ],
            [
                'id'    => 'meetings',
                'title' => 'Progress meetings',
                'icon'  => 'bi-people',
                'body'  => '
                    <p>Every project includes progress meetings where you meet with the team. The minutes from each meeting are uploaded to the system and visible to you in the project view.</p>
                    <ul>
                        <li><strong>Meeting minutes</strong> - decisions made, issues raised, and next steps.</li>
                        <li><strong>Commercial dashboard</strong> - an up-to-date financial snapshot shared after each meeting.</li>
                        <li><strong>Monthly programme &amp; financial reports</strong> - issued to you each month.</li>
                    </ul>
                ',
            ],
            [
                'id'    => 'security',
                'title' => 'Your profile &amp; security',
                'icon'  => 'bi-shield-lock',
                'body'  => '
                    <p>From <em>Profile</em> (bottom of the sidebar) you can:</p>
                    <ul>
                        <li>Update your name and phone number.</li>
                        <li>Change your password - you\'ll be shown a security code you must re-enter on the next page.</li>
                        <li>Switch light/dark theme using the moon/sun icon.</li>
                        <li>Translate the interface via the globe icon.</li>
                    </ul>
                ',
            ],
            [
                'id'    => 'contact',
                'title' => 'Need help?',
                'icon'  => 'bi-envelope-at',
                'body'  => '
                    <p>If you have any questions about a project, an invoice, or your account, reach out to the RG Trade team:</p>
                    <ul>
                        <li>Email: <a href="mailto:' . e(COMPANY_EMAIL) . '">' . e(COMPANY_EMAIL) . '</a></li>
                        <li>Phone: ' . e(COMPANY_PHONE) . '</li>
                    </ul>
                    <p class="mb-0">You can also use the <strong>Contact RG team</strong> link in the footer of any page.</p>
                ',
            ],
        ],
    ],
];

$guide = $guides[$role] ?? $guides['client'];

page_header('User guide', 'guide');
?>
<div class="row g-4">
  <div class="col-lg-3 col-xl-2 d-none d-lg-block">
    <div class="card border-0 shadow-sm rounded-4 sticky-top" style="top:84px;">
      <div class="card-body p-3">
        <div class="sidebar-section-title text-muted px-0 pb-2">On this page</div>
        <nav class="nav flex-column small">
          <?php foreach ($guide['sections'] as $i => $s): ?>
            <a class="nav-link py-1 px-0 text-decoration-none <?= $i === 0 ? 'text-brand fw-semibold' : '' ?>"
               href="#<?= e($s['id']) ?>">
              <i class="bi <?= e($s['icon']) ?> me-1"></i> <?= strip_tags($s['title']) ?>
            </a>
          <?php endforeach; ?>
        </nav>
      </div>
    </div>
  </div>

  <div class="col-lg-9 col-xl-10">

    <!-- Hero / intro -->
    <div class="card border-0 shadow-sm rounded-4 mb-4" style="background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%);color:#fff;">
      <div class="card-body p-4 p-lg-5">
        <div class="d-flex align-items-center gap-3 mb-3">
          <span class="d-inline-flex align-items-center justify-content-center rounded-circle"
                style="width:56px;height:56px;font-size:1.5rem;background:rgba(255,255,255,.1);">
            <i class="bi <?= e($guide['icon']) ?>"></i>
          </span>
          <div>
            <div class="small text-uppercase" style="letter-spacing:.6px;color:#93c5fd;font-weight:600;">User guide</div>
            <h4 class="fw-bold mb-0"><?= e($guide['title']) ?></h4>
          </div>
        </div>
        <p class="text-white-50 mb-0" style="max-width:640px;"><?= e($guide['intro']) ?></p>
      </div>
    </div>

    <!-- Sections -->
    <?php foreach ($guide['sections'] as $s): ?>
      <section id="<?= e($s['id']) ?>" class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4 p-lg-5">
          <div class="d-flex align-items-center gap-3 mb-3">
            <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-brand-soft"
                  style="width:44px;height:44px;font-size:1.15rem;">
              <i class="bi <?= e($s['icon']) ?>"></i>
            </span>
            <h5 class="fw-semibold mb-0"><?= $s['title'] ?></h5>
          </div>
          <div class="guide-body">
            <?= $s['body'] ?>
          </div>
        </div>
      </section>
    <?php endforeach; ?>

    <!-- Footer CTA -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
      <div class="card-body p-4 text-center">
        <div class="mb-2">
          <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-brand-soft"
                style="width:48px;height:48px;font-size:1.2rem;">
            <i class="bi bi-question-circle"></i>
          </span>
        </div>
        <h6 class="fw-semibold mb-2">Still stuck?</h6>
        <p class="small text-muted mb-3">Reach out to the RG Trade team and we\'ll help you get sorted.</p>
        <a href="mailto:<?= e(COMPANY_EMAIL) ?>?subject=RG%20PMS%20Support%20Request" class="btn btn-brand">
          <i class="bi bi-envelope-at me-2"></i>Contact RG team
        </a>
      </div>
    </div>

  </div>
</div>

<style>
.guide-body h6 { margin-top: 1rem; font-weight: 600; }
.guide-body p:last-child, .guide-body ul:last-child, .guide-body ol:last-child { margin-bottom: 0; }
.guide-body ol, .guide-body ul { padding-left: 1.25rem; }
.guide-body li { margin-bottom: .3rem; }
.guide-body a { color: #3b82f6; text-decoration: none; }
.guide-body a:hover { text-decoration: underline; }
.guide-body code { background: rgba(59,130,246,.1); color: #1e40af; padding: 1px 6px; border-radius: 5px; font-size: .85em; }
html.theme-dark .guide-body code { background: rgba(147,197,253,.15); color: #93c5fd; }
html.theme-dark .guide-body a { color: #93c5fd; }
</style>

<?php page_footer(); ?>