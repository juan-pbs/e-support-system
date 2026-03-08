<?php

namespace App\Services\Importacion;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ArchivoImportService
{
    /**
     * Lee un archivo tabular y devuelve filas normalizadas.
     *
     * @param UploadedFile $archivo
     * @param array<string, array<int, string>> $aliases
     * @param int $scanLines
     * @return array{
     *   header_row:int,
     *   headers:array<string,int>,
     *   rows:array<int,array<string,mixed>>
     * }
     */
    public function leer(UploadedFile $archivo, array $aliases, int $scanLines = 15): array
    {
        $rows = $this->extraerFilas($archivo);

        if (empty($rows)) {
            return [
                'header_row' => 1,
                'headers'    => [],
                'rows'       => [],
            ];
        }

        $headerRowIndex = $this->detectarFilaEncabezados($rows, $aliases, $scanLines);
        $headerRaw      = $rows[$headerRowIndex] ?? [];
        $headerMap      = $this->mapearEncabezados($headerRaw, $aliases);

        $items = [];

        foreach ($rows as $index => $row) {
            if ($index <= $headerRowIndex) {
                continue;
            }

            if ($this->filaVacia($row)) {
                continue;
            }

            $item = [
                '_row' => $index + 1,
                '_raw' => $row,
            ];

            foreach ($headerMap as $canonical => $colIndex) {
                $item[$canonical] = $this->cleanCell($row[$colIndex] ?? null);
            }

            $items[] = $item;
        }

        return [
            'header_row' => $headerRowIndex + 1,
            'headers'    => $headerMap,
            'rows'       => $items,
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function extraerFilas(UploadedFile $archivo): array
    {
        $ext = strtolower($archivo->getClientOriginalExtension());

        if ($ext === 'xlsx') {
            $sheet = IOFactory::load($archivo->getRealPath())->getActiveSheet();
            $rows  = $sheet->toArray(null, true, true, false);

            return array_values($rows);
        }

        $lines = file($archivo->getRealPath(), FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return [];
        }

        $rows = [];
        foreach ($lines as $line) {
            $rows[] = str_getcsv($line);
        }

        return $rows;
    }

    /**
     * @param array<int, array<int, mixed>> $rows
     * @param array<string, array<int, string>> $aliases
     */
    private function detectarFilaEncabezados(array $rows, array $aliases, int $scanLines = 15): int
    {
        $known = collect($aliases)
            ->flatten()
            ->map(fn($v) => $this->normalizeHeader((string) $v))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $bestIndex = 0;
        $bestScore = -1;
        $limit     = min(count($rows), $scanLines);

        for ($i = 0; $i < $limit; $i++) {
            $score = 0;

            foreach (($rows[$i] ?? []) as $cell) {
                $norm = $this->normalizeHeader((string) $cell);
                if ($norm !== '' && in_array($norm, $known, true)) {
                    $score++;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIndex = $i;
            }
        }

        return $bestIndex;
    }

    /**
     * @param array<int, mixed> $headerRow
     * @param array<string, array<int, string>> $aliases
     * @return array<string, int>
     */
    private function mapearEncabezados(array $headerRow, array $aliases): array
    {
        $normalizedHeader = [];
        foreach ($headerRow as $idx => $cell) {
            $normalizedHeader[$idx] = $this->normalizeHeader((string) $cell);
        }

        $mapped = [];

        foreach ($aliases as $canonical => $list) {
            $normalizedAliases = collect($list)
                ->map(fn($v) => $this->normalizeHeader((string) $v))
                ->filter()
                ->unique()
                ->values()
                ->all();

            foreach ($normalizedHeader as $idx => $header) {
                if (in_array($header, $normalizedAliases, true)) {
                    $mapped[$canonical] = $idx;
                    break;
                }
            }
        }

        return $mapped;
    }

    /**
     * @param array<int, mixed> $row
     */
    private function filaVacia(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeHeader(string $value): string
    {
        $value = Str::ascii($value);
        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';

        return trim($value);
    }

    private function cleanCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        return trim((string) $value);
    }
}
