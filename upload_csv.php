<?php
// upload_csv.php — creates a course, generates a course code, loads CSV enrollments,
// and prints a human-friendly summary page (no blank screens).

session_start();
require 'db.php';

// ===== Temporary debug (helpful while developing) =====
// Comment these 3 lines out once things are stable.
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); }

// ===== Auth guard (professor only) =====
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professor') {
    header('Location: login.php');
    exit();
}
$professor_id = (int)($_SESSION['user_id'] ?? 0);

// ===== Helpers =====
function bad_request($title, $msg) {
    http_response_code(400);
    echo "<!doctype html><html><head><meta charset='utf-8'><title>Error</title>
          <style>body{font-family:system-ui, -apple-system, Segoe UI, Roboto; padding:24px;}</style>
          </head><body><h2>$title</h2><p>$msg</p>
          <p><a href='professor_dashboard.php'>← Back to Dashboard</a></p></body></html>";
    exit();
}

// Generate a readable unique course code like CSE101-ABC123 (prefix can be derived from name)
function generate_course_code(mysqli $conn, string $course_name): string {
    // Take letters/digits from name to form a short prefix
    $slug = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $course_name));
    if ($slug === '') $slug = 'COURSE';
    $prefix = substr($slug, 0, 6);

    // 3 random uppercase letters + 3 random digits
    $letters = '';
    for ($i=0; $i<3; $i++) { $letters .= chr(mt_rand(65, 90)); }
    $digits = str_pad((string)mt_rand(0, 999), 3, '0', STR_PAD_LEFT);

    $code = $prefix . '-' . $letters . $digits;

    // ensure unique
    $tries = 0;
    $stmt = $conn->prepare("SELECT 1 FROM courses WHERE code=? LIMIT 1");
    while ($tries < 10) {
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_row();
        if (!$exists) break;

        // regenerate and try again
        $letters = '';
        for ($i=0; $i<3; $i++) { $letters .= chr(mt_rand(65, 90)); }
        $digits = str_pad((string)mt_rand(0, 999), 3, '0', STR_PAD_LEFT);
        $code = $prefix . '-' . $letters . $digits;
        $tries++;
    }
    $stmt->close();
    return $code;
}

// ===== Validate POST + FILES =====
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['create_course'])) {
    bad_request('Error', 'Invalid request.');
}

$course_name = trim($_POST['course_name'] ?? '');
$course_description = trim($_POST['course_description'] ?? '');

if ($course_name === '') {
    bad_request('Error', 'Course name is required.');
}

if (!isset($_FILES['csv_file']) || !is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
    bad_request('Error', 'Please choose a CSV file to upload.');
}

// MIME/type sanity check (best-effort; browsers vary)
$allowed_mimes = [
    'text/csv',
    'text/plain',
    'application/csv',
    'application/vnd.ms-excel',
    'application/octet-stream', // some browsers report this
];
$mime = $_FILES['csv_file']['type'] ?? '';
$ext  = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
if (!in_array($mime, $allowed_mimes, true) && $ext !== 'csv') {
    bad_request('Error', 'The uploaded file does not look like a CSV.');
}

// Read and interpret header row (supports aliases and headerless CSV)
$fh = fopen($_FILES['csv_file']['tmp_name'], 'r');
if (!$fh) {
    bad_request('Error', 'Unable to read the uploaded CSV file.');
}

$header = fgetcsv($fh);
if ($header === false) {
    bad_request('Error', 'CSV appears to be empty.');
}

// Normalise values for comparison
$norm = function($v){ return strtolower(trim((string)$v)); };
$header_norm = array_map($norm, $header);

// Accept common aliases
$id_aliases   = ['student_university_id','student_id','roll','rollno','roll_no','regno','reg_no','registration','registration_no','id'];
$cgpa_aliases = ['cgpa','gpa'];

