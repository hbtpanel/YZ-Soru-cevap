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
        // Uzman Dokunuşu: AJAX güvenliği için admin_init kancasını kullanıyoruz (Fonksiyonlar yüklendikten sonra çalışır)
        add_action( 'admin_init', [ $this, 'secure_ajax_endpoints' ] );
        
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
        
        // OTOMATİK RAG GÜNCELLEME: Eğer not varsa kalıcı hafızaya ekle
        if (!empty($quick_note)) {
            $table = $wpdb->prefix . 'ql_product_knowledge';
            $current_info = $wpdb->get_var($wpdb->prepare("SELECT product_info FROM $table WHERE barcode = %s", $barcode));
            $new_info = $current_info ? $current_info . "\n" . $quick_note : $quick_note;
            
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE barcode = %s", $barcode));
            if($exists) {
                $wpdb->update($table, ['product_info' => $new_info], ['barcode' => $barcode]);
            } else {
                $wpdb->insert($table, ['barcode' => $barcode, 'product_info' => $new_info]);
            }
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
                $combined_text = "Soru: " . $q_text . " Cevap: " . $answer;
                $vector_data = QualityLife_API_Services::get_text_embedding($combined_text);
                $vector_json = isset($vector_data['values']) ? wp_json_encode($vector_data['values']) : null;

                $wpdb->insert($table, [
                    'trendyol_id'   => $q_id,
                    'store_id'      => $store_id,
                    'product_name'  => $p_name,
                    'model_code'    => $barcode,
                    'question_text' => $q_text,
                    'answer_text'   => $answer,
                    'status'        => 'ANSWERED',
                    'created_date'  => current_time('mysql'),
                    'vector_data'   => $vector_json,
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
        
        // 1. PHP Zaman Aşımını Kapat (Çok ürünlü mağazalar için yarıda kesilmeyi önler)
        set_time_limit(0); 

        $stores = get_option('ql_trendyol_stores', []);
        $table_questions = $wpdb->prefix . 'ql_all_questions';

       $stats = [
            'new' => 0,
            'name' => 0,
            'barcode' => 0,
            'model' => 0,
            'store' => 0,
            'image' => 0 // YENİ: Resim istatistiği
        ];

        // 🚀 2. DEV OPTİMİZASYON: Veritabanındaki tüm ürünleri tek seferde RAM'e al!
        $existing_items = $wpdb->get_results("SELECT id, model_code, barcode, product_name, store_id, image_url, product_url FROM $table_questions GROUP BY model_code");
        $db_by_barcode = [];
        $db_by_model = [];
        
        foreach ($existing_items as $item) {
            if (!empty($item->barcode)) $db_by_barcode[$item->barcode] = $item;
            if (!empty($item->model_code)) $db_by_model[$item->model_code] = $item;
        }

        foreach ($stores as $sid => $s) {
            $trendyol_secret = QualityLife_API_Services::decrypt_data($s['secret']);
            $page = 0;
            
            while(true) {
                $url = "https://apigw.trendyol.com/integration/product/sellers/{$sid}/products?page={$page}&size=100";
                
                $response = wp_remote_get($url, [
                    'headers' => [
                        'Authorization' => 'Basic ' . base64_encode(trim($s['key']) . ':' . trim($trendyol_secret)),
                        'Accept' => 'application/json'
                    ],
                    'user-agent' => $sid . ' - SelfIntegration',
                    'timeout' => 30, 'sslverify' => false
                ]);

                if (is_wp_error($response)) break;
                
                $data = json_decode(wp_remote_retrieve_body($response), true);
                if (!isset($data['content']) || empty($data['content'])) break;

                foreach ($data['content'] as $p) {
                    $model_code = $p['productMainId'] ?? $p['stockCode'] ?? '';
                    $name = $p['title'] ?? $p['productName'] ?? 'İsimsiz Ürün';
                    
                   $barcode = $p['barcode'] ?? '';
                    if (empty($barcode) && !empty($p['variants']) && is_array($p['variants'])) {
                        $barcode = $p['variants'][0]['barcode'] ?? '';
                    }
                    
                    // YENİ: Trendyol'dan Ürün Resmini ve Linkini Yakala
                    $image_url = '';
                    $product_url = isset($p['productUrl']) ? $p['productUrl'] : '';
                    if (!empty($p['images']) && is_array($p['images']) && isset($p['images'][0]['url'])) {
                        $image_url = $p['images'][0]['url'];
                    }

                    if (empty($model_code) && empty($barcode)) continue;

                    // 3. Ürünü Veritabanında Değil, RAM'de (Hafızada) Ara (Işık hızında)
                    $target_row = null;
                    if (!empty($barcode) && isset($db_by_barcode[$barcode])) {
                        $target_row = $db_by_barcode[$barcode];
                    }
                    if (!$target_row && !empty($model_code) && isset($db_by_model[$model_code])) {
                        $target_row = $db_by_model[$model_code];
                    }

                    if ($target_row) {
                        $is_updated = false;
                        
                       if ($target_row->product_name !== $name) { $stats['name']++; $is_updated = true; }
                        if ($target_row->barcode !== $barcode && !empty($barcode)) { $stats['barcode']++; $is_updated = true; }
                        if ($target_row->model_code !== $model_code && !empty($model_code)) { $stats['model']++; $is_updated = true; }
                        if ($target_row->store_id !== $sid) { $stats['store']++; $is_updated = true; }
                        // YENİ: Eğer veritabanında resim yoksa, ama Trendyol'da varsa hemen ekle!
                        if (empty($target_row->image_url) && !empty($image_url)) { $stats['image']++; $is_updated = true; }
                        // Eğer ürün linki boşsa hemen veri tabanına doldur
                        if (empty($target_row->product_url) && !empty($product_url)) { $is_updated = true; } 

                        if ($is_updated) {
                            $old_model_code = $target_row->model_code;
                            $old_barcode = $target_row->barcode;
                            
                            $wpdb->query($wpdb->prepare(
                                "UPDATE $table_questions SET product_name = %s, barcode = %s, model_code = %s, store_id = %s, image_url = %s, product_url = %s WHERE model_code = %s OR barcode = %s",
                                $name, $barcode, $model_code, $sid, $image_url, $product_url, $old_model_code, $old_barcode
                            ));

                            // RAM'deki veriyi de güncelle
                            $target_row->product_name = $name;
                            $target_row->barcode = $barcode;
                            $target_row->model_code = $model_code;
                            $target_row->store_id = $sid;
                            $target_row->image_url = $image_url;
                            $target_row->product_url = $product_url;
                        }
                   } else {
                        // Yeni Ürün Ekle
                        $wpdb->insert($table_questions, [
                            'trendyol_id'   => 'SYNC-' . time() . '-' . rand(1000,9999),
                            'store_id'      => $sid,
                            'product_name'  => $name,
                            'model_code'    => $model_code,
                            'barcode'       => $barcode,
                            'image_url'     => $image_url,   // RESİM EKLENDİ
                            'product_url'   => $product_url, // LİNK EKLENDİ
                            'question_text' => 'OTOMATIK SENKRONIZASYON',
                            'answer_text'   => '',
                            'status'        => 'SYNCED',
                            'created_date'  => current_time('mysql')
                        ]);
                        
                        // Eklenen ürünü RAM'e de tanıt ki döngünün devamında tekrar eklemeye çalışmasın
                        $new_item = (object)[
                            'product_name' => $name, 
                            'barcode'      => $barcode, 
                            'model_code'   => $model_code, 
                            'store_id'     => $sid,
                            'image_url'    => $image_url,
                            'product_url'  => $product_url
                        ];
                        if (!empty($barcode)) $db_by_barcode[$barcode] = $new_item;
                        if (!empty($model_code)) $db_by_model[$model_code] = $new_item;

                        $stats['new']++;
                    }
                }

                $totalPages = isset($data['totalPages']) ? intval($data['totalPages']) : 1;
                if ($page >= $totalPages - 1 || $page >= 20) break; 
                $page++;
            }
        }

        // Özet Rapor
        $msg = "⚡ Işık Hızında Senkronizasyon Tamamlandı!\n\n";
        
        if (array_sum($stats) === 0) {
            $msg .= "✅ Ürünleriniz zaten en güncel halinde. Yeni bir değişiklik bulunamadı.";
        } else {
            $msg .= "📊 İŞLEM ÖZETİ:\n";
            if ($stats['new'] > 0) $msg .= "➕ " . $stats['new'] . " Yeni Ürün Eklendi.\n";
            if ($stats['name'] > 0) $msg .= "✏️ " . $stats['name'] . " Ürünün İsmi Güncellendi.\n";
            if ($stats['barcode'] > 0) $msg .= "🏷️ " . $stats['barcode'] . " Ürüne Yeni Barkod İşlendi.\n";
            if ($stats['model'] > 0) $msg .= "📦 " . $stats['model'] . " Ürünün Model Kodu Değişti.\n";
            if ($stats['store'] > 0) $msg .= "🏪 " . $stats['store'] . " Ürünün Mağaza Bilgisi Güncellendi.\n";
            if ($stats['image'] > 0) $msg .= "🖼️ " . $stats['image'] . " Eski Soruya/Ürüne Resim Eklendi.\n"; // YENİ EKLENDİ
        }

        wp_send_json_success($msg);
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
}