<?php
// User account management page for administrators.
// It allows creation of new profiles and displays the current list of users.
$page_title = "User Accounts";
$page_heading = "User Accounts";

require_once "config/database.php";
require_once "includes/auth.php";
require_role(["admin"]);
$user = current_user();

$error = "";
$successMessages = [
    'created' => 'Account created successfully.',
    'updated' => 'Account updated successfully.',
    'deactivated' => 'Account deactivated successfully.',
    'activated' => 'Account activated successfully.',
];
$success = $successMessages[$_GET['success'] ?? ''] ?? '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();

    try {
        $action = $_POST['action'] ?? 'create';
        $email = trim($_POST['email'] ?? '');
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $role = $_POST['role'] ?? '';
        if (in_array($action, ['create', 'update'], true) && (!filter_var($email, FILTER_VALIDATE_EMAIL) || $firstName === '' || $lastName === '' || !in_array($role, ['admin', 'registrar', 'student'], true))) {
            throw new RuntimeException('Enter a valid name, email, and account role.');
        }

        if ($action === 'create') {
            $password = (string) ($_POST['password'] ?? '');
            if (strlen($password) < 8) {
                throw new RuntimeException('New passwords must contain at least 8 characters.');
            }
            $stmt = $pdo->prepare(
                'INSERT INTO profiles(email, password, role, first_name, last_name) VALUES(?,?,?,?,?)'
            );
            $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT), $role, $firstName, $lastName]);
            $q = $pdo->prepare('SELECT id FROM profiles WHERE email=? LIMIT 1');
            $q->execute([$email]);
            log_action($pdo, 'created', 'User Accounts', $q->fetchColumn(), 'Created a new ' . $role . ' account.');
            header('Location: accounts.php?success=created');
            exit;
        }

        $targetId = trim((string) ($_POST['id'] ?? ''));
        $targetStmt = $pdo->prepare('SELECT id, email, role, status FROM profiles WHERE id = ? LIMIT 1');
        $targetStmt->execute([$targetId]);
        $target = $targetStmt->fetch();
        if (!$target) {
            throw new RuntimeException('The selected account no longer exists. Refresh the page and try again.');
        }

        if ($action === 'update') {
            $password = (string) ($_POST['password'] ?? '');
            if ($password !== '' && strlen($password) < 8) {
                throw new RuntimeException('New passwords must contain at least 8 characters.');
            }
            if ($targetId === ($user['id'] ?? '') && $role !== 'admin') {
                throw new RuntimeException('You cannot change your own administrator role.');
            }
            if ($target['role'] === 'admin' && $target['status'] === 'active' && $role !== 'admin') {
                $adminCount = (int) $pdo->query("SELECT COUNT(*) FROM profiles WHERE role='admin' AND status='active'")->fetchColumn();
                if ($adminCount <= 1) {
                    throw new RuntimeException('The system must keep at least one administrator account.');
                }
            }

            if ($password !== '') {
                $stmt = $pdo->prepare('UPDATE profiles SET email=?, role=?, first_name=?, last_name=?, password=? WHERE id=?');
                $stmt->execute([$email, $role, $firstName, $lastName, password_hash($password, PASSWORD_DEFAULT), $targetId]);
            } else {
                $stmt = $pdo->prepare('UPDATE profiles SET email=?, role=?, first_name=?, last_name=? WHERE id=?');
                $stmt->execute([$email, $role, $firstName, $lastName, $targetId]);
            }
            log_action($pdo, 'updated', 'User Accounts', $targetId, 'Updated account details for ' . $email . '.');
            header('Location: accounts.php?success=updated');
            exit;
        }

        if (in_array($action, ['deactivate', 'activate'], true)) {
            if ($targetId === ($user['id'] ?? '')) {
                throw new RuntimeException('You cannot deactivate the account you are currently using.');
            }
            if ($action === 'deactivate' && $target['status'] === 'active' && $target['role'] === 'admin') {
                $adminCount = (int) $pdo->query("SELECT COUNT(*) FROM profiles WHERE role='admin' AND status='active'")->fetchColumn();
                if ($adminCount <= 1) {
                    throw new RuntimeException('The last active administrator account cannot be deactivated.');
                }
            }

            $status = $action === 'deactivate' ? 'inactive' : 'active';
            $stmt = $pdo->prepare('UPDATE profiles SET status=? WHERE id=?');
            $stmt->execute([$status, $targetId]);
            $auditAction = $action === 'deactivate' ? 'deactivated' : 'activated';
            log_action($pdo, $auditAction, 'User Accounts', $targetId, ucfirst($auditAction) . ' account ' . $target['email'] . '.');
            header('Location: accounts.php?success=' . $action . 'd');
            exit;
        }

        throw new RuntimeException('Unknown account action.');
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $exception->getCode() === "23000"
            ? 'An account with that email already exists.'
            : 'Unable to save the account right now.';
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $exception->getMessage();
    }
}

