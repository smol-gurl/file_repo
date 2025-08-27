<?php
// course_dashboard.php — enrollments, custom groups, auto-group by CGPA, and group management (Task 2 complete)
session_start();
require 'db.php';

// ---- Dev diagnostics (comment out in prod) ----
// error_reporting(E_ALL); ini_set('display_errors', 1);
// if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); }

// ---- Auth (professor only) ----
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professor') {
    header('Location: login.php'); exit();
}
$professor_id = (int)($_SESSION['user_id'] ?? 0);

// ---- Course param + ownership check ----
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($course_id <= 0) { header('Location: professor_dashboard.php'); exit(); }

$stmt = $conn->prepare("SELECT id,name,code,created_at,professor_id FROM courses WHERE id=? LIMIT 1");
$stmt->bind_param('i', $course_id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$course || (int)$course['professor_id'] !== $professor_id) {
    header('Location: professor_dashboard.php'); exit();
}

function redirect_with($cid, $msg){
    header("Location: course_dashboard.php?id={$cid}&msg=".urlencode($msg));
    exit();
}

$flash = isset($_GET['msg']) ? trim($_GET['msg']) : '';

// ---- Remember last used group size in session ----
if (isset($_POST['group_size']) && is_numeric($_POST['group_size'])) {
    $_SESSION['last_group_size'] = max(2, min(10, (int)$_POST['group_size']));
}

/* =========================================================
   Actions
   ========================================================= */

// Create custom group
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_custom_group') {
    $group_name = trim($_POST['group_name'] ?? '');
    $members    = isset($_POST['members']) && is_array($_POST['members']) ? array_values($_POST['members']) : [];
    if ($group_name === '') redirect_with($course_id, 'Group name is required.');
    if (empty($members))    redirect_with($course_id, 'Select at least one student.');

    // Validate students belong to this course and grab CGPAs
    $placeholders = implode(',', array_fill(0, count($members), '?'));
    $types = 'i'.str_repeat('s', count($members));
    $sql = "SELECT student_university_id, student_cgpa
            FROM course_enrollments
            WHERE course_id=? AND student_university_id IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    $params = array_merge([$course_id], $members);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $valid_rows = []; $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $valid_rows[] = $r;
    $stmt->close();
    if (!$valid_rows) redirect_with($course_id, 'Selected students are not in this course.');

    // Create group
    $stmt = $conn->prepare("INSERT INTO `groups` (course_id, name) VALUES (?, ?)");
    $stmt->bind_param('is', $course_id, $group_name);
    $stmt->execute();
    $gid = (int)$stmt->insert_id;
    $stmt->close();

    // Add members
    $ins = $conn->prepare("INSERT IGNORE INTO group_members (group_id, student_university_id) VALUES (?, ?)");
    foreach ($valid_rows as $row) {
        $sid = $row['student_university_id'];
        $ins->bind_param('is', $gid, $sid);
        $ins->execute();
    }
    $ins->close();

    // Leader = highest CGPA in this group
    $leader=null; $cgmax=-INF;
    foreach ($valid_rows as $row){
        $cg=$row['student_cgpa'];
        if (is_numeric($cg) && (float)$cg>$cgmax){ $cgmax=(float)$cg; $leader=$row['student_university_id']; }
    }
    if ($leader){
        $u=$conn->prepare("UPDATE `groups` SET leader_student_id=? WHERE id=?");
        $u->bind_param('si',$leader,$gid);
        $u->execute(); $u->close();
    }

    redirect_with($course_id, "Group '{$group_name}' created" . ($leader ? " (Leader: {$leader})" : ""));
}

