<?php

// Coordinates student requests, registrar verification, SASO verification, and registrar processing.

$page_title = 'Good Moral Requests';
$page_heading = 'Good Moral Requests';

require_once 'config/database.php';
require_once 'includes/auth.php';

require_login();

$user = current_user();
$student = get_student_for_user($pdo);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'request' && $user['role'] === 'student') {
            if (!$student) {
                throw new Exception(
                    'Your student profile is not linked to this account. Ask SASO to link it.'
                );
            }

            $stmt = $pdo->prepare(
                "INSERT INTO good_moral_requests
                (student_id, requested_by, requester_name, purpose, status)
                VALUES (?, ?, ?, ?, 'pending_registrar')"
            );

            $stmt->execute([
                $student['id'],
                $user['id'],
                $user['first_name'] . ' ' . $user['last_name'],
                trim($_POST['purpose'])
            ]);

            log_action(
                $pdo,
                'created',
                'Good Moral',
                null,
                'Student submitted a Good Moral request.'
            );

            notify_role(
                $pdo,
                'registrar',
                'New Good Moral Request',
                $user['first_name'] . ' ' . $user['last_name'] .
                    ' submitted a Good Moral request and is waiting for Registrar verification.',
                'good_moral.php'
            );

            $message =
                'Your Good Moral request was submitted successfully and is now pending Registrar verification.';
        } elseif ($action === 'forward_saso' && $user['role'] === 'registrar') {
            $stmt = $pdo->prepare(
                "UPDATE good_moral_requests
                 SET status = 'pending_saso',
                     registrar_received_by = ?,
                     registrar_received_at = NOW(),
                     review_notes = ?
                 WHERE id = ?
                   AND status = 'pending_registrar'"
            );

            $stmt->execute([
                $user['first_name'] . ' ' . $user['last_name'],
                trim($_POST['notes'] ?? ''),
                $_POST['id']
            ]);

            if ($stmt->rowCount() === 0) {
                throw new Exception(
                    'This request could not be forwarded. It may have already been reviewed or forwarded by someone else — please refresh the page and try again.'
                );
            }

            log_action(
                $pdo,
                'forwarded',
                'Good Moral',
                $_POST['id'],
                'Registrar forwarded the Good Moral request to SASO for verification.'
            );

            $req = $pdo->prepare(
                'SELECT requested_by FROM good_moral_requests WHERE id = ?'
            );
            $req->execute([$_POST['id']]);
            $requesterId = $req->fetchColumn();

            notify(
                $pdo,
                $requesterId,
                'Good Moral Request Forwarded',
                'The Registrar reviewed your Good Moral request and forwarded it to SASO for verification.',
                'good_moral.php'
            );

            notify_role(
                $pdo,
                'admin',
                'Good Moral Request for SASO Verification',
                'A Good Moral request has been reviewed by the Registrar and is ready for SASO verification.',
                'good_moral.php'
            );

            $message = 'Good Moral request was forwarded to SASO for verification.';
        } elseif ($action === 'verify' && $user['role'] === 'admin') {
            $decision = $_POST['decision'] ?? 'verify';

            $status = $decision === 'hold'
                ? 'on_hold'
                : ($decision === 'reject' ? 'rejected' : 'saso_verified');

            $stmt = $pdo->prepare(
                "UPDATE good_moral_requests
                 SET status = ?,
                     saso_verified_by = ?,
                     saso_verified_at = NOW(),
                     saso_notes = ?
                 WHERE id = ?
                   AND status IN ('pending_saso', 'on_hold')"
            );

            $stmt->execute([
                $status,
                $user['first_name'] . ' ' . $user['last_name'],
                trim($_POST['notes'] ?? ''),
                $_POST['id']
            ]);

            if ($stmt->rowCount() === 0) {
                $currentStatusStmt = $pdo->prepare(
                    'SELECT status FROM good_moral_requests WHERE id = ?'
                );
                $currentStatusStmt->execute([$_POST['id']]);

                if ($currentStatusStmt->fetchColumn() !== $status) {
                    throw new Exception(
                        'This request could not be updated. It may have already been verified, rejected, or processed by someone else — please refresh the page and try again.'
                    );
                }
            }

            log_action(
                $pdo,
                'verified',
                'Good Moral',
                $_POST['id'],
                'SASO updated verification status to ' . $status . '.'
            );

            $req = $pdo->prepare(
                'SELECT requested_by FROM good_moral_requests WHERE id = ?'
            );

            $req->execute([$_POST['id']]);
            $requesterId = $req->fetchColumn();

            if ($status === 'saso_verified') {
                notify(
                    $pdo,
                    $requesterId,
                    'Good Moral Request Verified',
                    'SASO verified your Good Moral request. It has been forwarded to the Registrar for processing.',
                    'good_moral.php'
                );

                notify_role(
                    $pdo,
                    'registrar',
                    'Good Moral Request Ready',
                    'A Good Moral request has been SASO-verified and is ready for Registrar processing.',
                    'good_moral.php'
                );
            } elseif ($status === 'on_hold') {
                notify(
                    $pdo,
                    $requesterId,
                    'Good Moral Request On Hold',
                    'SASO placed your Good Moral request on hold.' .
                        (
                            trim($_POST['notes'] ?? '')
                                ? ' Note: ' . trim($_POST['notes'])
                                : ''
                        ),
                    'good_moral.php'
                );
            } elseif ($status === 'rejected') {
                notify(
                    $pdo,
                    $requesterId,
                    'Good Moral Request Rejected',
                    'SASO rejected your Good Moral request.' .
                        (
                            trim($_POST['notes'] ?? '')
                                ? ' Reason: ' . trim($_POST['notes'])
                                : ''
                        ),
                    'good_moral.php'
                );
            }

            $message = 'SASO verification was saved.';
        } elseif ($action === 'process' && $user['role'] === 'registrar') {
            $decision = $_POST['decision'] ?? 'processing';

            $status = $decision === 'complete'
                ? 'completed'
                : 'registrar_processing';

            $stmt = $pdo->prepare(
                "UPDATE good_moral_requests
                 SET status = ?,
                     registrar_processed_by = ?,
                     registrar_processed_at = NOW(),
                     review_notes = ?
                 WHERE id = ?
                   AND status IN ('saso_verified', 'registrar_processing')"
            );

            $stmt->execute([
                $status,
                $user['first_name'] . ' ' . $user['last_name'],
                trim($_POST['notes'] ?? ''),
                $_POST['id']
            ]);

            if ($stmt->rowCount() === 0) {
                throw new Exception(
                    'This request could not be updated. It may not be SASO-verified yet, or it was already completed/rejected by someone else — please refresh the page and try again.'
                );
            }

            log_action(
                $pdo,
                'processed',
                'Good Moral',
                $_POST['id'],
                'Registrar updated request status to ' . $status . '.'
            );

            $req = $pdo->prepare(
                'SELECT requested_by FROM good_moral_requests WHERE id = ?'
            );

            $req->execute([$_POST['id']]);
            $requesterId = $req->fetchColumn();

            if ($status === 'completed') {
                notify(
                    $pdo,
                    $requesterId,
                    'Good Moral Certificate Completed',
                    'Your Good Moral Certificate request has been completed. You may claim it from the Registrar.',
                    'good_moral.php'
                );
            } elseif ($status === 'rejected') {
                notify(
                    $pdo,
                    $requesterId,
                    'Good Moral Request Rejected',
                    'The Registrar rejected your Good Moral request.' .
                        (
                            trim($_POST['notes'] ?? '')
                                ? ' Reason: ' . trim($_POST['notes'])
                                : ''
                        ),
                    'good_moral.php'
                );
            } elseif ($status === 'registrar_processing') {
                notify(
                    $pdo,
                    $requesterId,
                    'Good Moral Request Processing',
                    'The Registrar is processing your Good Moral Certificate.',
                    'good_moral.php'
                );
            }

            $message = $status === 'completed'
                ? 'Good Moral request marked as completed.'
                : 'Good Moral request is now under Registrar processing.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($user['role'] === 'student') {
    $rows = [];

    if ($student) {
        $stmt = $pdo->prepare(
            "SELECT *
             FROM good_moral_requests
             WHERE student_id = ?
             ORDER BY created_at DESC"
        );

        $stmt->execute([$student['id']]);
        $rows = $stmt->fetchAll();
    }
} elseif ($user['role'] === 'registrar') {
    $rows = $pdo->query(
        "SELECT
            g.*,
            s.student_number,
            CONCAT(s.first_name, ' ', s.last_name) student_name,

            (SELECT COUNT(*)
             FROM violations v
             WHERE v.student_id = s.id) violation_count,

            (SELECT COUNT(*)
             FROM violations v
             WHERE v.student_id = s.id
               AND v.violation_type = 'major') major_violation_count,

            (SELECT COUNT(*)
             FROM violations v
             WHERE v.student_id = s.id
               AND v.status IN ('pending', 'under_review')) open_violation_count,

            (SELECT COUNT(*)
             FROM violations v
             WHERE v.student_id = s.id
               AND v.status = 'resolved') resolved_violation_count

         FROM good_moral_requests g
         JOIN students s ON s.id = g.student_id
         ORDER BY g.created_at DESC"
    )->fetchAll();
} else {
    $rows = $pdo->query(
        "SELECT
            g.*,
            s.student_number,
            CONCAT(s.first_name, ' ', s.last_name) student_name,

            (SELECT COUNT(*)
             FROM violations v
             WHERE v.student_id = s.id) violation_count,

            (SELECT COUNT(*)
             FROM violations v
             WHERE v.student_id = s.id
               AND v.violation_type = 'major') major_violation_count,

            (SELECT COUNT(*)
             FROM violations v
             WHERE v.student_id = s.id
               AND v.status IN ('pending', 'under_review')) open_violation_count,

            (SELECT COUNT(*)
             FROM violations v
             WHERE v.student_id = s.id
               AND v.status = 'resolved') resolved_violation_count

         FROM good_moral_requests g
         JOIN students s ON s.id = g.student_id
         ORDER BY g.created_at DESC"
    )->fetchAll();
}

