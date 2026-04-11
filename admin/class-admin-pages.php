<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class QualityLife_Admin_Pages {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'setup_admin_menus' ] );
        add_action( 'admin_post_ql_save_global', [ $this, 'save_global_settings' ] );
        add_action( 'admin_post_ql_save_store', [ $this, 'save_store_settings' ] );
        add_action( 'admin_post_ql_save_knowledge', [ $this, 'save_knowledge' ] );
    }

    public function setup_admin_menus() {
        add_menu_page('YZ Eğitim ve Ayarlar', 'YZ Asistan', 'manage_options', 'ql-ai-settings', [ $this, 'page_settings' ], 'dashicons-robot', 30);
        add_submenu_page('ql-ai-settings', 'Bekleyen Sorular', 'Bekleyen Sorular', 'manage_options', 'ql-ai-questions', [ $this, 'page_questions' ]);
        add_submenu_page('ql-ai-settings', 'Soru Arşivi', 'Soru Arşivi (Bot)', 'manage_options', 'ql-ai-past-questions', [ $this, 'page_past_questions' ]);
    }

    // --- SAYFA 1: AYARLAR ---
    public function page_settings() {
        global $wpdb;
        $stores = get_option('ql_trendyol_stores', []); 
        $gemini_api_key = get_option('ql_gemini_api_key', '');
        $global_prompt = get_option('ql_gemini_system_prompt', 'Sen bir müşteri temsilcisisin.');
        $table_name = $wpdb->prefix . 'ql_product_knowledge';
        $products = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY id DESC LIMIT 50");
        ?>
        <div class="wrap">
            <h1>⚙️ YZ Eğitim ve Mağaza Ayarları</h1>
            <hr>
            <?php if(isset($_GET['updated'])) echo '<div class="notice notice-success is-dismissible"><p>Ayarlar kaydedildi.</p></div>'; ?>
            
            <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 20px;">
                <div style="flex: 1; min-width: 300px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                    <h3>1. API ve Global Ayarlar</h3>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="ql_save_global">
                        <?php wp_nonce_field('ql_global_nonce'); ?>
                        <label>Gemini API Anahtarı:</label>
                        <input type="password" name="gemini_api_key" value="<?php echo esc_attr($gemini_api_key); ?>" style="width: 100%; margin-bottom: 10px;">
                        <label>Global Sistem Talimatı (Marka Dili):</label>
                        <textarea name="global_prompt" rows="5" style="width: 100%; margin-bottom: 15px;"><?php echo esc_textarea($global_prompt); ?></textarea>
                        <button type="submit" class="button button-primary">Ayarları Kaydet</button>
                    </form>

                    <hr style="margin: 20px 0;">

                    <h3>2. Trendyol Mağaza Ekle</h3>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="ql_save_store">
                        <?php wp_nonce_field('ql_store_nonce'); ?>
                        <input type="text" name="store_name" style="width: 100%; margin-bottom: 5px;" required placeholder="Mağaza Adı (Örn: QL Merkez)">
                        <input type="text" name="seller_id" style="width: 100%; margin-bottom: 5px;" required placeholder="Satıcı ID">
                        <input type="text" name="api_key" style="width: 100%; margin-bottom: 5px;" required placeholder="Trendyol API Key">
                        <input type="password" name="api_secret" style="width: 100%; margin-bottom: 10px;" required placeholder="Trendyol API Secret">
                        <button type="submit" class="button button-secondary">Mağazayı Ekle</button>
                    </form>

                    <div style="margin-top: 15px; background: #f0f6fc; padding: 10px; border-radius: 4px;">
                        <strong>Kayıtlı Mağazalar:</strong><br>
                        <?php foreach($stores as $id => $s): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; border-bottom: 1px solid #ddd; padding-bottom: 5px;">
                                <span><?php echo esc_html($s['name']) . " (ID: $id)"; ?></span>
                                <button type="button" class="button button-small btn-test-store" data-id="<?php echo esc_attr($id); ?>">Test Et</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div style="flex: 1; min-width: 300px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                    <h3>3. Manuel Ürün Eğitimi (RAG)</h3>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="ql_save_knowledge">
                        <?php wp_nonce_field('ql_knowledge_nonce'); ?>
                        <label>Ürün Model Kodu:</label>
                        <input type="text" name="barcode" style="width: 100%; margin-bottom: 10px;" required placeholder="Örn: QL-GOZ-001">
                        <label>Ürün Özellikleri:</label>
                        <textarea name="product_info" rows="4" style="width: 100%; margin-bottom: 15px;" required placeholder="Tuz içermez..."></textarea>
                        <button type="submit" class="button button-primary">Bilgiyi Kaydet</button>
                    </form>

                    <h4 style="margin-top: 20px;">Eğitilmiş Ürünler</h4>
                    <table class="wp-list-table widefat fixed striped">
                       <thead><tr><th style="width: 30%;">Model Kodu</th><th>Bilgi</th></tr></thead>
                        <tbody>
                            <?php foreach($products as $p): ?>
                                <tr><td><?php echo esc_html($p->barcode); ?></td><td><?php echo esc_html($p->product_info); ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.btn-test-store').forEach(btn => {
                btn.addEventListener('click', async function() {
                    const id = this.dataset.id;
                    const originalText = this.innerHTML;
                    this.innerHTML = '⏳ Test...'; this.disabled = true;
                    const fd = new FormData();
                    fd.append('action', 'ql_test_store'); fd.append('security', '<?php echo wp_create_nonce("ql_ajax_nonce"); ?>'); fd.append('store_id', id);
                    try {
                        const res = await fetch(ajaxurl, { method: 'POST', body: fd });
                        const data = await res.json();
                        alert(data.data.message);
                    } catch (e) { alert('Bağlantı hatası.'); }
                    this.innerHTML = originalText; this.disabled = false;
                });
            });
        });
        </script>
        <?php
    }

    // --- SAYFA 2: BEKLEYEN SORULAR ---
    public function page_questions() {
        $stores = get_option('ql_trendyol_stores', []);
        $selected_seller_id = isset($_GET['store_id']) ? sanitize_text_field($_GET['store_id']) : (empty($stores) ? '' : array_key_first($stores));

        $questions = [];
        if(!empty($selected_seller_id) && isset($stores[$selected_seller_id])) {
            $s = $stores[$selected_seller_id];
            $questions = QualityLife_API_Services::get_trendyol_questions($selected_seller_id, $s['key'], $s['secret']);
        }
        ?>
        <div class="wrap">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h1>📥 Bekleyen Trendyol Soruları</h1>
                <form method="get" action="">
                    <input type="hidden" name="page" value="ql-ai-questions">
                    <select name="store_id" onchange="this.form.submit()" style="padding: 5px; font-size: 14px;">
                        <option value="">Mağaza Seçin...</option>
                        <?php foreach($stores as $id => $store): ?>
                            <option value="<?php echo esc_attr($id); ?>" <?php selected($selected_seller_id, $id); ?>><?php echo esc_html($store['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <hr>
            <?php if(empty($questions)): ?>
                <div class="notice notice-info"><p>Bekleyen soru yok.</p></div>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr)); gap: 20px; margin-top: 20px;">
                    <?php foreach($questions as $q): 
                        $q_id = esc_attr($q['id']);
                        $text = esc_html($q['text']);
                        $product_name = esc_html($q['productName']);
                        $barcode = isset($q['productMainId']) ? esc_attr($q['productMainId']) : '';
                    ?>
                    <div class="ql-card" style="background: #fff; padding: 15px; border: 1px solid #ccd0d4; border-radius: 6px;">
                        <h4 style="margin: 0 0 5px 0;"><?php echo $product_name; ?></h4>
                        <span style="background: #eee; padding: 2px 6px; border-radius: 3px; font-size: 11px;">Model: <?php echo $barcode; ?></span>
                        <div style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 10px; margin: 10px 0;">
                            <strong>Soru:</strong> <?php echo $text; ?>
                        </div>
                        <textarea id="ans_<?php echo $q_id; ?>" rows="5" style="width: 100%; margin-bottom: 10px;" placeholder="Yapay zeka cevabı..."></textarea>
                        <div style="display: flex; justify-content: space-between;">
                            <button type="button" class="button btn-ask" data-id="<?php echo $q_id; ?>" data-barcode="<?php echo $barcode; ?>" data-q="<?php echo esc_attr($text); ?>">✨ YZ Hazırla</button>
                            <button type="button" class="button button-primary btn-send" data-id="<?php echo $q_id; ?>" data-store="<?php echo esc_attr($selected_seller_id); ?>">🚀 Gönder</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const nonce = '<?php echo wp_create_nonce("ql_ajax_nonce"); ?>';
            document.querySelectorAll('.btn-ask').forEach(btn => {
                btn.addEventListener('click', async function() {
                    const id = this.dataset.id;
                    const box = document.getElementById('ans_' + id);
                    this.disabled = true; this.innerHTML = '⏳ Hazırlanıyor...';
                    const fd = new FormData();
                    fd.append('action', 'ql_ask_ai'); fd.append('security', nonce); fd.append('question', this.dataset.q); fd.append('barcode', this.dataset.barcode);
                    try {
                        const res = await fetch(ajaxurl, { method: 'POST', body: fd });
                        const data = await res.json();
                        box.value = data.success ? data.data.answer : 'Hata oluştu.';
                    } catch (e) { box.value = 'Bağlantı hatası.'; }
                    this.disabled = false; this.innerHTML = '✨ Yeniden Hazırla';
                });
            });

            document.querySelectorAll('.btn-send').forEach(btn => {
                btn.addEventListener('click', async function() {
                    const id = this.dataset.id; const store = this.dataset.store;
                    const answer = document.getElementById('ans_' + id).value.trim();
                    if(!answer) return alert('Lütfen bir cevap yazın!');
                    if(!confirm('Bu cevap müşteriye gönderilecek. Emin misiniz?')) return;
                    this.disabled = true; this.innerHTML = '🚀 Gönderiliyor...';
                    const fd = new FormData();
                    fd.append('action', 'ql_send_answer'); fd.append('security', nonce); fd.append('q_id', id); fd.append('answer', answer); fd.append('store_id', store);
                    try {
                        const res = await fetch(ajaxurl, { method: 'POST', body: fd });
                        const data = await res.json();
                        if(data.success) { alert('İletildi!'); this.closest('.ql-card').style.display = 'none'; } 
                        else { alert('Hata.'); this.disabled = false; this.innerHTML = '🚀 Gönder'; }
                    } catch (e) { alert('Bağlantı koptu.'); this.disabled = false; }
                });
            });
        });
        </script>
        <?php
    }

    // --- SAYFA 3: ARŞİV VE BOT ---
    public function page_past_questions() {
        global $wpdb;
        $stores = get_option('ql_trendyol_stores', []);
        $table = $wpdb->prefix . 'ql_all_questions';

        $s_term  = isset($_GET['s_term']) ? sanitize_text_field($_GET['s_term']) : '';
        $s_store = isset($_GET['store_id']) ? sanitize_text_field($_GET['store_id']) : '';
        
        $items_per_page = 50;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $items_per_page;

        $where = "WHERE 1=1"; $params = [];
        if ($s_term) {
            $where .= " AND (product_name LIKE %s OR question_text LIKE %s OR model_code LIKE %s)";
            array_push($params, '%'.$s_term.'%', '%'.$s_term.'%', '%'.$s_term.'%');
        }
        if ($s_store) {
            $where .= " AND store_id = %s"; $params[] = $s_store;
        }

        $prepared_where = empty($params) ? $where : $wpdb->prepare($where, $params);
        $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table $prepared_where");
        $total_pages = ceil($total_items / $items_per_page);

        $query = "SELECT * FROM $table $prepared_where ORDER BY created_date DESC LIMIT %d OFFSET %d";
        $past_questions = $wpdb->get_results($wpdb->prepare($query, $items_per_page, $offset));
        ?>
        <div class="wrap">
            <h1>📜 Soru Arşivi ve Veri Botu</h1>
            
            <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                <div style="flex: 2; background: #fff; padding: 15px; border: 1px solid #ccd0d4; border-radius: 4px;">
                    <strong>1. Veri Çekme Botu (Trendyol'dan Arşive):</strong><br><br>
                    <select id="fetch_store">
                        <?php foreach($stores as $id => $st) echo "<option value='".esc_attr($id)."'>".esc_html($st['name'])."</option>"; ?>
                    </select>
                    <button class="button" onclick="startFetch(1)">Son 1 Gün</button>
                    <button class="button" onclick="startFetch(7)">Son 1 Hafta</button>
                    <button class="button" onclick="startFetch(30)">Son 1 Ay</button>
                    <button class="button" onclick="startFetch('all')">Tüm Zamanlar</button>
                    <div id="fetch_status" style="margin-top: 10px; font-weight: bold; color: #2271b1;"></div>
                </div>

                <div style="flex: 1; background: #fff; padding: 15px; border: 1px solid #ccd0d4; border-radius: 4px; border-left: 4px solid #6a1b9a;">
                    <strong>2. YZ Vektör Eğitimi (Arşivi Beyne Yaz):</strong><br><br>
                    <button class="button button-primary" onclick="startVectorization()" style="background: #6a1b9a; border-color: #4a148c;">🧠 Arşivi İndeksle</button>
                    <div id="vector_status" style="margin-top: 10px; font-weight: bold; color: #6a1b9a;">
                        <?php 
                        $remaining_vectors = $wpdb->get_var("SELECT COUNT(id) FROM $table WHERE vector_data IS NULL");
                        if($remaining_vectors > 0) echo "İndekslenmeyi bekleyen $remaining_vectors soru var.";
                        else echo "Tüm arşiv başarıyla indekslendi ✅";
                        ?>
                    </div>
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <form method="get" style="display: flex; gap: 10px;">
                    <input type="hidden" name="page" value="ql-ai-past-questions">
                    <input type="text" name="s_term" value="<?php echo esc_attr($s_term); ?>" placeholder="Ürün adı, model veya soru içinde ara..." style="flex: 1;">
                    <button type="submit" class="button button-primary">Arşivde Ara</button>
                    <a href="?page=ql-ai-past-questions" class="button">Temizle</a>
                </form>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr)); gap: 20px;">
                <?php if($past_questions): foreach($past_questions as $q): ?>
                   <div class="ql-card" style="background: #fff; padding: 15px; border: 1px solid #ccd0d4; border-radius: 6px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 11px; color: #999; margin-bottom: 5px;">
                            <span><?php echo esc_html($q->created_date); ?> | <?php echo esc_html($q->model_code); ?></span>
                            <?php if(!empty($q->vector_data)): ?>
                                <span style="background: #e7f5ea; color: #135e96; padding: 2px 6px; border-radius: 3px; font-weight: 500;" title="Yapay zeka beynine kaydedildi">🧠 İndekslendi</span>
                            <?php else: ?>
                                <span style="background: #fcf0f1; color: #d63638; padding: 2px 6px; border-radius: 3px;" title="Vektör işlemi bekliyor">⏳ İndeks Bekliyor</span>
                            <?php endif; ?>
                        </div>
                        <strong><?php echo esc_html($q->product_name); ?></strong>
                        <p style="background: #f9f9f9; padding: 10px;"><?php echo esc_html($q->question_text); ?></p>
                        <div style="font-size: 12px; color: #555;"><strong>Eski Cevap:</strong> <?php echo esc_html($q->answer_text); ?></div>
                        
                        <button type="button" class="button btn-test-ai" style="margin-top:10px" data-id="<?php echo esc_attr($q->id); ?>" data-barcode="<?php echo esc_attr($q->model_code); ?>" data-q="<?php echo esc_attr($q->question_text); ?>">✨ YZ Testi</button>
                        <div id="test_res_<?php echo esc_attr($q->id); ?>" style="display:none; margin-top:10px; background:#e7f5ea; padding:10px; border-radius:4px;"></div>
                    </div>
                <?php endforeach; else: ?>
                    <p>Arşivde soru bulunamadı.</p>
                <?php endif; ?>
            </div>

            <?php if($total_pages > 1): ?>
            <div style="margin-top: 30px; display: flex; justify-content: center; gap: 15px; align-items: center; background: #fff; padding: 15px; border: 1px solid #ccd0d4; border-radius: 4px;">
                <button type="button" class="button" onclick="changePage(<?php echo max(1, $current_page - 1); ?>)" <?php if($current_page <= 1) echo 'disabled'; ?>>« Önceki Sayfa</button>
                <span style="font-weight: 500; color: #2271b1;">Sayfa <?php echo $current_page; ?> / <?php echo $total_pages; ?> (Toplam <?php echo $total_items; ?>)</span>
                <button type="button" class="button" onclick="changePage(<?php echo min($total_pages, $current_page + 1); ?>)" <?php if($current_page >= $total_pages) echo 'disabled'; ?>>Sonraki Sayfa »</button>
            </div>
            <?php endif; ?>
        </div>

        <script>
        function changePage(page) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('paged', page);
            window.location.search = urlParams.toString();
        }

        async function startFetch(days) {
            const storeId = document.getElementById('fetch_store').value;
            const status = document.getElementById('fetch_status');
            let totalInserted = 0;
            status.innerHTML = '🤖 Bot başlatıldı...';

            let scanDays = days === 'all' ? 730 : parseInt(days);
            let chunks = []; let now = Date.now(); let msPerDay = 86400 * 1000;

            for(let i = 0; i < scanDays; i += 14) {
                let chunkDays = Math.min(14, scanDays - i);
                let endMs = now - (i * msPerDay);
                chunks.push({ start: endMs - (chunkDays * msPerDay), end: endMs, label: `${i} ile ${i+chunkDays} gün öncesi` });
            }

            for (let c = 0; c < chunks.length; c++) {
                let chunk = chunks[c]; let currentPage = 0; let hasDataInChunk = false;
                while(true) {
                    status.innerHTML = `⏳ [${chunk.label}] | Sayfa ${currentPage + 1}... (Yeni: ${totalInserted})`;
                    const fd = new FormData();
                    fd.append('action', 'ql_fetch_batch'); fd.append('security', '<?php echo wp_create_nonce("ql_ajax_nonce"); ?>');
                    fd.append('store_id', storeId); fd.append('page', currentPage);
                    fd.append('start_ms', chunk.start); fd.append('end_ms', chunk.end);
                    try {
                        const res = await fetch(ajaxurl, { method: 'POST', body: fd });
                        const data = await res.json();
                        if(!data.success) { status.innerHTML = '❌ Hata: ' + data.data.message; return; }
                        totalInserted += data.data.inserted;
                        if(data.data.total_pages === 0) break;
                        hasDataInChunk = true;
                        if(data.data.done) break; 
                        currentPage++;
                        await new Promise(r => setTimeout(r, 1000));
                    } catch(e) { status.innerHTML = '❌ Bağlantı koptu.'; return; }
                }
                if (days === 'all' && !hasDataInChunk && c > 1) break;
            }
            status.innerHTML = `✅ Bitti! ${totalInserted} yeni soru eklendi. Sayfa yenileniyor...`;
            setTimeout(() => location.reload(), 2000);
        }

        async function startVectorization() {
            const status = document.getElementById('vector_status');
            status.innerHTML = '🧠 İndeksleme Başladı... Lütfen sekmeyi kapatmayın.';
            
            while(true) {
                const fd = new FormData();
                fd.append('action', 'ql_vectorize_batch');
                fd.append('security', '<?php echo wp_create_nonce("ql_ajax_nonce"); ?>');

                try {
                    const res = await fetch(ajaxurl, { method: 'POST', body: fd });
                    const data = await res.json();

                   if(!data.success) { 
                        status.innerHTML = '❌ ' + (data.data && data.data.message ? data.data.message : 'Sistem Hatası.'); 
                        break; 
                    }
                    
                    if(data.data.done) {
                        status.innerHTML = '✅ Bitti! Tüm arşiv yapay zeka beynine yazıldı.';
                        break;
                    }

                    status.innerHTML = `⚙️ İşleniyor... Kalan Soru: ${data.data.remaining}`;
                    await new Promise(r => setTimeout(r, 1000)); // Google'ı yormamak için mola
                } catch(e) {
                    status.innerHTML = '❌ Bağlantı koptu. Tekrar butona basın.';
                    break;
                }
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.btn-test-ai').forEach(btn => {
                btn.addEventListener('click', async function() {
                    const id = this.dataset.id; const resDiv = document.getElementById('test_res_' + id);
                    this.disabled = true; this.innerHTML = '⏳...'; resDiv.style.display = 'block'; resDiv.innerHTML = 'Hesaplanıyor...';
                    const fd = new FormData();
                    fd.append('action', 'ql_ask_ai'); fd.append('security', '<?php echo wp_create_nonce("ql_ajax_nonce"); ?>');
                    fd.append('question', this.dataset.q); fd.append('barcode', this.dataset.barcode);
                    try {
                        const res = await fetch(ajaxurl, { method: 'POST', body: fd });
                        const data = await res.json();
                        resDiv.innerHTML = '<strong>YZ Cevabı:</strong><br>' + (data.success ? data.data.answer : 'Hata.');
                    } catch(e) { resDiv.innerHTML = 'Bağlantı hatası.'; }
                    this.disabled = false; this.innerHTML = '✨ Testi Tekrarla';
                });
            });
        });
        </script>
        <?php
    }

    // --- FORM İŞLEMCİLERİ ---
    public function save_global_settings() {
        if(!check_admin_referer('ql_global_nonce')) return;
        update_option('ql_gemini_api_key', sanitize_text_field($_POST['gemini_api_key']));
        update_option('ql_gemini_system_prompt', sanitize_textarea_field($_POST['global_prompt']));
        wp_redirect(admin_url('admin.php?page=ql-ai-settings&updated=1')); exit;
    }
    public function save_store_settings() {
        if(!check_admin_referer('ql_store_nonce')) return;
        $stores = get_option('ql_trendyol_stores', []);
        $stores[sanitize_text_field($_POST['seller_id'])] = [
            'name' => sanitize_text_field($_POST['store_name']),
            'key' => sanitize_text_field($_POST['api_key']),
            'secret' => sanitize_text_field($_POST['api_secret'])
        ];
        update_option('ql_trendyol_stores', $stores);
        wp_redirect(admin_url('admin.php?page=ql-ai-settings&updated=1')); exit;
    }
    public function save_knowledge() {
        if(!check_admin_referer('ql_knowledge_nonce')) return;
        global $wpdb;
        $table = $wpdb->prefix . 'ql_product_knowledge';
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (barcode, product_info) VALUES (%s, %s) ON DUPLICATE KEY UPDATE product_info = VALUES(product_info)",
            sanitize_text_field($_POST['barcode']), sanitize_textarea_field($_POST['product_info'])
        ));
        wp_redirect(admin_url('admin.php?page=ql-ai-settings&updated=1')); exit;
    }
}