// Helper: detect if first row looks like *data* (e.g., "2022-1-60-132, 3.98") rather than a header
$looks_like_data = function(array $row) use ($norm) {
    $a = $norm($row[0] ?? '');
    $b = $norm($row[1] ?? '');
    $a_is_id   = (bool)preg_match('/^[0-9A-Za-z\-]+$/', $a);          // roll/registration like 2022-1-60-132
    $b_is_cgpa = is_numeric($b);
    return $a_is_id && $b_is_cgpa;
};

$has_header = true;
$id_idx = 0;    // default first column is ID
$cgpa_idx = 1;  // default second column is CGPA

// Try to map header names to indices
$map_idx = function(array $aliases, array $hdr) {
    foreach ($aliases as $alias) {
        $pos = array_search($alias, $hdr, true);
        if ($pos !== false) return (int)$pos;
    }
    return -1;
};

$id_pos   = $map_idx($id_aliases, $header_norm);
$cgpa_pos = $map_idx($cgpa_aliases, $header_norm);

if ($id_pos === -1 || $cgpa_pos === -1) {
    // If we didn't find headers, check if this first row is actually data
    if ($looks_like_data($header)) {
        // Treat first row as data; revert pointer to start reading rows, and use default indices 0/1
        $has_header = false;
        // Rewind to beginning and do not consume the first row
        rewind($fh);
    } else {
        $msg = "CSV must have headers that map to student_university_id and cgpa (case-insensitive)."
             . "<br><small>Accepted ID aliases: " . implode(', ', $id_aliases) . "</small>"
             . "<br><small>Accepted CGPA aliases: " . implode(', ', $cgpa_aliases) . "</small>"
             . "<br><small>Detected headers: " . htmlspecialchars(implode(', ', $header)) . ".</small>"
             . "<p>Tip: simplest is exactly <code>student_university_id,cgpa</code>.</p>";
        bad_request('Error', $msg);
    }
} else {
    // We found usable headers
    $id_idx = $id_pos;
    $cgpa_idx = $cgpa_pos;
}

// ===== Create the course first =====
$course_code = generate_course_code($conn, $course_name);
$stmt = $conn->prepare("INSERT INTO courses (name, code, professor_id, created_at) VALUES (?, ?, ?, NOW())");
$stmt->bind_param('ssi', $course_name, $course_code, $professor_id);
$stmt->execute();
$course_id = (int)$stmt->insert_id;
$stmt->close();

// ===== Load rows and insert =====
$ins_count = 0;
$upd_count = 0; // (we will skip duplicates by default; keeping this for future)
$skip_count = 0;
$skipped = [];   // duplicated / invalid rows
$inserted_ids = [];