$statusLabels = [
    'pending_registrar' => 'Pending Registrar Verification',
    'pending_saso' => 'Pending SASO Verification',
    'saso_verified' => 'SASO Verified',
    'registrar_processing' => 'Registrar Processing',
    'completed' => 'Completed',
    'rejected' => 'Rejected',
    'on_hold' => 'On Hold'
];

$statusStep = [
    'pending_registrar' => 1,
    'pending_saso' => 2,
    'saso_verified' => 3,
    'registrar_processing' => 4,
    'completed' => 5,
    'rejected' => 5,
    'on_hold' => 2
];

include 'includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert success">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert danger">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="page-actions">
    <p class="muted">
        <?php if ($user['role'] === 'student'): ?>
            Request and track your Good Moral Certificate request.
        <?php elseif ($user['role'] === 'registrar'): ?>
            Receive and verify Good Moral requests before forwarding them to SASO, then process SASO-verified requests.
        <?php else: ?>
            Review the SASO verification stage of Good Moral requests.
        <?php endif; ?>
    </p>

    <?php if ($user['role'] === 'student'): ?>
        <button class="btn primary" onclick="openModal('moralModal')">
            + Request Good Moral
        </button>
    <?php endif; ?>
</div>

<?php if ($user['role'] === 'student' && $rows): ?>
    <section class="card moral-guide">
        <div class="card-head">
            <h3>Good Moral Request Process</h3>
            <span class="badge blue">Track your request</span>
        </div>

        <div class="moral-steps">
            <div>
                <b>1</b>
                <span>Submits request</span>
            </div>

            <div>
                <b>2</b>
                <span>Registrar verifies student record</span>
            </div>

            <div>
                <b>3</b>
                <span>SASO verifies disciplinary standing</span>
            </div>

            <div>
                <b>4</b>
                <span>Registrar processes and completes</span>
            </div>
        </div>
    </section>
