<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class QualityLife_AJAX_Handlers {

    public function __construct() {
        add_action( 'wp_ajax_ql_ask_ai', [ $this, 'ajax_ask_ai' ] );
        add_action( 'wp_ajax_ql_send_answer', [ $this, 'ajax_send_answer' ] );
        add_action( 'wp_ajax_ql_test_store', [ $this, 'ajax_test_store' ] );
        add_action( 'wp_ajax_ql_fetch_batch', [ $this, 'ajax_fetch_batch' ] );
        add_action( 'wp_ajax_ql_vectorize_batch', [ $this, 'ajax_vectorize_batch' ] );
    }

   public function ajax_ask_ai() {
        check_ajax_referer('ql_ajax_nonce', 'security');
        
        $question = sanitize_textarea_field($_POST['question']);
        $barcode = sanitize_text_field($_POST['barcode']);
        
        // ŞALTERİ İNDİRİYORUZ: Eski basit RAG modeli yerine Yeni Nesil Vektör Zekasını Çağır!
        $ans = QualityLife_API_Services::ask_gemini_with_vector($question, $barcode);
        
        wp_send_json_success(['answer' => $ans]);
    }

    public function ajax_send_answer() {
        check_ajax_referer('ql_ajax_nonce', 'security');
        $stores = get_option('ql_trendyol_stores', []);
        $store = $stores[sanitize_text_field($_POST['store_id'])] ?? null;
        if(!$store) wp_send_json_error();

        $result = QualityLife_API_Services::send_trendyol_answer($_POST['store_id'], $store['key'], $store['secret'], sanitize_text_field($_POST['q_id']), sanitize_textarea_field($_POST['answer']));
        $result ? wp_send_json_success() : wp_send_json_error();
    }

    public function ajax_test_store() {
        check_ajax_referer('ql_ajax_nonce', 'security');
        $stores = get_option('ql_trendyol_stores', []);
        $id = sanitize_text_field($_POST['store_id']);
        $s = $stores[$id] ?? null;

        if (!$s) wp_send_json_error(['message' => 'Mağaza bulunamadı.']);

        $url = "https://apigw.trendyol.com/integration/qna/sellers/{$id}/questions/filter?pageSize=1";
        $response = wp_remote_get($url, [
            'headers'    => ['Authorization' => 'Basic ' . base64_encode($s['key'] . ':' . $s['secret']), 'Accept' => 'application/json'],
            'user-agent' => $id . ' - SelfIntegration',
            'sslverify'  => false,
            'timeout'    => 15
        ]);

        if (is_wp_error($response)) { wp_send_json_error(['message' => "Sunucu Hatası: " . $response->get_error_message()]); return; } 
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body_json = json_decode(wp_remote_retrieve_body($response), true);
        $trendyol_error = isset($body_json['errors'][0]['message']) ? $body_json['errors'][0]['message'] : '';

        if ($status_code === 200) wp_send_json_success(['message' => "Bağlantı Başarılı! ✅ API sorunsuz çalışıyor."]);
        elseif ($status_code === 401) wp_send_json_error(['message' => "Yetki Hatası (401) ❌: API bilgileriniz hatalı."]);
        elseif ($status_code === 403) wp_send_json_error(['message' => "Erişim Engeli (403) ❌: Sunucu IP engelli veya IP bildirilmemiş."]);
        else wp_send_json_error(['message' => "Bağlantı Hatası (Kod: {$status_code}) ❌: " . $trendyol_error]);
    }

    public function ajax_fetch_batch() {
        check_ajax_referer('ql_ajax_nonce', 'security');
        global $wpdb;

        $store_id = sanitize_text_field($_POST['store_id']);
        $page     = intval($_POST['page']);
        
        $stores = get_option('ql_trendyol_stores', []);
        $s = $stores[$store_id] ?? null;
        if (!$s) wp_send_json_error(['message' => 'Mağaza bulunamadı.']);

        $url = "https://apigw.trendyol.com/integration/qna/sellers/{$store_id}/questions/filter?status=ANSWERED&size=50&page={$page}";
        if (!empty($_POST['start_ms']) && !empty($_POST['end_ms'])) {
            $url .= "&startDate=" . sanitize_text_field($_POST['start_ms']) . "&endDate=" . sanitize_text_field($_POST['end_ms']);
        }

        $response = wp_remote_get($url, [
            'headers'    => ['Authorization' => 'Basic ' . base64_encode($s['key'] . ':' . $s['secret']), 'Accept' => 'application/json'],
            'user-agent' => $store_id . ' - SelfIntegration',
            'sslverify'  => false,
            'timeout'    => 20
        ]);

        if (is_wp_error($response)) wp_send_json_error(['message' => 'Trendyol API bağlantı hatası.']);

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $questions = $body['content'] ?? [];
        $total_pages = $body['totalPages'] ?? 0;

        $table = $wpdb->prefix . 'ql_all_questions';
        $inserted = 0;

        foreach ($questions as $q) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE trendyol_id = %s", $q['id']));
            if (!$exists) {
                $wpdb->insert($table, [
                    'trendyol_id'   => $q['id'],
                    'store_id'      => $store_id,
                    'product_name'  => $q['productName'],
                    'model_code'    => $q['productMainId'],
                    'question_text' => $q['text'],
                    'answer_text'   => $q['answer']['text'] ?? '',
                    'status'        => $q['status'],
                    'created_date'  => date('Y-m-d H:i:s', $q['creationDate'] / 1000),
                    'vector_data'   => null // Vektörler boş başlıyor, bir sonraki aşamada dolduracağız.
                ]);
                $inserted++;
            }
        }

        wp_send_json_success([
            'current_page' => $page,
            'total_pages'  => $total_pages,
            'inserted'     => $inserted,
            'done'         => ($page >= $total_pages - 1)
        ]);
    }
    // --- YENİ: VEKTÖR İNDEKSLEME BOTU ---
    public function ajax_vectorize_batch() {
        check_ajax_referer('ql_ajax_nonce', 'security');
        global $wpdb;
        $table = $wpdb->prefix . 'ql_all_questions';

        // Artık tek seferde 20 soru işliyoruz!
     $unprocessed = $wpdb->get_results("SELECT id, question_text, answer_text FROM $table WHERE vector_data IS NULL LIMIT 100");

        if (empty($unprocessed)) {
            wp_send_json_success(['done' => true, 'message' => 'Tüm arşiv başarıyla indekslendi!']);
        }

        $texts_to_embed = [];
        $ids = [];
        foreach ($unprocessed as $row) {
            $texts_to_embed[] = "Soru: " . $row->question_text . " Cevap: " . $row->answer_text;
            $ids[] = $row->id; // ID'leri sırasıyla tutuyoruz ki veritabanına doğru yazalım
        }

        // Yeni Batch fonksiyonumuzu çağırıyoruz (20 Soru = 1 API İsteği)
        $result = QualityLife_API_Services::get_batch_text_embeddings($texts_to_embed);

        if (isset($result['embeddings'])) {
            $processed_count = 0;
            foreach ($result['embeddings'] as $index => $embedding_data) {
                if (isset($embedding_data['values'])) {
                    $wpdb->update(
                        $table,
                        ['vector_data' => wp_json_encode($embedding_data['values'])],
                        ['id' => $ids[$index]]
                    );
                    $processed_count++;
                }
            }
        } else {
            $err = isset($result['error']) ? $result['error'] : 'Bilinmeyen hata';
            wp_send_json_error(['message' => $err]);
        }

        $remaining = $wpdb->get_var("SELECT COUNT(id) FROM $table WHERE vector_data IS NULL");

        wp_send_json_success([
            'done' => false,
            'processed' => $processed_count ?? 0,
            'remaining' => $remaining
        ]);
    }
}