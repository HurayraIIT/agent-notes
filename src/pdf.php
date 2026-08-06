<?php

declare(strict_types=1);

use Dompdf\Dompdf;

/** Streams a note as a PDF attachment. */
function note_pdf(array $note): never
{
    if (rate_limited('pdf-ip:' . client_ip(), 20)) {
        header('Retry-After: 60');
        text_response('Too many PDF requests — try again in a minute.', status: 429);
    }

    $stmt = db()->prepare('SELECT pdf_cache FROM notes WHERE id = ?');
    $stmt->execute([$note['id']]);
    $pdf = $stmt->fetchColumn();

    if (!$pdf) {
        $pdf = generate_pdf($note);
        // updated_at = updated_at: a cache write is not an edit
        db()->prepare('UPDATE notes SET pdf_cache = ?, updated_at = updated_at WHERE id = ?')
            ->execute([$pdf, $note['id']]);
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $note['filename'] . '.pdf"');
    header('X-Robots-Tag: noindex, nofollow');
    echo $pdf;
    exit;
}

function generate_pdf(array $note): string
{
    $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; color: #1e293b; line-height: 1.55; }
        h1, h2, h3, h4 { color: #0f172a; line-height: 1.25; }
        h1.doc-title { font-size: 20pt; margin-bottom: 2pt; }
        p.doc-meta { color: #94a3b8; font-size: 9pt; margin-top: 0; padding-bottom: 8pt; border-bottom: 1px solid #e2e8f0; }
        pre { background: #f1f5f9; border: 1px solid #e2e8f0; padding: 8pt; font-size: 9pt; white-space: pre-wrap; word-wrap: break-word; }
        code { font-family: DejaVu Sans Mono, monospace; font-size: 9pt; background: #f1f5f9; }
        table { border-collapse: collapse; width: 100%; margin: 8pt 0; }
        th, td { border: 1px solid #cbd5e1; padding: 4pt 6pt; text-align: left; font-size: 10pt; }
        th { background: #f1f5f9; }
        blockquote { border-left: 3pt solid #cbd5e1; margin-left: 0; padding-left: 10pt; color: #475569; }
        a { color: #4f46e5; }
        img { max-width: 100%; }
        /* The dompdf default sheet already makes these display:block and implements no
           disclosure behaviour, so every <details> section prints expanded — right for a PDF. */
        summary { font-weight: bold; }
        .md-alert { border-left: 3pt solid #cbd5e1; margin-left: 0; padding-left: 10pt; }
        .md-alert-title { font-weight: bold; }
        </style></head><body>'
        . '<h1 class="doc-title">' . e($note['title']) . '</h1>'
        . '<p class="doc-meta">Updated ' . e(fmt_dt($note['updated_at'])) . ' · ' . e(note_url($note['slug'])) . '</p>'
        . markdown_to_html($note['content'])
        . '</body></html>';

    $dompdf = new Dompdf(); // remote resources stay disabled (default) — no SSRF via note content
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4');
    $dompdf->render();

    return $dompdf->output();
}
