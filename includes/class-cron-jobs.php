<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class QualityLife_Cron_Jobs {

   public function __construct() {
        // WordPress Cron Filtreleri
        add_filter( 'cron_schedules', [ $this, 'add_cron_intervals' ] );
        add_action( 'ql_daily_fetch_event', [ $this, 'run_daily_fetch' ] );
        add_action( 'ql_vector_indexer_event', [ $this, 'run_vector_indexer' ] );

        // --- YENİ: DIŞARIDAN TETİKLEME KONTROLÜ ---
        add_action( 'init', [ $this, 'check_external_trigger' ] );

        $this->schedule_events();
    }

    // Dışarıdan bir URL ile botu zorla uyandırma fonksiyonu
    public function check_external_trigger() {
        // Örn: siteniz.com/?ql_trigger=secret_key_123
        if ( isset($_GET['ql_trigger']) && $_GET['ql_trigger'] === 'ql_auto_pilot_789' ) {
            
            // 1. Veri Çekme Botunu Çalıştır
            $this->run_daily_fetch();
            
            // 2. İndeksleme Botunu Çalıştır
            $this->run_vector_indexer();

            die('Quality Life AI: Gece botları başarıyla tetiklendi ve görev tamamlandı.');
        }
    }

    public function add_cron_intervals( $schedules ) {
        $schedules['every_five_minutes'] = [
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display'  => 'Her 5 Dakikada Bir'
        ];
        return $schedules;
    }

    public function schedule_events() {
        // 1. Gece 03:00 Botu (Veri Çekme)
        if ( ! wp_next_scheduled( 'ql_daily_fetch_event' ) ) {
            $next_3am = strtotime('03:00:00');
            if ($next_3am <= time()) $next_3am += DAY_IN_SECONDS; // Eğer bugün saat 03:00'ü geçtiyse, yarına kur
            wp_schedule_event( $next_3am, 'daily', 'ql_daily_fetch_event' );
        }

        // 2. 5 Dakika Botu (İndeksleme)
        if ( ! wp_next_scheduled( 'ql_vector_indexer_event' ) ) {
            wp_schedule_event( time(), 'every_five_minutes', 'ql_vector_indexer_event' );
        }
    }

    // --- İŞÇİ 1: GÜNLÜK VERİ ÇEKME BOTU (Son 48 Saat) ---
    public function run_daily_fetch() {
        global $wpdb;
        $stores = get_option('ql_trendyol_stores', []);
        if(empty($stores)) return;

        $table = $wpdb->prefix . 'ql_all_questions';
        $end_ms = time() * 1000;
        $start_ms = (time() - (48 * 3600)) * 1000; // Tam olarak 48 Saat öncesi

        foreach ($stores as $store_id => $s) {
            if(empty($s['key']) || empty($s['secret'])) continue;
            
            $page = 0;
            while (true) {
                // Trendyol'dan durumu SADECE ANSWERED (Cevaplanmış) olan eski soruları çekiyoruz
                $url = "https://apigw.trendyol.com/integration/qna/sellers/{$store_id}/questions/filter?status=ANSWERED&size=50&page={$page}&startDate={$start_ms}&endDate={$end_ms}";
                
                $response = wp_remote_get($url, [
                    'headers'    => ['Authorization' => 'Basic ' . base64_encode($s['key'] . ':' . $s['secret']), 'Accept' => 'application/json'],
                    'user-agent' => $store_id . ' - QLCron',
                    'sslverify'  => false,
                    'timeout'    => 20
                ]);

                if (is_wp_error($response)) break; // API patlarsa diğer mağazaya geç

                $body = json_decode(wp_remote_retrieve_body($response), true);
                $questions = $body['content'] ?? [];
                $total_pages = $body['totalPages'] ?? 0;

                if(empty($questions)) break;

                foreach ($questions as $q) {
                    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE trendyol_id = %s", $q['id']));
                    if (!$exists) { // Veritabanında yoksa yeni ekle, vektörünü boş bırak
                        $wpdb->insert($table, [
                            'trendyol_id'   => $q['id'],
                            'store_id'      => $store_id,
                            'product_name'  => $q['productName'],
                            'model_code'    => $q['productMainId'],
                            'question_text' => $q['text'],
                            'answer_text'   => $q['answer']['text'] ?? '',
                            'status'        => $q['status'],
                            'created_date'  => date('Y-m-d H:i:s', $q['creationDate'] / 1000),
                            'vector_data'   => null 
                        ]);
                    }
                }

                // Sayfalar bittiyse veya güvenlik gereği 20 sayfayı (1000 soruyu) aştıysa dur
                if ($page >= $total_pages - 1 || $page > 20) break;
                $page++;
            }
        }
    }

    // --- İŞÇİ 2: SÜREKLİ İNDEKSLEME BOTU (Google'a Yazma) ---
    public function run_vector_indexer() {
        global $wpdb;
        $table = $wpdb->prefix . 'ql_all_questions';

        // Limit 10: Her 5 dakikada en fazla 10 soruyu indeksle. (Günde ~2800 soru yapar, limitlere takılmaz)
        $unprocessed = $wpdb->get_results("SELECT id, question_text, answer_text FROM $table WHERE vector_data IS NULL LIMIT 10");

        if (empty($unprocessed)) return; // İndekslenecek soru yoksa geri uyu

        foreach ($unprocessed as $row) {
            $combined_text = "Soru: " . $row->question_text . " Cevap: " . $row->answer_text;
            
            // Diğer dosyada yazdığımız Google API fonksiyonunu çağırıyoruz
            $result = QualityLife_API_Services::get_text_embedding($combined_text);
            
            if (isset($result['values'])) {
                $wpdb->update(
                    $table,
                    ['vector_data' => wp_json_encode($result['values'])],
                    ['id' => $row->id]
                );
            }
        }
    }
}