<?php
function shaven_receipt_request_ids() {
    $ids = [];
    foreach (explode(',', (string)($_GET['ids'] ?? '')) as $part) {
        $id = (int)trim($part);
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    $single = (int)($_GET['id'] ?? 0);
    if ($single > 0) {
        $ids[$single] = $single;
    }
    return array_values($ids);
}

function shaven_receipt_bundle($conn, array $ids) {
    $fail = function ($error, $code) {
        return ['ok' => false, 'error' => $error, 'code' => $code, 'rows' => []];
    };
    if (!$ids) {
        return $fail('That receipt was not found.', 404);
    }
    if (count($ids) > 24) {
        return $fail('A receipt can include up to 24 payments.', 404);
    }
    if (!shaven_role_is(1, 2, 3, 4)) {
        return $fail('This receipt is outside your account.', 403);
    }
    $ids = array_values(array_unique(array_map('intval', $ids)));
    $rows = shaven_rows(
        $conn,
        "SELECT p.id, p.payment_uid, p.payment_amount, p.payment_date, p.payment_month, p.payment_year,
                p.payment_day, p.period_start, p.period_end, p.due_date, p.rf, p.pay_channel, p.postedby,
                s.s_name, s.s_address, s.s_doi, pl.p_name, u.sys_user_fullname
         FROM tbl_payments p
         INNER JOIN tbl_subs_profile s ON s.id = p.payment_uid
         LEFT JOIN tbl_plans pl ON pl.id = s.plan_id
         LEFT JOIN tbl_sys_users u ON u.id = p.postedby
         WHERE p.status=1 AND p.id IN (" . implode(',', $ids) . ")"
    );
    if (count($rows) !== count($ids)) {
        return $fail('That receipt was not found.', 404);
    }
    $uid = (int)$rows[0]['payment_uid'];
    foreach ($rows as $row) {
        if ((int)$row['payment_uid'] !== $uid) {
            return $fail('Those payments are not on the same account.', 404);
        }
    }
    if (shaven_role_is(4) && $uid !== (int)($_SESSION['subscriber_id'] ?? 0)) {
        return $fail('This receipt is outside your account.', 403);
    }
    if (shaven_role_is(3)) {
        $poster = (int)($_SESSION['usr_id'] ?? 0);
        foreach ($rows as $row) {
            if ((int)$row['postedby'] !== $poster) {
                return $fail('This receipt is outside your account.', 403);
            }
        }
    }
    foreach ($rows as $index => $row) {
        $period = shaven_bill_period($row, $row['s_doi']);
        $rows[$index]['period_label'] = shaven_bill_period_label($row, $row['s_doi']);
        $rows[$index]['due_label'] = ($period && !empty($period['due'])) ? shaven_date($period['due'], 'F j, Y') : '';
        $rows[$index]['sort'] = ($period && !empty($period['start'])) ? $period['start'] : '9999-12-31';
        $rows[$index]['number'] = trim((string)$row['rf']) !== '' ? (string)$row['rf'] : ('Payment ' . (int)$row['id']);
        $rows[$index]['paid_label'] = !empty($row['payment_date']) ? shaven_date($row['payment_date'], 'F j, Y g:i A') : '';
        $rows[$index]['channel_label'] = shaven_channel_label(1, $row['pay_channel']);
        $rows[$index]['poster'] = trim((string)($row['sys_user_fullname'] ?? ''));
    }
    usort($rows, function ($a, $b) {
        $cmp = strcmp($a['sort'], $b['sort']);
        return $cmp !== 0 ? $cmp : ((int)$a['id'] <=> (int)$b['id']);
    });
    $total = 0.0;
    $dates = [];
    $channels = [];
    $posters = [];
    foreach ($rows as $row) {
        $total += (float)$row['payment_amount'];
        $dates[(string)$row['payment_date']] = true;
        $channels[$row['channel_label']] = true;
        if ($row['poster'] !== '') {
            $posters[$row['poster']] = true;
        }
    }
    $number = preg_replace('/[^A-Za-z0-9_-]/', '', $rows[0]['number']);
    if ($number === '') {
        $number = 'payment-' . (int)$rows[0]['id'];
    }
    $filename = count($rows) > 1
        ? 'SHAVEN-receipt-' . count($rows) . '-payments.png'
        : 'SHAVEN-receipt-' . $number . '.png';
    return [
        'ok' => true,
        'error' => '',
        'code' => 200,
        'rows' => $rows,
        'uid' => $uid,
        'name' => (string)$rows[0]['s_name'],
        'address' => trim((string)$rows[0]['s_address']),
        'plan' => trim((string)$rows[0]['p_name']) !== '' ? (string)$rows[0]['p_name'] : 'No plan',
        'total' => round($total, 2),
        'paid_on' => count($dates) === 1 ? $rows[0]['paid_label'] : '',
        'channel' => count($channels) === 1 ? (string)array_key_first($channels) : '',
        'posted_by' => count($posters) === 1 ? (string)array_key_first($posters) : '',
        'filename' => $filename,
    ];
}

function shaven_receipt_money($amount) {
    return '₱' . number_format((float)$amount, 2);
}

function shaven_receipt_font($bold) {
    $paths = $bold
        ? ['C:/Windows/Fonts/segoeuib.ttf', 'C:/Windows/Fonts/arialbd.ttf']
        : ['C:/Windows/Fonts/segoeui.ttf', 'C:/Windows/Fonts/arial.ttf'];
    foreach ($paths as $path) {
        if (is_readable($path)) {
            return $path;
        }
    }
    return '';
}

function shaven_receipt_width($font, $size, $text) {
    if ($text === '' || $font === '') {
        return 0;
    }
    $box = imagettfbbox($size, 0, $font, $text);
    return abs($box[2] - $box[0]);
}

function shaven_receipt_wrap($font, $size, $text, $maxWidth) {
    $text = trim(preg_replace('/\s+/', ' ', (string)$text));
    if ($text === '') {
        return [];
    }
    $lines = [];
    $line = '';
    foreach (preg_split('/\s+/', $text) as $word) {
        $trial = $line === '' ? $word : $line . ' ' . $word;
        if ($line !== '' && shaven_receipt_width($font, $size, $trial) > $maxWidth) {
            $lines[] = $line;
            $line = $word;
            continue;
        }
        $line = $trial;
    }
    if ($line !== '') {
        $lines[] = $line;
    }
    $fitted = [];
    foreach ($lines as $row) {
        if (shaven_receipt_width($font, $size, $row) <= $maxWidth) {
            $fitted[] = $row;
            continue;
        }
        $clipped = $row;
        while (mb_strlen($clipped) > 1 && shaven_receipt_width($font, $size, $clipped . '...') > $maxWidth) {
            $clipped = mb_substr($clipped, 0, -1);
        }
        $fitted[] = $clipped . '...';
    }
    return $fitted;
}

final class ShavenReceiptImage {
    private $width = 1000;
    private $y = 0;
    private $ops = [];
    private $regular;
    private $bold;

    public function __construct($regular, $bold) {
        $this->regular = $regular;
        $this->bold = $bold;
    }

    public function y() {
        return $this->y;
    }

    public function move($y) {
        $this->y = $y;
    }

    public function down($amount) {
        $this->y += $amount;
    }

    public function rect($x, $y, $w, $h, $color) {
        $this->ops[] = ['kind' => 'rect', 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'color' => $color];
    }

    public function rule($y, $color) {
        $this->rect(48, $y, $this->width - 96, 2, $color);
    }

    public function text($x, $size, $text, $bold = false, $color = '#1f2933') {
        $this->ops[] = ['kind' => 'text', 'x' => $x, 'y' => $this->y, 'size' => $size, 'text' => (string)$text, 'bold' => $bold, 'color' => $color];
    }

    public function textRight($right, $size, $text, $bold = false, $color = '#1f2933') {
        $this->ops[] = ['kind' => 'right', 'right' => $right, 'y' => $this->y, 'size' => $size, 'text' => (string)$text, 'bold' => $bold, 'color' => $color];
    }

    public function png() {
        $scale = 2;
        $height = max(200, $this->y + 40);
        $image = imagecreatetruecolor($this->width * $scale, $height * $scale);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, 0, 0, $this->width * $scale, $height * $scale, $white);
        if (function_exists('imageantialias')) {
            imageantialias($image, true);
        }
        $colors = [];
        $paint = function ($hex) use ($image, &$colors) {
            if (!isset($colors[$hex])) {
                $colors[$hex] = imagecolorallocate($image, hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2)));
            }
            return $colors[$hex];
        };
        foreach ($this->ops as $op) {
            if ($op['kind'] === 'rect') {
                imagefilledrectangle(
                    $image,
                    (int)round($op['x'] * $scale),
                    (int)round($op['y'] * $scale),
                    (int)round(($op['x'] + $op['w']) * $scale),
                    (int)round(($op['y'] + $op['h']) * $scale),
                    $paint($op['color'])
                );
                continue;
            }
            if ($op['text'] === '') {
                continue;
            }
            $font = $op['bold'] ? $this->bold : $this->regular;
            $size = $op['size'] * $scale;
            $box = imagettfbbox($size, 0, $font, $op['text']);
            $textWidth = abs($box[2] - $box[0]);
            $x = $op['kind'] === 'right'
                ? (int)round($op['right'] * $scale) - $textWidth
                : (int)round($op['x'] * $scale);
            $baseline = (int)round($op['y'] * $scale) - $box[7];
            imagettftext($image, $size, 0, $x, $baseline, $paint($op['color']), $font, $op['text']);
        }
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);
        return $png;
    }
}

