<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetAssignment;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * The whole asset register in PEPY's "Inventory List" template, as an .xlsx —
 * attached to the scheduled (and the manually emailed) asset summary report.
 * The same layout as the SPA's Export Excel (frontend/src/utils/excelExport.js):
 *
 *   [green logo]   អង្គការ លើកកម្ពស់យុវជន / Inventory List / scope / generated
 *   red headers with filter & sort dropdowns, frozen
 *   orange category rows → assets → "Total MOV" → … → grand total
 */
class InventoryListXlsx
{
    private const ORG_NAME_KM = 'អង្គការ លើកកម្ពស់យុវជន';

    private const COLUMNS = [
        ['Description', 'text', 34],
        ['Quantity', 'qty', 10],
        ['Asset ID', 'code', 18],
        ['Purchase Date', 'date', 14],
        ['Location', 'text', 22],
        ['Price', 'money', 13],
        ['Serial No.', 'text', 18],
        ['Assigned to', 'text', 24],
        ['Status', 'text', 14],
        ['Remark', 'text', 40],
    ];

    private const HEADER_ROW = 5;

    public static function fileName(): string
    {
        return 'inventory-list-'.now()->toDateString().'.xlsx';
    }

    /** @return string the .xlsx file's bytes */
    public static function build(): string
    {
        $book = new Spreadsheet;
        $book->getProperties()->setCreator('PEPY Assets')->setTitle('Inventory List');
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Inventory List');
        $lastCol = self::col(count(self::COLUMNS));

        self::titleBlock($sheet, $lastCol);
        self::header($sheet);

        $row = self::HEADER_ROW + 1;
        $grandQty = 0;
        $grandPrice = 0.0;
        $count = 0;

        foreach (self::groups() as $group) {
            // Orange category row: name on the left, its code under Asset ID.
            self::fill($sheet, "A{$row}:{$lastCol}{$row}", 'FFFFC000', true);
            $sheet->setCellValue("A{$row}", $group['label']);
            $sheet->setCellValue("C{$row}", $group['code']);
            $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;

            $qty = 0;
            $price = 0.0;
            foreach ($group['rows'] as $r) {
                self::dataRow($sheet, $row++, $r);
                $qty += 1;
                $price += (float) ($r[5] ?? 0);
            }

            self::totalRow($sheet, $row++, 'Total '.$group['code'], $qty, $price, 'FFFCE4D6', 'FFF8CBAD', false);
            $grandQty += $qty;
            $grandPrice += $price;
            $count += $qty;
        }

        self::totalRow($sheet, $row, "Total: {$count}", $grandQty, $grandPrice, 'FFE2EFDA', 'FF00B050', true);

        // Filter & sort dropdowns on every column, header frozen, widths, print.
        $sheet->setAutoFilter('A'.self::HEADER_ROW.":{$lastCol}".max($row - 1, self::HEADER_ROW));
        $sheet->freezePane('A'.(self::HEADER_ROW + 1));
        foreach (self::COLUMNS as $i => [, , $width]) {
            $sheet->getColumnDimension(self::col($i + 1))->setWidth($width);
        }
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd(self::HEADER_ROW, self::HEADER_ROW);

        $stream = fopen('php://temp', 'w+');
        (new Xlsx($book))->save($stream);
        rewind($stream);
        $bytes = stream_get_contents($stream);
        fclose($stream);
        $book->disconnectWorksheets();

        return $bytes;
    }

    /** @return array<int, array{label: string, code: string, rows: array<int, array>}> */
    private static function groups(): array
    {
        $groups = [];

        Asset::with([
            'category:id,name,short_name',
            'location:id,name',
            'assignments' => fn ($q) => $q->whereIn('status', AssetAssignment::CURRENT_STATUSES)->latest(),
        ])
            ->orderBy('category_id')
            ->orderBy('asset_code')
            ->chunk(500, function ($assets) use (&$groups) {
                foreach ($assets as $asset) {
                    $key = $asset->category_id ?? 0;
                    $name = $asset->category?->name ?? 'Uncategorised';
                    $code = $asset->category?->short_name ?: $name;
                    $groups[$key] ??= ['label' => $asset->category?->short_name ? "{$name} ( {$code} )" : $name, 'code' => $code, 'rows' => []];
                    $groups[$key]['rows'][] = [
                        $asset->name,
                        1,
                        $asset->asset_code,
                        $asset->purchase_date,
                        $asset->location?->name,
                        $asset->purchase_price,
                        $asset->serial_number,
                        $asset->assignments->first()?->recipient_name ?? 'Unassigned',
                        self::status($asset),
                        $asset->description ?: trim(($asset->brand ?? '').' '.($asset->model ?? '')),
                    ];
                }
            });

        return array_values($groups);
    }

    /** Same wording as the register screen's status badge. */
    private static function status(Asset $asset): string
    {
        return match (true) {
            $asset->status === 'disposed' => 'Retiring',
            $asset->condition === 'lost' => 'Lost',
            in_array($asset->condition, ['fair', 'broken'], true) => 'Needs repair',
            default => 'In use',
        };
    }

