<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorkPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class XlsxReportExporter
{
    public function download(Collection $rows, WorkPeriod $period, User $actor): BinaryFileResponse
    {
        $directory = storage_path('app/private/reports');
        File::ensureDirectoryExists($directory);
        $path = tempnam($directory, 'workload-');
        if ($path === false) {
            throw new \RuntimeException('Tidak dapat menyiapkan berkas XLSX sementara.');
        }
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Tidak dapat membuat berkas XLSX.');
        }
        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRelationships());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships());
        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheet($rows, $period, $actor));
        $zip->close();

        return response()->download($path, 'beban-kerja-'.$period->period_start->format('Y-m').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function worksheet(Collection $rows, WorkPeriod $period, User $actor): string
    {
        $data = [
            [['Laporan Beban Kerja Tim', 1]],
            [['Periode', 0], [$period->period_start->translatedFormat('F Y'), 0]],
            [['Dibuat oleh', 0], [$actor->name, 0]],
            [['Waktu pembuatan', 0], [now()->toDateTimeString(), 0]],
            [],
            array_map(fn ($value) => [$value, 2], ['ID Anggota', 'Nama', 'Unit', 'Jabatan', 'Status Validasi', 'Kapasitas Efektif (jam)', 'Waktu Aktual (jam)', 'Utilisasi (%)', 'Status Beban']),
        ];
        foreach ($rows as $row) {
            $data[] = [
                [$row['user']->employee_code ?? '', 0], [$row['user']->name, 0], [$row['user']->organizationalUnit?->name ?? '', 0],
                [$row['user']->position ?? '', 0], [$row['submission']?->status?->label() ?? 'Belum mengisi', 0],
                [round($row['metrics']['effective_minutes'] / 60, 2), 3, true], [round($row['metrics']['required_minutes'] / 60, 2), 3, true],
                [$row['metrics']['utilization'], 3, true], [$row['metrics']['status']->label(), 0],
            ];
        }
        $xmlRows = '';
        foreach ($data as $rowIndex => $cells) {
            $number = $rowIndex + 1;
            $xmlRows .= '<row r="'.$number.'">';
            foreach ($cells as $columnIndex => $cell) {
                [$value, $style] = $cell;
                $reference = $this->columnName($columnIndex + 1).$number;
                $numeric = ($cell[2] ?? false) && $value !== null;
                $xmlRows .= $numeric
                    ? '<c r="'.$reference.'" s="'.$style.'" t="n"><v>'.$value.'</v></c>'
                    : '<c r="'.$reference.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.$this->xml((string) $value).'</t></is></c>';
            }
            $xmlRows .= '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="6" topLeftCell="A7" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="1" width="15" customWidth="1"/><col min="2" max="5" width="24" customWidth="1"/><col min="6" max="8" width="20" customWidth="1"/><col min="9" max="9" width="24" customWidth="1"/></cols>'
            .'<sheetData>'.$xmlRows.'</sheetData><autoFilter ref="A6:I'.count($data).'"/><mergeCells count="1"><mergeCell ref="A1:I1"/></mergeCells></worksheet>';
    }

    private function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)).$name;
            $number = intdiv($number, 26);
        }

        return $name;
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>';
    }

    private function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    }

    private function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Beban Kerja" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function workbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="3"><font><sz val="10"/><name val="Aptos"/></font><font><b/><sz val="16"/><color rgb="FF183B65"/><name val="Aptos Display"/></font><font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Aptos"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF183B65"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyFill="1"/><xf numFmtId="2" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }
}
