<?php
session_start();
require 'db.php';

// Redirect if not professor
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professor') {
    header("Location: login.php");
    exit();
}

$professor_id = (int)($_SESSION['user_id'] ?? 0);
$success = $error = '';
$flash = isset($_GET['msg']) ? trim($_GET['msg']) : '';

// Handle new post submission (optional block from your version)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_post'])) {
    $classroom_id = (int)($_POST['classroom_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if ($title === '' || $content === '') {
        $error = "Title and content cannot be empty.";
    } else {
        $stmt = $conn->prepare("INSERT INTO classroom_posts (classroom_id, professor_id, title, content) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $classroom_id, $professor_id, $title, $content);
        if ($stmt->execute()) {
            $success = "Post created successfully!";
        } else {
            $error = "Failed to create post: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch professor posts
$posts = $conn->query(
    "SELECT cp.id, cp.title, cp.content, cp.created_at, c.name AS classroom_name
     FROM classroom_posts cp
     JOIN classrooms c ON cp.classroom_id = c.id
     WHERE cp.professor_id = {$professor_id}
     ORDER BY cp.created_at DESC"
);

// Fetch classrooms for dropdown
$classrooms = $conn->query("SELECT id, name FROM classrooms ORDER BY name ASC");

// Fetch professor courses (show code + student counts)
$courses = $conn->query(
    "SELECT c.id, c.name, c.code,
            (SELECT COUNT(*) FROM course_enrollments ce WHERE ce.course_id = c.id) AS student_count
     FROM courses c
     WHERE c.professor_id = {$professor_id}
     ORDER BY c.created_at DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Professor Dashboard | Cloud Storage</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    :root { --primary:#4361ee; --secondary:#3f37c9; --accent:#4895ef; --danger:#f72585; --success:#10b981; --light:#f8f9fa; --dark:#212529; --border:#e5e7eb; }
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:'Poppins',sans-serif; background:linear-gradient(135deg,#f5f7fa 0%,#c3cfe2 100%); min-height:100vh; padding:20px; color:var(--dark); }
    .container { max-width: 1000px; margin: 0 auto; }
    h2 { color: var(--secondary); margin-bottom: 16px; }

    .btn { background-color: var(--primary); color:#fff; padding:10px 14px; border:none; border-radius:10px; cursor:pointer; transition:.2s; }
    .btn:hover { background-color: var(--secondary); }

    .tag { display:inline-block; background:#eef2ff; color:#3730a3; padding:4px 10px; border-radius:999px; font-weight:600; }
    .error { color:#b91c1c; padding:10px; background:#fee2e2; border:1px solid #fecaca; border-radius:8px; margin:10px 0; }
    .success { color:#065f46; padding:10px; background:#ecfdf5; border:1px solid #a7f3d0; border-radius:8px; margin:10px 0; }
    .flash { color:#065f46; padding:10px; background:#ecfdf5; border:1px solid #a7f3d0; border-radius:8px; margin:10px 0; }

    .card { background:#fff; padding:20px; border-radius:12px; box-shadow:0 5px 15px rgba(0,0,0,.06); margin-bottom:20px; }
    .form-group { margin-bottom:12px; }
    input, select, textarea { width:100%; padding:10px; border:1px solid #ddd; border-radius:10px; font-size:14px; }
    textarea { resize: vertical; }

    ul.courses { list-style:none; padding:0; margin:10px 0 0; }
    ul.courses li { padding:12px 0; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; gap:10px; }
    .actions a { display:inline-block; text-decoration:none; background:#f3f4f6; border:1px solid var(--border); padding:8px 10px; border-radius:8px; color:#111827; margin-left:8px; }
    .actions a:hover { background:#e5e7eb; }

    .post-card { background: white; padding: 15px; margin-bottom: 15px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05); }
    .post-card h3 { margin-bottom: 8px; color: var(--primary); }
    .post-card small { color: #555; }
    .post-card a { margin-right: 10px; color: var(--accent); text-decoration: none; }
    .post-card a:hover { text-decoration: underline; }
  </style>
</head>
<body>
<div class="container">
  <h2>Welcome, Professor</h2>

  <?php if ($flash): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

  <!-- Create Course & Upload Enrollment CSV (Task 2) -->
  <div class="card">
    <h3>Create a Course & Upload Enrollment CSV</h3>
    <p style="margin:6px 0 16px;color:#555;">
      This creates a new course, generates a unique course code, and loads students from your CSV
      (<code>student_university_id,cgpa</code>).
    </p>
    <form action="upload_csv.php" method="POST" enctype="multipart/form-data">
      <div class="form-group">
        <label for="course_name">Course Name<span style="color:#f72585"> *</span></label>
        <input type="text" id="course_name" name="course_name" placeholder="e.g., CSE360 - Software Engineering" required>
      </div>
      <div class="form-group">
        <label for="course_description">Description (optional)</label>
        <textarea id="course_description" name="course_description" rows="3" placeholder="Short course intro..."></textarea>
      </div>
      <div class="form-group">
        <label for="csv_file">Enrollment CSV<span style="color:#f72585"> *</span></label>
        <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
        <small style="display:block;margin-top:6px;color:#666;">Expected headers: <code>student_university_id,cgpa</code></small>
      </div>
      <input type="hidden" name="create_course" value="1">
      <button type="submit" class="btn"><i class="fas fa-plus"></i> Create Course</button>
    </form>
  </div>

  <!-- My Courses (links to Course, Taskboard, Repository) -->
  <div class="card">
    <h3>My Courses</h3>
    <?php if ($courses && $courses->num_rows > 0): ?>
      <ul class="courses">
        <?php while ($c = $courses->fetch_assoc()): ?>
          <li>
            <div>
              <a href="course_dashboard.php?id=<?= (int)$c['id'] ?>" style="text-decoration:none;color:inherit">
                <strong><?= htmlspecialchars($c['name']) ?></strong>
              </a>
              <span class="tag" style="margin-left:8px;">Code: <code><?= htmlspecialchars($c['code']) ?></code></span>
            </div>
            <div class="actions">
              <span class="tag">Students: <?= (int)$c['student_count'] ?></span>
              <a href="course_dashboard.php?id=<?= (int)$c['id'] ?>">Open Course</a>
              <a href="taskboard.php?course_id=<?= (int)$c['id'] ?>">Task Board</a>
              <a href="repository.php?course_id=<?= (int)$c['id'] ?>">Repository</a>
            </div>
          </li>
        <?php endwhile; ?>
      </ul>
    <?php else: ?>
      <p style="color:#555;">No courses yet. Create your first course using the form above.</p>
    <?php endif; ?>
  </div>

  <!-- Create New Post -->
  <div class="card">
    <h3>Create Classroom Post</h3>
    <form method="POST">
      <div class="form-group">
        <label>Classroom</label>
        <select name="classroom_id" required>
          <option value="">Select Classroom</option>
          <?php while ($row = $classrooms->fetch_assoc()): ?>
            <option value="<?= (int)$row['id'] ?>"><?= htmlspecialchars($row['name']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Title</label>
        <input type="text" name="title" required>
      </div>
      <div class="form-group">
        <label>Content</label>
        <textarea name="content" rows="4" required></textarea>
      </div>
      <button type="submit" name="create_post" class="btn"><i class="fas fa-plus"></i> Create Post</button>
    </form>
  </div>

  <!-- Manage Posts -->
  <h3>Your Posts</h3>
  <?php if ($posts && $posts->num_rows > 0): ?>
    <?php while ($post = $posts->fetch_assoc()): ?>
      <div class="post-card">
        <h3><?= htmlspecialchars($post['title']) ?></h3>
        <small>Classroom: <?= htmlspecialchars($post['classroom_name']) ?> | Created: <?= htmlspecialchars($post['created_at']) ?></small>
        <p><?= nl2br(htmlspecialchars($post['content'])) ?></p>
        <a href="edit_post.php?id=<?= (int)$post['id'] ?>"><i class="fas fa-edit"></i> Edit</a>
        <a href="delete_post.php?id=<?= (int)$post['id'] ?>" onclick="return confirm('Are you sure?')"><i class="fas fa-trash"></i> Delete</a>
      </div>
    <?php endwhile; ?>
  <?php else: ?>
    <p>No posts found.</p>
  <?php endif; ?>
</div>
</body>
</html>