// Auto-group (and "Assign remaining")
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'auto_group') {
    $group_size = (int)($_POST['group_size'] ?? ($_SESSION['last_group_size'] ?? 3));
    $group_size = max(2, min(10, $group_size));
    $_SESSION['last_group_size'] = $group_size;

    // Unassigned students ordered by CGPA desc (NULLs last)
    $sql = "
      SELECT ce.student_university_id, ce.student_cgpa
      FROM course_enrollments ce
      WHERE ce.course_id = ?
        AND NOT EXISTS (
            SELECT 1 FROM group_members gm
            JOIN `groups` g ON g.id = gm.group_id
            WHERE g.course_id = ce.course_id AND gm.student_university_id = ce.student_university_id
        )
      ORDER BY (ce.student_cgpa IS NULL) ASC, ce.student_cgpa DESC, ce.student_university_id ASC
    ";
    $s = $conn->prepare($sql);
    $s->bind_param('i', $course_id);
    $s->execute();
    $r = $s->get_result();
    $unassigned=[]; while($row=$r->fetch_assoc()) $unassigned[]=$row;
    $s->close();

    if (count($unassigned) < 2) redirect_with($course_id, 'Not enough unassigned students to form groups.');

    $conn->begin_transaction();
    try {
        $insG = $conn->prepare("INSERT INTO `groups` (course_id, name) VALUES (?, ?)");
        $insM = $conn->prepare("INSERT INTO group_members (group_id, student_university_id) VALUES (?, ?)");
        $updL = $conn->prepare("UPDATE `groups` SET leader_student_id = ? WHERE id = ?");

        $chunks = array_chunk($unassigned, $group_size);
        $created=0; $seq=1;
        foreach ($chunks as $chunk){
            $gname = "Auto Group {$seq}";
            $insG->bind_param('is',$course_id,$gname);
            $insG->execute(); $gid=(int)$insG->insert_id;

            $leader=null; $cgmax=-INF;
            foreach($chunk as $row){
                $sid=$row['student_university_id']; $cg=$row['student_cgpa'];
                $insM->bind_param('is',$gid,$sid); $insM->execute();
                if (is_numeric($cg) && (float)$cg>$cgmax){ $cgmax=(float)$cg; $leader=$sid; }
            }
            if ($leader){ $updL->bind_param('si',$leader,$gid); $updL->execute(); }
            $created++; $seq++;
        }

        $conn->commit();
        redirect_with($course_id, "Auto-grouped {$created} group(s) (size {$group_size}).");
    } catch (Throwable $e) {
        $conn->rollback();
        redirect_with($course_id, 'Auto-group failed: '.$e->getMessage());
    }
}