<?php endif; ?>

<section class="card">
    <table id="dataTable">
        <thead>
            <tr>
                <?php if ($user['role'] !== 'student'): ?>
                    <th>Student</th>
                <?php endif; ?>

                <th>Purpose</th>
                <th>SASO Comments</th>
                <th>Status</th>
                <th>Requested</th>
                <th>Action</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <?php if ($user['role'] !== 'student'): ?>
                        <td>
                            <?= htmlspecialchars(
                                $r['student_number'] . ' - ' . $r['student_name']
                            ) ?>
                        </td>
                    <?php endif; ?>

                    <td>
                        <?= htmlspecialchars($r['purpose']) ?>
                    </td>

                    <td>
                        <?php if (trim((string) ($r['saso_notes'] ?? '')) !== ''): ?>
                            <?= nl2br(htmlspecialchars($r['saso_notes'])) ?>
                        <?php else: ?>
                            <span class="muted">No comments</span>
                        <?php endif; ?>
                    </td>

                    <td>
                        <span class="badge <?= good_moral_badge_class($r['status']) ?>">
                            <?= htmlspecialchars(
                                $statusLabels[$r['status']] ?? $r['status']
                            ) ?>
                        </span>
                    </td>

                    <td>
                        <?= htmlspecialchars($r['created_at']) ?>
                    </td>

                    <td>
                        <?php if (
                            $user['role'] === 'admin' &&
                            in_array($r['status'], ['pending_saso', 'on_hold'])
                        ): ?>
                            <button
                                class="btn secondary"
                                onclick='openVerify(<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>)'
                            >
                                Verify
                            </button>

                        <?php elseif (
                            $user['role'] === 'registrar' &&
                            $r['status'] === 'pending_registrar'
                        ): ?>
                            <button
                                class="btn secondary"
                                onclick='openRegistrarVerify(<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>)'
                            >
                                Verify &amp; Send to SASO
                            </button>

                        <?php elseif (
                            $user['role'] === 'registrar' &&
                            in_array($r['status'], ['saso_verified', 'registrar_processing'])
                        ): ?>
                            <button
                                class="btn secondary"
                                onclick='openProcess(<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>)'
                            >
                                Process
                            </button>

                        <?php else: ?>
                            <span class="muted">Track only</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (!$rows): ?>
                <tr>
                    <td colspan="7">No requests found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>

