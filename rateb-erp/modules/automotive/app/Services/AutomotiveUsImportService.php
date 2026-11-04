<?php
declare(strict_types=1);

namespace Rateb\App\Automotive\Services;

use PDO;

/** Loads local NHTSA and EPA snapshots. Does not call external APIs and does not match VehiclesDB. */
final class AutomotiveUsImportService
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<string, int|string> */
    public function dryRun(string $dir): array
    {
        $db = (string) $this->pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($db !== 'admin_rateb_epc_phase0') {
            throw new \RuntimeException('refused_database');
        }
        $makes = $this->decodeMakes($dir . '/nhtsa-makes.json');
        $models = $this->readModels($dir . '/nhtsa-models.csv');
        $mfrs = $this->readManufacturers($dir . '/mfr-pages');
        $epa = $this->readEpa($dir . '/vehicles.csv');
        return [
            'database' => $db,
            'nhtsa_makes' => count($makes),
            'nhtsa_models' => count($models),
            'nhtsa_manufacturers' => count($mfrs),
            'epa_vehicles' => count($epa),
        ];
    }

    public function import(string $dir, string $retrievedOn): void
    {
        $plan = $this->dryRun($dir);
        if ($plan['nhtsa_makes'] < 1 || $plan['nhtsa_models'] < 1 || $plan['nhtsa_manufacturers'] < 1 || $plan['epa_vehicles'] < 1) {
            throw new \RuntimeException('empty_snapshot');
        }
        $this->pdo->beginTransaction();
        try {
            $this->importMakes($dir, $retrievedOn);
            $this->importModels($dir, $retrievedOn);
            $this->importManufacturers($dir, $retrievedOn);
            $this->importEpa($dir, $retrievedOn);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function importMakes(string $dir, string $retrievedOn): void
    {
        $file = $dir . '/nhtsa-makes.json';
        $releaseId = $this->release('nhtsa', 'makes', $retrievedOn, hash_file('sha256', $file) ?: '', 'NHTSA vPIC GetAllMakes snapshot. US market only. Public government data, https://vpic.nhtsa.dot.gov/api/');
        $values = [];
        foreach ($this->decodeMakes($file) as $row) {
            $values[] = '(' . $releaseId . ',' . $row['Make_ID'] . ',' . $this->pdo->quote($row['Make_Name']) . ')';
        }
        $this->chunks('INSERT INTO rateb_auto_ref_us_nhtsa_makes (release_id, make_id, make_name) VALUES ', $values);
    }

    private function importModels(string $dir, string $retrievedOn): void
    {
        $file = $dir . '/nhtsa-models.csv';
        $releaseId = $this->release('nhtsa', 'models', $retrievedOn, hash_file('sha256', $file) ?: '', 'NHTSA vPIC GetModelsForMake snapshot. No model year in this file. US market only. https://vpic.nhtsa.dot.gov/api/');
        $values = [];
        foreach ($this->readModels($file) as $row) {
            $values[] = '(' . $releaseId . ',' . $row['make_id'] . ',' . $this->pdo->quote($row['make_name']) . ',' . $row['model_id'] . ',' . $this->pdo->quote($row['model_name']) . ')';
        }
        $this->chunks('INSERT INTO rateb_auto_ref_us_nhtsa_models (release_id, make_id, make_name, model_id, model_name) VALUES ', $values);
    }

    private function importManufacturers(string $dir, string $retrievedOn): void
    {
        $pages = $dir . '/mfr-pages';
        $releaseId = $this->release('nhtsa', 'manufacturers', $retrievedOn, $this->directoryHash($pages), 'NHTSA vPIC GetAllManufacturers pages 1-239. US reporting scope. https://vpic.nhtsa.dot.gov/api/');
        $values = [];
        foreach ($this->readManufacturers($pages) as $row) {
            $common = $row['common_name'] === null ? 'NULL' : $this->pdo->quote($row['common_name']);
            $values[] = '(' . $releaseId . ',' . $row['mfr_id'] . ',' . $this->pdo->quote($row['mfr_name']) . ',' . $common . ',' . $this->pdo->quote($row['country_name']) . ',' . $this->pdo->quote($row['vehicle_types']) . ')';
        }
        $this->chunks('INSERT INTO rateb_auto_ref_us_nhtsa_manufacturers (release_id, mfr_id, mfr_name, common_name, country_name, vehicle_types) VALUES ', $values);
    }

    private function importEpa(string $dir, string $retrievedOn): void
    {
        $file = $dir . '/vehicles.csv';
        $releaseId = $this->release('epa', 'vehicles', $retrievedOn, hash_file('sha256', $file) ?: '', 'US DOE/EPA FuelEconomy.gov vehicles.csv. https://fueleconomy.gov/feg/download.shtml');
        $values = [];
        $q = fn (?string $value): string => $value === null ? 'NULL' : $this->pdo->quote($value);
        foreach ($this->readEpa($file) as $row) {
            $values[] = '(' . implode(',', [
                $releaseId, $row['epa'], $row['year'], $q($row['make']), $q($row['model']), $q($row['base']), $q($row['class']), $q($row['fuel']),
                $q($row['cyl']), $q($row['displ']), $q($row['trany']), $q($row['drive']), $q($row['city']), $q($row['hwy']), $q($row['comb']), $q($row['eng']), $q($row['hash']),
            ]) . ')';
        }
        $this->chunks(
            'INSERT INTO rateb_auto_ref_us_epa_vehicles (release_id, epa_id, model_year, make_name, model_name, base_model, vclass, fuel_type1, cylinders, displ, trany, drive, city_mpg, highway_mpg, comb_mpg, eng_id, line_sha256) VALUES ',
            $values
        );
    }

    /** @param list<string> $values */
    private function chunks(string $sql, array $values): void
    {
        foreach (array_chunk($values, 200) as $chunk) {
            $this->pdo->exec($sql . implode(',', $chunk));
        }
    }

    private function release(string $provider, string $dataset, string $retrievedOn, string $hash, string $attribution): int
    {
        if (strlen($hash) !== 64) {
            throw new \RuntimeException('bad_hash');
        }
        $exists = $this->pdo->prepare('SELECT id FROM rateb_auto_ref_us_releases WHERE provider_code = :provider AND dataset_code = :dataset AND file_sha256 = :hash');
        $exists->execute(['provider' => $provider, 'dataset' => $dataset, 'hash' => $hash]);
        if ($exists->fetch()) {
            throw new \RuntimeException('duplicate_release');
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO rateb_auto_ref_us_releases (provider_code, dataset_code, version_label, retrieved_on, attribution, license_evidence, file_sha256, market_scope)
             VALUES (:provider, :dataset, :version, :retrieved, :attribution, :license, :hash, :scope)'
        );
        $stmt->execute([
            'provider' => $provider,
            'dataset' => $dataset,
            'version' => $retrievedOn,
            'retrieved' => $retrievedOn,
            'attribution' => $attribution,
            'license' => $provider === 'epa'
                ? 'FuelEconomy.gov vehicles.csv. Commercial redistribution rights have not been independently established. https://fueleconomy.gov/feg/download.shtml'
                : 'NHTSA vPIC public API. Commercial redistribution rights have not been independently established. https://vpic.nhtsa.dot.gov/api/',
            'hash' => $hash,
            'scope' => 'US',
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<array{Make_ID:int,Make_Name:string}> */
    private function decodeMakes(string $file): array
    {
        $json = json_decode((string) file_get_contents($file), true);
        $rows = [];
        $seen = [];
        foreach ($json['Results'] ?? [] as $row) {
            $id = (int) ($row['Make_ID'] ?? 0);
            $name = trim((string) ($row['Make_Name'] ?? ''));
            if ($id < 1 || $name === '' || isset($seen[$id])) {
                throw new \RuntimeException('invalid_make');
            }
            $seen[$id] = true;
            $rows[] = ['Make_ID' => $id, 'Make_Name' => $name];
        }
        return $rows;
    }

    /** @return list<array{make_id:int,make_name:string,model_id:int,model_name:string}> */
    private function readModels(string $file): array
    {
        $fh = fopen($file, 'rb');
        if ($fh === false) {
            throw new \RuntimeException('models_missing');
        }
        $header = fgetcsv($fh);
        if ($header !== ['make_id', 'make_name', 'model_id', 'model_name']) {
            fclose($fh);
            throw new \RuntimeException('models_header');
        }
        $rows = [];
        $seen = [];
        while (($csv = fgetcsv($fh)) !== false) {
            if (count($csv) < 4) {
                continue;
            }
            $key = $csv[0] . ':' . $csv[2];
            if (isset($seen[$key]) || !ctype_digit($csv[0]) || !ctype_digit($csv[2]) || trim($csv[1]) === '' || trim($csv[3]) === '') {
                fclose($fh);
                throw new \RuntimeException('invalid_model');
            }
            $seen[$key] = true;
            $rows[] = ['make_id' => (int) $csv[0], 'make_name' => trim($csv[1]), 'model_id' => (int) $csv[2], 'model_name' => trim($csv[3])];
        }
        fclose($fh);
        return $rows;
    }

    /** @return list<array{mfr_id:int,mfr_name:string,common_name:?string,country_name:string,vehicle_types:string}> */
    private function readManufacturers(string $dir): array
    {
        $files = glob($dir . '/page-*.json') ?: [];
        natsort($files);
        $rows = [];
        $seen = [];
        foreach ($files as $file) {
            $json = json_decode((string) file_get_contents($file), true);
            foreach ($json['Results'] ?? [] as $row) {
                $id = (int) ($row['Mfr_ID'] ?? 0);
                $name = trim((string) ($row['Mfr_Name'] ?? ''));
                $country = trim((string) ($row['Country'] ?? ''));
                if ($id < 1 || $name === '' || isset($seen[$id])) {
                    throw new \RuntimeException('invalid_manufacturer');
                }
                $seen[$id] = true;
                $types = [];
                foreach ($row['VehicleTypes'] ?? [] as $type) {
                    $label = trim((string) ($type['Name'] ?? ''));
                    if ($label !== '') {
                        $types[] = $label;
                    }
                }
                $common = trim((string) ($row['Mfr_CommonName'] ?? ''));
                $rows[] = [
                    'mfr_id' => $id,
                    'mfr_name' => $name,
                    'common_name' => $common === '' ? null : $common,
                    'country_name' => $country,
                    'vehicle_types' => implode(', ', $types),
                ];
            }
        }
        return $rows;
    }

    /** @return list<array<string, int|string|null>> */
    private function readEpa(string $file): array
    {
        $fh = fopen($file, 'rb');
        if ($fh === false) {
            throw new \RuntimeException('epa_missing');
        }
        $header = fgetcsv($fh);
        if (!is_array($header)) {
            fclose($fh);
            throw new \RuntimeException('epa_header');
        }
        $index = array_flip($header);
        foreach (['id', 'year', 'make', 'model', 'VClass', 'fuelType1', 'city08', 'highway08', 'comb08'] as $required) {
            if (!isset($index[$required])) {
                fclose($fh);
                throw new \RuntimeException('epa_header');
            }
        }
        $rows = [];
        $seen = [];
        while (($csv = fgetcsv($fh)) !== false) {
            $line = implode(',', $csv);
            $id = (int) ($csv[$index['id']] ?? 0);
            $year = (int) ($csv[$index['year']] ?? 0);
            $make = trim((string) ($csv[$index['make']] ?? ''));
            $model = trim((string) ($csv[$index['model']] ?? ''));
            if ($id < 1 || $year < 1984 || $make === '' || $model === '' || isset($seen[$id])) {
                fclose($fh);
                throw new \RuntimeException('invalid_epa');
            }
            $seen[$id] = true;
            $optional = static function (array $csv, array $index, string $key): ?string {
                if (!isset($index[$key])) {
                    return null;
                }
                $value = trim((string) ($csv[$index[$key]] ?? ''));
                return $value === '' ? null : $value;
            };
            $rows[] = [
                'epa' => $id,
                'year' => $year,
                'make' => $make,
                'model' => $model,
                'base' => $optional($csv, $index, 'baseModel'),
                'class' => trim((string) $csv[$index['VClass']]),
                'fuel' => trim((string) $csv[$index['fuelType1']]),
                'cyl' => $optional($csv, $index, 'cylinders'),
                'displ' => $optional($csv, $index, 'displ'),
                'trany' => $optional($csv, $index, 'trany'),
                'drive' => $optional($csv, $index, 'drive'),
                'city' => trim((string) $csv[$index['city08']]),
                'hwy' => trim((string) $csv[$index['highway08']]),
                'comb' => trim((string) $csv[$index['comb08']]),
                'eng' => $optional($csv, $index, 'engId'),
                'hash' => hash('sha256', $line),
            ];
        }
        fclose($fh);
        return $rows;
    }

    private function directoryHash(string $dir): string
    {
        $files = glob($dir . '/page-*.json') ?: [];
        natsort($files);
        $ctx = hash_init('sha256');
        foreach ($files as $file) {
            hash_update_file($ctx, $file);
        }
        $hash = hash_final($ctx);
        return strlen($hash) === 64 ? $hash : '';
    }
}
