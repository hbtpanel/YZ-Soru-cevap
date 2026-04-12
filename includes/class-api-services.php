<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class QualityLife_API_Services {

    // 1. Gemini Metin Üretme (Flash Modeli)
    public static function ask_gemini($question_text, $barcode) {
        global $wpdb;
        $encrypted_key = get_option('ql_gemini_api_key', '');
$api_key = self::decrypt_data($encrypted_key);
        if(empty($api_key)) return "Hata: Lütfen ayarlardan Gemini API Key giriniz.";

        $global_prompt = get_option('ql_gemini_system_prompt', 'Sen bir müşteri temsilcisisin.');
        
        $table_name = $wpdb->prefix . 'ql_product_knowledge';
        $product_info_row = $wpdb->get_row($wpdb->prepare("SELECT product_info FROM {$table_name} WHERE barcode = %s", $barcode));
        $product_info = $product_info_row ? $product_info_row->product_info : "Genel kurallara göre nazikçe yanıtla.";

       $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$api_key}";
        $prompt = "Ürün Özellikleri: {$product_info}\n\nMüşteri Sorusu: {$question_text}\n\nE-ticaret formatında net bir cevap üret.";

        $body = [
            "system_instruction" => [ "parts" => [["text" => $global_prompt]] ],
            "contents" => [ ["parts" => [["text" => $prompt]]] ],
            "generationConfig" => [ "temperature" => 0.6 ]
        ];

        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($body),
            'timeout' => 20
        ]);

        if (is_wp_error($response)) return "Bağlantı hatası.";
        $data = json_decode(wp_remote_retrieve_body($response), true);
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? "Cevap üretilemedi.";
    }

   // 2. Gemini Vektör (Embedding) Oluşturma (Yeni!)
    public static function get_text_embedding($text) {
        $encrypted_key = get_option('ql_gemini_api_key', '');
$api_key = self::decrypt_data($encrypted_key);
        if(empty($api_key)) return ['error' => 'API Anahtarı eksik.'];

     $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:embedContent?key={$api_key}";
$body = [
    "model" => "models/gemini-embedding-001",
    "content" => [ "parts" => [ ["text" => $text] ] ]
];

        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($body),
            'timeout' => 15
        ]);

        if (is_wp_error($response)) return ['error' => 'Sunucu dışarı çıkamıyor: ' . $response->get_error_message()];
        
        $body_str = wp_remote_retrieve_body($response);
        $data = json_decode($body_str, true);
        
        // Google'dan dönen gerçek hatayı yakala
        if (isset($data['error'])) return ['error' => 'Google API: ' . $data['error']['message']];
        if (isset($data['embedding']['values'])) return ['values' => $data['embedding']['values']];

        return ['error' => 'Bilinmeyen API Yanıtı.'];
    }

    // YENİ: Toplu (Batch) Vektör Oluşturma - Kotayı 20 kat rahatlatır
    public static function get_batch_text_embeddings($texts) {
        $encrypted_key = get_option('ql_gemini_api_key', '');
$api_key = self::decrypt_data($encrypted_key);
        if(empty($api_key)) return ['error' => 'API Anahtarı eksik.'];

        // Batch işlemi için özel uç nokta (endpoint)
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:batchEmbedContents?key={$api_key}";

        $requests = [];
        foreach($texts as $text) {
            $requests[] = [
                "model" => "models/gemini-embedding-001",
                "content" => [ "parts" => [ ["text" => $text] ] ]
            ];
        }

        $body = [ "requests" => $requests ];

        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($body),
           'timeout' => 60 // 100 soruluk dev işlem için süreyi maksimize ettik
        ]);

        if (is_wp_error($response)) return ['error' => 'Sunucu hatası: ' . $response->get_error_message()];
        
        $body_str = wp_remote_retrieve_body($response);
        $data = json_decode($body_str, true);

        if (isset($data['error'])) return ['error' => 'Google API: ' . $data['error']['message']];
        if (isset($data['embeddings'])) return ['embeddings' => $data['embeddings']];

        return ['error' => 'Bilinmeyen API Yanıtı.'];
    }

    // 3. Trendyol Soru Çekme
    public static function get_trendyol_questions($seller_id, $api_key, $api_secret) {
        $url = "https://apigw.trendyol.com/integration/qna/sellers/{$seller_id}/questions/filter?status=WAITING_FOR_ANSWER";
        $response = wp_remote_get($url, [
            'headers'    => ['Authorization' => 'Basic ' . base64_encode($api_key . ':' . $api_secret), 'Accept' => 'application/json'],
            'user-agent' => $seller_id . ' - SelfIntegration',
            'sslverify'  => false,
            'timeout'    => 15
        ]);
        if (is_wp_error($response)) return [];
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['content'] ?? [];
    }

    // 4. Trendyol Cevap Gönderme
    public static function send_trendyol_answer($seller_id, $api_key, $api_secret, $question_id, $answer_text) {
        $url = "https://apigw.trendyol.com/integration/qna/sellers/{$seller_id}/questions/{$question_id}/answers";
        $response = wp_remote_post($url, [
            'headers'    => ['Authorization' => 'Basic ' . base64_encode($api_key . ':' . $api_secret), 'Content-Type' => 'application/json'],
            'body'       => wp_json_encode(['text' => sanitize_textarea_field($answer_text)]),
            'user-agent' => $seller_id . ' - SelfIntegration',
            'sslverify'  => false,
            'timeout'    => 15
        ]);
        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }
    // --- YENİ EKLENENLER: VEKTÖR ZEKASI VE KOSİNÜS BENZERLİĞİ ---

    // 1. Matematik Formülü: İki vektör arasındaki anlamsal mesafeyi ölçer (1'e ne kadar yakınsa o kadar benzerdir)
    public static function cosine_similarity($vec1, $vec2) {
        $dot_product = 0.0;
        $norm_a = 0.0;
        $norm_b = 0.0;
        
        $count = count($vec1);
        for ($i = 0; $i < $count; $i++) {
            $dot_product += $vec1[$i] * $vec2[$i];
            $norm_a += $vec1[$i] * $vec1[$i];
            $norm_b += $vec2[$i] * $vec2[$i];
        }
        
        if ($norm_a == 0 || $norm_b == 0) return 0;
        return $dot_product / (sqrt($norm_a) * sqrt($norm_b));
    }

    // 2. Yeni Nesil Yapay Zeka Sorgusu (Vektör Destekli)
   public static function ask_gemini_with_vector($question_text, $model_code, $store_id = '') {
        global $wpdb;
        $encrypted_key = get_option('ql_gemini_api_key', '');
$api_key = self::decrypt_data($encrypted_key);
        if(empty($api_key)) return "Hata: API Key eksik.";

        // A. Soruyu Koordinatlara Çevir
        $new_question_vector_data = self::get_text_embedding($question_text);
        if(!isset($new_question_vector_data['values'])) {
            return "Hata: Yeni sorunun vektörü çıkarılamadı. " . ($new_question_vector_data['error'] ?? '');
        }
        $new_vector = $new_question_vector_data['values'];

       // B. Veritabanından o ürüne ait İndekslenmiş EN GÜNCEL 500 eski soruyu çek (Performans ve Güncellik için)
        $table_all = $wpdb->prefix . 'ql_all_questions';
       $past_questions = $wpdb->get_results($wpdb->prepare("SELECT question_text, answer_text, vector_data FROM $table_all WHERE model_code = %s AND vector_data IS NOT NULL ORDER BY created_date DESC LIMIT 500", $model_code));

        // C. Matematiksel Benzerlik Yarışması (Skorlama)
        $scored_questions = [];
        foreach($past_questions as $pq) {
            $old_vector = json_decode($pq->vector_data, true);
            if(is_array($old_vector)) {
                $score = self::cosine_similarity($new_vector, $old_vector);
                $scored_questions[] = [
                    'score' => $score,
                    'q' => $pq->question_text,
                    'a' => $pq->answer_text
                ];
            }
        }

        // D. En yüksek puanlı (en benzer) 3 eski soruyu seç
        usort($scored_questions, function($a, $b) { return $b['score'] <=> $a['score']; });
        $top_3 = array_slice($scored_questions, 0, 3);

        // E. Manuel girdiğimiz ürün bilgisini de al (RAG)
        $table_knowledge = $wpdb->prefix . 'ql_product_knowledge';
        $product_info = $wpdb->get_var($wpdb->prepare("SELECT product_info FROM {$table_knowledge} WHERE barcode = %s", $model_code));
        $product_info = $product_info ? $product_info : "Özel ürün bilgisi girilmemiş.";

       // F. Gemini'ye Hiyerarşik Öncelik Dosyasını (Prompt) Hazırla
        $stores = get_option('ql_trendyol_stores', []);
        $store_prompt = "Sen bir e-ticaret müşteri temsilcisisin. Nazik ve profesyonelce cevap ver.";
        
        if(!empty($store_id) && isset($stores[$store_id]) && !empty($stores[$store_id]['prompt'])) {
            $store_prompt = $stores[$store_id]['prompt'];
        }

        $prompt = "Aşağıdaki yeni müşteri sorusuna cevap yazarken ŞU ÖNCELİK SIRASINA KESİNLİKLE UYMALISIN:\n\n";
        $prompt .= "🔴 1. ÖNCELİK (MAĞAZA KURALLARI VE DİLİ):\n\"{$store_prompt}\"\n\n";
        $prompt .= "🟡 2. ÖNCELİK (ÜRÜN BİLGİSİ):\n\"{$product_info}\"\n\n";
        
        if(!empty($top_3)) {
            $prompt .= "🟢 3. ÖNCELİK (GEÇMİŞ CEVAPLAR / REFERANS VE UYARLAMA):\n";
            $prompt .= "Aşağıdaki eski cevapları SADECE BİLGİ KAYNAĞI olarak kullan. Eski cevapları birebir kopyalama!\n";
            $prompt .= "Eski cevabın içindeki 'efendim', 'iyi günler' gibi hitapları ve yazım hatalarını tamamen temizle. Sadece net bilgiyi al ve 1. kuraldaki Mağaza Dili'ne yedirerek pürüzsüz, tek parça bir cümle oluştur.\n";
            foreach($top_3 as $index => $t3) {
                if($t3['score'] > 0.70) {
                    $prompt .= "Referans " . ($index+1) . " (Soru: " . $t3['q'] . ") -> Eski Cevap (Sadece içindeki bilgiyi al): " . $t3['a'] . "\n";
                }
            }
            $prompt .= "\n";
        }

        $prompt .= "🟣 4. KESİN KURAL (AKICILIK VE TEKRAR KONTROLÜ):\n";
        $prompt .= "Cevabını müşteriye göndermeden önce son bir kez kontrol et. Aynı hitap kelimesini (örneğin 'efendim', 'merhaba') cümlede iki defa KULLANMA. Frankenstein gibi eklenmiş durmasın, doğal bir insanın ağzından çıkmış gibi akıcı olsun.\n\n";

        $prompt .= "--- YENİ MÜŞTERİ SORUSU ---\n{$question_text}\n\nCEVAP:";

        // G. Sonucu Gemini'den İste
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$api_key}";
        
        // DİKKAT: Google API 2.5 Standartlarına %100 Uygun JSON Şeması
        $body = [
            "systemInstruction" => [
                "role" => "system",
                "parts" => [
                    [ "text" => (string) $store_prompt ]
                ]
            ],
            "contents" => [
                [
                    "role" => "user",
                    "parts" => [
                        [ "text" => (string) $prompt ]
                    ]
                ]
            ],
            "generationConfig" => [ "temperature" => 0.4 ]
        ];

        $response = wp_remote_post($url, ['headers' => ['Content-Type' => 'application/json'], 'body' => wp_json_encode($body), 'timeout' => 20]);

        if (is_wp_error($response)) return "Sunucu hatası: " . $response->get_error_message();
        
        $body_str = wp_remote_retrieve_body($response);
        $data = json_decode($body_str, true);

        // 1. Google API doğrudan hata döndürdüyse (Quota, Model NotFound vb.)
        if (isset($data['error'])) {
            return "❌ Google API Hatası: " . $data['error']['message'];
        }

        // 2. Cevap üretildi ama güvenlik filtresine (Safety Block) takıldıysa
        if (isset($data['candidates'][0]['finishReason']) && $data['candidates'][0]['finishReason'] !== 'STOP') {
            return "🛡️ Güvenlik Engeli: Google bu soruya cevap vermeyi reddetti. Sebep (FinishReason): " . $data['candidates'][0]['finishReason'];
        }

        // 3. Başarılı ve temiz cevap
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return $data['candidates'][0]['content']['parts'][0]['text'];
        }

        // 4. Beklenmeyen garip bir JSON geldiyse ne olduğunu görelim
        return "⚠️ Bilinmeyen Yapı: Google'dan gelen cevap okunamadı. Veri: " . wp_strip_all_tags($body_str);
    }

    // --- GÜVENLİK (ŞİFRELEME) MOTORU ---
    
    // 1. Veriyi Şifreler (Veritabanına kaydetmeden önce)
    public static function encrypt_data($data) {
        if (empty($data)) return $data;
        $encrypt_method = "AES-256-CBC";
        // wp-config.php içindeki eşsiz anahtarları kullanıyoruz
        $secret_key = defined('AUTH_KEY') ? AUTH_KEY : 'ql_default_secure_key_123!';
        $secret_iv = defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : 'ql_default_secure_iv_456!';
        
        $key = hash('sha256', $secret_key);
        $iv = substr(hash('sha256', $secret_iv), 0, 16);
        $output = openssl_encrypt($data, $encrypt_method, $key, 0, $iv);
        return base64_encode($output);
    }

    // 2. Şifreyi Çözer (API'ye istek atarken)
    public static function decrypt_data($data) {
        if (empty($data)) return $data;
        $encrypt_method = "AES-256-CBC";
        $secret_key = defined('AUTH_KEY') ? AUTH_KEY : 'ql_default_secure_key_123!';
        $secret_iv = defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : 'ql_default_secure_iv_456!';
        
        $key = hash('sha256', $secret_key);
        $iv = substr(hash('sha256', $secret_iv), 0, 16);
        return openssl_decrypt(base64_decode($data), $encrypt_method, $key, 0, $iv);
    }
    // --- TRENDYOL TÜM ÜRÜNLERİ ÇEKME ---
    public static function get_trendyol_products($seller_id, $api_key, $api_secret) {
        $all_items = [];
        $page = 0;
        $size = 100; // Her sayfada 100 ürün çekelim

        while (true) {
            $url = "https://apigw.trendyol.com/integration/product/sellers/{$seller_id}/products?page={$page}&size={$size}";
            
            $response = wp_remote_get($url, [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($api_key . ':' . $api_secret),
                    'User-Agent'    => $seller_id . ' - QL-AI'
                ],
                'timeout' => 30
            ]);

            if (is_wp_error($response)) break;
            
            $data = json_decode(wp_remote_retrieve_body($response), true);
            $items = $data['content'] ?? [];

            if (empty($items)) break;

            foreach ($items as $item) {
                $all_items[] = [
                    'barcode'    => $item['barcode'] ?? '',
                    'model_code' => $item['productMainId'] ?? '',
                    'name'       => $item['title'] ?? $item['productName'] ?? 'İsimsiz Ürün'
                ];
            }

            // Toplam sayfa sayısına ulaştıysak dur
            if ($page >= ($data['totalPages'] ?? 0) - 1) break;
            $page++;
        }
        return $all_items;
    }
}