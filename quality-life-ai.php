<?php
/**
 * Plugin Name: Quality Life - Yapay Zeka Soru Asistanı
 * Description: Trendyol sorularını Gemini 1.5 Flash ve Vektörel Semantik Arama ile yanıtlayan e-ticaret asistanı.
 * Version: 2.0.0
 * Author: Quality Life Developer
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Alt dosyaları sisteme dahil et
require_once plugin_dir_path( __FILE__ ) . 'includes/class-api-services.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-cron-jobs.php'; // YENİ
require_once plugin_dir_path( __FILE__ ) . 'admin/class-admin-pages.php';
require_once plugin_dir_path( __FILE__ ) . 'admin/class-ajax-handlers.php';

class QualityLife_AI_Core {

   public function __construct() {
        register_activation_hook( __FILE__, [ $this, 'activate_plugin' ] );
        
        // Veritabanı Eksik Sütun Zorlaması (Fail-safe)
        global $wpdb;
        $table = $wpdb->prefix . 'ql_all_questions';
        if($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            $has_column = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'vector_data'");
            if(empty($has_column)) {
                $wpdb->query("ALTER TABLE $table ADD vector_data LONGTEXT NULL");
            }
            $has_barcode = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'barcode'");
            if(empty($has_barcode)) {
                $wpdb->query("ALTER TABLE $table ADD barcode varchar(100) NULL AFTER model_code");
            }
            $has_golden = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'is_golden'");
            if(empty($has_golden)) {
                $wpdb->query("ALTER TABLE $table ADD is_golden tinyint(1) DEFAULT 0 AFTER vector_data");
            }
            // YENİ: Resim ve Müşteri Sütunları Kontrolü
            $has_image = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'image_url'");
            if(empty($has_image)) {
                $wpdb->query("ALTER TABLE $table ADD image_url varchar(500) NULL AFTER barcode");
                $wpdb->query("ALTER TABLE $table ADD customer_name varchar(100) NULL AFTER image_url");
            }
            // YENİ: Direkt Ürün Linki
            $has_url = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'product_url'");
            if(empty($has_url)) {
                $wpdb->query("ALTER TABLE $table ADD product_url varchar(500) NULL AFTER image_url");
            }
        }

       // Modülleri Başlat
        new QualityLife_Admin_Pages();
        new QualityLife_AJAX_Handlers();
        new QualityLife_Cron_Jobs(); // YENİ EKLENDİ
    }

    public function activate_plugin() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // 1. Ürün Bilgi Tablosu (Manuel RAG için)
        $table_knowledge = $wpdb->prefix . 'ql_product_knowledge';
        $sql1 = "CREATE TABLE $table_knowledge (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            barcode varchar(100) NOT NULL,
            product_info text NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY barcode (barcode)
        ) $charset_collate;";
        
        // 2. Soru Arşivi ve Vektör Tablosu (Yeni Yapay Zeka Modeli İçin)
        $table_all_questions = $wpdb->prefix . 'ql_all_questions';
        $sql2 = "CREATE TABLE $table_all_questions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            trendyol_id varchar(50) NOT NULL,
            store_id varchar(50) NOT NULL,
            product_name varchar(255),
            model_code varchar(100),
            question_text text,
            answer_text text,
            status varchar(20),
            created_date datetime,
            vector_data longtext, -- YENİ: Vektör Koordinatları (JSON) burada tutulacak
            PRIMARY KEY  (id),
            UNIQUE KEY trendyol_id (trendyol_id),
            INDEX status_idx (status),
            INDEX store_idx (store_id),
            INDEX golden_idx (is_golden),
            INDEX model_idx (model_code)
        ) $charset_collate;";
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql1 );
        dbDelta( $sql2 );
        $table_logs = $wpdb->prefix . 'ql_api_logs';
        $sql_logs = "CREATE TABLE $table_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            action_type varchar(50) NOT NULL,
            tokens_in int(11) DEFAULT 0,
            tokens_out int(11) DEFAULT 0,
            cost_usd decimal(10,6) DEFAULT 0.000000,
            created_at datetime DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta($sql_logs);
    }
}

// Sistemi Ateşle
new QualityLife_AI_Core();