$rows = $pdo
    ->query("SELECT id, email, role, status, first_name, last_name, created_at FROM profiles ORDER BY created_at DESC")
    ->fetchAll();

include_once "includes/header.php";
?>

<div class="page-actions">
    <p class="muted">Create and update login accounts, or deactivate access while retaining account and student records.</p>
    <button class="btn primary" type="button" onclick="newAccount()">+ Add Account</button>
</div>

<?php if ($success): ?><div class="alert success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<section class="card">
    <table>
        <thead>
            <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th><th>Actions</th></tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row["first_name"] . " " . $row["last_name"]) ?></td>
                    <td><?= htmlspecialchars($row["email"]) ?></td>
                    <td><span class="badge gold"><?= htmlspecialchars($row["role"]) ?></span></td>
                    <td><span class="badge <?= $row['status'] === 'active' ? 'green' : 'red' ?>"><?= htmlspecialchars(ucfirst($row['status'])) ?></span></td>
                    <td><?= htmlspecialchars($row["created_at"]) ?></td>
                    <td class="account-actions">
                        <button class="btn secondary" type="button" onclick='editAccount(<?= json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>Edit</button>
                        <?php if ($row['id'] !== ($user['id'] ?? '')): ?>
                            <form method="post" style="display:inline" data-confirmation="<?= $row['status'] === 'active' ? 'Deactivate this account? The account and linked records will be retained.' : 'Activate this account and allow it to sign in again?' ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="<?= $row['status'] === 'active' ? 'deactivate' : 'activate' ?>">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8') ?>">
                                <button class="btn secondary" type="submit"><?= $row['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
                            </form>
                        <?php else: ?>
                            <span class="muted">Current account</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="6">No accounts found.</td></tr><?php endif; ?>
        </tbody>
    </table>
</section>

<div class="modal" id="accountModal">
    <div class="modal-box">
        <button class="close" onclick="closeModal('accountModal')">×</button>
        <h2 id="accountModalTitle">Create Account</h2>
        <form method="post" class="form-grid"><?= csrf_field() ?>
            <input type="hidden" name="action" id="accountAction" value="create">
            <input type="hidden" name="id" id="accountId">
            <label>First Name<input name="first_name" id="accountFirstName" required maxlength="100"></label>
            <label>Last Name<input name="last_name" id="accountLastName" required maxlength="100"></label>
            <label>Email<input type="email" name="email" id="accountEmail" required maxlength="190"></label>
            <label>Role<select name="role" id="accountRole"><option value="admin">Admin</option><option value="registrar">Registrar</option><option value="student">Student</option></select></label>
            <label class="wide">Password<input type="password" name="password" id="accountPassword" minlength="8" autocomplete="new-password"><small class="muted" id="accountPasswordHint">At least 8 characters.</small></label>
            <div class="form-end"><button class="btn primary" id="accountSubmit">Create Account</button></div>
        </form>
    </div>
</div>

<script>
function newAccount() {
    document.getElementById('accountModalTitle').textContent = 'Create Account';
    document.getElementById('accountAction').value = 'create';
    document.getElementById('accountId').value = '';
    document.getElementById('accountFirstName').value = '';
    document.getElementById('accountLastName').value = '';
    document.getElementById('accountEmail').value = '';
    document.getElementById('accountRole').value = 'student';
    document.getElementById('accountPassword').value = '';
    document.getElementById('accountPassword').required = true;
    document.getElementById('accountPasswordHint').textContent = 'At least 8 characters.';
    document.getElementById('accountSubmit').textContent = 'Create Account';
    openModal('accountModal');
}

function editAccount(account) {
    document.getElementById('accountModalTitle').textContent = 'Update Account';
    document.getElementById('accountAction').value = 'update';
    document.getElementById('accountId').value = account.id;
    document.getElementById('accountFirstName').value = account.first_name;
    document.getElementById('accountLastName').value = account.last_name;
    document.getElementById('accountEmail').value = account.email;
    document.getElementById('accountRole').value = account.role;
    document.getElementById('accountPassword').value = '';
    document.getElementById('accountPassword').required = false;
    document.getElementById('accountPasswordHint').textContent = 'Leave blank to keep the current password.';
    document.getElementById('accountSubmit').textContent = 'Save Changes';
    openModal('accountModal');
}
</script>

<?php include_once "includes/footer.php"; ?>