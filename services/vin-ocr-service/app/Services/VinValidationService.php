<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Exception;
use Shared\Core\BaseService;

class VinValidationService extends BaseService
{
    /**
     * Validate VIN and return detailed information
     *
     * @param string $vin
     * @return array
     */
    public function validateVin(string $vin): array
    {
        try {
            Log::info('Starting VIN validation', ['vin' => $vin]);

            // Clean and normalize VIN
            $cleanVin = $this->cleanVin($vin);
            
            // Basic format validation
            $formatValidation = $this->validateFormat($cleanVin);
            if (!$formatValidation['valid']) {
                return [
                    'valid' => false,
                    'vin' => $cleanVin,
                    'errors' => $formatValidation['errors'],
                    'details' => null
                ];
            }

            // Extract VIN components
            $components = $this->extractVinComponents($cleanVin);
            
            // Validate check digit
            $checkDigitValidation = $this->validateCheckDigit($cleanVin);
            
            // Get manufacturer information
            $manufacturerInfo = $this->getManufacturerInfo($components['wmi']);
            
            // Get year information
            $yearInfo = $this->getYearInfo($components['year_code']);
            
            // Compile validation result
            $result = [
                'valid' => $checkDigitValidation['valid'],
                'vin' => $cleanVin,
                'errors' => $checkDigitValidation['valid'] ? [] : $checkDigitValidation['errors'],
                'details' => [
                    'components' => $components,
                    'manufacturer' => $manufacturerInfo,
                    'year' => $yearInfo,
                    'check_digit' => $checkDigitValidation,
                    'validation_timestamp' => now()->toISOString()
                ]
            ];

            Log::info('VIN validation completed', [
                'vin' => $cleanVin,
                'valid' => $result['valid'],
                'manufacturer' => $manufacturerInfo['name'] ?? 'Unknown'
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('VIN validation failed', [
                'vin' => $vin,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'valid' => false,
                'vin' => $vin,
                'errors' => ['Validation service error: ' . $e->getMessage()],
                'details' => null
            ];
        }
    }

    /**
     * Clean and normalize VIN
     *
     * @param string $vin
     * @return string
     */
    protected function cleanVin(string $vin): string
    {
        // Remove spaces, dashes, and convert to uppercase
        return strtoupper(preg_replace('/[^A-Z0-9]/', '', $vin));
    }

    /**
     * Validate VIN format
     *
     * @param string $vin
     * @return array
     */
    protected function validateFormat(string $vin): array
    {
        $errors = [];

        // Check length
        if (strlen($vin) !== 17) {
            $errors[] = "VIN must be exactly 17 characters long (current: " . strlen($vin) . ")";
        }

        // Check for invalid characters (I, O, Q are not allowed)
        if (preg_match('/[IOQ]/', $vin)) {
            $errors[] = "VIN cannot contain letters I, O, or Q";
        }

        // Check for valid characters only
        if (!preg_match('/^[A-HJ-NPR-Z0-9]+$/', $vin)) {
            $errors[] = "VIN contains invalid characters";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Extract VIN components
     *
     * @param string $vin
     * @return array
     */
    protected function extractVinComponents(string $vin): array
    {
        if (strlen($vin) !== 17) {
            return [];
        }

        return [
            'wmi' => substr($vin, 0, 3),           // World Manufacturer Identifier
            'vds' => substr($vin, 3, 6),           // Vehicle Descriptor Section
            'vis' => substr($vin, 9, 8),           // Vehicle Identifier Section
            'year_code' => substr($vin, 9, 1),     // Model year
            'plant_code' => substr($vin, 10, 1),   // Manufacturing plant
            'serial_number' => substr($vin, 11, 6), // Serial number
            'check_digit' => substr($vin, 8, 1)    // Check digit (9th position)
        ];
    }

    /**
     * Validate check digit using VIN algorithm
     *
     * @param string $vin
     * @return array
     */
    protected function validateCheckDigit(string $vin): array
    {
        if (strlen($vin) !== 17) {
            return [
                'valid' => false,
                'errors' => ['Invalid VIN length for check digit validation']
            ];
        }

        // VIN character values
        $values = [
            'A' => 1, 'B' => 2, 'C' => 3, 'D' => 4, 'E' => 5, 'F' => 6, 'G' => 7, 'H' => 8,
            'J' => 1, 'K' => 2, 'L' => 3, 'M' => 4, 'N' => 5, 'P' => 7, 'R' => 9,
            'S' => 2, 'T' => 3, 'U' => 4, 'V' => 5, 'W' => 6, 'X' => 7, 'Y' => 8, 'Z' => 9,
            '0' => 0, '1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6, '7' => 7, '8' => 8, '9' => 9
        ];

        // Position weights
        $weights = [8, 7, 6, 5, 4, 3, 2, 10, 0, 9, 8, 7, 6, 5, 4, 3, 2];

        $sum = 0;
        for ($i = 0; $i < 17; $i++) {
            if ($i === 8) continue; // Skip check digit position
            
            $char = $vin[$i];
            if (!isset($values[$char])) {
                return [
                    'valid' => false,
                    'errors' => ["Invalid character '{$char}' at position " . ($i + 1)]
                ];
            }
            
            $sum += $values[$char] * $weights[$i];
        }

        $remainder = $sum % 11;
        $expectedCheckDigit = $remainder === 10 ? 'X' : (string)$remainder;
        $actualCheckDigit = $vin[8];

        $valid = $expectedCheckDigit === $actualCheckDigit;

        return [
            'valid' => $valid,
            'expected' => $expectedCheckDigit,
            'actual' => $actualCheckDigit,
            'errors' => $valid ? [] : ["Check digit mismatch: expected '{$expectedCheckDigit}', got '{$actualCheckDigit}'"]
        ];
    }

    /**
     * Get manufacturer information from WMI
     *
     * @param string $wmi
     * @return array
     */
    protected function getManufacturerInfo(string $wmi): array
    {
        // Common WMI codes and manufacturers
        $manufacturers = [
            // US Manufacturers
            '1HG' => ['name' => 'Honda', 'country' => 'United States', 'region' => 'North America'],
            '1G1' => ['name' => 'Chevrolet', 'country' => 'United States', 'region' => 'North America'],
            '1FT' => ['name' => 'Ford', 'country' => 'United States', 'region' => 'North America'],
            '1C3' => ['name' => 'Chrysler', 'country' => 'United States', 'region' => 'North America'],
            '1N4' => ['name' => 'Nissan', 'country' => 'United States', 'region' => 'North America'],
            '1VW' => ['name' => 'Volkswagen', 'country' => 'United States', 'region' => 'North America'],
            
            // Japanese Manufacturers
            'JH4' => ['name' => 'Honda', 'country' => 'Japan', 'region' => 'Asia'],
            'JHM' => ['name' => 'Honda', 'country' => 'Japan', 'region' => 'Asia'],
            'JT2' => ['name' => 'Toyota', 'country' => 'Japan', 'region' => 'Asia'],
            'JF1' => ['name' => 'Subaru', 'country' => 'Japan', 'region' => 'Asia'],
            'JM1' => ['name' => 'Mazda', 'country' => 'Japan', 'region' => 'Asia'],
            
            // German Manufacturers
            'WBA' => ['name' => 'BMW', 'country' => 'Germany', 'region' => 'Europe'],
            'WBS' => ['name' => 'BMW', 'country' => 'Germany', 'region' => 'Europe'],
            'WDD' => ['name' => 'Mercedes-Benz', 'country' => 'Germany', 'region' => 'Europe'],
            'WVW' => ['name' => 'Volkswagen', 'country' => 'Germany', 'region' => 'Europe'],
            'WAU' => ['name' => 'Audi', 'country' => 'Germany', 'region' => 'Europe'],
            
            // Korean Manufacturers
            'KMH' => ['name' => 'Hyundai', 'country' => 'South Korea', 'region' => 'Asia'],
            'KNA' => ['name' => 'Kia', 'country' => 'South Korea', 'region' => 'Asia'],
            '5NP' => ['name' => 'Hyundai', 'country' => 'South Korea', 'region' => 'Asia'],
            
            // Other
            '5YJ' => ['name' => 'Tesla', 'country' => 'United States', 'region' => 'North America'],
            'YV1' => ['name' => 'Volvo', 'country' => 'Sweden', 'region' => 'Europe'],
        ];

        if (isset($manufacturers[$wmi])) {
            return $manufacturers[$wmi];
        }

        // Try to determine region from first character
        $firstChar = $wmi[0];
        $region = 'Unknown';
        $country = 'Unknown';

        if (in_array($firstChar, ['1', '4', '5'])) {
            $region = 'North America';
            $country = 'United States/Canada/Mexico';
        } elseif (in_array($firstChar, ['J'])) {
            $region = 'Asia';
            $country = 'Japan';
        } elseif (in_array($firstChar, ['K'])) {
            $region = 'Asia';
            $country = 'South Korea';
        } elseif (in_array($firstChar, ['S', 'W', 'Z'])) {
            $region = 'Europe';
        } elseif (in_array($firstChar, ['6', '7'])) {
            $region = 'Oceania';
        } elseif (in_array($firstChar, ['8', '9', 'A', 'B', 'C', 'D', 'E', 'F'])) {
            $region = 'South America';
        }

        return [
            'name' => 'Unknown Manufacturer',
            'country' => $country,
            'region' => $region,
            'wmi' => $wmi
        ];
    }

    /**
     * Get year information from year code
     *
     * @param string $yearCode
     * @return array
     */
    protected function getYearInfo(string $yearCode): array
    {
        // VIN year codes (30-year cycle)
        $yearCodes = [
            'A' => 1980, 'B' => 1981, 'C' => 1982, 'D' => 1983, 'E' => 1984, 'F' => 1985,
            'G' => 1986, 'H' => 1987, 'J' => 1988, 'K' => 1989, 'L' => 1990, 'M' => 1991,
            'N' => 1992, 'P' => 1993, 'R' => 1994, 'S' => 1995, 'T' => 1996, 'V' => 1997,
            'W' => 1998, 'X' => 1999, 'Y' => 2000, '1' => 2001, '2' => 2002, '3' => 2003,
            '4' => 2004, '5' => 2005, '6' => 2006, '7' => 2007, '8' => 2008, '9' => 2009,
        ];

        if (!isset($yearCodes[$yearCode])) {
            return [
                'code' => $yearCode,
                'year' => null,
                'possible_years' => [],
                'error' => 'Invalid year code'
            ];
        }

        $baseYear = $yearCodes[$yearCode];
        $currentYear = (int)date('Y');
        
        // Calculate possible years (30-year cycle)
        $possibleYears = [];
        for ($cycle = 0; $cycle < 3; $cycle++) {
            $year = $baseYear + ($cycle * 30);
            if ($year <= $currentYear + 1) { // Allow for next model year
                $possibleYears[] = $year;
            }
        }

        // Most likely year is the most recent one
        $mostLikelyYear = end($possibleYears);

        return [
            'code' => $yearCode,
            'year' => $mostLikelyYear,
            'possible_years' => $possibleYears,
            'cycle_info' => [
                'base_year' => $baseYear,
                'cycle_length' => 30,
                'note' => 'VIN year codes repeat every 30 years'
            ]
        ];
    }

    /**
     * Batch validate multiple VINs
     *
     * @param array $vins
     * @return array
     */
    public function batchValidate(array $vins): array
    {
        $results = [];
        
        foreach ($vins as $index => $vin) {
            $results[$index] = $this->validateVin($vin);
        }
        
        return [
            'total' => count($vins),
            'valid' => count(array_filter($results, fn($r) => $r['valid'])),
            'invalid' => count(array_filter($results, fn($r) => !$r['valid'])),
            'results' => $results
        ];
    }

    /**
     * Get VIN validation statistics
     *
     * @param array $validationResults
     * @return array
     */
    public function getValidationStats(array $validationResults): array
    {
        $stats = [
            'total' => count($validationResults),
            'valid' => 0,
            'invalid' => 0,
            'manufacturers' => [],
            'years' => [],
            'common_errors' => []
        ];

        foreach ($validationResults as $result) {
            if ($result['valid']) {
                $stats['valid']++;
                
                // Count manufacturers
                if (isset($result['details']['manufacturer']['name'])) {
                    $manufacturer = $result['details']['manufacturer']['name'];
                    $stats['manufacturers'][$manufacturer] = ($stats['manufacturers'][$manufacturer] ?? 0) + 1;
                }
                
                // Count years
                if (isset($result['details']['year']['year'])) {
                    $year = $result['details']['year']['year'];
                    $stats['years'][$year] = ($stats['years'][$year] ?? 0) + 1;
                }
            } else {
                $stats['invalid']++;
                
                // Count common errors
                foreach ($result['errors'] as $error) {
                    $stats['common_errors'][$error] = ($stats['common_errors'][$error] ?? 0) + 1;
                }
            }
        }

        // Sort by frequency
        arsort($stats['manufacturers']);
        arsort($stats['years']);
        arsort($stats['common_errors']);

        return $stats;
    }
}
