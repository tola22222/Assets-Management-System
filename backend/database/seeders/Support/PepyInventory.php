<?php

namespace Database\Seeders\Support;

use RuntimeException;

/**
 * Reads the PEPY fixed-asset register out of the repo's own source document,
 * `.claude/commands/PEPY_Asset_Inventory_Cleaned.md`, at seed time.
 *
 * The register is parsed from that file rather than transcribed into a PHP
 * array on purpose: a transcription is a second copy that silently drifts from
 * the source, and the whole point of the UAT dataset is that every asset in it
 * is traceable to a documented row. Nothing here invents, estimates, or fills
 * in a value the document leaves blank.
 *
 * Two shapes come out of the file:
 *
 *  - DETAIL rows  — one itemised asset per row (161 of them: 10 MOV, 104 FAF,
 *                   4 COM, 43 EQU, matching the document's own summary table).
 *  - RANGE rows   — groups the source only ever described as an ID range
 *                   ("Plastic Chairs PEY-SR-FAF-0034 to 0074"). The source did
 *                   not list these individually, so neither do we: each stays
 *                   ONE summary record. Expanding them would fabricate hundreds
 *                   of assets that no document backs.
 *
 * Range rows whose source cell gives no asset ID at all ("—") are returned
 * separately as `unimportableRanges()`: `assets.asset_code` is NOT NULL and
 * UNIQUE, so storing them would require inventing a tag. They are reported
 * instead of guessed.
 */
class PepyInventory
{
    /** Site names as they appear in the document's Location column. */
    private const CATEGORY_SECTIONS = [
        'MOV' => 'MOV — Vehicles',
        'FAF' => 'FAF — Furniture (Detail)',
        'COM' => 'COM — Computers (Detail)',
        'EQU' => 'EQU — Equipment (Detail)',
    ];

    private const RANGE_SECTIONS = [
        'FAF' => 'FAF — Furniture (Ranges)',
        'COM' => 'COM — Computers (Ranges)',
    ];

    private string $path;

    /** @var array<int,array<string,mixed>> */
    private array $detail = [];

    /** @var array<int,array<string,mixed>> */
    private array $ranges = [];

    /** @var array<int,array<string,mixed>> */
    private array $unimportable = [];

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? dirname(base_path()).'/.claude/commands/PEPY_Asset_Inventory_Cleaned.md';

        if (! is_file($this->path)) {
            throw new RuntimeException("Inventory source not found at {$this->path}. The UAT seeder reads the register from this document and will not invent asset data in its absence.");
        }

