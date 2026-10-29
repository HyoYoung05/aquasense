<?php
declare(strict_types=1);

function phase7_csv_cell(mixed $value): string
{
    if ($value === null) {
        return '';
    }

    $text = (string) $value;
    if (preg_match('/^[\x00-\x20]*[=+\-@]/u', $text)) {
        $text = "'".$text;
    }

    return $text;
}

/** @param resource $output */
function phase7_csv_row($output, array $cells): void
{
    fputcsv($output, $cells, ',', '"', '', "\r\n");
}

function phase7_csv_response(array $data, array $filters): never
{
    $filename = 'aquasense-'.$data['type'].'-'.$filters['period']['from'].'-'.$filters['period']['to'].'.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('X-Content-Type-Options: nosniff');

    $output = fopen('php://output', 'wb');
    if ($output === false) {
        throw new RuntimeException('Unable to create the CSV output stream.');
    }

    // Some Android spreadsheet viewers display a UTF-8 BOM as literal text in
    // the first cell, so this standards-based UTF-8 CSV intentionally omits it.
    phase7_csv_row($output, ['Report', 'AQUASENSE+ - '.$data['definition']['title']]);
    phase7_csv_row($output, ['Period', $filters['period']['label']]);
    phase7_csv_row($output, ['Filters', phase7_filter_summary($filters)]);
    phase7_csv_row($output, []);
    phase7_csv_row($output, ['Summary metric', 'Value']);
    foreach ($data['summary'] as $item) {
        phase7_csv_row($output, [
            phase7_csv_cell($item['label']),
            phase7_csv_cell($item['value']),
        ]);
    }

    phase7_csv_row($output, []);
    phase7_csv_row($output, array_values($data['columns']));
    foreach ($data['rows'] as $row) {
        $line = [];
        foreach (array_keys($data['columns']) as $key) {
            $line[] = phase7_csv_cell($row[$key] ?? null);
        }
        phase7_csv_row($output, $line);
    }

    if ($data['truncated']) {
        phase7_csv_row($output, [
            'NOTICE',
            'Export limited to '.count($data['rows']).' rows. Narrow the filters for a complete export.',
        ]);
    }

    fclose($output);
    exit;
}