function shaven_receipt_png(array $bundle) {
    $regular = shaven_receipt_font(false);
    $bold = shaven_receipt_font(true);
    if ($regular === '' || $bold === '') {
        return '';
    }
    $sheet = new ShavenReceiptImage($regular, $bold);
    $ink = '#1f2933';
    $muted = '#52606d';
    $line = '#d8dee6';
    $sheet->rect(0, 0, 1000, 8, $ink);
    $sheet->move(36);
    $sheet->text(48, 18, 'SHAVEN NETWORKS', true, $ink);
    $sheet->textRight(952, 13, 'PAYMENT RECEIPT', true, $muted);
    $sheet->down(28);
    $sheet->text(48, 14, 'Maralag, Dumingag', false, $muted);
    $sheet->down(20);
    $sheet->text(48, 14, 'Zamboanga del Sur, Philippines 7028', false, $muted);
    $sheet->down(18);
    $sheet->rule($sheet->y(), $ink);
    $sheet->down(28);
    if (count($bundle['rows']) === 1) {
        $sheet->text(48, 26, $bundle['rows'][0]['number'], true, $ink);
    } else {
        $sheet->text(48, 26, count($bundle['rows']) . ' payments', true, $ink);
    }
    $sheet->down(48);
    if ($bundle['paid_on'] !== '') {
        $sheet->text(48, 15, 'Paid ' . $bundle['paid_on'], false, $muted);
        $sheet->down(28);
    }
    $sheet->text(48, 13, 'BILLED TO', true, $muted);
    $sheet->textRight(952, 13, 'PLAN', true, $muted);
    $sheet->down(22);
    $nameTop = $sheet->y();
    $nameLines = shaven_receipt_wrap($bold, 18, $bundle['name'], 520);
    if (!$nameLines) {
        $nameLines = ['Subscriber'];
    }
    foreach ($nameLines as $nameLine) {
        $sheet->text(48, 18, $nameLine, true, $ink);
        $sheet->down(24);
    }
    foreach (preg_split("/\r\n|\n|\r/", (string)$bundle['address']) as $part) {
        foreach (shaven_receipt_wrap($regular, 15, $part, 520) as $addressLine) {
            $sheet->text(48, 15, $addressLine, false, $muted);
            $sheet->down(20);
        }
    }
    $afterLeft = $sheet->y();
    $sheet->move($nameTop);
    foreach (shaven_receipt_wrap($regular, 15, $bundle['plan'], 300) as $planLine) {
        $sheet->textRight(952, 15, $planLine, false, $ink);
        $sheet->down(20);
    }
    if ($bundle['channel'] !== '') {
        $sheet->textRight(952, 14, 'Received through ' . $bundle['channel'], false, $muted);
        $sheet->down(20);
    }
    if ($bundle['posted_by'] !== '') {
        $sheet->textRight(952, 14, 'Posted by ' . $bundle['posted_by'], false, $muted);
        $sheet->down(20);
    }
    $sheet->move(max($afterLeft, $sheet->y()) + 8);
    $head = $sheet->y();
    $sheet->rect(48, $head, 904, 32, '#eef1f4');
    $sheet->move($head + 8);
    $sheet->text(56, 12, 'RECEIPT NO.', true, $muted);
    $sheet->text(300, 12, 'BILLING PERIOD', true, $muted);
    $sheet->text(690, 12, 'DUE DATE', true, $muted);
    $sheet->textRight(940, 12, 'AMOUNT', true, $muted);
    $sheet->move($head + 44);
    foreach ($bundle['rows'] as $row) {
        $periodLines = shaven_receipt_wrap($regular, 15, $row['period_label'], 360);
        if (!$periodLines) {
            $periodLines = ['Billing month not on file'];
        }
        $due = $row['due_label'] !== '' ? $row['due_label'] : 'No due date';
        $rowTop = $sheet->y();
        $numberLines = shaven_receipt_wrap($bold, 14, $row['number'], 230);
        $sheet->text(56, 14, $numberLines ? $numberLines[0] : $row['number'], true, $ink);
        foreach ($periodLines as $periodLine) {
            $sheet->text(300, 15, $periodLine, false, $ink);
            $sheet->down(20);
        }
        $afterPeriod = $sheet->y();
        $sheet->move($rowTop);
        $sheet->text(690, 15, $due, false, $ink);
        $sheet->textRight(940, 15, shaven_receipt_money($row['payment_amount']), true, $ink);
        $sheet->move(max($afterPeriod, $rowTop + 22));
        $meta = [];
        if ($bundle['paid_on'] === '' && $row['paid_label'] !== '') {
            $meta[] = 'Paid ' . $row['paid_label'];
        }
        if ($bundle['channel'] === '') {
            $meta[] = $row['channel_label'];
        }
        if ($meta) {
            $sheet->text(300, 13, implode(' · ', $meta), false, $muted);
            $sheet->down(20);
        }
        $sheet->down(8);
        $sheet->rule($sheet->y(), $line);
        $sheet->down(16);
    }
    $sheet->down(8);
    $sheet->text(48, 20, 'Total amount paid', true, $ink);
    $sheet->textRight(940, 20, shaven_receipt_money($bundle['total']), true, $ink);
    $sheet->down(36);
    $sheet->text(48, 15, 'Thank you for your payment.', false, $muted);
    $sheet->down(22);
    $sheet->text(48, 14, 'Questions: abminevets@gmail.com', false, $muted);
    $sheet->down(8);
    return $sheet->png();
}

