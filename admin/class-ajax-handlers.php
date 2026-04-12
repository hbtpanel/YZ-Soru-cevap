<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class QualityLife_AJAX_Handlers {

    public function __construct() {
        add_action( 'wp_ajax_ql_ask_ai', [ $this, 'ajax_ask_ai' ] );
        add_action( 'wp_ajax_ql_send_answer', [ $this, 'ajax_send_answer' ] );
        add_action( 'wp_ajax_ql_test_store', [ $this, 'ajax_test_store' ] );
        add_action( 'wp_ajax_ql_fetch_batch', [ $this, 'ajax_fetch_batch' ] );
        add_action( 'wp_ajax_ql_vectorize_batch', [ $this, 'ajax_vectorize_batch' ] );
        add_action( 'wp_ajax_ql_fetch_training_products', [ $this, 'ajax_fetch_training_products' ] );
        add_action( 'wp_ajax_ql_save_product_rule', [ $this, 'ajax_save_product_rule' ] );
        add_action( 'wp_ajax_ql_sync_trendyol_products', [ $this, 'ajax_sync_trendyol_products' ] );
    }

   public function ajax_ask_ai() {
        check_ajax_referer('ql_ajax_nonce', 'security');
        
       $question = sanitize_textarea_field($_POST['question']);
        $barcode = sanitize_text_field($_POST['barcode']);
        $store_id = isset($_POST['store_id']) ? sanitize_text_field($_POST['store_id']) : '';
        
        // Mağaza ID'sini de gönderiyoruz ki doğru kişiliği seçsin!
        $ans = QualityLife_API_Services::ask_gemini_with_vector($question, $barcode, $store_id);
        
        wp_send_json_success(['answer' => $ans]);
    }

    public function ajax_send_answer() {
        check_ajax_referer('ql_ajax_nonce', 'security');
        $stores = get_option('ql_trendyol_stores', []);
        $store = $stores[sanitize_text_field($_POST['store_id'])] ?? null;
        if(!$store) wp_send_json_error();

        $trendyol_secret = QualityLife_API_Services::decrypt_data($store['secret']);
        $result = QualityLife_API_Services::send_trendyol_answer($_POST['store_id'], $store['key'], $trendyol_secret, sanitize_text_field($_POST['q_id']), sanitize_textarea_field($_POST['answer']));
        $result ? wp_send_json_success() : wp_send_json_error();
    }

    public function ajax_test_store() {
        check_ajax_referer('ql_ajax_nonce', 'security');
        $stores = get_option('ql_trendyol_stores', []);
        $id = sanitize_text_field($_POST['store_id']);
        $s = $stores[$id] ?? null;

        if (!$s) wp_send_json_error(['message' => 'Mağaza bulunamadı.']);
        
        $trendyol_secret = QualityLife_API_Services::decrypt_data($s['secret']);

        $url = "https://apigw.trendyol.com/integration/qna/sellers/{$id}/questions/filter?pageSize=1";
        $response = wp_remote_get($url, [
            'headers'    => ['Authorization' => 'Basic ' . base64_encode($s['key'] . ':' . $trendyol_secret), 'Accept' => 'application/json'],
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
        
        $trendyol_secret = QualityLife_API_Services::decrypt_data($s['secret']);

        $url = "https://apigw.trendyol.com/integration/qna/sellers/{$store_id}/questions/filter?status=ANSWERED&size=50&page={$page}";
        if (!empty($_POST['start_ms']) && !empty($_POST['end_ms'])) {
            $url .= "&startDate=" . sanitize_text_field($_POST['start_ms']) . "&endDate=" . sanitize_text_field($_POST['end_ms']);
        }

        $response = wp_remote_get($url, [
            'headers'    => ['Authorization' => 'Basic ' . base64_encode($s['key'] . ':' . $trendyol_secret), 'Accept' => 'application/json'],
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
    // --- YENİ: RAG ÜRÜN LİSTELEME ---
    public function ajax_fetch_training_products() {
        check_ajax_referer('ql_ajax_nonce', 'security');
        global $wpdb;
        $search = sanitize_text_field($_POST['search'] ?? '');
        
        $table_knowledge = $wpdb->prefix . 'ql_product_knowledge';
        $table_questions = $wpdb->prefix . 'ql_all_questions';

        // Arşivdeki benzersiz ürünleri çekiyoruz
        $query = "SELECT model_code, product_name, barcode FROM $table_questions";
        if(!empty($search)) {
            $query .= " WHERE product_name LIKE '%$search%' OR model_code LIKE '%$search%' OR barcode LIKE '%$search%'";
        }
        $query .= " GROUP BY model_code ORDER BY created_date DESC LIMIT 50";
        
        $products = $wpdb->get_results($query);
        
        if(empty($products)) {
            wp_send_json_success(['html' => '<div style="padding:20px; text-align:center;">Ürün bulunamadı veya henüz soru arşivi oluşmadı.</div>']);
        }

        $html = '';
        foreach($products as $p) {
            // Varsa eğitilmiş kuralı bul
            $info = $wpdb->get_var($wpdb->prepare("SELECT product_info FROM $table_knowledge WHERE barcode = %s", $p->model_code));
            $info_excerpt = $info ? wp_trim_words($info, 8, '...') : '<span style="color:#e00000; font-weight:500;">❌ Kural Girilmemiş</span>';
            $info_safe = esc_attr($info);

            $html .= '<div style="display: grid; grid-template-columns: 1fr 2fr 1.5fr 1fr; padding: 15px; border-bottom: 1px solid #eee; align-items: center;">';
            $html .= '<div><strong>' . esc_html($p->model_code) . '</strong></div>';
            $html .= '<div style="font-size: 13px;">' . esc_html($p->product_name) . '</div>';
            $html .= '<div style="font-size: 13px; color: #555;">' . $info_excerpt . '</div>';
            $html .= '<div style="text-align: right;"><button class="button btn-edit-product" data-model="'.esc_attr($p->model_code).'" data-name="'.esc_attr($p->product_name).'" data-info="'.$info_safe.'">✏️ Eğit / Düzenle</button></div>';
            $html .= '</div>';
        }
        
        wp_send_json_success(['html' => $html]);
    }

    // --- YENİ: RAG KURALINI KAYDET ---
    public function ajax_save_product_rule() {
        check_ajax_referer('ql_ajax_nonce', 'security');
        global $wpdb;
        $barcode = sanitize_text_field($_POST['barcode']);
        $info = sanitize_textarea_field($_POST['info']);
        
        $table = $wpdb->prefix . 'ql_product_knowledge';
        
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE barcode = %s", $barcode));
        
        if($exists) {
            $wpdb->update($table, ['product_info' => $info], ['barcode' => $barcode]);
        } else {
            $wpdb->insert($table, ['barcode' => $barcode, 'product_info' => $info]);
        }
        
        wp_send_json_success('Başarı');
    }
  public function ajax_sync_trendyol_products() {
        check_ajax_referer('ql_ajax_nonce', 'security');
        global $wpdb;
        $stores = get_option('ql_trendyol_stores', []);
        $table_questions = $wpdb->prefix . 'ql_all_questions';

        $sync_count = 0;
        $debug_sample = "";

        foreach ($stores as $sid => $s) {
            // Trendyol'un en kararlı çalışan V1 Ana Ürün servisine dönüyoruz.
            $url = "https://apigw.trendyol.com/integration/product/sellers/{$sid}/products?page=0&size=100";
            
            $response = wp_remote_get($url, [
                'headers'    => [
                    'Authorization' => 'Basic ' . base64_encode(trim($s['key']) . ':' . trim($s['secret'])),
                    'Accept'        => 'application/json'
                ],
                'user-agent' => $sid . ' - SelfIntegration',
                'timeout'    => 30,
                'sslverify'  => false
            ]);

            if (is_wp_error($response)) {
                wp_send_json_error("Bağlantı Hatası: " . $response->get_error_message());
            }

            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            // Eğer bağlantı koptuysa uyar
            if ($code !== 200 || !isset($data['content'])) {
                wp_send_json_error("Trendyol API Hatası (Kod $code): " . wp_strip_all_tags($body));
            }

            // X-RAY CİHAZI: Trendyol'dan gelen ilk ürünün haritasını çıkar
            if (isset($data['content'][0]) && empty($debug_sample)) {
                $debug_sample = json_encode($data['content'][0], JSON_UNESCAPED_UNICODE);
            }

            foreach ($data['content'] as $p) {
                // V1 sisteminin farklı ihtimallerine göre modeli buluyoruz
                $model_code = $p['productMainId'] ?? $p['stockCode'] ?? '';
                $name = $p['title'] ?? $p['productName'] ?? 'İsimsiz Ürün';
                
                $barcode = $p['barcode'] ?? '';
                if (empty($barcode) && !empty($p['variants']) && is_array($p['variants'])) {
                    $barcode = $p['variants'][0]['barcode'] ?? '';
                }

                // Eğer Trendyol bize model kodu vermediyse o ürünü atla
                if (empty($model_code)) continue;

                // Ürün zaten var mı diye kontrol et
                $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_questions WHERE model_code = %s", $model_code));
                
                if (!$exists) {
                    $wpdb->insert($table_questions, [
                        'trendyol_id'   => 'SYNC-' . time() . '-' . rand(100,999),
                        'store_id'      => $sid,
                        'product_name'  => $name,
                        'model_code'    => $model_code,
                        'barcode'       => $barcode,
                        'question_text' => 'OTOMATIK SENKRONIZASYON',
                        'answer_text'   => '',
                        'status'        => 'SYNCED',
                        'created_date'  => current_time('mysql')
                    ]);
                    $sync_count++;
                }
            }
        }

        if ($sync_count === 0) {
            // Eğer 0 ürün çekerse, X-RAY çıktısını ekrana uyarı olarak bas!
            $msg = "Sistem 0 yeni ürün içeri aktardı.\n\nBunun 2 sebebi olabilir:\n1- Tüm ürünleriniz zaten listeye eklenmiş.\n2- Trendyol veriyi farklı gönderiyor.\n\n[Gelen Verinin Haritası]:\n" . substr($debug_sample, 0, 400) . "...";
            wp_send_json_success($msg);
        } else {
            wp_send_json_success($sync_count . ' yeni ürün başarıyla içeri aktarıldı.');
        }
    }
}