function phase7_pdf_html(array $data, array $filters, array $user, string $orientation = 'portrait'): string
{
    if (!in_array($orientation, ['portrait', 'landscape'], true)) {
        throw new InvalidArgumentException('Unsupported PDF orientation.');
    }
    $portrait = $orientation === 'portrait';
    $escape = static fn (mixed $value): string => htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    $summaryColumns = $portrait ? 2 : 3;
    $summaryRows = '';
    foreach (array_chunk($data['summary'], $summaryColumns) as $items) {
        $summaryRows .= '<tr>';
        foreach ($items as $item) {
            $summaryRows .= '<td><b>'.$escape($item['value']).'</b><span>'.$escape($item['label']).'</span></td>';
        }
        for ($missing = count($items); $missing < $summaryColumns; $missing++) {
            $summaryRows .= '<td class="empty-metric"></td>';
        }
        $summaryRows .= '</tr>';
    }

    $tableHead = '';
    foreach ($data['columns'] as $label) {
        $tableHead .= '<th>'.$escape($label).'</th>';
    }

    $tableBody = '';
    foreach ($data['rows'] as $row) {
        $tableBody .= '<tr>';
        foreach (array_keys($data['columns']) as $key) {
            $tableBody .= '<td>'.$escape($row[$key] ?? '').'</td>';
        }
        $tableBody .= '</tr>';
    }
    if (!$data['rows']) {
        $tableBody = '<tr><td colspan="'.count($data['columns']).'">No data matches the selected filters.</td></tr>';
    }

    $portraitRows = '';
    if ($data['rows']) {
        foreach (array_chunk($data['rows'], 2) as $pair) {
            $portraitRows .= '<tr>';
            foreach ($pair as $row) {
                $portraitRows .= '<td class="record-slot"><table class="record-card"><tbody>';
                foreach ($data['columns'] as $key => $label) {
                    $portraitRows .= '<tr><td class="record-label">'.$escape($label).'</td><td>'.$escape($row[$key] ?? '').'</td></tr>';
                }
                $portraitRows .= '</tbody></table></td>';
            }
            if (count($pair) === 1) {
                $portraitRows .= '<td class="record-slot empty-record"></td>';
            }
            $portraitRows .= '</tr>';
        }
    } else {
        $portraitRows = '<tr><td class="no-data" colspan="2">No data matches the selected filters.</td></tr>';
    }

    $notice = $data['truncated']
        ? '<p class="notice">Large report limited to '.count($data['rows']).' rows. Narrow the filters for a complete record.</p>'
        : '';
    $columnCount = max(1, count($data['columns']));
    $detailFont = $columnCount >= 11 ? '6.2px' : ($columnCount >= 8 ? '6.8px' : '7.4px');
    $metricWidth = number_format(100 / $summaryColumns, 3, '.', '');
    $detailMarkup = $portrait
        ? '<table class="record-grid"><tbody>'.$portraitRows.'</tbody></table>'
        : '<table class="detail"><thead><tr>'.$tableHead.'</tr></thead><tbody>'.$tableBody.'</tbody></table>';

    return '<!doctype html><html><head><meta charset="utf-8"><style>
        @page{size:A4 '.$orientation.';margin:24px 26px 30px}
        *{box-sizing:border-box}body{font-family:DejaVu Sans,sans-serif;color:#263e38;font-size:7.5px;margin:0}
        .report-header{border-bottom:2px solid #174d43;padding-bottom:7px;margin-bottom:8px}
        .eyebrow{font-size:6.5px;color:#72827b;margin-bottom:3px}.report-header h1{font-size:17px;margin:0 0 3px;color:#183d35}
        .report-header p{font-size:7.5px;color:#65756f;margin:0}.meta{width:100%;margin:0 0 8px;border-collapse:collapse}
        .meta td{border:0;padding:2px 8px 2px 0;color:#53655e}.meta strong{color:#263e38}
        .metric-grid{width:100%;table-layout:fixed;border-collapse:separate;border-spacing:5px 4px;margin:0 -5px 8px}
        .metric-grid td{width:'.$metricWidth.'%;border:1px solid #d8e2dd;border-radius:4px;padding:5px 7px;vertical-align:top}
        .metric-grid td.empty-metric{border-color:transparent}.metric-grid b,.metric-grid span{display:block}
        .metric-grid b{font-size:10px;color:#174d43;line-height:1.05}.metric-grid span{font-size:6.5px;color:#65756f;margin-top:2px}
        .section-heading{margin:4px 0 6px}.section-heading h2{font-size:10px;margin:0 0 2px;color:#263e38}
        .section-heading p{font-size:7px;margin:0;color:#65756f}.detail{width:100%;table-layout:fixed;border-collapse:collapse;font-size:'.$detailFont.'}
        .detail thead{display:table-header-group}.detail tr{page-break-inside:avoid}.detail th{background:#174d43;color:#fff;text-align:left;padding:4px 3px;font-weight:700;overflow-wrap:anywhere}
        .detail td{border:1px solid #e0e8e4;padding:4px 3px;vertical-align:top;overflow-wrap:anywhere;word-wrap:break-word}
        .detail tbody tr:nth-child(even){background:#f7faf8}.record-grid{width:100%;table-layout:fixed;border-collapse:separate;border-spacing:4px 5px;margin:0 -4px}
        .record-slot{width:50%;padding:0;vertical-align:top}.record-slot.empty-record{border:0}.record-card{width:100%;border-collapse:collapse;font-size:7px;page-break-inside:avoid}
        .record-card td{border:1px solid #dfe8e3;padding:3px 4px;vertical-align:top;overflow-wrap:anywhere}.record-card .record-label{width:38%;background:#edf4f0;color:#174d43;font-weight:700}
        .no-data{border:1px solid #dfe8e3;padding:10px;text-align:center;color:#65756f}.notice{color:#8b4b20;margin:4px 0}.footer{position:fixed;bottom:-20px;left:0;right:0;text-align:center;color:#788781;font-size:6px}.footer:after{content:"AQUASENSE+ official report - Page " counter(page)}
    </style></head><body>
        <header class="report-header"><div class="eyebrow">Workspace / Reports</div><h1>Reporting and audit review</h1><p>Filter, review, print, and export operational records using Manila reporting dates.</p></header>
        <table class="meta"><tr><td><strong>Report:</strong> '.$escape($data['definition']['title']).'</td><td><strong>Period:</strong> '.$escape($filters['period']['label']).'</td><td><strong>Orientation:</strong> '.ucfirst($orientation).'</td></tr><tr><td><strong>Generated:</strong> '.$escape(date('M j, Y g:i A')).' (Asia/Manila)</td><td><strong>Filters:</strong> '.$escape(phase7_filter_summary($filters)).'</td><td><strong>Generated by:</strong> '.$escape($user['full_name']).'</td></tr></table>
        <table class="metric-grid"><tbody>'.$summaryRows.'</tbody></table>'.$notice.'
        <div class="section-heading"><h2>'.$escape($data['definition']['title']).'</h2><p>'.$escape($data['definition']['description']).' - '.number_format((int) $data['total']).' matching records</p></div>
        '.$detailMarkup.'<div class="footer"></div>
    </body></html>';
}

function phase7_pdf_response(array $data, array $filters, array $user, string $orientation = 'portrait'): never
{
    $autoload = dirname(__DIR__).'/vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('PDF support is not installed. Run composer install.');
    }
    require_once $autoload;

    $options = new Dompdf\Options();
    $options->set('isRemoteEnabled', false);
    $options->set('isPhpEnabled', false);
    $options->set('defaultFont', 'DejaVu Sans');
    $options->setChroot(dirname(__DIR__));

    $dompdf = new Dompdf\Dompdf($options);
    $dompdf->loadHtml(phase7_pdf_html($data, $filters, $user, $orientation), 'UTF-8');
    $dompdf->setPaper('A4', $orientation);
    $dompdf->render();

    phase7_audit_export(db(), $user, 'pdf', $filters);
    $filename = 'aquasense-'.$data['type'].'-'.$filters['period']['from'].'-'.$filters['period']['to'].'-'.$orientation.'.pdf';
    $dompdf->stream($filename, ['Attachment' => true]);
    exit;
}
