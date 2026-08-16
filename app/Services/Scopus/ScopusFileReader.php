<?php

namespace App\Services\Scopus;

use Generator;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Turns a Scopus export into rows, whatever shape it arrives in.
 *
 * Two formats show up. The Directorate of Research circulates a trimmed .xlsx
 * with one sheet per download date — ten of them in the file we have, and the
 * column order differs across four of those sheets. A raw .csv straight out of
 * Scopus carries thirty columns including the ones that matter most:
 * Author(s) ID and EID.
 *
 * Everything here reads by header name. Reading by position looked fine on the
 * first sheet and would have silently taken Correspondence Address for Year on
 * the fourth.
 */
class ScopusFileReader
{
    /** Columns we rely on; a file without these cannot be processed. */
    public const REQUIRED = ['Title', 'Authors with affiliations'];

    /**
     * Every data row in the file, as an array keyed by column name.
     *
     * A generator, because the csv is 4.7 MB and the workbook has ten sheets —
     * holding all of it as arrays at once is avoidable.
     *
     * @return Generator<int, array<string, string|null>>
     */
    public function rows(string $path): Generator
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        yield from $extension === 'csv'
            ? $this->csvRows($path)
            : $this->spreadsheetRows($path);
    }

    /** @return Generator<int, array<string, string|null>> */
    protected function csvRows(string $path): Generator
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Could not open {$path}.");
        }

        // The escape argument is passed explicitly: PHP 8.4 deprecates leaving
        // it to the default, and a Scopus title with a backslash would be
        // mangled by the historical default anyway.
        $header = $this->normaliseHeader(fgetcsv($handle, 0, ',', '"', '\\') ?: []);

        $this->assertUsable($header, basename($path));

        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $assoc = $this->combine($header, $row);

            if ($assoc !== null && filled($assoc['Title'] ?? null)) {
                yield $assoc;
            }
        }

        fclose($handle);
    }

    /**
     * Every sheet of a workbook, one after another.
     *
     * The DoR file's ten sheets are cumulative — 49 rows in the December
     * download, 874 in July, 876 distinct DOIs across the lot. Reading them all
     * and letting the caller deduplicate is safer than guessing which sheet is
     * authoritative, and costs one pass.
     *
     * @return Generator<int, array<string, string|null>>
     */
    protected function spreadsheetRows(string $path): Generator
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        $book = $reader->load($path);
        $usableSheets = 0;

        foreach ($book->getSheetNames() as $index => $name) {
            $sheet = $book->getSheet($index);

            $header = $this->normaliseHeader($sheet->rangeToArray('A1:AZ1', null, false, false)[0] ?? []);

            // A sheet that is not an export — a legend, a pivot — is skipped
            // rather than failing the whole file.
            if (array_diff(self::REQUIRED, $header)) {
                continue;
            }

            $usableSheets++;

            $lastRow = $sheet->getHighestDataRow();
            $lastColumn = Coordinate::stringFromColumnIndex(max(count($header), 1));

            foreach ($sheet->rangeToArray("A2:{$lastColumn}{$lastRow}", null, false, false) as $row) {
                $assoc = $this->combine($header, $row);

                if ($assoc !== null && filled($assoc['Title'] ?? null)) {
                    $assoc['_sheet'] = $name;

                    yield $assoc;
                }
            }
        }

        if ($usableSheets === 0) {
            throw new RuntimeException(
                'No sheet in ' . basename($path) . ' has the columns Title and Authors with affiliations.'
            );
        }
    }

    /** @return array<int, string> */
    protected function normaliseHeader(array $header): array
    {
        return array_map(
            function ($value) {
                $clean = preg_replace('/^\xEF\xBB\xBF/', '', (string) $value);
                return trim(preg_replace('/\s+/', ' ', $clean));
            },
            $header,
        );
    }

    /**
     * Pairs a data row with its header.
     *
     * Trailing empty header cells are common in these exports, so the two
     * arrays rarely have the same length and array_combine would fail. The
     * header decides; anything past it is ignored.
     *
     * @return array<string, string|null>|null
     */
    protected function combine(array $header, array $row): ?array
    {
        $assoc = [];

        foreach ($header as $index => $name) {
            if ($name === '') {
                continue;
            }

            $value = $row[$index] ?? null;

            $assoc[$name] = is_string($value) ? trim($value) : $value;
        }

        return $assoc === [] ? null : $assoc;
    }

    /** @param  array<int, string>  $header */
    protected function assertUsable(array $header, string $filename): void
    {
        $missing = array_diff(self::REQUIRED, $header);

        if ($missing) {
            throw new RuntimeException(
                $filename . ' is missing the column(s): ' . implode(', ', $missing)
                . '. Export from Scopus with at least Title and Authors with affiliations.'
            );
        }
    }
}
