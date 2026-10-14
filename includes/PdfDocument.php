<?php

class PdfDocument
{
    public static function document(string $body): string
    {
        return '<!doctype html><html lang="en"><head><meta charset="UTF-8"><style>'
            . self::css()
            . '</style></head><body>'
            . $body
            . '</body></html>';
    }

    public static function css(): string
    {
        return '@page { margin: 14mm 12mm 16mm; }'
            . '* { box-sizing: border-box; }'
            . 'body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #23170d; margin: 0; }'
            . 'table { border-collapse: collapse; width: 100%; }'
            . '.head td { border: 0; padding: 0 0 10px; vertical-align: top; }'
            . '.head .brand-cell { width: 58%; }'
            . '.head .doc-cell { width: 42%; text-align: right; }'
            . '.brand { font-size: 20px; font-weight: 700; color: #2c1505; line-height: 1; }'
            . '.brand-mark { color: #d4840e; }'
            . '.brand-sub { color: #8a6d4f; font-size: 9px; line-height: 1.45; margin-top: 5px; }'
            . '.kicker { color: #b8620a; font-size: 8px; font-weight: 700; letter-spacing: 1.4px; }'
            . '.title { font-size: 22px; font-weight: 700; color: #2c1505; letter-spacing: 1px; line-height: 1.05; margin-top: 2px; }'
            . '.meta { color: #5c4632; font-size: 10px; line-height: 1.5; margin-top: 6px; }'
            . '.rule td { background: #d4840e; height: 3px; line-height: 0; font-size: 0; padding: 0; border: 0; }'
            . '.spacer td { height: 8px; line-height: 0; font-size: 0; padding: 0; border: 0; }'
            . '.parties td { width: 50%; vertical-align: top; border: 0; padding: 8px 6px 0 0; }'
            . '.parties td.right { padding: 8px 0 0 6px; }'
            . '.box { background: #fff8ee; border: 1px solid #ead7b8; border-left: 3px solid #d4840e; padding: 8px 10px; }'
            . '.box h3 { margin: 0 0 4px; font-size: 8px; letter-spacing: 0.8px; color: #b8620a; font-weight: 700; }'
            . '.box p { margin: 0; line-height: 1.45; color: #23170d; }'
            . '.items { table-layout: fixed; width: 100%; margin-top: 12px; }'
            . '.items th, .items td { border: 1px solid #ead7b8; padding: 5px 6px; vertical-align: middle; }'
            . '.items th { background: #2c1505; color: #fff8ee; font-size: 8px; font-weight: 700; text-align: center; letter-spacing: 0.4px; }'
            . '.items thead { display: table-header-group; }'
            . '.items tr { page-break-inside: avoid; }'
            . '.items tr.alt td { background: #fff8ee; }'
            . '.product-image { width: 42px; height: 42px; }'
            . '.pname { font-weight: 700; line-height: 1.3; color: #2c1505; }'
            . '.center { text-align: center; }'
            . '.money { text-align: right; }'
            . '.aed { font-weight: 700; font-size: 9px; line-height: 1.25; color: #2c1505; }'
            . '.usd { color: #8a6d4f; font-size: 8px; line-height: 1.25; }'
            . '.muted { color: #8a6d4f; font-size: 8px; line-height: 1.3; }'
            . '.totals-wrap { width: 100%; margin-top: 12px; }'
            . '.totals-wrap > tbody > tr > td { border: 0; padding: 0; vertical-align: top; }'
            . '.totals-gap { width: 52%; }'
            . '.totals { width: 100%; }'
            . '.totals td { border: 1px solid #ead7b8; padding: 6px 8px; vertical-align: middle; }'
            . '.totals .lbl { width: 46%; font-weight: 700; background: #fff8ee; color: #2c1505; }'
            . '.totals .discount .aed, .totals .discount .usd { color: #9b3d2f; }'
            . '.totals .grand td { background: #d4840e; color: #fff; font-weight: 700; border-color: #d4840e; }'
            . '.totals .grand .aed, .totals .grand .usd { color: #fff; }'
            . '.section-title { margin-top: 12px; margin-bottom: 4px; font-weight: 700; font-size: 8px; letter-spacing: 0.8px; color: #b8620a; }'
            . '.notes, .notice { background: #fff8ee; border: 1px solid #ead7b8; padding: 8px 10px; line-height: 1.5; color: #5c4632; }'
            . '.notice { margin-top: 10px; }'
            . '.footer { margin-top: 16px; padding-top: 8px; border-top: 1px solid #ead7b8; text-align: center; color: #8a6d4f; font-size: 9px; line-height: 1.5; }';
    }

