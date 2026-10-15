<?php
session_start();
require_once 'config.php';

// RBAC Check for Teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: index.php");
    exit;
}

// Fetch all students who have a finalized reading profile OR a completed GST result
$stmt = $pdo->query("
    SELECT u.id as student_id, u.fname, u.lname, u.grade_level, u.section,
           rp.independent_grade, rp.instructional_grade, rp.frustration_grade,
           g.needs_individual_assessment,
           COALESCE(rp.updated_at, g.completed_at) as updated_at
    FROM users u
    LEFT JOIN reading_profiles rp ON u.id = rp.user_id
    LEFT JOIN (
        SELECT user_id, needs_individual_assessment, completed_at
        FROM gst_results
        WHERE id IN (SELECT MAX(id) FROM gst_results GROUP BY user_id)
    ) g ON u.id = g.user_id
    WHERE u.role = 'student' AND (rp.user_id IS NOT NULL OR g.user_id IS NOT NULL)
    ORDER BY COALESCE(rp.updated_at, g.completed_at) DESC
");
$profiles = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="https://tibungcodistrict.wordpress.com/wp-content/uploads/2018/06/untitled-1.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment Results - Teacher Dashboard</title>
    <!-- Tailwind CSS -->
    <script>
        const originalWarn = console.warn;
        console.warn = function() {
            if (arguments[0] && typeof arguments[0] === 'string' && arguments[0].includes('cdn.tailwindcss.com should not be used in production')) return;
            originalWarn.apply(console, arguments);
        };
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #F8FAFC; }
            .sidebar { background-color: #1a365d; }
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

        /* Bug Fixes for hover states and cards */
        .bg-slate-100, .bg-slate-200 { background-color: #334155 !important; color: #e2e8f0 !important; }
        .hover\:bg-slate-50:hover, tr:hover { background-color: #334155 !important; }
        .card { background-color: #1e293b !important; border-color: #334155 !important; }
        
        /* Logo Fix */
        .sidebar img { background-color: transparent !important; filter: drop-shadow(0px 0px 2px rgba(255,255,255,0.5)) !important; }
        <?php endif; ?>
    </style>


    <script src="js/htmx.min.js"></script>
    <meta name="htmx-config" content='{"globalViewTransitions":true}'>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="flex h-screen overflow-hidden text-slate-800">
    <?php include 'teacher_sidebar.php'; ?>
    <div class="flex-1 flex flex-col h-screen overflow-hidden">
    
    <!-- Top Navbar -->
    <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 md:px-8 shrink-0">
        <div class="flex items-center">
            <a href="dashboard_teacher.php" class="text-slate-400 hover:text-slate-600 mr-4"><i class="fas fa-arrow-left"></i></a>
            <i class="fas fa-users text-rose-500 text-2xl mr-3"></i>
            <h1 class="font-bold text-lg text-slate-800">Student Profiles</h1>
        </div>
        <div class="flex items-center space-x-4">
            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-bold">
                <?php echo substr($_SESSION['fname'], 0, 1); ?>
            </div>
            <div class="leading-tight">
                <p class="font-semibold text-sm"><?php echo htmlspecialchars($_SESSION['fname']); ?></p>
                <p class="text-xs text-slate-500 capitalize"><?php echo htmlspecialchars($_SESSION['role']); ?></p>
            </div>
        </div>
    </header>

    <main class="flex-1 p-4 md:p-8 max-w-6xl mx-auto w-full overflow-y-auto">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between mb-6 gap-4">
                <h2 class="text-xl font-bold text-slate-800">Completed Pre-Assessments</h2>
                <div class="flex flex-wrap items-center gap-2">
                    <select id="assessmentGradeFilter" class="border border-slate-300 rounded px-3 py-1 text-sm focus:outline-none focus:border-blue-500 bg-white">
                        <option value="">All Grades</option>
                        <option value="Grade 7">Grade 7</option>
                        <option value="Grade 8">Grade 8</option>
                        <option value="Grade 9">Grade 9</option>
                        <option value="Grade 10">Grade 10</option>
                    </select>
                    <select id="assessmentProfileFilter" class="border border-slate-300 rounded px-3 py-1 text-sm focus:outline-none focus:border-blue-500 bg-white">
                        <option value="">All Profiles</option>
                        <option value="Independent">Independent</option>
                        <option value="Instructional">Instructional</option>
                        <option value="Frustration">Frustration</option>
                        <option value="Non-Reader">Non-Reader</option>
                    </select>
                    <input type="text" id="assessmentSearch" placeholder="Search results..." class="border border-slate-300 rounded px-3 py-1 text-sm focus:outline-none focus:border-blue-500 w-64">
                </div>
            </div>
            
            <?php if (count($profiles) > 0): ?>
                <div class="overflow-x-auto">
                    <table id="assessmentTable" class="w-full text-sm text-left border-collapse">
                        <thead class="text-xs text-slate-500 bg-slate-50 border-y border-slate-200">
                            <tr>
                                <th class="py-3 px-4 font-medium">Student</th>
                                <th class="py-3 px-4 font-medium">Grade & Section</th>
                                <th class="py-3 px-4 font-medium">Date Completed</th>
                                
                                <th class="py-3 px-4 font-bold text-slate-800 whitespace-nowrap">Oral Reading</th>
                                <th class="py-3 px-4 font-medium text-right no-sort">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach($profiles as $prof): 
                                $date = date('M d, Y g:i A', strtotime($prof['updated_at']));
                                
                                // Calculate Phil-IRI Oral Reading Profile based on Enrolled Grade Level
                                $enrolled_grade = intval(preg_replace('/[^0-9]/', '', $prof['grade_level']));
                                $final_status = 'Pending';
                                
                                if (isset($prof['needs_individual_assessment']) && $prof['needs_individual_assessment'] == 0) {
                                    $final_status = 'Independent';
                                } elseif ($prof['independent_grade'] !== null || $prof['instructional_grade'] !== null || $prof['frustration_grade'] !== null) {
                                    if ($prof['independent_grade'] !== null && $enrolled_grade <= intval($prof['independent_grade'])) {
                                        $final_status = 'Independent';
                                    } elseif ($prof['instructional_grade'] !== null && $enrolled_grade <= intval($prof['instructional_grade'])) {
                                        $final_status = 'Instructional';
                                    } else {
                                        if ($prof['frustration_grade'] !== null && intval($prof['frustration_grade']) <= 4 && $prof['instructional_grade'] === null && $prof['independent_grade'] === null) {
                                            $final_status = 'Non-Reader';
                                        } else {
                                            $final_status = 'Frustration';
                                        }
                                    }
                                }
                                
                                $display_verdict = $final_status;
                                
                                $status_color = 'bg-slate-100 text-slate-800';
                                if ($final_status === 'Frustration') $status_color = 'bg-rose-100 text-rose-800';
                                elseif ($final_status === 'Instructional') $status_color = 'bg-amber-100 text-amber-800';
                                elseif ($final_status === 'Independent') $status_color = 'bg-emerald-100 text-emerald-800';
                                elseif ($final_status === 'Non-Reader') $status_color = 'bg-slate-700 text-white';
                            ?>
                            <tr class="hover:bg-slate-50" data-grade="<?php echo htmlspecialchars($prof['grade_level']); ?>" data-profile="<?php echo htmlspecialchars(strtolower($display_verdict)); ?>">
                                <td class="py-3 px-4 font-medium text-slate-800"><?php echo htmlspecialchars($prof['fname'] . ' ' . $prof['lname']); ?></td>
                                <td class="py-3 px-4 text-slate-500"><?php echo htmlspecialchars($prof['grade_level'] . ' - ' . preg_replace('/^Grade\s+\d+\s+/i', '', $prof['section'])); ?></td>
                                <td class="py-3 px-4 text-slate-500"><?php echo $date; ?></td>
                                
                                <td class="py-3 px-4">
                                    <span class="inline-block whitespace-nowrap px-2.5 py-1 rounded-full text-xs font-bold <?php echo $status_color; ?>"><?php echo htmlspecialchars($display_verdict); ?></span>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <a href="student_submissions.php?user_id=<?php echo $prof['student_id']; ?>" class="inline-flex items-center px-3 py-1.5 bg-blue-50 text-blue-600 hover:bg-blue-100 rounded text-xs font-medium transition">
                                        <i class="fas fa-folder-open mr-1.5"></i> View Submissions
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-12">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-100 mb-4">
                        <i class="fas fa-inbox text-2xl text-slate-400"></i>
                    </div>
                    <h3 class="text-lg font-medium text-slate-800 mb-1">No assessments yet</h3>
                    <p class="text-slate-500">When students complete their pre-assessment, their final profiles will appear here.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

        </div>
<!-- Custom Table Filter -->
<script src="v536/public/table_filter.js?v=<?php echo time(); ?>"></script>
<script>
    function initAssessmentFilters() {
        initTailwindTable('assessmentTable', 'assessmentSearch', [
            { id: 'assessmentGradeFilter', colIndex: 1 },
            { id: 'assessmentProfileFilter', colIndex: 3 }
        ]);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAssessmentFilters);
    } else {
        initAssessmentFilters();
    }
</script>
<script>
        window.toggleSidebar = function() {
            const sidebar = document.getElementById('appSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            if(sidebar) sidebar.classList.toggle('-translate-x-full');
            if(overlay) overlay.classList.toggle('hidden');
        }
    </script>
</body>
</html>
