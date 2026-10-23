<?php

function zt_pdf_clean_text($value, $max_chars = 120000) {
    $text = (string) $value;
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', ' ', $text);
    if ($max_chars > 0 && strlen($text) > $max_chars) {
        $text = substr($text, 0, $max_chars) . "\n\n[ZeroThink PDF Creator truncated the document at the safe export limit.]";
    }
    return trim($text);
}

function zt_pdf_to_winansi($value) {
    $text = zt_pdf_clean_text($value, 0);
    $text = strtr($text, [
        "\xE2\x80\x98" => "'",
        "\xE2\x80\x99" => "'",
        "\xE2\x80\x9C" => '"',
        "\xE2\x80\x9D" => '"',
        "\xE2\x80\x93" => "-",
        "\xE2\x80\x94" => "-",
        "\xE2\x80\xA2" => "*",
        "\xE2\x80\xA6" => "...",
        "\xC2\xA0" => " ",
    ]);

    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            $text = $converted;
        }
    }

    $text = preg_replace('/[^\x09\x0A\x20-\x7E\x80-\xFF]/', '?', $text);
    return str_replace("\t", "    ", $text);
}

function zt_pdf_wrap_text($text, $max_chars = 94) {
    $lines = [];
    $paragraphs = explode("\n", (string) $text);
    foreach ($paragraphs as $paragraph) {
        $paragraph = trim(preg_replace('/[ \t]+/', ' ', $paragraph));
        if ($paragraph === '') {
            $lines[] = '';
            continue;
        }

        $words = preg_split('/\s+/', $paragraph);
        $line = '';
        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }
            if (strlen($word) > $max_chars) {
                if ($line !== '') {
                    $lines[] = $line;
                    $line = '';
                }
                while (strlen($word) > $max_chars) {
                    $lines[] = substr($word, 0, $max_chars);
                    $word = substr($word, $max_chars);
                }
            }

            $candidate = $line === '' ? $word : $line . ' ' . $word;
            if (strlen($candidate) > $max_chars) {
                if ($line !== '') {
                    $lines[] = $line;
                }
                $line = $word;
            } else {
                $line = $candidate;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }
    }
    return $lines;
}

function zt_pdf_escape($text) {
    $out = '';
    $len = strlen($text);
    for ($i = 0; $i < $len; $i++) {
        $ch = $text[$i];
        $ord = ord($ch);
        if ($ch === '\\' || $ch === '(' || $ch === ')') {
            $out .= '\\' . $ch;
        } elseif ($ord < 32 || $ord > 126) {
            $out .= sprintf('\\%03o', $ord);
        } else {
            $out .= $ch;
        }
    }
    return $out;
}

function zt_pdf_num($value) {
    $formatted = number_format((float) $value, 2, '.', '');
    return rtrim(rtrim($formatted, '0'), '.');
}

function zt_pdf_text_command($text, $size, $x, $y) {
    return 'BT /F1 ' . zt_pdf_num($size) . ' Tf 1 0 0 1 ' . zt_pdf_num($x) . ' ' . zt_pdf_num($y) . ' Tm (' . zt_pdf_escape($text) . ") Tj ET\n";
}

function zt_pdf_build_stream($entries, $page_number, $page_count, $footer) {
    $stream = "q\n0.06 0.08 0.12 rg\n";
    foreach ($entries as $entry) {
        $stream .= zt_pdf_text_command($entry['text'], $entry['size'], $entry['x'], $entry['y']);
    }

    $stream .= "0.02 0.70 0.78 RG 0.8 w 50 46 m 545 46 l S\n";
    $stream .= "0.35 0.38 0.44 rg\n";
    $stream .= zt_pdf_text_command($footer, 8, 50, 32);
    $stream .= zt_pdf_text_command('Page ' . $page_number . ' of ' . $page_count, 8, 500, 32);
    $stream .= "Q\n";
    return $stream;
}

