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
        add_action( 'wp_ajax_ql_check_waiting_questions', [ $this, 'ajax_check_waiting_questions' ] );
        add_action( 'wp_ajax_ql_toggle_golden', [ $this, 'ajax_toggle_golden' ] );
        add_action( 'wp_ajax_ql_load_pending_cards', [ $this, 'ajax_load_pending_cards' ] );
        // Uzman Dokunuşu: AJAX güvenliği için admin_init kancasını kullanıyoruz (Fonksiyonlar yüklendikten sonra çalışır)
        add_action( 'admin_init', [ $this, 'secure_ajax_endpoints' ] );
        add_action( 'wp_ajax_ql_restore_history', [ $this, 'ajax_restore_history' ] );
        
    }

    public function secure_ajax_endpoints() {
        // Sadece AJAX anında ve SADECE "ql_" ile başlayan bizim eklentimize ait isteklerde devreye girer.
        // Böylece hbt-trendyol-profit-tracker gibi diğer eklentilerin çalışmasını bozmaz.
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX && isset($_REQUEST['action']) && strpos($_REQUEST['action'], 'ql_') === 0 ) {
            if ( !current_user_can('manage_options') ) {
                wp_send_json_error(['message' => 'Güvenlik Kalkanı: Bu işlem için yönetici yetkisi gerekiyor.']);
                exit;
            }
        }
    }

  public function ajax_ask_ai() {
        check_ajax_referer('ql_ajax_nonce', 'security');
        global $wpdb;
        $question   = sanitize_textarea_field($_POST['question']);
        $barcode    = sanitize_text_field($_POST['barcode']);
        $store_id   = isset($_POST['store_id']) ? sanitize_text_field($_POST['store_id']) : '';
        $quick_note = isset($_POST['quick_note']) ? sanitize_textarea_field($_POST['quick_note']) : '';
        
        // OTOMATİK RAG GÜNCELLEME VE LOGLAMA: Eğer not varsa kalıcı hafızaya ekle ve logla
        if (!empty($quick_note)) {
            $table = $wpdb->prefix . 'ql_product_knowledge';
            $table_history = $wpdb->prefix . 'ql_product_history';
            $table_questions = $wpdb->prefix . 'ql_all_questions';
            
            $current_info = $wpdb->get_var($wpdb->prepare("SELECT product_info FROM $table WHERE barcode = %s", $barcode));
            $old_content = $current_info ? $current_info : '';
            $new_info = $current_info ? $current_info . "\n\nEk Not: " . $quick_note : $quick_note;
            
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE barcode = %s", $barcode));
            if($exists) {
                $wpdb->update($table, ['product_info' => $new_info], ['barcode' => $barcode]);
            } else {
                $wpdb->insert($table, ['barcode' => $barcode, 'product_info' => $new_info]);
            }

            // Snapshot Verilerini Al (Log ekranında resim ve mağaza görünmesi için)
            $product_details = $wpdb->get_row($wpdb->prepare("SELECT store_id, product_name, image_url FROM $table_questions WHERE model_code = %s OR barcode = %s LIMIT 1", $barcode, $barcode));
            $p_name = $product_details ? $product_details->product_name : 'Bilinmeyen Ürün';
            $i_url = $product_details ? $product_details->image_url : '';
            $s_id = $product_details ? $product_details->store_id : $store_id; 
            
            $stores = get_option('ql_trendyol_stores', []);
            $s_name = isset($stores[$s_id]) ? $stores[$s_id]['name'] : 'Genel Kural';

            // İşlem geçmişine (Log) yaz
            $wpdb->insert($table_history, [
                'barcode' => $barcode,
                'store_name' => $s_name,
                'product_name' => $p_name,
                'image_url' => $i_url,
                'old_content' => $old_content,
                'new_content' => $new_info,
                'change_source' => 'Özel Not (Hızlı Hazırlama)',
                'changed_by' => get_current_user_id()
            ]);
        }

        $ans = QualityLife_API_Services::ask_gemini_with_vector($question, $barcode, $store_id, $quick_note);
        
        if (is_array($ans)) {
            wp_send_json_success(['answer' => $ans['text'], 'score' => $ans['score']]);
        } else {
            wp_send_json_success(['answer' => $ans, 'score' => 0]);
        }
    }

  public function ajax_send_answer() {
        check_ajax_referer('ql_ajax_nonce', 'security');
        global $wpdb;
        $stores = get_option('ql_trendyol_stores', []);
        $store_id = sanitize_text_field($_POST['store_id']);
        $store = $stores[$store_id] ?? null;
        if(!$store) wp_send_json_error();

        $trendyol_secret = QualityLife_API_Services::decrypt_data($store['secret']);
        $q_id = sanitize_text_field($_POST['q_id']);
        $answer = sanitize_textarea_field($_POST['answer']);
        
        $result = QualityLife_API_Services::send_trendyol_answer($store_id, $store['key'], $trendyol_secret, $q_id, $answer);
        
        if ($result) {
            // SESSİZ ÖĞRENME (AUTO-GOLDEN): Gönderilen cevabı anında vektörleyip arşive kazıyoruz!
            $table = $wpdb->prefix . 'ql_all_questions';
            $q_text = sanitize_textarea_field($_POST['q_text'] ?? '');
            $barcode = sanitize_text_field($_POST['barcode'] ?? '');
            $p_name = sanitize_text_field($_POST['p_name'] ?? '');
            
           if (!empty($q_text)) {
                $wpdb->insert($table, [
                    'trendyol_id'   => $q_id,
                    'store_id'      => $store_id,
                    'product_name'  => $p_name,
                    'model_code'    => $barcode,
                    'question_text' => $q_text,
                    'answer_text'   => $answer,
                    'status'        => 'ANSWERED',
                    'created_date'  => current_time('mysql'),
                    // Uzman Dokunuşu: Gönderim anında YZ'yi bekletip sistemi kilitlemiyoruz. Vektör işini "Soru Arşivi" sayfasındaki bota bırakıyoruz.
                    'vector_data'   => null, 
                    'is_golden'     => 1 // Otomatik Altın Kural!
                ]);
            }
            
            // CEVAP GÖNDERİLDİĞİNDE: Tüm ekranlarda verinin anında güncellenmesi için kalkanı parçala!
            delete_transient('ql_waiting_qs_cache_all');
            delete_transient('ql_waiting_qs_cache_' . $store_id);
            
            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
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
                   'image_url'     => isset($q['imageUrl']) ? $q['imageUrl'] : '',
                    'product_url'   => isset($q['webUrl']) ? $q['webUrl'] : '',
                    'customer_name' => isset($q['userName']) ? $q['userName'] : 'Müşteri',
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
        $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
        $per_page = 20; // Sayfa başı ürün sayısı
        $offset = ($page - 1) * $per_page;

        $stores = get_option('ql_trendyol_stores', []);
        
        $table_knowledge = $wpdb->prefix . 'ql_product_knowledge';
        $table_questions = $wpdb->prefix . 'ql_all_questions';

       $untrained_only = isset($_POST['untrained_only']) && $_POST['untrained_only'] === 'true';

        $where = "WHERE 1=1";
        if(!empty($search)) {
            $where .= " AND (q.product_name LIKE '%$search%' OR q.model_code LIKE '%$search%' OR q.barcode LIKE '%$search%')";
        }
        if ($untrained_only) {
            $where .= " AND (k.product_info IS NULL OR k.product_info = '')";
        }

        $total_items = $wpdb->get_var("SELECT COUNT(DISTINCT q.model_code) FROM $table_questions q LEFT JOIN $table_knowledge k ON q.model_code = k.barcode $where");
        $total_pages = ceil($total_items / $per_page);

        $query = "SELECT q.store_id, q.model_code, q.product_name, q.barcode, q.image_url, q.product_url, k.product_info FROM $table_questions q LEFT JOIN $table_knowledge k ON q.model_code = k.barcode $where GROUP BY q.model_code ORDER BY q.created_date DESC LIMIT $per_page OFFSET $offset";
        $products = $wpdb->get_results($query);
        
        if(empty($products)) {
            wp_send_json_success(['html' => '<div style="padding:40px; text-align:center; color:#64748b; font-weight:500;">Sonuç bulunamadı. 🎉</div>', 'pagination' => '']);
        }

       $html = '';
        foreach($products as $p) {
            $store_name = isset($stores[$p->store_id]) ? $stores[$p->store_id]['name'] : 'Bilinmeyen';
            $info = $p->product_info; 
            $info_safe = esc_attr($info);
            $rag_img = !empty($p->image_url) ? esc_url($p->image_url) : 'https://placehold.co/100x100/f8fafc/64748b?text=Yok';
            
            // Badge (Rozet) ve Tooltip Mantığı
            if (!empty($info)) {
                $badge_html = '<div class="ql-tooltip" id="badge-'.esc_attr($p->model_code).'"><span style="background: #dcfce7; color: #166534; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; border: 1px solid #bbf7d0; cursor:help;">🟢 Eğitildi</span><span class="ql-tooltiptext">'.esc_html($info).'</span></div>';
            } else {
                $badge_html = '<div id="badge-'.esc_attr($p->model_code).'"><span style="background: #fee2e2; color: #991b1b; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; border: 1px solid #fecaca;">🔴 Eğitimsiz</span></div>';
            }

            // Direkt link veya arama sayfası linkini belirle
            $ty_link = !empty($p->product_url) ? esc_url($p->product_url) : "https://www.trendyol.com/sr?q=" . esc_attr($p->model_code);

            $html .= '<div class="ql-rag-row" id="row-'.esc_attr($p->model_code).'">';
            $html .= '<div data-label="Mağaza"><span style="background: #f0f6fc; color: #2271b1; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">🏪 ' . esc_html($store_name) . '</span></div>';
            $html .= '<div data-label="Barkod" style="font-size: 13px;">' . esc_html($p->barcode ?: '-') . '</div>';
            $html .= '<div data-label="Model" style="font-size: 13px; font-weight: bold;">' . esc_html($p->model_code) . '</div>';
            $html .= '<div data-label="Ürün">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <a href="'.$ty_link.'" target="_blank" title="Trendyol\'da Aç" style="flex-shrink:0;">
                                <img src="'.$rag_img.'" style="width:36px; height:36px; border-radius:6px; object-fit:contain; background:#fff; border:1px solid #e2e8f0;">
                            </a>
                            <span style="font-size: 13px; line-height:1.4;">' . esc_html($p->product_name) . '</span>
                        </div>
                      </div>';
            $html .= '<div data-label="Durum">' . $badge_html . '</div>';
            $html .= '<div style="text-align: right;"><button class="button btn-edit-product" data-model="'.esc_attr($p->model_code).'" data-name="'.esc_attr($p->product_name).'" data-info="'.$info_safe.'">✏️ Eğit</button></div>';
            $html .= '</div>';
        }
        
        // Alt Kısım Sayfalama Butonları
        $pagination = '';
        if($total_pages > 1) {
            $pagination .= '<div style="padding: 15px; text-align: center; background: #f8f9fa; border-top: 1px solid #eee; display: flex; justify-content: center; gap: 10px; align-items: center;">';
            $pagination .= '<button class="button btn-page" data-page="'.max(1, $page - 1).'" '.($page <= 1 ? 'disabled' : '').'>« Önceki</button>';
            $pagination .= '<span style="font-weight: bold; font-size: 14px;">Sayfa ' . $page . ' / ' . $total_pages . '</span>';
            $pagination .= '<button class="button btn-page" data-page="'.min($total_pages, $page + 1).'" '.($page >= $total_pages ? 'disabled' : '').'>Sonraki »</button>';
            $pagination .= '</div>';
        }
        
        // YENİ: Toplam ürün sayısı sayacı
        $pagination .= '<div style="text-align: right; padding: 12px 15px; background: #fff; border-top: 1px solid #eee; font-size: 13px; color: #64748b; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px;">';
        $pagination .= 'Toplam <strong style="color: #0ea5e9; font-size: 14px;">' . number_format($total_items, 0, ',', '.') . '</strong> benzersiz ürün listeleniyor.';
        $pagination .= '</div>';

        wp_send_json_success(['html' => $html, 'pagination' => $pagination]);
    
    }

 // --- YENİ: RAG KURALINI KAYDET VE LOGLA (SNAPSHOT MİMARİSİ) ---
    public function ajax_save_product_rule() {
        check_ajax_referer('ql_ajax_nonce', 'security');
        global $wpdb;
        $barcode = sanitize_text_field($_POST['barcode']);
        $info = sanitize_textarea_field($_POST['info']);
        $is_append = isset($_POST['is_append']) && $_POST['is_append'] === 'true'; 
        
        $table_rag = $wpdb->prefix . 'ql_product_knowledge';
        $table_questions = $wpdb->prefix . 'ql_all_questions';
        $table_history = $wpdb->prefix . 'ql_product_history';
        
        // 1. ADIM: Snapshot İçin Ürün Kimliğini Bul (İsim, Resim, Mağaza ID)
        $product_details = $wpdb->get_row($wpdb->prepare("SELECT store_id, product_name, image_url FROM $table_questions WHERE model_code = %s OR barcode = %s LIMIT 1", $barcode, $barcode));
        
        $p_name = $product_details ? $product_details->product_name : 'Bilinmeyen Ürün';
        $i_url = $product_details ? $product_details->image_url : '';
        $s_id = $product_details ? $product_details->store_id : '';
        
        // Mağaza Adını Çözümle
        $stores = get_option('ql_trendyol_stores', []);
        $s_name = isset($stores[$s_id]) ? $stores[$s_id]['name'] : 'Genel Kural';
        
        // İşlemin Nereden Yapıldığını Tespit Et
        $change_source = $is_append ? 'Auto-RAG (Pop-up)' : 'Admin Panel / Manuel Not';
        
        // 2. ADIM: Eski Veriyi Al
        $old_content = $wpdb->get_var($wpdb->prepare("SELECT product_info FROM $table_rag WHERE barcode = %s", $barcode));
        if ($old_content === null) $old_content = '';

        $final_info = '';

        // 3. ADIM: Kaydetme ve Loglama Motoru
        if (trim($info) === '') {
            $final_info = ''; 
            if ($old_content !== '') {
                $wpdb->delete($table_rag, ['barcode' => $barcode]); // Veriyi sil
                
                // Silinme İşlemini Logla
                $wpdb->insert($table_history, [
                    'barcode' => $barcode, 'store_name' => $s_name, 'product_name' => $p_name, 'image_url' => $i_url,
                    'old_content' => $old_content, 'new_content' => '[KURAL SİLİNDİ]', 'change_source' => $change_source, 'changed_by' => get_current_user_id()
                ]);
            }
        } else {
            if ($old_content !== '') {
                // Eskinin üzerine veya sonuna ekle
                $final_info = $is_append ? $old_content . "\n\nKalıcı Kural: " . $info : $info;
                $wpdb->update($table_rag, ['product_info' => $final_info], ['barcode' => $barcode]);
            } else {
                // Sıfırdan ekle
                $final_info = $info;
                $wpdb->insert($table_rag, ['barcode' => $barcode, 'product_info' => $final_info]);
            }
            
            // SADECE metinde bir değişim olduysa Log tablosuna yaz (Gereksiz çöp veri birikmesini önler)
            if ($old_content !== $final_info) {
                $wpdb->insert($table_history, [
                    'barcode' => $barcode, 'store_name' => $s_name, 'product_name' => $p_name, 'image_url' => $i_url,
                    'old_content' => $old_content, 'new_content' => $final_info, 'change_source' => $change_source, 'changed_by' => get_current_user_id()
                ]);
            }
        }
        
        wp_send_json_success('Başarı');
    }
 public function ajax_sync_trendyol_products() {
        check_ajax_referer('ql_ajax_nonce', 'security');
        global $wpdb;
        set_time_limit(0); 

        $stores = get_option('ql_trendyol_stores', []);
        $table_questions = $wpdb->prefix . 'ql_all_questions';

        // Javascript'in bize verdiği "Şu an nerede kalmıştık?" bilgisini alıyoruz
        $current_store_id = isset($_POST['current_store_id']) ? sanitize_text_field($_POST['current_store_id']) : '';
        $page = isset($_POST['page']) ? intval($_POST['page']) : 0;

        $store_ids = array_keys($stores);
        if (empty($store_ids)) {
            wp_send_json_success(['done' => true, 'log' => '<span style="color:#ef4444;">[HATA] Kayıtlı mağaza bulunamadı.</span>']);
        }

        // Eğer ilk istekse sıfırdan ilk mağaza ile başla
        if (empty($current_store_id)) {
            $current_store_id = $store_ids[0];
        }

        $s = $stores[$current_store_id];
        $trendyol_secret = QualityLife_API_Services::decrypt_data($s['secret']);
        $store_name = $s['name'];

        $stats = ['new' => 0, 'updated' => 0];

        // SADECE İSTENEN 1 SAYFAYI (100 Ürün) ÇEKİYORUZ
        $url = "https://apigw.trendyol.com/integration/product/sellers/{$current_store_id}/products?page={$page}&size=100";
        
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode(trim($s['key']) . ':' . trim($trendyol_secret)),
                'Accept' => 'application/json'
            ],
            'user-agent' => $current_store_id . ' - SelfIntegration',
            'timeout' => 30, 
            'sslverify' => true 
        ]);

        if (is_wp_error($response)) {
            wp_send_json_success([
                'done' => true, 
                'log' => '<span style="color:#ef4444;">[HATA] Trendyol API ile bağlantı kurulamadı: ' . $response->get_error_message() . '</span>'
            ]);
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($data['content']) || empty($data['content'])) {
            $totalPages = 0; // Bu mağazada ürün yok
        } else {
            // === RAM OPTİMİZASYONU BİREBİR KORUNDU ===
            $incoming_barcodes = [];
            $incoming_models = [];
            
            foreach ($data['content'] as $p) {
                $m_code = $p['productMainId'] ?? $p['stockCode'] ?? '';
                $b_code = $p['barcode'] ?? '';
                if (empty($b_code) && !empty($p['variants']) && is_array($p['variants'])) { $b_code = $p['variants'][0]['barcode'] ?? ''; }
                if (!empty($m_code)) $incoming_models[] = $m_code;
                if (!empty($b_code)) $incoming_barcodes[] = $b_code;
            }

            $db_by_barcode = []; $db_by_model = [];
            
            if (!empty($incoming_models) || !empty($incoming_barcodes)) {
                $where_clauses = [];
                if (!empty($incoming_models)) {
                    $models_placeholder = implode(',', array_fill(0, count($incoming_models), '%s'));
                    $where_clauses[] = $wpdb->prepare("model_code IN ($models_placeholder)", ...$incoming_models);
                }
                if (!empty($incoming_barcodes)) {
                    $barcodes_placeholder = implode(',', array_fill(0, count($incoming_barcodes), '%s'));
                    $where_clauses[] = $wpdb->prepare("barcode IN ($barcodes_placeholder)", ...$incoming_barcodes);
                }
                
                if (!empty($where_clauses)) {
                    $where_sql = implode(' OR ', $where_clauses);
                    $chunk_items = $wpdb->get_results("SELECT id, model_code, barcode, product_name, store_id, image_url, product_url FROM $table_questions WHERE $where_sql GROUP BY model_code");
                    foreach ($chunk_items as $item) {
                        if (!empty($item->barcode)) $db_by_barcode[$item->barcode] = $item;
                        if (!empty($item->model_code)) $db_by_model[$item->model_code] = $item;
                    }
                }
            }

            foreach ($data['content'] as $p) {
                $model_code = $p['productMainId'] ?? $p['stockCode'] ?? '';
                $name = $p['title'] ?? $p['productName'] ?? 'İsimsiz Ürün';
                
                $barcode = $p['barcode'] ?? '';
                if (empty($barcode) && !empty($p['variants']) && is_array($p['variants'])) { $barcode = $p['variants'][0]['barcode'] ?? ''; }
                
                $image_url = ''; $product_url = isset($p['productUrl']) ? $p['productUrl'] : '';
                if (!empty($p['images']) && is_array($p['images']) && isset($p['images'][0]['url'])) { $image_url = $p['images'][0]['url']; }

                if (empty($model_code) && empty($barcode)) continue;

                $target_row = null;
                if (!empty($barcode) && isset($db_by_barcode[$barcode])) { $target_row = $db_by_barcode[$barcode]; }
                if (!$target_row && !empty($model_code) && isset($db_by_model[$model_code])) { $target_row = $db_by_model[$model_code]; }

                if ($target_row) {
                    $is_updated = false;
                    if ($target_row->product_name !== $name) { $is_updated = true; }
                    if ($target_row->barcode !== $barcode && !empty($barcode)) { $is_updated = true; }
                    if ($target_row->model_code !== $model_code && !empty($model_code)) { $is_updated = true; }
                    if ($target_row->store_id !== $current_store_id) { $is_updated = true; }
                    if (empty($target_row->image_url) && !empty($image_url)) { $is_updated = true; }
                    if (empty($target_row->product_url) && !empty($product_url)) { $is_updated = true; } 

                    if ($is_updated) {
                        $stats['updated']++;
                        $old_model_code = $target_row->model_code;
                        $old_barcode = $target_row->barcode;
                        
                        $wpdb->query($wpdb->prepare(
                            "UPDATE $table_questions SET product_name = %s, barcode = %s, model_code = %s, store_id = %s, image_url = %s, product_url = %s WHERE model_code = %s OR barcode = %s",
                            $name, $barcode, $model_code, $current_store_id, $image_url, $product_url, $old_model_code, $old_barcode
                        ));
                    }
                } else {
                    $wpdb->insert($table_questions, [
                        'trendyol_id'   => 'SYNC-' . time() . '-' . rand(1000,9999),
                        'store_id'      => $current_store_id,
                        'product_name'  => $name,
                        'model_code'    => $model_code,
                        'barcode'       => $barcode,
                        'image_url'     => $image_url, 
                        'product_url'   => $product_url,
                        'question_text' => 'OTOMATIK SENKRONIZASYON',
                        'answer_text'   => '',
                        'status'        => 'SYNCED',
                        'created_date'  => current_time('mysql')
                    ]);
                    $stats['new']++;
                }
            }
            $totalPages = isset($data['totalPages']) ? intval($data['totalPages']) : 1;
        }

        // BİR SONRAKİ ADIMI (NEXT STEP) HESAPLA VE JAVASCRIPT'E GÖNDER
        $next_page = $page + 1;
        $next_store_id = $current_store_id;
        $is_done = false;

        // Sayfalar bittiyse diğer mağazaya geç
        if (!isset($totalPages) || $next_page >= $totalPages) {
            $current_index = array_search($current_store_id, $store_ids);
            if ($current_index !== false && isset($store_ids[$current_index + 1])) {
                $next_store_id = $store_ids[$current_index + 1];
                $next_page = 0;
            } else {
                $is_done = true; // Tüm mağazalar ve sayfalar bitti!
            }
        }

        // CANLI TERMİNAL İÇİN LOG METNİNİ OLUŞTUR
        $time = date('H:i:s');
        $log_line = "[$time] 🏪 {$store_name} | ";
        if ($totalPages > 0) {
            $log_line .= "Sayfa: " . ($page + 1) . "/$totalPages | ";
            if ($stats['new'] > 0 || $stats['updated'] > 0) {
                $log_line .= "<span style='color:#38bdf8;'>Taranan: 100 Ürün</span> -> <span style='color:#10b981;'>➕ {$stats['new']} yeni, 🔄 {$stats['updated']} güncellendi.</span>";
            } else {
                $log_line .= "<span style='color:#38bdf8;'>Taranan: 100 Ürün</span> -> <span style='color:#64748b;'>✅ Değişiklik yok.</span>";
            }
        } else {
             $log_line .= "<span style='color:#f59e0b;'>⚠️ Bu mağazada ürün bulunamadı.</span>";
        }

        wp_send_json_success([
            'done' => $is_done,
            'next_store_id' => $next_store_id,
            'next_page' => $next_page,
            'log' => $log_line
        ]);
    }
   public function ajax_check_waiting_questions() {
        check_ajax_referer('ql_ajax_nonce', 'security');
        
        // 1. Manuel buton kontrolü ve Filtreleme
        $force_refresh = isset($_POST['force_refresh']) && $_POST['force_refresh'] === 'true';
        $store_id_filter = isset($_POST['store_id']) ? sanitize_text_field($_POST['store_id']) : 'all';
        
        // Kalkanın adını mağazaya göre dinamik yapıyoruz (Karışmaması için)
        $cache_key = 'ql_waiting_qs_cache_' . $store_id_filter;

        if ( $force_refresh ) {
            delete_transient($cache_key);
        }

        // 2. Kalkanı Kontrol Et (60 saniyelik hafıza)
        $response_data = get_transient($cache_key);

        if ( false === $response_data ) {
            // Kalkan boş! Trendyol'a bağlanıyoruz.
            $stores = get_option('ql_trendyol_stores', []);
            $total_waiting = 0;
            $all_questions = []; // Radar (ses) için soruları da alıyoruz
            
            foreach($stores as $sid => $s) {
                if ($store_id_filter !== 'all' && $store_id_filter !== $sid) continue;
                $trendyol_secret = QualityLife_API_Services::decrypt_data($s['secret']);
                $qs = QualityLife_API_Services::get_trendyol_questions($sid, $s['key'], $trendyol_secret);
                
                if (is_array($qs)) {
                    $total_waiting += count($qs);
                    $all_questions = array_merge($all_questions, $qs); // JS tarafı için soruları topla
                }
            }
            
            // Eski 'count' yapını bozmadan içine 'questions' verisini de ekliyoruz
            $response_data = [
                'count' => $total_waiting,
                'questions' => $all_questions
            ];

            // Çekilen veriyi 60 saniyeliğine mühürle
            set_transient($cache_key, $response_data, 60);
        }

        wp_send_json_success($response_data);
    }
    public function ajax_toggle_golden() {
        check_ajax_referer('ql_ajax_nonce', 'security');
        global $wpdb;
        $id = intval($_POST['item_id']);
        $table = $wpdb->prefix . 'ql_all_questions';
        $current = $wpdb->get_var($wpdb->prepare("SELECT is_golden FROM $table WHERE id = %d", $id));
        $wpdb->update($table, ['is_golden' => $current ? 0 : 1], ['id' => $id]);
        wp_send_json_success();
    }
    // --- UZMAN DOKUNUŞU: AJAX İLE KARTLARI ASENKRON YÜKLEME ---
    public function ajax_load_pending_cards() {
        check_ajax_referer('ql_ajax_nonce', 'security');
        global $wpdb;
        $stores = get_option('ql_trendyol_stores', []);
        $selected_seller_id = isset($_POST['store_id']) ? sanitize_text_field($_POST['store_id']) : 'all';

        $all_questions = [];
        foreach($stores as $sid => $s) {
            if ($selected_seller_id !== 'all' && $selected_seller_id !== $sid) continue;
            $trendyol_secret = QualityLife_API_Services::decrypt_data($s['secret']);
            $qs = QualityLife_API_Services::get_trendyol_questions($sid, $s['key'], $trendyol_secret);
            if (!empty($qs) && is_array($qs)) {
                foreach($qs as $q) {
                    $q['ql_store_name'] = $s['name'];
                    $q['ql_store_id'] = $sid;
                    $all_questions[] = $q;
                }
            }
        }

        if(empty($all_questions)) {
            wp_send_json_success([
                'count' => 0,
                'html' => '<div style="grid-column: 1 / -1; text-align:center; padding: 100px 20px; background:#fff; border-radius:30px; border: 2px dashed #cbd5e1;"><div style="font-size:50px; margin-bottom:20px;">🎉</div><h2 style="margin:0; color:#1e293b;">Tebrikler! Bekleyen soru yok.</h2><p style="color:#64748b;">Yeni sorular geldiğinde burada görünecekler.</p></div>'
            ]);
        }

        ob_start();
        $table_knowledge = $wpdb->prefix . 'ql_product_knowledge';
        $table_questions = $wpdb->prefix . 'ql_all_questions';
        
        foreach($all_questions as $q):
            $q_id = esc_attr($q['id']);
            $text = esc_html($q['text']);
            $product_name = esc_html($q['productName']);
            $barcode = isset($q['productMainId']) ? esc_attr($q['productMainId']) : '';
            $store_id = esc_attr($q['ql_store_id']);
            $rag_rule = $wpdb->get_var($wpdb->prepare("SELECT product_info FROM {$table_knowledge} WHERE barcode = %s", $barcode));
            
            $db_media = $wpdb->get_row($wpdb->prepare("SELECT image_url, product_url FROM {$table_questions} WHERE model_code = %s AND image_url != '' LIMIT 1", $barcode));
            // Uzman Dokunuşu: Sıfır Maliyetli Duygu Analizi (Sentiment Engine)
            $q_lower = mb_strtolower($text, 'UTF-8');
            $sentiment_badge = '<span style="font-size:11px; background:#f8fafc; padding:3px 8px; border-radius:6px; font-weight:600; color:#64748b; border: 1px solid #e2e8f0;">💬 Standart</span>';
            $sentiment_border = 'transparent';
            
            // Kızgın/Acil Kelime Havuzu
            if (preg_match('/(acil|rezalet|dava|tüketici|şikayet|iptal|nerede|gelmedi|eksik|kırık|ayıplı|bozuk|yazıklar|paramı|iade|kötü|sahte|çöp|berbat)/', $q_lower)) {
                $sentiment_badge = '<span style="font-size:10px; background:#fef2f2; padding:3px 8px; border-radius:6px; font-weight:900; color:#ef4444; border: 1px solid #fecaca; box-shadow: 0 0 10px rgba(239, 68, 68, 0.2); letter-spacing: 0.5px;">🚨 ACİL / KIZGIN</span>';
                $sentiment_border = '#ef4444'; // Kartın sol kenarlığını kalın kırmızı yap
            } 
            // Mutlu/Memnun Kelime Havuzu
            elseif (preg_match('/(teşekkür|harika|sağol|mükemmel|güzel|memnun|süper|iyi çalışmalar|bayıldım|kaliteli)/', $q_lower)) {
                $sentiment_badge = '<span style="font-size:10px; background:#f0fdf4; padding:3px 8px; border-radius:6px; font-weight:800; color:#166534; border: 1px solid #bbf7d0;">🥰 MEMNUN</span>';
                $sentiment_border = '#10b981'; // Kartın sol kenarlığını yeşil yap
            }
            $img_url = !empty($q['imageUrl']) ? esc_url($q['imageUrl']) : ($db_media && !empty($db_media->image_url) ? esc_url($db_media->image_url) : 'https://placehold.co/100x100/f8fafc/64748b?text=Gorsel+Yok');
            $ty_link = !empty($q['webUrl']) ? esc_url($q['webUrl']) : ($db_media && !empty($db_media->product_url) ? esc_url($db_media->product_url) : "https://www.trendyol.com/sr?q=" . $barcode);
            ?>
          <div class="ql-question-card" id="ql-card-<?php echo esc_attr($q['id']); ?>" style="border-left-color: <?php echo $sentiment_border; ?>;">
                <div class="ql-card-header" style="display:flex; gap:15px; align-items:center; position:relative;">
                    <a href="<?php echo $ty_link; ?>" target="_blank" style="flex-shrink:0;" title="Trendyol'da Görüntüle">
                        <img src="<?php echo $img_url; ?>" style="width:60px; height:60px; border-radius:10px; object-fit:contain; background:#fff; border:1px solid #e2e8f0; box-shadow: 0 2px 5px rgba(0,0,0,0.05); transition: 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                    </a>
                    <div class="ql-product-info" style="flex-grow:1;">
                        <h4 class="ql-product-title" style="margin-bottom:4px; font-size:15px; padding-right:0;"><?php echo $product_name; ?></h4>
                       <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                            <span class="ql-model-badge" style="font-size:11px; color:#64748b;">Barkod: <?php echo $barcode; ?></span>
                            <span style="font-size:11px; background:#f1f5f9; padding:3px 8px; border-radius:6px; font-weight:600; color:#475569;">👤 <?php echo isset($q['userName']) ? esc_html($q['userName']) : 'Gizli Müşteri'; ?></span>
                            <?php echo $sentiment_badge; ?>
                        </div>
                    </div>
                    <span class="ql-store-badge" style="top:15px; right:15px;"><?php echo esc_html($q['ql_store_name']); ?></span>
                </div>

                <div class="ql-card-body">
                    <div class="ql-q-box"><span class="ql-q-label">Soru</span><?php echo $text; ?></div>

                    <div style="display: flex; gap: 8px; margin-bottom: 12px;">
                        <button type="button" class="ql-btn-m ql-btn-s btn-toggle-rag" data-target="rag_container_<?php echo $q_id; ?>" style="font-size: 11px; padding: 6px 12px;">🧠 Bilgi Düzenle</button>
                        <button type="button" class="ql-btn-m ql-btn-s btn-toggle-note" data-target="note_container_<?php echo $q_id; ?>" style="font-size: 11px; padding: 6px 12px; border-color: #cbd5e1; background: #f1f5f9;">💡 Özel Not Ekle</button>
                    </div>

                    <div class="ql-rag-box" id="rag_container_<?php echo $q_id; ?>" style="display: none;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-size:10px; font-weight:900; color:#92400e; text-transform:uppercase;">🧠 Ürün Bilgisi (Hafıza)</span>
                            <span id="rag_status_<?php echo $q_id; ?>" style="font-size:10px; font-weight:bold; color:var(--ql-success);"></span>
                        </div>
                        <div class="ql-rag-input-group">
                            <input type="text" id="rag_<?php echo $q_id; ?>" class="ql-rag-input" value="<?php echo esc_attr($rag_rule); ?>" placeholder="Kalıcı kural girin...">
                            <button type="button" class="ql-btn-save btn-save-rag" data-barcode="<?php echo $barcode; ?>" data-id="<?php echo $q_id; ?>">💾</button>
                        </div>
                    </div>

                    <div id="note_container_<?php echo $q_id; ?>" style="display: none; background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 12px; padding: 12px; margin-bottom: 15px;">
                        <label style="font-size: 10px; font-weight: 800; color: #0369a1; display: block; margin-bottom: 5px;">💡 BU SORUYA ÖZEL NOT (Hafızaya eklenir)</label>
                        <textarea id="note_<?php echo $q_id; ?>" style="width: 100%; border: 1px solid #7dd3fc; border-radius: 8px; font-size: 12px; padding: 8px;" rows="2" placeholder="Örn: Bu ürün kedi için uygun değildir de."></textarea>
                    </div>

                    <div id="score_box_<?php echo $q_id; ?>" class="ql-score-pill"></div>
                    <textarea id="ans_<?php echo $q_id; ?>" class="ql-ans-textarea" placeholder="Yapay zeka cevabı hazırlayın..."></textarea>
                </div>

                <div class="ql-card-footer">
                    <button type="button" class="ql-btn-m ql-btn-s btn-ask" style="flex:1; justify-content:center;" data-id="<?php echo $q_id; ?>" data-barcode="<?php echo $barcode; ?>" data-q="<?php echo esc_attr($text); ?>" data-store="<?php echo $store_id; ?>">✨ Hazırla</button>
                    <button type="button" class="ql-btn-m ql-btn-p btn-send" style="flex:1.5; justify-content:center;" data-id="<?php echo $q_id; ?>" data-store="<?php echo $store_id; ?>" data-barcode="<?php echo $barcode; ?>" data-q="<?php echo esc_attr($text); ?>" data-pname="<?php echo esc_attr($product_name); ?>">🚀 Gönder</button>
                </div>
            </div>
            <?php
        endforeach;
        $html = ob_get_clean();

        // Akıllı radar (ses) sistemi için ID'leri döndürüyoruz
        $id_list = array_column($all_questions, 'id');

        wp_send_json_success(['html' => $html, 'count' => count($all_questions), 'ids' => $id_list]);
    }
    // --- YENİ: TARİHÇEDEN GERİ YÜKLEME VE SNAPSHOT ---
    public function ajax_restore_history() {
        check_ajax_referer('ql_ajax_nonce', 'security');
        global $wpdb;

        $barcode = sanitize_text_field($_POST['barcode']);
        // Uzman Dokunuşu: Verinin ham halini korumak ama zararlı kodlardan arındırmak için
        $content = wp_kses_post(wp_unslash($_POST['content'])); 
        
        if ($content === '[KURAL SİLİNDİ]') {
            $content = '';
        }

        $table_rag = $wpdb->prefix . 'ql_product_knowledge';
        $table_questions = $wpdb->prefix . 'ql_all_questions';
        $table_history = $wpdb->prefix . 'ql_product_history';

        // 1. Snapshot Verilerini Al
        $product_details = $wpdb->get_row($wpdb->prepare("SELECT store_id, product_name, image_url FROM $table_questions WHERE model_code = %s OR barcode = %s LIMIT 1", $barcode, $barcode));
        
        $p_name = $product_details ? $product_details->product_name : 'Bilinmeyen Ürün';
        $i_url = $product_details ? $product_details->image_url : '';
        $s_id = $product_details ? $product_details->store_id : '';
        $stores = get_option('ql_trendyol_stores', []);
        $s_name = isset($stores[$s_id]) ? $stores[$s_id]['name'] : 'Genel Kural';

        // 2. Mevcut Metni Al (Loglama İçin)
        $old_live_content = $wpdb->get_var($wpdb->prepare("SELECT product_info FROM $table_rag WHERE barcode = %s", $barcode));
        if ($old_live_content === null) $old_live_content = '';

        // 3. Geri Yükleme (Update/Delete/Insert)
        if (trim($content) === '') {
            $wpdb->delete($table_rag, ['barcode' => $barcode]);
        } else {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_rag WHERE barcode = %s", $barcode));
            if ($exists) {
                $wpdb->update($table_rag, ['product_info' => $content], ['barcode' => $barcode]);
            } else {
                $wpdb->insert($table_rag, ['barcode' => $barcode, 'product_info' => $content]);
            }
        }

        // 4. Yeni Bir Log Kaydı At (Zinciri Bozma)
        $wpdb->insert($table_history, [
            'barcode' => $barcode, 'store_name' => $s_name, 'product_name' => $p_name, 'image_url' => $i_url,
            'old_content' => $old_live_content, 'new_content' => $content, 'change_source' => '🕒 Geri Yükleme (Restore)', 'changed_by' => get_current_user_id()
        ]);

        wp_send_json_success();
    }
}