function shaven_receipt_send_image(array $bundle) {
    $png = shaven_receipt_png($bundle);
    if ($png === '') {
        http_response_code(500);
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Receipt</title></head><body><p>The receipt image could not be created.</p></body></html>';
        return;
    }
    header('Content-Type: image/png');
    header('Content-Disposition: attachment; filename="' . $bundle['filename'] . '"');
    header('Content-Length: ' . strlen($png));
    header('Cache-Control: private, no-store');
    echo $png;
}

function shaven_receipt_error(array $bundle) {
    http_response_code((int)$bundle['code']);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Receipt</title></head><body><p>' . h($bundle['error']) . '</p></body></html>';
}

function shaven_receipt_page(array $bundle, $flash, $back) {
    $ids = [];
    foreach ($bundle['rows'] as $row) {
        $ids[] = (int)$row['id'];
    }
    $download = 'receipt.php?ids=' . implode(',', $ids) . '&download=1';
    $title = count($bundle['rows']) === 1 ? 'Receipt ' . $bundle['rows'][0]['number'] : 'Payment receipt';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo h($title); ?></title>
    <!-- This receipt keeps its own print styles. shaven-theme.css is not applied. -->
    <style>
        body { margin: 0; background: #eef1f4; color: #1f2933; font-family: "Segoe UI", Helvetica, Arial, sans-serif; }
        .or-wrap { max-width: 820px; margin: 28px auto; padding: 0 16px 32px; }
        .or-note { background: #e8f6ee; border: 1px solid #b7e0c6; color: #146c43; border-radius: 8px; padding: 12px 14px; margin-bottom: 14px; }
        .or-sheet { background: #fff; border: 1px solid #d8dee6; border-radius: 10px; padding: 32px; box-shadow: 0 8px 24px rgba(31, 41, 51, 0.06); }
        .or-top { display: flex; justify-content: space-between; gap: 24px; border-bottom: 2px solid #1f2933; padding-bottom: 16px; }
        .or-brand { margin: 0 0 4px; letter-spacing: 0.04em; }
        .or-muted { margin: 0; color: #52606d; }
        .or-kicker { margin: 0; font-size: 13px; letter-spacing: 0.12em; color: #52606d; }
        h1 { margin: 4px 0 0; font-size: 28px; }
        .or-parties { display: flex; justify-content: space-between; gap: 24px; margin: 22px 0; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 12px; letter-spacing: 0.04em; text-transform: uppercase; color: #52606d; border-bottom: 1px solid #d8dee6; padding: 8px 8px 8px 0; }
        td { padding: 12px 8px 12px 0; border-bottom: 1px solid #eef1f4; vertical-align: top; }
        .or-num { text-align: right; white-space: nowrap; }
        .or-total { display: flex; justify-content: space-between; margin-top: 16px; font-size: 20px; font-weight: 700; }
        .or-meta { margin-top: 18px; color: #52606d; }
        .or-actions { display: flex; gap: 8px; margin-top: 22px; }
        .or-btn, .or-actions a { display: inline-block; background: #1f2933; color: #fff; text-decoration: none; border: 0; border-radius: 6px; padding: 10px 14px; cursor: pointer; font: inherit; }
        .or-actions a.or-quiet, button.or-quiet { background: #fff; color: #1f2933; border: 1px solid #c5ced8; }
        @media print {
            body { background: #fff; }
            .or-wrap { margin: 0; max-width: none; }
            .or-sheet { border: 0; box-shadow: none; padding: 0; }
            .or-note, .or-actions { display: none; }
        }
    </style>
</head>
<body>
<div class="or-wrap">
    <?php if (is_array($flash) && trim((string)($flash['message'] ?? '')) !== ''): ?>
        <div class="or-note"><?php echo h($flash['message']); ?></div>
    <?php endif; ?>
    <article class="or-sheet">
        <div class="or-top">
            <div>
                <h2 class="or-brand">SHAVEN NETWORKS</h2>
                <p class="or-muted">Maralag, Dumingag<br>Zamboanga del Sur, Philippines 7028</p>
            </div>
            <div style="text-align:right">
                <p class="or-kicker">PAYMENT RECEIPT</p>
                <h1><?php echo count($bundle['rows']) === 1 ? h($bundle['rows'][0]['number']) : h(count($bundle['rows']) . ' payments'); ?></h1>
                <?php if ($bundle['paid_on'] !== ''): ?>
                    <p class="or-muted">Paid <?php echo h($bundle['paid_on']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="or-parties">
            <div>
                <strong>Billed to</strong>
                <p class="or-muted"><?php echo h($bundle['name']); ?><?php if ($bundle['address'] !== ''): ?><br><?php echo nl2br(h($bundle['address'])); ?><?php endif; ?></p>
            </div>
            <div style="text-align:right">
                <strong>Plan</strong>
                <p class="or-muted"><?php echo h($bundle['plan']); ?><?php if ($bundle['channel'] !== ''): ?><br>Received through <?php echo h($bundle['channel']); ?><?php endif; ?><?php if ($bundle['posted_by'] !== ''): ?><br>Posted by <?php echo h($bundle['posted_by']); ?><?php endif; ?></p>
            </div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Receipt no.</th>
                    <th>Billing period</th>
                    <th>Due date</th>
                    <th class="or-num">Amount</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($bundle['rows'] as $row): ?>
                <tr>
                    <td><?php echo h($row['number']); ?><?php if ($bundle['paid_on'] === '' && $row['paid_label'] !== ''): ?><br><span class="or-muted">Paid <?php echo h($row['paid_label']); ?></span><?php endif; ?></td>
                    <td><?php echo h($row['period_label']); ?><?php if ($bundle['channel'] === ''): ?><br><span class="or-muted"><?php echo h($row['channel_label']); ?></span><?php endif; ?></td>
                    <td><?php echo $row['due_label'] !== '' ? h($row['due_label']) : 'No due date'; ?></td>
                    <td class="or-num"><?php echo shaven_peso($row['payment_amount']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="or-total"><span>Total amount paid</span><span><?php echo shaven_peso($bundle['total']); ?></span></div>
        <p class="or-meta">Thank you for your payment. Questions: abminevets@gmail.com</p>
        <div class="or-actions">
            <a href="<?php echo h($download); ?>">Download image</a>
            <button class="or-quiet" type="button" onclick="window.print()">Print</button>
            <a class="or-quiet" href="<?php echo h($back); ?>">Close</a>
        </div>
    </article>
</div>
</body>
</html>
    <?php
}