function zt_pdf_create_bytes($title, $body, $meta = []) {
    $title = zt_pdf_clean_text($title, 180);
    $body = zt_pdf_clean_text($body, 120000);
    if ($title === '') {
        $title = 'ZeroThink PDF';
    }
    if ($body === '') {
        $body = 'No document body was supplied.';
    }

    $subtitle = zt_pdf_clean_text(isset($meta['subtitle']) ? $meta['subtitle'] : '', 240);
    $author = zt_pdf_clean_text(isset($meta['author']) ? $meta['author'] : '', 160);
    $footer = zt_pdf_to_winansi(isset($meta['footer']) ? $meta['footer'] : 'ZeroThink PDF Creator');
    $generated = gmdate('Y-m-d H:i') . ' UTC';

    $pages = [];
    $current = [];
    $y = 792;

    foreach (zt_pdf_wrap_text(zt_pdf_to_winansi($title), 45) as $line) {
        if ($line !== '') {
            $current[] = ['text' => $line, 'size' => 18, 'x' => 50, 'y' => $y];
        }
        $y -= 22;
    }

    if ($subtitle !== '') {
        foreach (zt_pdf_wrap_text(zt_pdf_to_winansi($subtitle), 72) as $line) {
            if ($line !== '') {
                $current[] = ['text' => $line, 'size' => 10.5, 'x' => 50, 'y' => $y];
            }
            $y -= 14;
        }
        $y -= 4;
    }

    $meta_line = 'Generated: ' . $generated;
    if ($author !== '') {
        $meta_line .= ' | Author: ' . zt_pdf_to_winansi($author);
    }
    $current[] = ['text' => $meta_line, 'size' => 8.5, 'x' => 50, 'y' => $y];
    $y -= 26;

    $body_lines = zt_pdf_wrap_text(zt_pdf_to_winansi($body), 96);
    foreach ($body_lines as $line) {
        if ($y < 62) {
            $pages[] = $current;
            $current = [];
            $y = 792;
            $continued = zt_pdf_wrap_text(zt_pdf_to_winansi($title . ' (continued)'), 70);
            $current[] = ['text' => isset($continued[0]) ? $continued[0] : 'Document continued', 'size' => 10, 'x' => 50, 'y' => $y];
            $y -= 26;
        }

        if ($line === '') {
            $y -= 9;
            continue;
        }

        $current[] = ['text' => $line, 'size' => 10.2, 'x' => 50, 'y' => $y];
        $y -= 13.6;
    }

    if ($current) {
        $pages[] = $current;
    }
    if (!$pages) {
        $pages[] = [['text' => 'No content.', 'size' => 10.2, 'x' => 50, 'y' => 792]];
    }

    $objects = [];
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';

    $kids = [];
    $page_count = count($pages);
    foreach ($pages as $idx => $entries) {
        $page_obj = 4 + ($idx * 2);
        $content_obj = $page_obj + 1;
        $kids[] = $page_obj . ' 0 R';

        $stream = zt_pdf_build_stream($entries, $idx + 1, $page_count, $footer);
        $objects[$content_obj] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
        $objects[$page_obj] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] /Resources << /Font << /F1 3 0 R >> >> /Contents ' . $content_obj . ' 0 R >>';
    }
    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $page_count . ' >>';
    ksort($objects, SORT_NUMERIC);

    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [0 => 0];
    foreach ($objects as $num => $body_text) {
        $offsets[$num] = strlen($pdf);
        $pdf .= $num . " 0 obj\n" . $body_text . "\nendobj\n";
    }

    $max = max(array_keys($objects));
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . ($max + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= $max; $i++) {
        $pdf .= sprintf("%010d 00000 n \n", isset($offsets[$i]) ? $offsets[$i] : 0);
    }
    $pdf .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
    return $pdf;
}

function zt_pdf_sanitize_filename($value, $fallback = 'zerothink-document') {
    $name = zt_pdf_to_winansi($value);
    $name = strtolower(preg_replace('/[^A-Za-z0-9._-]+/', '-', $name));
    $name = trim($name, '.-_');
    if ($name === '') {
        $name = $fallback;
    }
    if (strlen($name) > 88) {
        $name = rtrim(substr($name, 0, 88), '.-_');
    }
    if (substr($name, -4) !== '.pdf') {
        $name .= '.pdf';
    }
    return $name;
}
