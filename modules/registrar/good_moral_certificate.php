<?php
// Registrar-only printable certificate for requests that remain SASO-verified.
require_once 'config/database.php';
require_once 'includes/auth.php';
require_role(['registrar']);

$page_title = 'Print Good Moral Certificate';
$page_heading = $page_title;
$requestId = trim((string) ($_GET['id'] ?? ''));
$certificate = null;

if ($requestId !== '') {
    $stmt = $pdo->prepare(
        "SELECT g.id, g.status, g.purpose, g.saso_verified_by, g.saso_verified_at,
                s.student_number, s.first_name, s.middle_name, s.last_name,
                s.course, s.year_level
         FROM good_moral_requests g
         JOIN students s ON s.id = g.student_id
         WHERE g.id = ? AND g.status = 'saso_verified'
         LIMIT 1"
    );
    $stmt->execute([$requestId]);
    $certificate = $stmt->fetch() ?: null;
}

if (!$certificate) {
    http_response_code($requestId === '' ? 400 : 404);
}

$studentName = $certificate
    ? strtoupper(trim($certificate['first_name'] . ' ' . ($certificate['middle_name'] ?? '') . ' ' . $certificate['last_name']))
    : '';
$issueDate = new DateTimeImmutable();
$day = (int) $issueDate->format('j');
$daySuffix = 'th';
if ($day % 100 < 11 || $day % 100 > 13) {
    $daySuffix = [1 => 'st', 2 => 'nd', 3 => 'rd'][$day % 10] ?? 'th';
}
$academicYearStart = (int) $issueDate->format('Y');
if ((int) $issueDate->format('n') < 6) {
    $academicYearStart--;
}

