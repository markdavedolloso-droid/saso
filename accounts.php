<?php
// User account management page for administrators.
// It allows creation of new profiles and displays the current list of users.
$page_title = "User Accounts";
$page_heading = "User Accounts";

require_once "config/database.php";
require_once "includes/auth.php";
require_role(["admin"]);

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();

    try {
        $hash = password_hash($_POST["password"], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            "INSERT INTO profiles(email, password, role, first_name, last_name) VALUES(?,?,?,?,?)"
        );
        $stmt->execute([
            trim($_POST["email"] ?? ""),
            $hash,
            $_POST["role"] ?? "",
            trim($_POST["first_name"] ?? ""),
            trim($_POST["last_name"] ?? ""),
        ]);

        $q = $pdo->prepare("SELECT id FROM profiles WHERE email=? LIMIT 1");
        $q->execute([trim($_POST["email"] ?? "")]);
        log_action($pdo, "created", "User Accounts", $q->fetchColumn(), "Created a new {$_POST['role']} account.");

        header("Location: accounts.php");
        exit;
    } catch (PDOException $exception) {
        $error = $exception->getCode() === "23000"
            ? "An account with that email already exists."
            : "Unable to create the account right now.";
    }
}

$rows = $pdo
    ->query("SELECT id, email, role, first_name, last_name, created_at FROM profiles ORDER BY created_at DESC")
    ->fetchAll();

include_once "includes/header.php";
?>

<div class="page-actions">
    <p class="muted">Manage login accounts and access roles.</p>
    <button class="btn primary" onclick="openModal('accountModal')">+ Add Account</button>
</div>

<?php if ($error): ?><div class="alert danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<section class="card">
    <table>
        <thead>
            <tr><th>Name</th><th>Email</th><th>Role</th><th>Created</th></tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row["first_name"] . " " . $row["last_name"]) ?></td>
                    <td><?= htmlspecialchars($row["email"]) ?></td>
                    <td><span class="badge gold"><?= htmlspecialchars($row["role"]) ?></span></td>
                    <td><?= htmlspecialchars($row["created_at"]) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<div class="modal" id="accountModal">
    <div class="modal-box">
        <button class="close" onclick="closeModal('accountModal')">×</button>
        <h2>Create Account</h2>
        <form method="post" class="form-grid"><?= csrf_field() ?>
            <label>First Name<input name="first_name" required></label>
            <label>Last Name<input name="last_name" required></label>
            <label>Email<input type="email" name="email" required></label>
            <label>Role<select name="role"><option>admin</option><option>registrar</option><option>student</option></select></label>
            <label class="wide">Password<input type="password" name="password" required minlength="6"></label>
            <div class="form-end"><button class="btn primary">Create</button></div>
        </form>
    </div>
</div>

<?php include_once "includes/footer.php"; ?>