        $this->parse();
    }

    public function sourcePath(): string
    {
        return $this->path;
    }

    /** @return array<int,array<string,mixed>> */
    public function detailRows(): array
    {
        return $this->detail;
    }

    /** @return array<int,array<string,mixed>> */
    public function rangeRows(): array
    {
        return $this->ranges;
    }

    /** @return array<int,array<string,mixed>> */
    public function unimportableRanges(): array
    {
        return $this->unimportable;
    }

    /** Every asset row we can actually store, detail + range summaries. */
    public function importableRows(): array
    {
        return array_merge($this->detail, $this->ranges);
    }

    // -----------------------------------------------------------------

    private function parse(): void
    {
        $sections = $this->sections();

        foreach (self::CATEGORY_SECTIONS as $code => $heading) {
            foreach ($this->tableRows($sections[$heading] ?? '') as $cells) {
                $row = $this->parseDetailRow($code, $cells);
                if ($row !== null) {
                    $this->detail[] = $row;
                }
            }
        }

        foreach (self::RANGE_SECTIONS as $code => $heading) {
            foreach ($this->tableRows($sections[$heading] ?? '') as $cells) {
                $row = $this->parseRangeRow($code, $cells);
                if ($row === null) {
                    continue;
                }
                if ($row['asset_code'] === null) {
                    $this->unimportable[] = $row;
                } else {
                    $this->ranges[] = $row;
                }
            }
        }
    }

    /** @return array<string,string> heading => body */
    private function sections(): array
    {
        $parts = preg_split('/^## /m', file_get_contents($this->path));
        $out = [];

        foreach ($parts as $part) {
            $lines = explode("\n", $part);
            $out[trim(array_shift($lines))] = implode("\n", $lines);
        }

        return $out;
    }

    /**
     * Markdown table body rows, split into trimmed cells. Header and the
     * |---|---| separator are dropped.
     *
     * @return array<int,array<int,string>>
     */
    private function tableRows(string $body): array
    {
        $rows = [];

        foreach (explode("\n", $body) as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] !== '|') {
                continue;
            }
            if (preg_match('/^\|[\s:|-]+\|$/', $line)) {
                continue;   // |---|---| separator
            }

            $cells = array_map('trim', explode('|', trim($line, '|')));

            // Header row: first cell is a column label, never an asset description
            // followed by a numeric qty. Detect by looking for a known header word.
            if (preg_match('/^(Description|Item \/ Group)/i', $cells[0])) {
                continue;
            }

            $rows[] = $cells;
        }

        return $rows;
    }

    /**
     * Detail table columns are consistent across all four category sections:
     * Description | Qty | Asset ID | Purchase Date | Location | Price | Serial/Source | Used By | (Source)
     */
    private function parseDetailRow(string $categoryCode, array $c): ?array
    {
        $idCell = $c[2] ?? '';

        if (! preg_match('/PEY-[A-Z.]+-[A-Z]{2,6}-\d{4}/', $idCell)) {
            return null;
        }

        // Two documented rows carry an ID RANGE in the ID column
        // (PEY-SR-COM-0204 to 0207, PEY-SR-EQU-0041 to 0045). The
        // "do not expand ranges" rule applies to those too: keep the row,
        // anchor it on the first ID, record the span in the description.
        $spans = $this->spansIn($idCell);
        $code = $spans[0];

        return [
            'kind' => count($spans) > 1 || str_contains($idCell, ' to ') ? 'detail-range' : 'detail',
            'category_code' => $categoryCode,
            'asset_code' => $code,
            'asset_code_raw' => $idCell,
            'name' => $c[0],
            'quantity' => $this->intOrNull($c[1] ?? ''),
            'purchase_date' => $this->date($c[3] ?? ''),
            'purchase_date_raw' => $this->blank($c[3] ?? ''),
            'location_name' => $this->blank($c[4] ?? ''),
            'purchase_price' => $this->price($c[5] ?? ''),
            'serial_number' => $this->blank($c[6] ?? ''),
            'used_by' => $this->blank($c[7] ?? ''),
            'source_note' => $this->blank($c[8] ?? ''),
        ];
    }

    /**
     * Range table columns:
     * Item / Group | Start Asset ID | End Asset ID | Note
     */
    private function parseRangeRow(string $categoryCode, array $c): ?array
    {
        $label = $c[0] ?? '';

        if ($label === '') {
            return null;
        }

        $startCell = $c[1] ?? '';
        $endCell = $c[2] ?? '';
        $spans = array_merge($this->spansIn($startCell), $this->spansIn($endCell));

        return [
            'kind' => 'range',
            'category_code' => $categoryCode,
            'asset_code' => $spans[0] ?? null,   // null => no ID given in source
            'asset_code_raw' => trim($startCell.' '.$endCell),
            'name' => $this->rangeName($label),
            'label' => $label,
            'start' => $this->blank($startCell),
            'end' => $this->blank($endCell),
            'note' => $this->blank($c[3] ?? ''),
            'quantity' => null,
            'purchase_date' => null,
            'purchase_date_raw' => null,
            'location_name' => $this->rangeLocation($label),
            'purchase_price' => null,
            'serial_number' => null,
            'used_by' => null,
        ];
    }

    /** All PEY-…-#### tags mentioned in a cell, in document order. */
    private function spansIn(string $cell): array
    {
        // "PEY-SR-COM-0204 to 0207" — the trailing 0207 is a bare sequence,
        // not a full tag. Only full tags are returned; the raw cell is kept
        // verbatim elsewhere so nothing is lost.
        preg_match_all('/PEY-[A-Z.]+-[A-Z]{2,6}-\d{4}/', $cell, $m);

        return $m[0];
    }

    /**
     * The range label is a long descriptive string
     * ("Plastic Chairs - PEPY Office (Office)"). The part before the first
     * " - " is the item name; the site/programme context after it is dropped.
     *
     * Some labels carry no " - " at all and instead end in a price annotation
     * ("Drone (DJI Mini 4k) ($230.00-$283.00, Comm. Team)"). Only the trailing
     * bracket that actually contains a "$" is stripped, so a price note goes
     * but a genuine model name stays — "(DJI Mini 4k)" and
     * "(TP-Link AX1500, AX1800)" are part of what the item IS.
     */
    private function rangeName(string $label): string
    {
        $name = trim(preg_split('/\s+-\s+/', $label)[0]);

        if ($name === '') {
            $name = trim($label);
        }

        return trim(preg_replace('/\s*\([^()]*\$[^()]*\)\s*$/', '', $name));
    }

    /** Best-effort site mention inside a range label, or null. Never guessed beyond an exact name match. */
    private function rangeLocation(string $label): ?string
    {
        foreach (self::SITE_NAMES as $site) {
            if (str_contains($label, $site)) {
                return $site;
            }
        }

        return null;
    }

    public const SITE_NAMES = [
        'PEPY Office', 'Kralanh HS', 'Sen Sok HS', 'Varin HS', 'Banteay Srei HS',
        'Kork Dong HS', 'Sna Techo 317 HS', 'Sreyvibol Khae HS', 'Pong Ro Leu HS',
        'Preah Theat HS', 'Srae Khva HS', 'Roeul HS', 'Spean Thnort HS',
    ];

    // ---- cell coercion ------------------------------------------------

    private function blank(string $v): ?string
    {
        $v = trim($v);

        return ($v === '' || $v === '—' || $v === '-') ? null : $v;
    }

    private function intOrNull(string $v): ?int
    {
        $v = trim($v);

        return is_numeric($v) ? (int) $v : null;
    }

    /** "$1,850.00" => 1850.00 ; "" or "—" => null. Never defaults to 0. */
    private function price(string $v): ?float
    {
        $v = trim($v);

        if ($v === '' || $v === '—') {
            return null;
        }

        if (! preg_match('/([\d,]+(?:\.\d+)?)/', $v, $m)) {
            return null;
        }

        return (float) str_replace(',', '', $m[1]);
    }

    /**
     * The document mixes formats: "28-Sep-17", "1-Oct-19", "10/31/2025".
     * Anything unparseable returns null and the raw string is preserved in
     * the description — a blank date is documented data, not an error.
     */
    private function date(string $v): ?string
    {
        $v = trim($v);

        if ($v === '' || $v === '—') {
            return null;
        }

        foreach (['j-M-y', 'd-M-y', 'n/j/Y', 'm/d/Y', 'Y-m-d'] as $format) {
            $d = \DateTime::createFromFormat($format, $v);
            if ($d !== false && $d->format($format) === $v) {
                return $d->format('Y-m-d');
            }
        }

        $ts = strtotime($v);

        return $ts === false ? null : date('Y-m-d', $ts);
    }
}