include 'includes/header.php';
?>
<style>
    .certificate-sheet {
        max-width: 850px;
        min-height: 1120px;
        margin: 24px auto;
        padding: 42px 66px 30px;
        background: #fff;
        color: #241a1d;
        box-shadow: 0 10px 30px rgba(63, 17, 30, 0.08);
        font-family: 'Times New Roman', Georgia, serif;
        display: flex;
        flex-direction: column;
    }
    .certificate-heading {
        position: relative;
        text-align: center;
        min-height: 150px;
        padding: 0 64px 0 76px;
    }
    .certificate-heading img {
        position: absolute;
        top: 16px;
        left: 0;
        width: 72px;
        height: 72px;
        object-fit: contain;
    }
    .certificate-heading h1 {
        margin: 2px 0 0;
        color: #454545;
        font-size: 23px;
        font-weight: 400;
    }
    .certificate-heading p {
        margin: 2px 0 0;
        color: #454545;
        font-size: 13px;
    }
    .certificate-heading .certificate-membership {
        margin-top: 8px;
        font-size: 10px;
    }
    .certificate-officers {
        display: flex;
        justify-content: center;
        gap: 45px;
        margin: 18px 0 12px;
        font-size: 10px;
        font-style: italic;
    }
    .certificate-officers span {
        display: inline-block;
        min-width: 165px;
        text-align: center;
    }
    .certificate-rule {
        position: relative;
        height: 1px;
        margin: 0 8px;
        background: #777;
    }
    .certificate-rule::before,
    .certificate-rule::after {
        position: absolute;
        top: -4px;
        width: 0;
        height: 0;
        border-top: 4px solid transparent;
        border-bottom: 4px solid transparent;
        content: '';
    }
    .certificate-rule::before { left: -2px; border-right: 7px solid #777; }
    .certificate-rule::after { right: -2px; border-left: 7px solid #777; }
    .certificate-office {
        margin: 17px 0 0;
        text-align: center;
        font-size: 15px;
    }
    .certificate-title {
        margin: 14px 0 54px;
        color: #454545;
        text-align: center;
        font-size: 20px;
    }
    .certificate-body {
        padding: 0 10px;
        font-size: 14px;
        line-height: 1.45;
    }
    .certificate-body p {
        margin: 0 0 14px;
        text-indent: 38px;
        text-align: justify;
    }
    .certificate-body .certificate-salutation {
        margin-bottom: 12px;
        text-indent: 0;
        font-weight: 700;
    }
    .certificate-editable {
        border-radius: 2px;
        outline: none;
    }
    .certificate-editable:focus {
        background: #fff9dc;
        box-shadow: 0 0 0 2px #c79a43;
    }
    .certificate-editable:hover {
        background: #fffdf2;
    }
    .certificate-date {
        margin: 0 10px;
        font-size: 14px;
    }
    .certificate-signatures {
        display: flex;
        justify-content: flex-end;
        margin: 58px 20px 0 0;
        text-align: center;
        font-family: 'Times New Roman', Georgia, serif;
        font-size: 14px;
    }
    .certificate-signature-line {
        min-width: 210px;
        font-weight: 700;
        text-decoration: underline;
    }
    .certificate-signature-line .certificate-role {
        font-weight: 700;
        text-decoration: none;
    }
    .certificate-seal-notice {
        margin: 56px 0 0 10px;
        font-family: Arial, sans-serif;
        font-size: 14px;
        font-weight: 700;
    }
    .certificate-seal-notice span {
        display: block;
        margin: 14px 0 0 45px;
        letter-spacing: 8px;
    }
    .certificate-footer {
        margin-top: auto;
        padding: 8px 12px;
        border: 1px solid #aaa;
        color: #666;
        text-align: center;
        font-size: 9px;
        font-style: italic;
    }
    @media print {
        @page { size: A4 portrait; margin: 0; }
        html, body, .app, .main { width: 210mm; min-height: 297mm; margin: 0; background: #fff; }
        .certificate-sheet {
            width: 210mm;
            height: 297mm;
            max-width: none;
            min-height: 0;
            margin: 0;
            padding: 23mm 20mm 14mm;
            border: 0;
            box-shadow: none;
            break-inside: avoid;
        }
        .certificate-editable:focus,
        .certificate-editable:hover { background: transparent; box-shadow: none; }
        .no-print { display: none !important; }
    }
</style>

<?php if (!$certificate): ?>
    <div class="alert danger">This certificate is unavailable. Only a request that is currently SASO-verified can be printed.</div>
<?php else: ?>
    <div class="page-actions no-print">
        <p class="muted">Click certificate text to edit it before printing. Changes apply to this printout only.</p>
        <button class="btn primary" type="button" onclick="window.print()">Print Certificate</button>
    </div>

    <main class="certificate-sheet">
        <header class="certificate-heading">
            <img src="logo.png" alt="Cebu Roosevelt Memorial Colleges seal">
            <h1 class="certificate-editable" contenteditable="true" spellcheck="false">Cebu Roosevelt Memorial Colleges</h1>
            <p class="certificate-editable" contenteditable="true" spellcheck="false">Bogo, Cebu</p>
            <p class="certificate-membership certificate-editable" contenteditable="true" spellcheck="false">Member: Philippine Association of Colleges and Universities (PACU)<br>Center for Educational Measurement (CEM)</p>
            <div class="certificate-officers">
                <span><span class="certificate-editable" contenteditable="true" spellcheck="false">Dr. Victor L. Lepiten, Sr.</span><br><span class="certificate-editable" contenteditable="true" spellcheck="false">FOUNDER</span></span>
                <span><span class="certificate-editable" contenteditable="true" spellcheck="false">Victor Eliot S. Lepiten III, CPA</span><br><span class="certificate-editable" contenteditable="true" spellcheck="false">PRESIDENT</span></span>
            </div>
        </header>
        <div class="certificate-rule"></div>
        <p class="certificate-office certificate-editable" contenteditable="true" spellcheck="false">Office of the Registrar</p>

        <h2 class="certificate-title certificate-editable" contenteditable="true" spellcheck="false">CERTIFICATE OF GOOD MORAL</h2>

        <section class="certificate-body">
            <p class="certificate-salutation certificate-editable" contenteditable="true" spellcheck="false">TO WHOM IT MAY CONCERN:</p>
            <p class="certificate-editable" contenteditable="true" spellcheck="false">
                This is to certify that <strong><?= htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') ?></strong> was enrolled in this institution taking <strong><?= htmlspecialchars(strtoupper(trim($certificate['course'] ?? '')), ENT_QUOTES, 'UTF-8') ?></strong> during <strong>First semester, <?= $academicYearStart ?>-<?= $academicYearStart + 1 ?></strong>.
            </p>
            <p class="certificate-editable" contenteditable="true" spellcheck="false">This certifies further that the aforementioned student is of good moral and has no derogatory records while in school.</p>
            <p class="certificate-editable" contenteditable="true" spellcheck="false">This certification is issued for <strong><?= htmlspecialchars(trim($certificate['purpose'] ?? '') ?: 'On-the-Job Training purposes', ENT_QUOTES, 'UTF-8') ?></strong>.</p>
        </section>

        <p class="certificate-date certificate-editable" contenteditable="true" spellcheck="false">Done this <?= $day . $daySuffix ?> day of <?= htmlspecialchars($issueDate->format('F, Y'), ENT_QUOTES, 'UTF-8') ?> at Bogo City, Cebu, Philippines.</p>

        <div class="certificate-signatures">
            <div class="certificate-signature-line">
                <span class="certificate-editable" contenteditable="true" spellcheck="false">JAC LESTER R. LEPITEN</span><br>
                <span class="certificate-role certificate-editable" contenteditable="true" spellcheck="false">Registrar</span>
            </div>
        </div>

        <p class="certificate-seal-notice certificate-editable" contenteditable="true" spellcheck="false">NOT VALID WITHOUT<span>SEAL</span></p>
        <footer class="certificate-footer certificate-editable" contenteditable="true" spellcheck="false">“If your plan is for one year, plant rice; for ten years, plant trees; for a hundred years, educate men.”<br>Mobile No.: 0966 145 6659 / Telephone #: (032) 344 3795</footer>
    </main>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