    public static function money(float $amount, float $rate, bool $negative = false): string
    {
        $amount = abs($amount);
        $usd = $amount * $rate;
        $sign = $negative ? '-' : '';
        $e = static function ($value): string {
            return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        };

        return '<div class="aed">' . $sign . 'AED ' . $e(number_format($amount, 2)) . '</div>'
            . '<div class="usd">' . $sign . 'USD ' . $e(number_format($usd, 2)) . '</div>';
    }

    public static function header(string $title, array $metaLines): string
    {
        $meta = '';
        foreach ($metaLines as $line) {
            $meta .= ($meta === '' ? '' : '<br>') . $line;
        }

        return '<table class="head"><tr>'
            . '<td class="brand-cell"><div class="brand">Aleppo <span class="brand-mark">Gift</span></div>'
            . '<div class="brand-sub">Premium Syrian Products<br>www.aleppogift.com<br>+971 56 112 5320</div></td>'
            . '<td class="doc-cell"><div class="kicker">ALEPPO GIFT</div><div class="title">'
            . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
            . '</div><div class="meta">' . $meta . '</div></td>'
            . '</tr></table>'
            . '<table class="rule"><tr><td>&nbsp;</td></tr></table>';
    }

    public static function parties(string $leftTitle, string $leftHtml, string $rightTitle, string $rightHtml): string
    {
        return '<table class="parties"><tr>'
            . '<td><div class="box"><h3>' . htmlspecialchars($leftTitle, ENT_QUOTES, 'UTF-8') . '</h3><p>' . $leftHtml . '</p></div></td>'
            . '<td class="right"><div class="box"><h3>' . htmlspecialchars($rightTitle, ENT_QUOTES, 'UTF-8') . '</h3><p>' . $rightHtml . '</p></div></td>'
            . '</tr></table>';
    }

    public static function itemsTable(string $rowsHtml, bool $withPrices = true): string
    {
        if (!$withPrices) {
            return '<table class="items"><colgroup>'
                . '<col style="width:8%"><col style="width:14%"><col style="width:64%"><col style="width:14%">'
                . '</colgroup><thead><tr>'
                . '<th>#</th><th>IMAGE</th><th>PRODUCT</th><th>QTY</th>'
                . '</tr></thead><tbody>' . $rowsHtml . '</tbody></table>';
        }

        return '<table class="items"><colgroup>'
            . '<col style="width:6%"><col style="width:12%"><col style="width:38%"><col style="width:8%"><col style="width:18%"><col style="width:18%">'
            . '</colgroup><thead><tr>'
            . '<th>#</th><th>IMAGE</th><th>PRODUCT</th><th>QTY</th><th>UNIT PRICE</th><th>TOTAL</th>'
            . '</tr></thead><tbody>' . $rowsHtml . '</tbody></table>';
    }

    public static function totals(string $rowsHtml): string
    {
        return '<table class="totals-wrap"><tr><td class="totals-gap"></td><td><table class="totals">'
            . $rowsHtml
            . '</table></td></tr></table>';
    }

    public static function footer(string $message): string
    {
        return '<div class="footer">' . $message
            . '<br>www.aleppogift.com &nbsp;|&nbsp; info@aleppogift.com'
            . '<br>Amounts are shown in AED with the USD equivalent.</div>';
    }
}
