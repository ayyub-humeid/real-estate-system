<?php

namespace App\Jobs;

use App\Filament\Exports\Contracts\HasNumericExportColumns;
use Filament\Actions\Exports\Jobs\CreateXlsxFile;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\File;
use League\Csv\Reader as CsvReader;
use League\Csv\Statement;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Writes the export's money columns as numeric cells instead of text, and widens
 * every column to fit the content it actually carries.
 *
 * Mirrors Filament's own CreateXlsxFile, changing two things: for an exporter
 * that declares numeric columns, those cells are cast to float before openspout
 * types them (so a finance team can total the column in Excel), and each column
 * gets a width matched to the longest value it holds (so a sheet opens readable,
 * without manual resizing).
 */
class CreateNumericAwareXlsxFile extends CreateXlsxFile
{
    /** Hard caps keep a stray long cell from pushing a column off the screen. */
    private const MIN_WIDTH = 10.0;
    private const MAX_WIDTH = 50.0;
    private const PADDING = 2.0;

    public function handle(): void
    {
        $disk = $this->export->getFileDisk();

        $writer = app(Writer::class);
        $writer->openToFile($temporaryFile = tempnam(sys_get_temp_dir(), $this->export->file_name));

        $csvDelimiter = $this->exporter::getCsvDelimiter();
        $numericPositions = $this->numericColumnPositions();
        $directory = $this->export->getFileDirectory();

        $this->applyColumnWidths($writer, $disk, $directory, $csvDelimiter);

        $writeRowsFromFile = function (string $file, ?Style $style = null, bool $castNumerics = false) use ($csvDelimiter, $disk, $writer, $numericPositions): void {
            $csvReader = CsvReader::createFromStream($disk->readStream($file));
            $csvReader->setDelimiter($csvDelimiter);

            foreach ((new Statement)->process($csvReader)->getRecords() as $row) {
                $values = array_values($row);

                if ($castNumerics) {
                    foreach ($numericPositions as $position) {
                        $value = $values[$position] ?? null;
                        if ($value !== null && $value !== '' && is_numeric($value)) {
                            $values[$position] = (float) $value;
                        }
                    }
                }

                $writer->addRow(Row::fromValues($values, $style));
            }
        };

        $cellStyle = $this->exporter->getXlsxCellStyle();

        // Headers stay text; only data rows are cast.
        $writeRowsFromFile(
            $directory.DIRECTORY_SEPARATOR.'headers.csv',
            $this->exporter->getXlsxHeaderCellStyle() ?? $cellStyle,
        );

        foreach ($disk->files($directory) as $file) {
            if (str($file)->endsWith('headers.csv') || ! str($file)->endsWith('.csv')) {
                continue;
            }

            $writeRowsFromFile($file, $cellStyle, castNumerics: true);
        }

        $writer->close();

        $disk->putFileAs(
            $directory,
            new File($temporaryFile),
            "{$this->export->file_name}.xlsx",
            Filesystem::VISIBILITY_PRIVATE,
        );

        unlink($temporaryFile);
    }

    /**
     * One scan across header + data to compute each column's longest cell,
     * then set a width that fits that cell. openspout requires widths to be
     * set before rows are written, so the scan has to happen first.
     */
    private function applyColumnWidths(Writer $writer, Filesystem $disk, string $directory, string $csvDelimiter): void
    {
        $maxLengths = $this->maxLengthsFromFile($disk, $directory.DIRECTORY_SEPARATOR.'headers.csv', $csvDelimiter, []);

        foreach ($disk->files($directory) as $file) {
            if (str($file)->endsWith('headers.csv') || ! str($file)->endsWith('.csv')) {
                continue;
            }
            $maxLengths = $this->maxLengthsFromFile($disk, $file, $csvDelimiter, $maxLengths);
        }

        if ($maxLengths === []) {
            return;
        }

        $sheet = $writer->getCurrentSheet();
        foreach ($maxLengths as $index => $length) {
            $width = max(self::MIN_WIDTH, min(self::MAX_WIDTH, $length + self::PADDING));
            // openspout column indices are 1-based.
            $sheet->setColumnWidth($width, $index + 1);
        }
    }

    /**
     * @param array<int, int> $carry
     * @return array<int, int>
     */
    private function maxLengthsFromFile(Filesystem $disk, string $file, string $csvDelimiter, array $carry): array
    {
        $csvReader = CsvReader::createFromStream($disk->readStream($file));
        $csvReader->setDelimiter($csvDelimiter);

        foreach ((new Statement)->process($csvReader)->getRecords() as $row) {
            foreach (array_values($row) as $index => $value) {
                $length = mb_strlen((string) $value);
                $carry[$index] = max($carry[$index] ?? 0, $length);
            }
        }

        return $carry;
    }

    /**
     * Column positions in the exported sheet that hold numbers. The export's column
     * map is ordered, so a name's position in it is its column index.
     *
     * @return array<int, int>
     */
    protected function numericColumnPositions(): array
    {
        if (! $this->exporter instanceof HasNumericExportColumns) {
            return [];
        }

        $numericNames = $this->exporter::numericColumns();

        return array_keys(array_filter(
            array_keys($this->columnMap),
            fn (string $name): bool => in_array($name, $numericNames, true),
        ));
    }
}
