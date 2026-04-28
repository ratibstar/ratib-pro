<?php
/**
 * EN: Handles API endpoint/business logic in `api/core/date-helper.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/core/date-helper.php`.
 */
/**
 * Date Formatting Helper for Accounting System
 * Ensures all dates are formatted consistently as MM/DD/YYYY for frontend display
 */

if (!function_exists('formatDateForDisplay')) {
    /**
     * Format date for frontend display (MM/DD/YYYY)
     * @param string|null $dateString Date in any format (YYYY-MM-DD, timestamp, etc.)
     * @return string Formatted date as MM/DD/YYYY or empty string
     */
    function formatDateForDisplay($dateString) {
        if (empty($dateString) || $dateString === '0000-00-00' || $dateString === '0000-00-00 00:00:00') {
            return '';
        }
        
        try {
            // Handle different input formats
            $timestamp = null;
            
            // If already in YYYY-MM-DD format
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $dateString)) {
                $parts = explode(' ', $dateString);
                $datePart = $parts[0];
                $dateParts = explode('-', $datePart);
                if (count($dateParts) === 3) {
                    return sprintf('%02d/%02d/%04d', $dateParts[1], $dateParts[2], $dateParts[0]);
                }
            }
            
            // Try to parse as date
            $timestamp = strtotime($dateString);
            if ($timestamp === false) {
                return $dateString; // Return original if can't parse
            }
            
            // Format as MM/DD/YYYY
            return date('m/d/Y', $timestamp);
        } catch (Exception $e) {
            return $dateString; // Return original on error
        }
    }
}

if (!function_exists('formatDateForDatabase')) {
    /**
     * Convert date from frontend format (MM/DD/YYYY) to database format (YYYY-MM-DD)
     * @param string|null $dateString Date in MM/DD/YYYY format
     * @return string|null Date in YYYY-MM-DD format or null
     */
    function formatDateForDatabase($dateString) {
        if (empty($dateString) || $dateString === null || $dateString === '') {
            return null;
        }
        
        // Trim whitespace
        $dateString = trim($dateString);
        if (empty($dateString)) {
            return null;
        }
        
        try {
            // If already in YYYY-MM-DD format, return as is
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $dateString)) {
                $parts = explode(' ', $dateString);
                return $parts[0]; // Return just the date part
            }
            
            // If in MM/DD/YYYY format, convert
            if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})/', $dateString, $matches)) {
                $month = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                $day = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                $year = $matches[3];
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
            
            // Try to parse and convert
            $timestamp = strtotime($dateString);
            if ($timestamp !== false) {
                return date('Y-m-d', $timestamp);
            }
            
            return null;
        } catch (Exception $e) {
            error_log("formatDateForDatabase error: " . $e->getMessage() . " for input: " . $dateString);
            return null;
        } catch (Throwable $e) {
            error_log("formatDateForDatabase error: " . $e->getMessage() . " for input: " . $dateString);
            return null;
        }
    }
}

if (!function_exists('formatDatesInArray')) {
    /**
     * Recursively format all date fields in an array
     * @param array $data Array containing date fields
     * @param array $dateFields List of field names that contain dates
     * @return array Array with formatted dates
     */
    function formatDatesInArray($data, $dateFields = null) {
        if (!is_array($data)) {
            return $data;
        }
        
        // Default date field names
        if ($dateFields === null) {
            $dateFields = [
                'date', 'transaction_date', 'invoice_date', 'bill_date', 'due_date',
                'entry_date', 'posting_date', 'voucher_date', 'payment_date',
                'created_at', 'updated_at', 'issue_date', 'expiry_date',
                'reconciliation_date', 'start_date', 'end_date', 'closing_date',
                'allocation_date', 'period', 'as_of'
            ];
        }
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = formatDatesInArray($value, $dateFields);
            } elseif (in_array($key, $dateFields) && !empty($value)) {
                $data[$key] = formatDateForDisplay($value);
            }
        }
        
        return $data;
    }
}