    private static function titleBlock(Worksheet $sheet, string $lastCol): void
    {
        $title = function (int $row, string $text, array $font, float $height, string $align = Alignment::HORIZONTAL_CENTER) use ($sheet, $lastCol) {
            $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
            $sheet->setCellValue("A{$row}", $text);
            $style = $sheet->getStyle("A{$row}");
            $style->getFont()->applyFromArray($font);
            $style->getAlignment()->setHorizontal($align)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getRowDimension($row)->setRowHeight($height);
        };

        $title(1, self::ORG_NAME_KM, ['name' => 'Khmer OS Muol Light', 'size' => 14, 'bold' => true, 'color' => ['argb' => 'FF0E7A3B']], 26);
        $title(2, 'Inventory List', ['name' => 'Cambria', 'size' => 15, 'color' => ['argb' => 'FF1F5FAD']], 22);
        $title(3, 'PEPY Office / Learning Center / Target High Schools', ['name' => 'Cambria', 'size' => 14, 'bold' => true, 'underline' => true], 22);
        $title(4, 'Generated '.now()->format('j M Y'), ['name' => 'Calibri', 'size' => 9, 'italic' => true, 'color' => ['argb' => 'FF7F7F7F']], 16, Alignment::HORIZONTAL_RIGHT);

        $logo = resource_path('images/pepy-logo-green.png');
        if (is_file($logo)) {
            $drawing = new Drawing;
            $drawing->setName('PEPY');
            $drawing->setPath($logo);
            $drawing->setHeight(64);
            $drawing->setCoordinates('A1');
            $drawing->setOffsetX(4);
            $drawing->setOffsetY(3);
            $drawing->setWorksheet($sheet);
        }
    }

    private static function header(Worksheet $sheet): void
    {
        foreach (self::COLUMNS as $i => [$label]) {
            $sheet->setCellValue(self::col($i + 1).self::HEADER_ROW, $label);
        }
        $range = 'A'.self::HEADER_ROW.':'.self::col(count(self::COLUMNS)).self::HEADER_ROW;
        $style = $sheet->getStyle($range);
        $style->getFont()->applyFromArray(['name' => 'Calibri', 'size' => 11, 'bold' => true, 'color' => ['argb' => 'FFC00000']]);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $style->getBorders()->applyFromArray([
            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFBFBFBF']],
            'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF808080']],
            'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF808080']],
        ]);
        $sheet->getRowDimension(self::HEADER_ROW)->setRowHeight(20);
    }

    private static function dataRow(Worksheet $sheet, int $row, array $values): void
    {
        foreach (self::COLUMNS as $i => [, $type]) {
            $cell = self::col($i + 1).$row;
            $value = $values[$i] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            match ($type) {
                'date' => $sheet->setCellValue($cell, ExcelDate::PHPToExcel(new \DateTime(substr((string) $value, 0, 10)))),
                'money', 'qty' => $sheet->setCellValue($cell, (float) $value),
                default => $sheet->setCellValueExplicit($cell, (string) $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING),
            };
        }

        $lastCol = self::col(count(self::COLUMNS));
        $style = $sheet->getStyle("A{$row}:{$lastCol}{$row}");
        $style->getFont()->setName('Calibri')->setSize(10);
        $style->getBorders()->applyFromArray([
            'left' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFBFBFBF']],
            'right' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFBFBFBF']],
            'bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['argb' => 'FFBFBFBF']],
            'vertical' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFBFBFBF']],
        ]);
        foreach (self::COLUMNS as $i => [, $type]) {
            self::formatCell($sheet, self::col($i + 1).$row, $type);
        }
    }

    private static function totalRow(Worksheet $sheet, int $row, string $label, int $qty, float $price, string $fill, string $qtyFill, bool $whiteQty): void
    {
        $lastCol = self::col(count(self::COLUMNS));
        self::fill($sheet, "A{$row}:{$lastCol}{$row}", $fill, true);
        $sheet->setCellValue("A{$row}", $label);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->setCellValue("B{$row}", $qty);
        self::formatCell($sheet, "B{$row}", 'qty');
        $sheet->getStyle("B{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($qtyFill);
        if ($whiteQty) {
            $sheet->getStyle("B{$row}")->getFont()->getColor()->setARGB('FFFFFFFF');
        }
        if ($price > 0) {
            $sheet->setCellValue("F{$row}", $price);
            self::formatCell($sheet, "F{$row}", 'money');
        }
    }

    private static function fill(Worksheet $sheet, string $range, string $argb, bool $bold): void
    {
        $style = $sheet->getStyle($range);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($argb);
        $style->getFont()->setName('Calibri')->setSize(10)->setBold($bold);
        $style->getBorders()->applyFromArray([
            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFBFBFBF']],
        ]);
    }

    private static function formatCell(Worksheet $sheet, string $cell, string $type): void
    {
        $style = $sheet->getStyle($cell);
        match ($type) {
            'money' => $style->getNumberFormat()->setFormatCode('"$"#,##0.00'),
            'date' => $style->getNumberFormat()->setFormatCode('d-mmm-yy'),
            default => null,
        };
        $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(match ($type) {
            'money' => Alignment::HORIZONTAL_RIGHT,
            'qty', 'date', 'code' => Alignment::HORIZONTAL_CENTER,
            default => Alignment::HORIZONTAL_LEFT,
        });
    }

    private static function col(int $index): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index);
    }
}
