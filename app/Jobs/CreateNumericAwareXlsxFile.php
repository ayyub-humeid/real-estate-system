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
 * Writes the export's money columns as numeric cells instead of text.
 *
 * Mirrors Filament's own CreateXlsxFile, changing one thing: for an exporter that
 * declares numeric columns, those cells are cast to float before openspout types
 * them, so a finance team can total the column in Excel.
 */
class CreateNumericAwareXlsxFile extends CreateXlsxFile
{
    public function handle(): void
    {
        $disk = $this->export->getFileDisk();

        $writer = app(Writer::class);
        $writer->openToFile($temporaryFile = tempnam(sys_get_temp_dir(), $this->export->file_name));

        $csvDelimiter = $this->exporter::getCsvDelimiter();
        $numericPositions = $this->numericColumnPositions();

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
            $this->export->getFileDirectory().DIRECTORY_SEPARATOR.'headers.csv',
            $this->exporter->getXlsxHeaderCellStyle() ?? $cellStyle,
        );

        foreach ($disk->files($this->export->getFileDirectory()) as $file) {
            if (str($file)->endsWith('headers.csv') || ! str($file)->endsWith('.csv')) {
                continue;
            }

            $writeRowsFromFile($file, $cellStyle, castNumerics: true);
        }

        $writer->close();

        $disk->putFileAs(
            $this->export->getFileDirectory(),
            new File($temporaryFile),
            "{$this->export->file_name}.xlsx",
            Filesystem::VISIBILITY_PRIVATE,
        );

        unlink($temporaryFile);
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