$conn->begin_transaction();
try {
    // Prepared statements
    $ins = $conn->prepare("
        INSERT IGNORE INTO course_enrollments (course_id, student_university_id, student_cgpa, created_at)
        VALUES (?, ?, ?, NOW())
    ");

    // read the rest of the rows
    while (($row = fgetcsv($fh)) !== false) {
        // allow blank trailing columns safely
        $row = array_map(function($v){ return trim((string)$v); }, $row);

        $student_id = $row[$id_idx] ?? '';
        $cgpa_raw   = $row[$cgpa_idx] ?? '';

        if ($student_id === '') {
            $skip_count++;
            $skipped[] = ['reason' => 'Missing student_university_id', 'row' => $row];
            continue;
        }

        // CGPA: allow blank (store NULL), otherwise validate numeric 0–4 or 0–5 (adjust if needed)
        $cgpa = null;
        if ($cgpa_raw !== '') {
            if (!is_numeric($cgpa_raw)) {
                $skip_count++;
                $skipped[] = ['reason' => 'Non-numeric CGPA', 'row' => $row];
                continue;
            }
            $cg = (float)$cgpa_raw;
            if ($cg < 0 || $cg > 4.0) { // adjust to 5.0 if your scale is out of 5
                $skip_count++;
                $skipped[] = ['reason' => 'CGPA out of range (0–4.0)', 'row' => $row];
                continue;
            }
            $cgpa = $cg;
        }

        // Try insert; if duplicate (same course_id + student_university_id), INSERT IGNORE will skip
        // Schema should have a UNIQUE on (course_id, student_university_id) ideally.
        // Bind null properly:
        // mysqli requires "d" for double; use "s" for string ID; "i" for course_id; "d" for cgpa but allow null with set_param trick
        if ($cgpa === null) {
            // When binding NULL for a double, set as null and use "d", then call $ins->send_long_data is not needed.
            $ins->bind_param('isd', $course_id, $student_id, $cgpa);
        } else {
            $ins->bind_param('isd', $course_id, $student_id, $cgpa);
        }

        $ins->execute();
        if ($ins->affected_rows > 0) {
            $ins_count++;
            $inserted_ids[] = $student_id;
        } else {
            $skip_count++;
            $skipped[] = ['reason' => 'Duplicate (already enrolled or duplicate in CSV)', 'row' => $row];
        }
    }

    fclose($fh);
    $ins->close();

    $conn->commit();
} catch (Throwable $e) {
    fclose($fh);
    $conn->rollback();
    bad_request('Upload failed', 'Transaction rolled back: ' . htmlspecialchars($e->getMessage()));
}

// ===== Render summary page =====
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Course Created — Summary</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root{--primary:#4361ee;--secondary:#3f37c9;--ok:#065f46;--warn:#9a3412;--muted:#6b7280;--border:#e5e7eb}
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto; margin:0; padding:24px;
         background:linear-gradient(135deg,#f5f7fa 0%,#c3cfe2 100%)}
    .card{max-width:900px;margin:0 auto;background:#fff;border-radius:12px;
          box-shadow:0 6px 18px rgba(0,0,0,.06); padding:20px}
    .btn{display:inline-block;background:var(--primary);color:#fff;text-decoration:none;
         padding:10px 14px;border-radius:10px}
    .btn:hover{background:var(--secondary)}
    h1{margin:0 0 10px}
    .muted{color:var(--muted)}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{padding:10px;border-bottom:1px solid var(--border);text-align:left}
    th{background:#f9fafb}
    code{background:#f3f4f6;padding:2px 6px;border-radius:6px}
    .pill{display:inline-block;background:#eef2ff;color:#3730a3;border-radius:999px;padding:3px 10px;font-weight:600}
    .ok{color:var(--ok)} .warn{color:var(--warn)}
  </style>
</head>
<body>
  <div class="card">
    <h1>Course Created ✅</h1>
    <p class="muted">Your course and enrollments were processed.</p>

    <p>
      <strong>Course:</strong> <?= htmlspecialchars($course_name) ?><br>
      <strong>Code:</strong> <code><?= htmlspecialchars($course_code) ?></code>
    </p>

    <p>
      <span class="pill">Inserted: <?= (int)$ins_count ?></span>
      <span class="pill">Skipped: <?= (int)$skip_count ?></span>
    </p>

    <?php if (!empty($inserted_ids)): ?>
      <details>
        <summary>Show inserted student IDs</summary>
        <div class="muted" style="margin-top:8px"><?= htmlspecialchars(implode(', ', $inserted_ids)) ?></div>
      </details>
    <?php endif; ?>

    <?php if (!empty($skipped)): ?>
      <h3 style="margin-top:18px">Skipped rows (reason)</h3>
      <table>
        <thead><tr><th>Reason</th><th>Student ID</th><th>CGPA</th></tr></thead>
        <tbody>
        <?php foreach ($skipped as $s):
            $sid = $s['row'][0] ?? '';
            $cg  = $s['row'][1] ?? '';
        ?>
          <tr>
            <td class="warn"><?= htmlspecialchars($s['reason']) ?></td>
            <td><?= htmlspecialchars($sid) ?></td>
            <td><?= htmlspecialchars($cg) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <div style="margin-top:18px;display:flex;gap:12px;flex-wrap:wrap">
      <a class="btn" href="course_dashboard.php?id=<?= (int)$course_id ?>">Go to Course Dashboard</a>
      <a class="btn" href="professor_dashboard.php">Back to Professor Dashboard</a>
    </div>
  </div>
</body>
</html>