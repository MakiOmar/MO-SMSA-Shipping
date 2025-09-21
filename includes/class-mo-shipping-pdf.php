<?php
/**
 * PDF Handler for MO Shipping
 *
 * @package MO_Shipping_Integration
 * @since 2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use setasign\Fpdi\Fpdi;

/**
 * MO Shipping PDF Handler Class
 */
class MO_Shipping_PDF {

    /**
     * Concatenate multiple PDF files
     *
     * @param array $files Array of file paths
     * @param string $output_path Output file path
     * @return bool
     */
    public static function concatenate_pdfs($files, $output_path) {
        if (empty($files) || !is_array($files)) {
            return false;
        }

        try {
            $pdf = new Fpdi();
            
            foreach ($files as $file) {
                if (!file_exists($file)) {
                    continue;
                }
                
                $page_count = $pdf->setSourceFile($file);
                
                for ($page_no = 1; $page_no <= $page_count; $page_no++) {
                    $page_id = $pdf->importPage($page_no);
                    $size = $pdf->getTemplateSize($page_id);
                    $pdf->addPage($size['orientation'], $size);
                    $pdf->useTemplate($page_id);
                }
            }
            
            $pdf->output('F', $output_path);
            return true;
            
        } catch (Exception $e) {
            error_log('MO Shipping PDF Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Save base64 PDF data to file
     *
     * @param string $base64_data Base64 encoded PDF data
     * @param string $filename Filename
     * @return string|false File path on success, false on failure
     */
    public static function save_base64_pdf($base64_data, $filename) {
        $upload_dir = wp_upload_dir();
        $upload_path = $upload_dir['path'];
        
        if (!wp_is_writable($upload_path)) {
            return false;
        }
        
        $file_path = $upload_path . '/' . sanitize_file_name($filename);
        $pdf_data = base64_decode($base64_data);
        
        if ($pdf_data === false) {
            return false;
        }
        
        $result = file_put_contents($file_path, $pdf_data);
        
        return $result !== false ? $file_path : false;
    }

    /**
     * Get PDF file URL from path
     *
     * @param string $file_path File path
     * @return string|false File URL on success, false on failure
     */
    public static function get_pdf_url($file_path) {
        $upload_dir = wp_upload_dir();
        $upload_path = $upload_dir['path'];
        $upload_url = $upload_dir['url'];
        
        if (strpos($file_path, $upload_path) === 0) {
            $relative_path = str_replace($upload_path, '', $file_path);
            return $upload_url . $relative_path;
        }
        
        return false;
    }

    /**
     * Clean up temporary files
     *
     * @param array $files Array of file paths to delete
     * @return bool
     */
    public static function cleanup_temp_files($files) {
        if (!is_array($files)) {
            return false;
        }
        
        $success = true;
        foreach ($files as $file) {
            if (file_exists($file) && is_writable($file)) {
                if (!unlink($file)) {
                    $success = false;
                }
            }
        }
        
        return $success;
    }

    /**
     * Generate unique filename with timestamp
     *
     * @param string $prefix Filename prefix
     * @param string $extension File extension
     * @return string
     */
    public static function generate_filename($prefix = 'mo_shipping', $extension = 'pdf') {
        $date_timezone = new DateTime("now", new DateTimeZone('Asia/Riyadh'));
        $timestamp = $date_timezone->format('Y-m-d-H-i-s');
        return $prefix . '-' . $timestamp . '.' . $extension;
    }
}
