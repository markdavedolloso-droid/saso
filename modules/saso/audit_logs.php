<?php
// Displays the newest 15 audit entries; older entries are kept in the archive.
$page_title='Audit Logs'; $page_heading='Audit Logs';
require_once 'config/database.php'; require_once 'includes/auth.php'; require_role(['admin']);
$rows=$pdo->query("SELECT a.*,COALESCE(CONCAT(p.first_name,' ',p.last_name),'System') user_name,COALESCE(p.email,'') email FROM audit_logs a LEFT JOIN profiles p ON p.id=a.profile_id ORDER BY a.created_at DESC, a.id DESC LIMIT 15")->fetchAll();
include 'includes/header.php';
?>
<div class="page-actions"><p class="muted">Review the latest 15 recorded system actions. Older entries are retained in the archive.</p></div>
<section class="card"><div class="search"><input id="tableSearch" placeholder="Search logs..."></div><table id="dataTable"><thead><tr><th>Date</th><th>User</th><th>Action</th><th>Module</th><th>Record ID</th><th>Details</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=htmlspecialchars($r['created_at'])?></td><td><?=htmlspecialchars($r['user_name'])?><br><small><?=htmlspecialchars($r['email'])?></small></td><td><span class="badge gold"><?=htmlspecialchars($r['action'])?></span></td><td><?=htmlspecialchars($r['module'])?></td><td><?=htmlspecialchars($r['record_id']??'—')?></td><td><?=htmlspecialchars($r['details']??'')?></td></tr><?php endforeach;if(!$rows):?><tr><td colspan="6">No audit logs recorded yet.</td></tr><?php endif;?></tbody></table></section>
<?php include 'includes/footer.php'; ?>