<?php if ($user['role'] === 'student'): ?>
    <div class="modal" id="moralModal">
        <div class="modal-box">
            <button class="close" onclick="closeModal('moralModal')">
                ×
            </button>

            <h2>Request Good Moral Certificate</h2>

            <p class="hint">
                Your request will first be reviewed by the Registrar, then forwarded to SASO for disciplinary verification. You can monitor the request status here.
            </p>

            <form method="post">
                <?= csrf_field() ?>

                <input type="hidden" name="action" value="request">

                <label>
                    Purpose
                    <textarea
                        name="purpose"
                        rows="4"
                        required
                        placeholder="Scholarship, employment, transfer, etc."
                    ></textarea>
                </label>

                <button class="btn primary">
                    Submit Request
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if ($user['role'] === 'admin'): ?>
    <div class="modal" id="verifyModal">
        <div class="modal-box">
            <button class="close" onclick="closeModal('verifyModal')">
                ×
            </button>

            <h2>SASO Verification</h2>

            <p class="hint">
                Review the student's disciplinary standing before deciding whether
                to verify, place on hold, or reject the request.
            </p>

            <div id="verifyInfo" class="hint"></div>

            <div id="disciplineInfo" class="ai-summary section-gap"></div>

            <form method="post">
                <?= csrf_field() ?>

                <input type="hidden" name="action" value="verify">
                <input type="hidden" name="id" id="verifyId">

                <label>
                    Notes
                    <textarea name="notes" rows="4"></textarea>
                </label>

                <label>
                    Decision
                    <select name="decision">
                        <option value="verify">Verify for Registrar</option>
                        <option value="hold">Put on Hold</option>
                        <option value="reject">Reject</option>
                    </select>
                </label>

                <button class="btn primary">
                    Save Verification
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if ($user['role'] === 'registrar'): ?>
    <div class="modal" id="registrarVerifyModal">
        <div class="modal-box">
            <button class="close" onclick="closeModal('registrarVerifyModal')">×</button>
            <h2>Registrar Verification</h2>
            <p class="hint">Review the student's official record before forwarding the request to SASO for disciplinary verification.</p>
            <div id="registrarVerifyInfo" class="hint"></div>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="forward_saso">
                <input type="hidden" name="id" id="registrarVerifyId">
                <label>Notes<textarea name="notes" rows="4"></textarea></label>
                <button class="btn primary">Verify and Send to SASO</button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if ($user['role'] === 'registrar'): ?>
    <div class="modal" id="processModal">
        <div class="modal-box">
            <button class="close" onclick="closeModal('processModal')">
                ×
            </button>

            <h2>Registrar Processing</h2>

            <p class="hint">
                Only SASO-verified requests can proceed to Registrar processing.
                The Registrar may keep the request processing or mark it completed.
            </p>

            <div id="processInfo" class="hint"></div>

            <form method="post">
                <?= csrf_field() ?>

                <input type="hidden" name="action" value="process">
                <input type="hidden" name="id" id="processId">

                <label>
                    Notes
                    <textarea name="notes" rows="4"></textarea>
                </label>

                <label>
                    Decision
                    <select name="decision">
                        <option value="processing">Keep Processing</option>
                        <option value="complete">Mark Completed</option>
                    </select>
                </label>

                <button class="btn primary">
                    Save Status
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
    function openVerify(r) {
        document.getElementById('verifyId').value = r.id;
        document.querySelector('#verifyModal textarea[name="notes"]').value = r.saso_notes || '';

        document.getElementById('verifyInfo').innerHTML =
            '<b>Student:</b> ' +
            escapeHtml(r.student_name || '') +
            '<br><b>Student Number:</b> ' +
            escapeHtml(r.student_number || '') +
            '<br><b>Purpose:</b> ' +
            escapeHtml(r.purpose || '');

        document.getElementById('disciplineInfo').innerHTML =
            '<b>Disciplinary Standing</b><br>Total cases: ' +
            escapeHtml(r.violation_count || 0) +
            ' · Major: ' +
            escapeHtml(r.major_violation_count || 0) +
            ' · Open: ' +
            escapeHtml(r.open_violation_count || 0) +
            ' · Resolved: ' +
            escapeHtml(r.resolved_violation_count || 0) +
            '<br><small>Review the individual discipline records and CRMC policy before making a final decision.</small>';

        openModal('verifyModal');
    }

    function openProcess(r) {
        document.getElementById('processId').value = r.id;

        document.getElementById('processInfo').innerHTML =
            '<b>Student:</b> ' +
            escapeHtml(r.student_name || '') +
            '<br><b>SASO status:</b> ' +
            escapeHtml(r.status || '');

        openModal('processModal');
    }

    function escapeHtml(s) {
        return String(s).replace(
            /[&<>'"]/g,
            c => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                "'": '&#039;',
                '"': '&quot;'
            }[c])
        );
    }
</script>

<script>
function openRegistrarVerify(r) {
    document.getElementById('registrarVerifyId').value = r.id;
    document.getElementById('registrarVerifyInfo').innerHTML = '<strong>' + (r.student_number || '') + ' - ' + (r.student_name || '') + '</strong><br>Purpose: ' + (r.purpose || '') + '<br>Status: Pending Registrar Verification';
    openModal('registrarVerifyModal');
}
</script>

<?php include 'includes/footer.php'; ?>
