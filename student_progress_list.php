<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: index.php");
    exit;
}

// Fetch all students with their latest GST, cascading reading profile, and course assessment stats
$stmt = $pdo->query("
    SELECT u.id, u.fname, u.mname, u.lname, u.grade_level, u.section, u.lrn, u.created_at as registered_at,
           g.score as gst_score, g.total_items as gst_total, g.percentage as gst_pct,
           g.gst_category, g.needs_individual_assessment, g.starting_grade, g.completed_at as gst_completed_at,
           rp.independent_grade, rp.instructional_grade, rp.frustration_grade, rp.updated_at as rp_updated_at,
           ca.last_course_activity,
           COALESCE(ca.total_course_tests, 0) as total_course_tests
    FROM users u
    LEFT JOIN (
        SELECT user_id, score, total_items, percentage, gst_category, needs_individual_assessment, starting_grade, completed_at
        FROM gst_results
        WHERE id IN (SELECT MAX(id) FROM gst_results GROUP BY user_id)
    ) g ON u.id = g.user_id
    LEFT JOIN reading_profiles rp ON u.id = rp.user_id
    LEFT JOIN (
        SELECT user_id, MAX(created_at) as last_course_activity, COUNT(id) as total_course_tests
        FROM reading_attempts
        WHERE phase IN ('Course-Pre-Test', 'Course-Post-Test')
        GROUP BY user_id
    ) ca ON u.id = ca.user_id
    WHERE u.role = 'student'
    ORDER BY COALESCE(ca.last_course_activity, g.completed_at, rp.updated_at, u.created_at) DESC
");
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Unique sections, grade levels, and counters for summary metrics
$sections = [];
$grade_levels = [];
$total_gst = 0;
$total_cascading = 0;
$total_course = 0;

foreach($students as $st) {
    $sec = preg_replace('/^Grade\s+\d+\s+/i', '', trim($st['section'] ?? ''));
    if (!empty($sec) && !in_array($sec, $sections)) {
        $sections[] = $sec;
    }
    $gl = trim($st['grade_level'] ?? '');
    if (!empty($gl) && !in_array($gl, $grade_levels)) {
        $grade_levels[] = $gl;
    }
    if ($st['gst_score'] !== null) {
        $total_gst++;
    }
    if ($st['instructional_grade'] !== null || $st['frustration_grade'] !== null || $st['independent_grade'] !== null || (isset($st['needs_individual_assessment']) && $st['needs_individual_assessment'] == 0)) {
        $total_cascading++;
    }
    if ($st['total_course_tests'] > 0) {
        $total_course++;
    }
}
sort($sections);
sort($grade_levels);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="https://tibungcodistrict.wordpress.com/wp-content/uploads/2018/06/untitled-1.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Progress Tracker - Phil-IRI ARAL</title>
    <script>
        const originalWarn = console.warn;
        console.warn = function() {
            if (arguments[0] && typeof arguments[0] === 'string' && arguments[0].includes('cdn.tailwindcss.com should not be used in production')) return;
            originalWarn.apply(console, arguments);
        };
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #F8FAFC; }
        .sidebar { background-color: #1a365d; }
        .card { background: white; border-radius: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #E2E8F0; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 4px; }
        <?php $is_dark = isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark'; ?>
        <?php if($is_dark): ?>
        /* Refined Slate Dark Mode */
        body { background-color: #0f172a !important; color: #f8fafc !important; }
        .bg-white, .bg-slate-50 { background-color: #1e293b !important; border-color: #334155 !important; color: #f8fafc !important; }
        
        .text-slate-800, .text-slate-700 { color: #f1f5f9 !important; }
        .text-slate-600, .text-slate-500, .text-slate-400 { color: #cbd5e1 !important; }
        .border-slate-200, .border-slate-100, .border-b, .border-l { border-color: #334155 !important; }
        .border-slate-300 { border-color: #475569 !important; }
        .sidebar { background-color: #0b1120 !important; border-right: 1px solid #1e293b !important; }
        input, select, textarea { background-color: #0f172a !important; color: white !important; border-color: #475569 !important; }
        .shadow-sm { box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.5) !important; }

        /* Colored Badges / Cards Fixes */
        .bg-blue-50, .bg-blue-100 { background-color: rgba(59, 130, 246, 0.2) !important; color: #93c5fd !important; }
        .text-blue-600, .text-blue-700, .text-blue-800 { color: #60a5fa !important; }
        .border-blue-100, .border-blue-200, .border-l-blue-500 { border-color: rgba(59, 130, 246, 0.3) !important; }

        .bg-emerald-50, .bg-emerald-100 { background-color: rgba(16, 185, 129, 0.2) !important; color: #6ee7b7 !important; }
        .text-emerald-600, .text-emerald-700, .text-emerald-800 { color: #34d399 !important; }
        .border-emerald-100, .border-emerald-200 { border-color: rgba(16, 185, 129, 0.3) !important; }

        .bg-amber-50, .bg-amber-100 { background-color: rgba(245, 158, 11, 0.2) !important; color: #fcd34d !important; }
        .text-amber-600, .text-amber-700, .text-amber-800 { color: #fbbf24 !important; }
        .border-amber-100, .border-amber-200 { border-color: rgba(245, 158, 11, 0.3) !important; }

        .bg-rose-50, .bg-rose-100 { background-color: rgba(244, 63, 94, 0.2) !important; color: #fda4af !important; }
        .text-rose-600, .text-rose-700, .text-rose-800 { color: #fb7185 !important; }
        .border-rose-100, .border-rose-200 { border-color: rgba(244, 63, 94, 0.3) !important; }
        
        .bg-purple-50, .bg-purple-100 { background-color: rgba(168, 85, 247, 0.2) !important; color: #d8b4fe !important; }
        .text-purple-600, .text-purple-700, .text-purple-800 { color: #c084fc !important; }

        .bg-slate-100, .bg-slate-200 { background-color: #334155 !important; color: #e2e8f0 !important; }
        .hover\:bg-slate-50:hover, tr:hover { background-color: #334155 !important; }
        .card { background-color: #1e293b !important; border-color: #334155 !important; }
        .sidebar img { background-color: transparent !important; filter: drop-shadow(0px 0px 2px rgba(255,255,255,0.5)) !important; }
        <?php endif; ?>
    </style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-800 flex overflow-hidden">
    
    <!-- Sidebar -->
    <?php include 'teacher_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col h-screen overflow-hidden relative transition-all duration-300" id="mainContent">
        
        <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 md:px-8 shrink-0">
            <div class="flex items-center">
                <button onclick="window.toggleSidebar()" class="mr-4 text-slate-500 hover:text-slate-700 focus:outline-none md:hidden">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-sm">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div>
                        <h1 class="font-bold text-lg text-slate-800 leading-tight">Student Progress Tracker</h1>
                        <p class="text-[11px] text-slate-500">Comprehensive Pre-Assessment (GST & Cascading) & Course Intervention</p>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="dashboard_teacher.php" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold transition flex items-center gap-1.5">
                    <i class="fas fa-border-all text-slate-500"></i> Dashboard
                </a>
                <a href="assessment_result.php" class="px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg text-xs font-semibold transition flex items-center gap-1.5">
                    <i class="fas fa-microphone-alt text-blue-500"></i> Assessment Results
                </a>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-4 md:p-8">
            <div class="max-w-7xl mx-auto space-y-6">
                
                <!-- Summary Metrics Bar -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-xs">
                        <p class="text-xs font-semibold text-slate-500 mb-1">Total Students</p>
                        <h3 class="text-2xl font-bold text-slate-800"><?php echo count($students); ?></h3>
                        <p class="text-[11px] text-slate-400 mt-0.5">Enrolled learners</p>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-xs border-l-4 border-l-blue-500">
                        <p class="text-xs font-semibold text-blue-600 mb-1">GST Screened</p>
                        <h3 class="text-2xl font-bold text-slate-800"><?php echo $total_gst; ?></h3>
                        <p class="text-[11px] text-slate-400 mt-0.5">Group test completed</p>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-xs border-l-4 border-l-amber-500">
                        <p class="text-xs font-semibold text-amber-600 mb-1">Cascading Diagnosed</p>
                        <h3 class="text-2xl font-bold text-slate-800"><?php echo $total_cascading; ?></h3>
                        <p class="text-[11px] text-slate-400 mt-0.5">Baseline profiles set</p>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-xs border-l-4 border-l-emerald-500">
                        <p class="text-xs font-semibold text-emerald-600 mb-1">Course Intervention</p>
                        <h3 class="text-2xl font-bold text-slate-800"><?php echo $total_course; ?></h3>
                        <p class="text-[11px] text-slate-400 mt-0.5">Pre/Post attempts</p>
                    </div>
                </div>

                <!-- Main Table Card -->
                <div class="bg-white rounded-xl shadow-xs border border-slate-200 overflow-hidden">
                    
                    <!-- Table Header Controls -->
                    <div class="p-4 sm:p-5 border-b border-slate-200 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 bg-white">
                        <div>
                            <h2 class="text-base font-bold text-slate-800">Phil-IRI Assessment & Course Progress Roster</h2>
                            <p class="text-xs text-slate-500 mt-0.5">Monitors Pre-Assessment diagnostic results and intervention test performance</p>
                        </div>

                        <!-- Filter Controls -->
                        <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                            <!-- Search -->
                            <div class="relative flex-1 sm:flex-initial">
                                <i class="fas fa-search absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                                <input type="text" id="progressSearch" onkeyup="applyProgressFilters()" placeholder="Search name or LRN..." class="pl-8 pr-3 py-1.5 border border-slate-300 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 w-full sm:w-48 bg-white">
                            </div>

                            <!-- Grade Filter -->
                            <select id="progressGradeFilter" onchange="applyProgressFilters()" class="border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white font-medium">
                                <option value="">All Grades</option>
                                <?php foreach($grade_levels as $gl): ?>
                                    <option value="<?php echo htmlspecialchars($gl); ?>"><?php echo htmlspecialchars($gl); ?></option>
                                <?php endforeach; ?>
                            </select>

                            <!-- Section Filter -->
                            <select id="progressSectionFilter" onchange="applyProgressFilters()" class="border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white font-medium">
                                <option value="">All Sections</option>
                                <?php foreach($sections as $sec): ?>
                                    <option value="<?php echo htmlspecialchars($sec); ?>"><?php echo htmlspecialchars($sec); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <?php if(count($students) > 0): ?>
                        <div class="overflow-x-auto">
                            <table id="progressTable" class="w-full text-left border-collapse min-w-[980px]">
                                <thead>
                                    <!-- Grouped Tier 1 Headers -->
                                    <tr class="bg-slate-100/80 border-b border-slate-200 text-[11px] uppercase tracking-wider text-slate-600 font-bold">
                                        <th class="py-2.5 px-4" colspan="2">Student Profile</th>
                                        <th class="py-2.5 px-4 bg-blue-50/70 text-blue-800 border-x border-slate-200 text-center" colspan="2">
                                            <i class="fas fa-clipboard-check mr-1 text-blue-600"></i> Phil-IRI Pre-Assessment (Diagnostic)
                                        </th>
                                        <th class="py-2.5 px-4 bg-emerald-50/70 text-emerald-800 border-r border-slate-200 text-center" colspan="2">
                                            <i class="fas fa-book-reader mr-1 text-emerald-600"></i> Course Assessment (Intervention)
                                        </th>
                                        <th class="py-2.5 px-4" colspan="3">Status & Action</th>
                                    </tr>

                                    <!-- Column Tier 2 Headers -->
                                    <tr class="bg-slate-50 border-b border-slate-200 text-xs uppercase tracking-wider text-slate-500 font-semibold">
                                        <th class="py-3 px-4">Student Name & LRN</th>
                                        <th class="py-3 px-4">Grade & Sec</th>
                                        <th class="py-3 px-4 bg-blue-50/30 border-l border-slate-200">GST Screening</th>
                                        <th class="py-3 px-4 bg-blue-50/30 border-r border-slate-200">Cascading Profile</th>
                                        <th class="py-3 px-4 bg-emerald-50/30">Course Pre-Test</th>
                                        <th class="py-3 px-4 bg-emerald-50/30 border-r border-slate-200">Course Post-Test</th>
                                        <th class="py-3 px-4">Remarks</th>
                                        <th class="py-3 px-4">Last Activity</th>
                                        <th class="py-3 px-4 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php 
                                    foreach($students as $s): 
                                        $uid = $s['id'];
                                        $summary = get_student_course_progress_summary($pdo, $uid);
                                        $pre = $summary['pre'];
                                        $post = $summary['post'];
                                        $clean_sec = preg_replace('/^Grade\s+\d+\s+/i', '', $s['section'] ?? '');
                                        $mi = !empty($s['mname']) ? strtoupper(substr($s['mname'], 0, 1)) . '.' : '';
                                        $name = htmlspecialchars($s['fname'] . ' ' . $mi . ' ' . $s['lname']);

                                        // Compute baseline cascading oral reading profile
                                        $enrolled_grade = intval(preg_replace('/[^0-9]/', '', $s['grade_level'] ?? ''));
                                        $cascading_status = 'Pending';
                                        if (isset($s['needs_individual_assessment']) && (int)$s['needs_individual_assessment'] === 0) {
                                            $cascading_status = 'Independent';
                                        } elseif ($s['independent_grade'] !== null || $s['instructional_grade'] !== null || $s['frustration_grade'] !== null) {
                                            if ($s['independent_grade'] !== null && $enrolled_grade <= intval($s['independent_grade'])) {
                                                $cascading_status = 'Independent';
                                            } elseif ($s['instructional_grade'] !== null && $enrolled_grade <= intval($s['instructional_grade'])) {
                                                $cascading_status = 'Instructional';
                                            } else {
                                                if ($s['frustration_grade'] !== null && intval($s['frustration_grade']) <= 4 && $s['instructional_grade'] === null && $s['independent_grade'] === null) {
                                                    $cascading_status = 'Non-Reader';
                                                } else {
                                                    $cascading_status = 'Frustration';
                                                }
                                            }
                                        }

                                        // Cascading badge styling
                                        $casc_badge = 'bg-slate-100 text-slate-500';
                                        if ($cascading_status === 'Independent') $casc_badge = 'bg-emerald-100 text-emerald-800';
                                        elseif ($cascading_status === 'Instructional') $casc_badge = 'bg-amber-100 text-amber-800';
                                        elseif ($cascading_status === 'Frustration') $casc_badge = 'bg-rose-100 text-rose-800';
                                        elseif ($cascading_status === 'Non-Reader') $casc_badge = 'bg-slate-700 text-white';

                                        // Overall remarks determination
                                        $remark_text = 'Not Started';
                                        $remark_class = 'bg-slate-100 text-slate-500';
                                        $custom_tag = null;

                                        if ($summary['has_tests']) {
                                            $remark_text = $summary['outcome'];
                                            $remark_class = $summary['badge_class'];
                                            if ($summary['has_custom_rec'] && !empty($summary['condition_tag'])) {
                                                $custom_tag = $summary['condition_tag'];
                                            }
                                        } elseif ($cascading_status !== 'Pending') {
                                            if ($cascading_status === 'Independent') {
                                                $remark_text = 'Grade-Ready (GST)';
                                                $remark_class = 'bg-emerald-100 text-emerald-800 font-semibold';
                                            } elseif ($cascading_status === 'Instructional') {
                                                $remark_text = 'Ready for Intervention';
                                                $remark_class = 'bg-amber-100 text-amber-800 font-semibold';
                                            } elseif ($cascading_status === 'Frustration') {
                                                $remark_text = 'Needs Course Pre-Test';
                                                $remark_class = 'bg-rose-100 text-rose-800 font-semibold';
                                            } else {
                                                $remark_text = 'Urgent Support';
                                                $remark_class = 'bg-slate-700 text-white font-semibold';
                                            }
                                        } elseif ($s['gst_score'] !== null) {
                                            $remark_text = 'Awaiting Cascading';
                                            $remark_class = 'bg-blue-50 text-blue-700 border border-blue-200 font-semibold';
                                        } else {
                                            $remark_text = 'Awaiting GST';
                                            $remark_class = 'bg-slate-100 text-slate-500 font-medium';
                                        }

                                        // Timestamp of latest activity across GST, cascading, course attempts, or registration
                                        $last_act_ts = $s['last_course_activity'] ?? $s['gst_completed_at'] ?? $s['rp_updated_at'] ?? $s['registered_at'];
                                        $last_act_str = $last_act_ts ? date('M d, Y h:i A', strtotime($last_act_ts)) : 'Never';
                                    ?>
                                    <tr class="hover:bg-slate-50/80 transition"
                                        data-grade="<?php echo htmlspecialchars($s['grade_level'] ?? ''); ?>"
                                        data-section="<?php echo htmlspecialchars($clean_sec); ?>"
                                        data-name="<?php echo strtolower($s['fname'] . ' ' . $s['lname'] . ' ' . ($s['lrn'] ?? '')); ?>">
                                        
                                        <!-- Student Name & LRN -->
                                        <td class="py-3 px-4 font-medium text-slate-800 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold mr-3 text-xs shrink-0">
                                                    <?php echo strtoupper(substr($s['fname'], 0, 1)); ?>
                                                </div>
                                                <div class="leading-tight">
                                                    <span class="font-bold text-slate-800 block text-xs sm:text-sm"><?php echo $name; ?></span>
                                                    <span class="inline-block mt-0.5 px-1.5 py-0.5 rounded font-mono text-[11px] font-semibold bg-slate-100 text-slate-600 border border-slate-200/80">
                                                        <span class="text-slate-400 font-sans text-[10px] mr-1">LRN</span><?php echo htmlspecialchars($s['lrn'] ?? 'N/A'); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        
                                        <!-- Grade & Section -->
                                        <td class="py-3 px-4 text-slate-600 text-xs font-medium whitespace-nowrap">
                                            <?php echo htmlspecialchars(($s['grade_level'] ?? '') . ' - ' . $clean_sec); ?>
                                        </td>
                                        
                                        <!-- GST Result (Diagnostic) -->
                                        <td class="py-3 px-4 bg-blue-50/20 border-l border-slate-200">
                                            <?php if ($s['gst_score'] !== null): ?>
                                                <div class="text-xs">
                                                    <span class="font-bold text-slate-800"><?php echo htmlspecialchars($s['gst_score'] . '/' . $s['gst_total']); ?></span>
                                                    <span class="text-[10px] text-slate-500 font-medium">(<?php echo number_format($s['gst_pct'], 1); ?>%)</span>
                                                </div>
                                                <?php if ((int)$s['needs_individual_assessment'] === 0): ?>
                                                    <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800 mt-0.5">
                                                        <i class="fas fa-check-circle text-[9px] mr-0.5"></i> Exempt
                                                    </span>
                                                <?php else: ?>
                                                    <div class="mt-0.5">
                                                        <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800">
                                                            Needs Indiv.
                                                        </span>
                                                        <?php if (!empty($s['starting_grade'])): ?>
                                                            <div class="text-[10px] text-blue-600 font-semibold mt-0.5">Start: Gr <?php echo htmlspecialchars($s['starting_grade']); ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-xs text-slate-400 font-medium">Not Taken</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Cascading Profile (Diagnostic) -->
                                        <td class="py-3 px-4 bg-blue-50/20 border-r border-slate-200">
                                            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold <?php echo $casc_badge; ?>">
                                                <?php echo htmlspecialchars($cascading_status); ?>
                                            </span>
                                            <?php if ($cascading_status === 'Independent' && (int)($s['needs_individual_assessment'] ?? 1) === 0): ?>
                                                <div class="text-[10px] text-slate-500 font-medium mt-0.5">Exempt (GST Passed)</div>
                                            <?php elseif ($s['instructional_grade'] !== null || $s['frustration_grade'] !== null || $s['independent_grade'] !== null): ?>
                                                <div class="text-[10px] text-slate-500 mt-0.5 space-x-1">
                                                    <?php if ($s['instructional_grade']): ?><span>Ins: <b>Gr <?php echo htmlspecialchars($s['instructional_grade']); ?></b></span><?php endif; ?>
                                                    <?php if ($s['frustration_grade']): ?><span>| Fru: <b>Gr <?php echo htmlspecialchars($s['frustration_grade']); ?></b></span><?php endif; ?>
                                                </div>
                                            <?php elseif ($s['gst_score'] !== null): ?>
                                                <div class="text-[10px] text-amber-600 font-medium mt-0.5">Pending Passage</div>
                                            <?php else: ?>
                                                <div class="text-[10px] text-slate-400 mt-0.5">Pending GST</div>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Course Pre-Test Result -->
                                        <td class="py-3 px-4 bg-emerald-50/20">
                                            <?php if ($pre): ?>
                                                <div class="text-xs">
                                                    <span class="font-bold text-slate-800"><?php echo number_format($pre['accuracy_score'], 1); ?>%</span> <span class="text-[10px] text-slate-500">Acc</span>
                                                    <span class="text-slate-300 mx-1">|</span>
                                                    <span class="font-bold text-blue-600"><?php echo number_format($pre['comprehension_score'], 1); ?>%</span> <span class="text-[10px] text-slate-500">Comp</span>
                                                    <div class="text-[10px] font-semibold text-slate-600 mt-0.5"><?php echo htmlspecialchars($pre['oral_reading_profile'] ?? ''); ?></div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-xs text-slate-400 font-medium">Not Taken</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Course Post-Test Result -->
                                        <td class="py-3 px-4 bg-emerald-50/20 border-r border-slate-200">
                                            <?php if ($post): ?>
                                                <div class="text-xs">
                                                    <span class="font-bold text-slate-800"><?php echo number_format($post['accuracy_score'], 1); ?>%</span> <span class="text-[10px] text-slate-500">Acc</span>
                                                    <span class="text-slate-300 mx-1">|</span>
                                                    <span class="font-bold text-blue-600"><?php echo number_format($post['comprehension_score'], 1); ?>%</span> <span class="text-[10px] text-slate-500">Comp</span>
                                                    <?php 
                                                    if ($pre) {
                                                        $diffAcc = $post['accuracy_score'] - $pre['accuracy_score'];
                                                        $col = $diffAcc > 0 ? 'text-emerald-600' : ($diffAcc < 0 ? 'text-rose-600' : 'text-slate-400');
                                                        $sign = $diffAcc > 0 ? '+' : '';
                                                        echo '<div class="text-[10px] font-bold ' . $col . ' mt-0.5">' . $sign . number_format($diffAcc, 1) . '% Acc (' . htmlspecialchars($post['oral_reading_profile'] ?? '') . ')</div>';
                                                    } else {
                                                        echo '<div class="text-[10px] font-semibold text-slate-600 mt-0.5">' . htmlspecialchars($post['oral_reading_profile'] ?? '') . '</div>';
                                                    }
                                                    ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-xs text-slate-400 font-medium">Not Taken</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Overall Remarks -->
                                        <td class="py-3 px-4">
                                            <span class="inline-block px-2.5 py-0.5 rounded-full text-xs <?php echo $remark_class; ?>">
                                                <?php echo htmlspecialchars($remark_text); ?>
                                            </span>
                                            <?php if (!empty($custom_tag)): ?>
                                                <div class="text-[10px] text-indigo-600 font-semibold mt-1">
                                                    <i class="fas fa-tag mr-0.5"></i> <?php echo htmlspecialchars($custom_tag); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Last Activity -->
                                        <td class="py-3 px-4 text-slate-500 text-xs whitespace-nowrap">
                                            <?php echo $last_act_str; ?>
                                        </td>

                                        <!-- Action Buttons -->
                                        <td class="py-3 px-4 text-right whitespace-nowrap">
                                            <a href="teacher_student_grades.php?user_id=<?php echo $s['id']; ?>" class="inline-flex items-center px-2.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded text-xs font-semibold transition shadow-2xs">
                                                <i class="fas fa-chart-line mr-1"></i> Course Grades
                                            </a>
                                            <?php if ($s['instructional_grade'] !== null || $s['frustration_grade'] !== null || $s['gst_score'] !== null): ?>
                                                <a href="student_submissions.php?user_id=<?php echo $s['id']; ?>" class="block text-[11px] text-blue-600 hover:text-blue-800 font-medium mt-1" title="Review Phil-IRI oral recordings and passage audio">
                                                    <i class="fas fa-microphone-alt mr-1 text-slate-400"></i> Pre-Assess Audio
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-100 mb-4">
                                <i class="fas fa-users text-2xl text-slate-400"></i>
                            </div>
                            <h3 class="text-lg font-medium text-slate-800 mb-1">No Students Found</h3>
                            <p class="text-slate-500">Enrolled students will appear here with their assessment and progress results.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Client-side Search and Filter Script -->
    <script>
        window.toggleSidebar = function() {
            const sidebar = document.getElementById('appSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            if(sidebar) sidebar.classList.toggle('-translate-x-full');
            if(overlay) overlay.classList.toggle('hidden');
        };

        function applyProgressFilters() {
            const searchVal = (document.getElementById('progressSearch')?.value || '').toLowerCase().trim();
            const gradeVal = (document.getElementById('progressGradeFilter')?.value || '').toLowerCase().trim();
            const sectionVal = (document.getElementById('progressSectionFilter')?.value || '').toLowerCase().trim();

            const rows = document.querySelectorAll('#progressTable tbody tr');
            rows.forEach(row => {
                const rowName = (row.getAttribute('data-name') || '').toLowerCase();
                const rowGrade = (row.getAttribute('data-grade') || '').toLowerCase();
                const rowSection = (row.getAttribute('data-section') || '').toLowerCase();

                const matchSearch = !searchVal || rowName.includes(searchVal);
                const matchGrade = !gradeVal || rowGrade === gradeVal;
                const matchSection = !sectionVal || rowSection === sectionVal;

                if (matchSearch && matchGrade && matchSection) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
