<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * The L&D report as an editable Excel workbook — one sheet per table, laid
 * out like the report's own tables (caption, bordered grid, TOTAL row), with
 * figures written as numbers so they can be re-summed in Excel.
 */
class LdReportExcel
{
    private const SHEETS = [
        't1' => 'Table 1', 't2' => 'Table 2', 't3' => 'Table 3',
        't4' => 'Table 4', 't5' => 'Table 5', 'monitoring' => 'L&D Monitoring',
    ];

    public static function write(LdReport $report, string $path): void
    {
        $book = new Spreadsheet();
        $book->removeSheetByIndex(0);
        $book->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

        foreach ($report->tables() as $table) {
            $sheet = $book->createSheet();
            $sheet->setTitle(self::SHEETS[$table['report']]);

            $sheet->setCellValue('A1', $table['title']);
            $sheet->getStyle('A1')->getFont()->setItalic(true);
            $sheet->setCellValue('A2', $report->periodLabel());
            $sheet->getStyle('A2')->getFont()->setSize(9)->getColor()->setRGB('666666');

            $cols = $table['columns'];
            $last = Coordinate::stringFromColumnIndex(count($cols));
            $r = 4;

            foreach ($cols as $i => $col) {
                $sheet->setCellValue([$i + 1, $r], $col['label']);
                $width = in_array($col['key'], ['title', 'trainings', 'level', 'parameter', 'competency', 'name'], true) ? 34 : 13;
                $sheet->getColumnDimensionByColumn($i + 1)->setWidth($width);
            }
            $sheet->getStyle("A{$r}:{$last}{$r}")->getFont()->setBold(true);
            $sheet->getStyle("A{$r}:{$last}{$r}")->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

            $rows = array_map(fn ($row) => $row['cells'], $table['rows']);
            if ($table['total']) {
                $rows[] = $table['total'];
            }

            foreach ($rows as $cells) {
                $r++;
                foreach ($cols as $i => $col) {
                    $v = $cells[$col['key']] ?? null;
                    $sheet->setCellValue([$i + 1, $r], is_numeric($v) ? $v + 0 : $v);
                }
            }

            if ($table['total']) {
                $sheet->getStyle("A{$r}:{$last}{$r}")->getFont()->setBold(true);
            }
            if (! $table['rows']) {
                $sheet->setCellValue([1, $r + 1], 'Nothing recorded for this period.');
            }

            $grid = $sheet->getStyle("A4:{$last}{$r}");
            $grid->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $grid->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        }

        $book->setActiveSheetIndex(0);
        (new Xlsx($book))->save($path);
    }
}
