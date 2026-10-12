<?php
session_start();
require_once 'config.php';

// RBAC Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: index.php");
    exit;
}

$filter_grade = trim($_GET['grade'] ?? '');
$filter_section = trim($_GET['section'] ?? '');
$filter_status = trim($_GET['status'] ?? '');

// Fetch students with reading profile & GST
$stmt = $pdo->prepare("
    SELECT u.*, 
           p.independent_grade, p.instructional_grade, p.frustration_grade,
           g.needs_individual_assessment
    FROM users u
    LEFT JOIN reading_profiles p ON u.id = p.user_id
    LEFT JOIN (
        SELECT user_id, needs_individual_assessment
        FROM gst_results
        WHERE id IN (SELECT MAX(id) FROM gst_results GROUP BY user_id)
    ) g ON u.id = g.user_id
    WHERE u.role = 'student' 
    ORDER BY u.grade_level ASC, u.section ASC, u.lname ASC, u.fname ASC
");
$stmt->execute();
$all_students = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filtered_students = [];
$counts = [
    'Independent' => 0,
    'Instructional' => 0,
    'Frustration' => 0,
    'Non-Reader' => 0,
    'Pending' => 0
];

foreach ($all_students as $s) {
    $enrolled_grade = intval(preg_replace('/[^0-9]/', '', $s['grade_level'] ?? ''));
    $final_status = 'Pending';
    
    if (isset($s['needs_individual_assessment']) && (int)$s['needs_individual_assessment'] === 0) {
        $final_status = 'Independent';
    } elseif ($s['independent_grade'] !== null || $s['instructional_grade'] !== null || $s['frustration_grade'] !== null) {
        if ($s['independent_grade'] !== null && $enrolled_grade <= intval($s['independent_grade'])) {
            $final_status = 'Independent';
        } elseif ($s['instructional_grade'] !== null && $enrolled_grade <= intval($s['instructional_grade'])) {
            $final_status = 'Instructional';
        } else {
            if ($s['frustration_grade'] !== null && intval($s['frustration_grade']) <= 4 && $s['instructional_grade'] === null && $s['independent_grade'] === null) {
                $final_status = 'Non-Reader';
            } else {
                $final_status = 'Frustration';
            }
        }
    }

    // Enrich with course assessment progress & custom teacher recommendations
    $courseSummary = get_student_course_progress_summary($pdo, $s['id']);
    $s['course_summary'] = $courseSummary;

    $profRank = ['non-reader' => 0, 'frustration' => 1, 'instructional' => 2, 'independent' => 3];
    if ($courseSummary['has_tests'] && !empty($courseSummary['latest_profile']) && $courseSummary['latest_profile'] !== 'Pending') {
        $courseProf = $courseSummary['latest_profile'];
        $courseRank = $profRank[strtolower(trim($courseProf))] ?? 1;
        $baselineRank = $profRank[strtolower(trim($final_status))] ?? -1;
        if ($courseSummary['post'] || $courseRank > $baselineRank || $final_status === 'Pending') {
            $final_status = ucfirst(strtolower(trim($courseProf)));
        }
    }
    
    $clean_section = preg_replace('/^Grade\s+\d+\s+/i', '', $s['section'] ?? '');
    $student_grade = trim($s['grade_level'] ?? '');

    // Apply Filters
    if (!empty($filter_grade) && strcasecmp($student_grade, $filter_grade) !== 0) {
        continue;
    }
    if (!empty($filter_section) && strcasecmp($clean_section, $filter_section) !== 0 && strcasecmp($s['section'] ?? '', $filter_section) !== 0) {
        continue;
    }
    if (!empty($filter_status) && strcasecmp($final_status, $filter_status) !== 0) {
        continue;
    }

    // Status Remarks
    if (!empty($courseSummary['has_custom_rec']) && !empty($courseSummary['condition_tag'])) {
        $remarks = 'Teacher Note: ' . $courseSummary['condition_tag'];
    } elseif (!empty($courseSummary['has_custom_rec']) && !empty($courseSummary['recommendation_text'])) {
        $remarks = 'Teacher Recommendation Applied';
    } elseif (!empty($courseSummary['post'])) {
        $remarks = $courseSummary['outcome'] . ' (' . $final_status . ') - Post-Test Completed';
    } elseif (!empty($courseSummary['pre'])) {
        $remarks = 'Course Pre-Test Completed (In Progress)';
    } else {
        if ($final_status === 'Independent') {
            $remarks = 'Mastery / Grade-Ready (Exempt or Passed)';
        } elseif ($final_status === 'Instructional') {
            $remarks = 'Recommended for Guided Reading Practice';
        } elseif ($final_status === 'Frustration') {
            $remarks = 'Requires Targeted Reading Support';
        } elseif ($final_status === 'Non-Reader') {
            $remarks = 'Urgent: Individual Reading Intervention';
        } else {
            $remarks = 'Awaiting Assessment / GST';
        }
    }

    $s['computed_profile'] = $final_status;
    $s['computed_remarks'] = $remarks;
    $s['clean_section'] = $clean_section;

    if (isset($counts[$final_status])) {
        $counts[$final_status]++;
    }

    $filtered_students[] = $s;
}

$total = count($filtered_students);

// Generate Filename
$filename_parts = ['ARAL_Report'];
if (!empty($filter_grade)) $filename_parts[] = str_replace(' ', '', $filter_grade);
if (!empty($filter_section)) $filename_parts[] = str_replace(' ', '', $filter_section);
if (!empty($filter_status)) $filename_parts[] = str_replace(' ', '', $filter_status);
$filename_parts[] = date('Ymd_His');
$filename = implode('_', $filename_parts) . '.txt';

header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Build Text Content
$out = [];
$out[] = "==============================================================================================================";
$out[] = "                                   PHIL-IRI ARAL READING ASSESSMENT PROGRAM                                   ";
$out[] = "                                        STUDENT MASTERLIST & PROGRESS REPORT                                   ";
$out[] = "==============================================================================================================";
$out[] = sprintf("Generated On   : %s", date('F d, Y h:i A'));
$out[] = sprintf("Generated By   : %s (Teacher)", $_SESSION['fname'] ?? 'Teacher');
$out[] = sprintf("Filter Grade   : %s", !empty($filter_grade) ? $filter_grade : "All Grades");
$out[] = sprintf("Filter Section : %s", !empty($filter_section) ? $filter_section : "All Sections");
$out[] = sprintf("Filter Profile : %s", !empty($filter_status) ? $filter_status : "All Profiles");
$out[] = "--------------------------------------------------------------------------------------------------------------";
$out[] = "SUMMARY STATISTICS:";
$out[] = sprintf("  Total Students in Report : %d", $total);
$out[] = sprintf("  - Independent Readers    : %d (%s%%)", $counts['Independent'], $total > 0 ? round(($counts['Independent']/$total)*100, 1) : 0);
$out[] = sprintf("  - Instructional Readers  : %d (%s%%)", $counts['Instructional'], $total > 0 ? round(($counts['Instructional']/$total)*100, 1) : 0);
$out[] = sprintf("  - Frustration Readers    : %d (%s%%)", $counts['Frustration'], $total > 0 ? round(($counts['Frustration']/$total)*100, 1) : 0);
$out[] = sprintf("  - Non-Readers            : %d (%s%%)", $counts['Non-Reader'], $total > 0 ? round(($counts['Non-Reader']/$total)*100, 1) : 0);
$out[] = sprintf("  - Pending Assessment     : %d (%s%%)", $counts['Pending'], $total > 0 ? round(($counts['Pending']/$total)*100, 1) : 0);
$out[] = "==============================================================================================================";
$out[] = sprintf("%-4s | %-26s | %-13s | %-18s | %-14s | %s", "NO.", "STUDENT NAME", "LRN", "GRADE & SECTION", "ORAL PROFILE", "REMARKS");
$out[] = "-----+----------------------------+---------------+--------------------+----------------+---------------------";

if ($total === 0) {
    $out[] = "     No students found matching the selected filter criteria.";
} else {
    $num = 1;
    foreach ($filtered_students as $st) {
        $mi = !empty($st['mname']) ? strtoupper(substr($st['mname'], 0, 1)) . '.' : '';
        $name = trim(($st['lname'] ?? '') . ', ' . ($st['fname'] ?? '') . ' ' . $mi);
        if (mb_strlen($name) > 26) {
            $name = mb_substr($name, 0, 23) . '...';
        }

        $grSec = trim(($st['grade_level'] ?? '') . ' - ' . ($st['clean_section'] ?? ''));
        if (mb_strlen($grSec) > 18) {
            $grSec = mb_substr($grSec, 0, 15) . '...';
        }

        $out[] = sprintf(
            "%-4d | %-26s | %-13s | %-18s | %-14s | %s",
            $num++,
            $name,
            $st['lrn'] ?? 'N/A',
            $grSec,
            $st['computed_profile'] ?? 'Pending',
            $st['computed_remarks'] ?? ''
        );
    }
}

$out[] = "==============================================================================================================";
$out[] = "Official DepEd Phil-IRI Reference: DepEd Order No. 14, s. 2018.";
$out[] = "Report generated by ARAL Reading Assessment System.";
$out[] = "";

echo implode("\r\n", $out);
exit;