// Rename / Delete group / Remove member / Change leader
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['action'] ?? ''), ['delete_group','remove_member','rename_group','change_leader'], true)) {
    $gid = (int)($_POST['group_id'] ?? 0);
    if ($gid <= 0) redirect_with($course_id, 'Invalid group.');

    $chk = $conn->prepare("SELECT id FROM `groups` WHERE id=? AND course_id=?");
    $chk->bind_param('ii',$gid,$course_id);
    $chk->execute();
    $ok = $chk->get_result()->fetch_assoc();
    $chk->close();
    if (!$ok) redirect_with($course_id, 'Group not found for this course.');

    $act = $_POST['action'];

    if ($act === 'delete_group') {
        $d1=$conn->prepare("DELETE FROM group_members WHERE group_id=?");
        $d1->bind_param('i',$gid); $d1->execute(); $d1->close();
        $d2=$conn->prepare("DELETE FROM `groups` WHERE id=?");
        $d2->bind_param('i',$gid); $d2->execute(); $d2->close();
        redirect_with($course_id,'Group deleted.');
    }

    if ($act === 'rename_group') {
        $new = trim($_POST['new_name'] ?? '');
        if ($new==='') redirect_with($course_id,'New name cannot be empty.');
        $u=$conn->prepare("UPDATE `groups` SET name=? WHERE id=?");
        $u->bind_param('si',$new,$gid); $u->execute(); $u->close();
        redirect_with($course_id,'Group renamed.');
    }

    if ($act === 'remove_member') {
        $sid = trim($_POST['student_university_id'] ?? '');
        if ($sid==='') redirect_with($course_id,'Invalid member.');
        $rm=$conn->prepare("DELETE FROM group_members WHERE group_id=? AND student_university_id=?");
        $rm->bind_param('is',$gid,$sid); $rm->execute(); $rm->close();

        // Recompute leader (highest CGPA among remaining)
        $leader=null; $cgmax=-INF;
        $q=$conn->prepare("SELECT gm.student_university_id, ce.student_cgpa
                           FROM group_members gm
                           LEFT JOIN course_enrollments ce ON ce.course_id=? AND ce.student_university_id=gm.student_university_id
                           WHERE gm.group_id=?");
        $q->bind_param('ii',$course_id,$gid);
        $q->execute(); $res=$q->get_result();
        while($row=$res->fetch_assoc()){
            $cg=$row['student_cgpa'];
            if(is_numeric($cg) && (float)$cg>$cgmax){ $cgmax=(float)$cg; $leader=$row['student_university_id']; }
        }
        $q->close();
        $u=$conn->prepare("UPDATE `groups` SET leader_student_id=? WHERE id=?");
        $u->bind_param('si',$leader,$gid); $u->execute(); $u->close();

        redirect_with($course_id,'Member removed' . ($leader ? " (Leader: {$leader})" : ''));
    }

    if ($act === 'change_leader') {
        $sid = trim($_POST['student_university_id'] ?? '');
        if ($sid==='') redirect_with($course_id,'Select a member to promote as leader.');

        // Ensure the student is a member of this group
        $ck=$conn->prepare("SELECT 1 FROM group_members WHERE group_id=? AND student_university_id=? LIMIT 1");
        $ck->bind_param('is',$gid,$sid); $ck->execute();
        $is_member = (bool)$ck->get_result()->fetch_row(); $ck->close();
        if (!$is_member) redirect_with($course_id,'Selected student is not a member of this group.');

        $u=$conn->prepare("UPDATE `groups` SET leader_student_id=? WHERE id=?");
        $u->bind_param('si',$sid,$gid); $u->execute(); $u->close();
        redirect_with($course_id,"Leader changed to {$sid}.");
    }
}

/* =========================================================
   Data for page
   ========================================================= */

// Enrollments (with search)
$search = trim($_GET['q'] ?? '');
$enroll_sql = "SELECT student_university_id, student_cgpa FROM course_enrollments WHERE course_id = ?";
$params = [$course_id]; $types='i';
if ($search!==''){ $enroll_sql.=" AND student_university_id LIKE ?"; $params[]="%$search%"; $types.='s'; }
$enroll_sql.=" ORDER BY student_university_id ASC";
$es=$conn->prepare($enroll_sql); $es->bind_param($types, ...$params); $es->execute(); $students=$es->get_result(); $es->close();

// Totals
$c=$conn->prepare("SELECT COUNT(*) cnt FROM course_enrollments WHERE course_id=?");
$c->bind_param('i',$course_id); $c->execute();
$total_count=(int)$c->get_result()->fetch_assoc()['cnt']; $c->close();

// Unassigned count
$u=$conn->prepare("
  SELECT COUNT(*) cnt
  FROM course_enrollments ce
  WHERE ce.course_id=?
    AND NOT EXISTS (
      SELECT 1 FROM group_members gm
      JOIN `groups` g ON g.id=gm.group_id
      WHERE g.course_id=ce.course_id AND gm.student_university_id=ce.student_university_id
    )
");
$u->bind_param('i',$course_id); $u->execute();
$unassigned_count=(int)$u->get_result()->fetch_assoc()['cnt']; $u->close();

// Which students already belong to a group
$assigned=[];
$asg=$conn->prepare("SELECT gm.student_university_id FROM group_members gm JOIN `groups` g ON g.id=gm.group_id WHERE g.course_id=?");
$asg->bind_param('i',$course_id); $asg->execute();
$ar=$asg->get_result(); while($a=$ar->fetch_assoc()) $assigned[$a['student_university_id']]=true; $asg->close();

// Groups
$groups=[]; 
$g=$conn->prepare("SELECT id,name,leader_student_id,created_at FROM `groups` WHERE course_id=? ORDER BY created_at ASC, id ASC");
$g->bind_param('i',$course_id); $g->execute(); $gr=$g->get_result();
while($row=$gr->fetch_assoc()) $groups[]=$row; 
$g->close();

// Members by group
$members_by_group=[];
if ($groups){
  $gm=$conn->prepare("SELECT student_university_id FROM group_members WHERE group_id=? ORDER BY student_university_id ASC");
  foreach($groups as $gg){
    $gid=(int)$gg['id'];
    $gm->bind_param('i',$gid); $gm->execute(); $rr=$gm->get_result();
    $members_by_group[$gid]=[];
    while($m=$rr->fetch_assoc()) $members_by_group[$gid][]=$m['student_university_id'];
  }
  $gm->close();
}

$last_size = (int)($_SESSION['last_group_size'] ?? 3);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Course Dashboard | <?= htmlspecialchars($course['name']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  :root { --primary:#4361ee; --secondary:#3f37c9; --panel:#fff; --border:#e5e7eb; --muted:#6b7280; }
  body{margin:0;font-family:Poppins,system-ui,-apple-system,Segoe UI,Roboto;background:#f5f7fa;color:#111827}
  .container{max-width:1100px;margin:32px auto;padding:0 16px}
  .card{background:var(--panel);border-radius:12px;box-shadow:0 6px 18px rgba(0,0,0,.06);padding:18px;margin-bottom:18px}
  .btn{background:var(--primary);color:#fff;border:none;border-radius:10px;padding:10px 14px;cursor:pointer}
  .btn:hover{background:var(--secondary)}
  .pill{display:inline-block;background:#eef2ff;color:#3730a3;border-radius:999px;padding:4px 10px;font-weight:600}
  .muted{color:var(--muted)}
  .row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
  input[type="text"], input[type="number"]{padding:10px 12px;border:1px solid var(--border);border-radius:10px}
  table{width:100%;border-collapse:collapse;margin-top:12px}
  th,td{padding:10px;border-bottom:1px solid var(--border);text-align:left}
  th{background:#f9fafb;font-weight:600}
  .notice{padding:10px 12px;border-radius:10px;margin:10px 0;font-weight:500}
  .notice.ok{background:#ecfdf5;color:#065f46}
  .notice.warn{background:#fff7ed;color:#9a3412}
  .inline-form{display:inline-flex;gap:6px;align-items:center}
  .mini{padding:6px 10px;border-radius:8px;font-size:13px}
  .danger{background:#f43f5e}.danger:hover{background:#e11d48}
  .link{color:#3f37c9;text-decoration:none}
</style>
</head>
<body>
<div class="container">
  <a href="professor_dashboard.php" class="link">← Back to Professor Dashboard</a>

  <?php if ($flash): ?>
    <div class="notice <?= (stripos($flash,'created')!==false || stripos($flash,'Auto-grouped')!==false || stripos($flash,'renamed')!==false || stripos($flash,'deleted')!==false || stripos($flash,'removed')!==false || stripos($flash,'Leader changed')!==false) ? 'ok' : 'warn' ?>">
      <?= htmlspecialchars($flash) ?>
    </div>
  <?php endif; ?>

  <!-- Course header -->
  <div class="card">
    <div class="row" style="justify-content:space-between">
      <div>
        <h2 style="margin:0 0 6px"><?= htmlspecialchars($course['name']) ?></h2>
        <div class="muted">Code: <code><?= htmlspecialchars($course['code']) ?></code> · Created: <?= htmlspecialchars($course['created_at']) ?></div>
      </div>
      <div class="row">
        <span class="pill">Students: <?= (int)$total_count ?></span>
        <span class="pill" title="Students not in any group yet">Unassigned: <?= (int)$unassigned_count ?></span>

        <!-- Auto-Group control -->
        <form method="post" action="course_dashboard.php?id=<?= (int)$course_id ?>" class="row" style="gap:6px">
          <input type="hidden" name="action" value="auto_group">
          <input type="number" min="2" max="10" name="group_size" value="<?= (int)$last_size ?>" style="width:150px" title="Group size">
          <button class="btn" type="submit">Auto-Group by CGPA</button>
        </form>

        <!-- Assign remaining quick button (uses last size) -->
        <?php if ($unassigned_count > 0): ?>
          <form method="post" action="course_dashboard.php?id=<?= (int)$course_id ?>" class="inline-form">
            <input type="hidden" name="action" value="auto_group">
            <input type="hidden" name="group_size" value="<?= (int)$last_size ?>">
            <button class="btn mini" type="submit" title="Assign all unassigned using size <?= (int)$last_size ?>">Assign remaining (<?= (int)$last_size ?>)</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <div class="row" style="margin-top:10px">
      <a class="btn" href="taskboard.php?course_id=<?= (int)$course_id ?>">Task Board</a>
      <a class="btn" href="repository.php?course_id=<?= (int)$course_id ?>">Repository</a>
    </div>
  </div>

  <!-- Enrolled + Custom Group Creation -->
  <div class="card">
    <div class="row" style="justify-content:space-between;align-items:flex-end">
      <div>
        <h3 style="margin:0">Enrolled Students</h3>
        <p class="muted" style="margin:6px 0 0">Tick unassigned students and create a custom group.</p>
      </div>
      <form method="get" action="course_dashboard.php" class="row">
        <input type="hidden" name="id" value="<?= (int)$course_id ?>">
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search by University ID">
        <button class="btn" type="submit">Search</button>
      </form>
    </div>

    <form method="post" action="course_dashboard.php?id=<?= (int)$course_id ?>">
      <input type="hidden" name="action" value="create_custom_group">
      <div class="row" style="margin:10px 0">
        <input type="text" name="group_name" placeholder="New group name (e.g., Group Alpha)" style="min-width:280px" required>
        <button class="btn" type="submit">+ Create Group with Selected</button>
      </div>

      <?php if ($students->num_rows > 0): ?>
        <table>
          <thead><tr><th style="width:36px">Select</th><th style="width:50%">Student University ID</th><th>CGPA</th><th>Status</th></tr></thead>
          <tbody>
            <?php while($s=$students->fetch_assoc()):
              $sid=$s['student_university_id']; $in_group=isset($assigned[$sid]); ?>
              <tr>
                <td><input type="checkbox" name="members[]" value="<?= htmlspecialchars($sid) ?>" <?= $in_group ? 'disabled' : '' ?>></td>
                <td><?= htmlspecialchars($sid) ?></td>
                <td><?= is_null($s['student_cgpa']) ? '<span class="muted">—</span>' : htmlspecialchars(number_format((float)$s['student_cgpa'], 2)) ?></td>
                <td><?= $in_group ? '<span class="muted">Already in a group</span>' : '<span class="muted">Unassigned</span>' ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="muted" style="margin-top:12px">No students found<?= $search ? ' (filter applied)' : '' ?>.</p>
      <?php endif; ?>
    </form>
  </div>

  <!-- Groups (manage) -->
  <div class="card">
    <h3 style="margin:0 0 10px">Groups</h3>
    <?php if (!empty($groups)): ?>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:12px;">
        <?php foreach($groups as $g):
          $gid=(int)$g['id']; $members=$members_by_group[$gid] ?? []; ?>
          <div class="card" style="box-shadow:none;border:1px solid var(--border);margin:0">
            <div class="row" style="justify-content:space-between;align-items:center">
              <strong><?= htmlspecialchars($g['name']) ?></strong>
              <div class="row">
                <?php if (!empty($g['leader_student_id'])): ?>
                  <span class="pill">Leader: <?= htmlspecialchars($g['leader_student_id']) ?></span>
                <?php else: ?>
                  <span class="pill">Leader: —</span>
                <?php endif; ?>
                <form class="inline-form" method="post" action="course_dashboard.php?id=<?= (int)$course_id ?>">
                  <input type="hidden" name="action" value="rename_group">
                  <input type="hidden" name="group_id" value="<?= $gid ?>">
                  <input type="text" name="new_name" placeholder="Rename…" style="padding:6px 8px;border:1px solid var(--border);border-radius:8px;min-width:120px">
                  <button class="btn mini" type="submit">Rename</button>
                </form>
                <form class="inline-form" method="post" action="course_dashboard.php?id=<?= (int)$course_id ?>" onsubmit="return confirm('Delete this group?');">
                  <input type="hidden" name="action" value="delete_group">
                  <input type="hidden" name="group_id" value="<?= $gid ?>">
                  <button class="btn mini danger" type="submit">Delete</button>
                </form>
              </div>
            </div>

            <?php if ($members): ?>
              <!-- Change leader dropdown -->
              <form class="inline-form" method="post" action="course_dashboard.php?id=<?= (int)$course_id ?>" style="margin-top:8px">
                <input type="hidden" name="action" value="change_leader">
                <input type="hidden" name="group_id" value="<?= $gid ?>">
                <select name="student_university_id" style="padding:6px 8px;border:1px solid var(--border);border-radius:8px">
                  <?php foreach ($members as $sid): ?>
                    <option value="<?= htmlspecialchars($sid) ?>" <?= (!empty($g['leader_student_id']) && $g['leader_student_id']===$sid)?'selected':'' ?>>
                      <?= htmlspecialchars($sid) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <button class="btn mini" type="submit">Change Leader</button>
              </form>

              <ul style="list-style:none;padding:0;margin:10px 0 0">
                <?php foreach ($members as $sid): ?>
                  <li style="padding:6px 0;border-bottom:1px solid #f3f4f6;display:flex;justify-content:space-between;align-items:center">
                    <div>
                      <?= htmlspecialchars($sid) ?>
                      <?php if (!empty($g['leader_student_id']) && $g['leader_student_id'] === $sid): ?>
                        <span style="color:#10b981;font-weight:600;"> (Leader)</span>
                      <?php endif; ?>
                    </div>
                    <form method="post" action="course_dashboard.php?id=<?= (int)$course_id ?>" onsubmit="return confirm('Remove this student from the group?');">
                      <input type="hidden" name="action" value="remove_member">
                      <input type="hidden" name="group_id" value="<?= $gid ?>">
                      <input type="hidden" name="student_university_id" value="<?= htmlspecialchars($sid) ?>">
                      <button class="btn mini danger" type="submit">Remove</button>
                    </form>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <p class="muted" style="margin:8px 0 0">No members yet.</p>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="muted">No groups created yet.</p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>