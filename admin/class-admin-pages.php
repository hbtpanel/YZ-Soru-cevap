<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class QualityLife_Admin_Pages {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'setup_admin_menus' ] );
        add_action( 'admin_post_ql_save_global', [ $this, 'save_global_settings' ] );
        add_action( 'admin_post_ql_save_store', [ $this, 'save_store_settings' ] );
        add_action( 'admin_post_ql_save_knowledge', [ $this, 'save_knowledge' ] );
        add_action( 'admin_post_ql_update_store_prompt', [ $this, 'update_store_prompt' ] );
        add_action( 'admin_post_ql_update_knowledge', [ $this, 'update_knowledge' ] );
    }

    public function setup_admin_menus() {
        add_menu_page('YZ Eğitim ve Ayarlar', 'YZ Asistan', 'manage_options', 'ql-ai-settings', [ $this, 'page_settings' ], 'dashicons-robot', 30);
        add_submenu_page('ql-ai-settings', 'Bekleyen Sorular', 'Bekleyen Sorular', 'manage_options', 'ql-ai-questions', [ $this, 'page_questions' ]);
        add_submenu_page('ql-ai-settings', 'Soru Arşivi', 'Soru Arşivi (Bot)', 'manage_options', 'ql-ai-past-questions', [ $this, 'page_past_questions' ]);
       add_submenu_page('ql-ai-settings', 'Ürün Eğitimi (RAG)', '🧠 Ürün Eğitimi', 'manage_options', 'ql-ai-training', [$this, 'page_product_training']);
    }

    // --- SAYFA 1: AYARLAR ---
    // --- SAYFA 1: AYARLAR VE EĞİTİM ---
    public function page_settings() {
        global $wpdb;
        $stores = get_option('ql_trendyol_stores', []); 
        
        // Şifrelenmiş veriyi çekip, ekranda göstermek için çözüyoruz (Güvenlik zinciri)
        $encrypted_gemini = get_option('ql_gemini_api_key', '');
        $gemini_api_key = QualityLife_API_Services::decrypt_data($encrypted_gemini);
        
        $global_prompt = get_option('ql_gemini_system_prompt', 'Sen bir müşteri temsilcisisin.');
        $table_name = $wpdb->prefix . 'ql_product_knowledge';
        $products = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY id DESC LIMIT 50");
        ?>
        <style>
            /* --- QL YZ Modern SaaS UI CSS --- */
            :root {
                --ql-primary: #4f46e5;
                --ql-primary-hover: #4338ca;
                --ql-secondary: #0ea5e9;
                --ql-bg: #f8fafc;
                --ql-card: #ffffff;
                --ql-text: #1e293b;
                --ql-text-light: #64748b;
                --ql-border: #e2e8f0;
                --ql-success: #10b981;
            }
            .ql-wrap {
                margin: 20px 20px 0 0;
                font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
                color: var(--ql-text);
            }
            .ql-header {
                display: flex; justify-content: space-between; align-items: center;
                background: linear-gradient(135deg, var(--ql-primary), var(--ql-secondary));
                padding: 25px 35px; border-radius: 16px; color: white; 
                box-shadow: 0 10px 25px -5px rgba(79, 70, 229, 0.3);
                margin-bottom: 30px;
            }
            .ql-header h1 { color: white; margin: 0; font-size: 26px; font-weight: 700; letter-spacing: -0.5px; }
            .ql-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 25px; }
            .ql-card {
                background: var(--ql-card); border-radius: 16px; padding: 25px;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
                border: 1px solid var(--ql-border); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }
            .ql-card:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); }
            .ql-card h3 { margin-top: 0; border-bottom: 2px solid var(--ql-bg); padding-bottom: 12px; color: var(--ql-text); font-weight: 600; font-size: 18px; }
            .ql-input, .ql-textarea {
                width: 100%; padding: 12px 16px; border: 1px solid var(--ql-border); border-radius: 10px;
                margin-bottom: 16px; font-size: 14px; transition: all 0.2s;
                background: #fbfbfc; box-sizing: border-box; color: var(--ql-text);
            }
            .ql-input:focus, .ql-textarea:focus {
                border-color: var(--ql-primary); box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.15); outline: none; background: #fff;
            }
            .ql-btn {
                background: var(--ql-primary); color: white; border: none; padding: 12px 24px;
                border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 14px;
                transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            }
            .ql-btn:hover { background: var(--ql-primary-hover); transform: translateY(-1px); }
            .ql-btn:active { transform: translateY(1px); }
            .ql-btn-secondary { background: var(--ql-bg); color: var(--ql-text); border: 1px solid var(--ql-border); }
            .ql-btn-secondary:hover { background: #e2e8f0; }
            .ql-list-item {
                display: flex; justify-content: space-between; align-items: center;
                padding: 12px 16px; background: var(--ql-bg); border-radius: 10px; margin-bottom: 10px;
                border: 1px solid var(--ql-border); transition: border-color 0.2s;
            }
            .ql-list-item:hover { border-color: var(--ql-primary); }
            .ql-badge { background: #e0e7ff; color: var(--ql-primary); padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; }
            
            /* --- POPUP (MODAL) SİSTEMİ CSS --- */
            .ql-modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.7); z-index: 99999; backdrop-filter: blur(5px); align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s ease; }
            .ql-modal-overlay.active { display: flex; opacity: 1; }
            .ql-modal { background: #fff; width: 700px; max-width: 95%; border-radius: 20px; padding: 35px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.3); transform: translateY(30px) scale(0.95); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); position: relative; border: 1px solid rgba(255,255,255,0.2); }
            .ql-modal-overlay.active .ql-modal { transform: translateY(0) scale(1); }
            .ql-modal-close { position: absolute; top: 20px; right: 25px; background: #f1f5f9; border: none; width: 32px; height: 32px; border-radius: 50%; font-size: 16px; cursor: pointer; color: var(--ql-text-light); transition: all 0.2s; display:flex; align-items:center; justify-content:center; }
            .ql-modal-close:hover { background: #ef4444; color: #fff; transform: rotate(90deg); }
            .ql-modal textarea { min-height: 250px; font-size: 15px; line-height: 1.6; padding: 20px; resize: vertical; }
        </style>

        <div class="ql-wrap">
            <div class="ql-header">
                <div>
                    <h1>✨ YZ Asistan Kontrol Merkezi</h1>
                    <div style="font-size: 14px; opacity: 0.9; margin-top: 5px;">Müşteri ilişkilerinizi yöneten akıllı beyin.</div>
                </div>
                <div style="background: rgba(255,255,255,0.2); padding: 8px 16px; border-radius: 20px; font-weight: 600; backdrop-filter: blur(10px);">
                    🚀 Sürüm 2.0 Turbo Mod Aktif
                </div>
            </div>

            <?php if(isset($_GET['updated'])) echo '<div class="notice notice-success is-dismissible" style="border-radius: 10px; border-left: 4px solid var(--ql-success); padding: 12px; background: #fff;"><p style="margin:0; font-size: 14px;"><strong>Harika!</strong> Ayarlar başarıyla güncellendi ve AES-256 ile şifrelenerek güvene alındı. 🔒</p></div>'; ?>

            <div class="ql-grid" style="margin-top: 25px;">
                <div class="ql-card">
                    <h3>🔐 1. Yapay Zeka (Gemini) Bağlantısı</h3>
                    <p style="color: var(--ql-text-light); font-size: 13px; margin-bottom: 15px; line-height: 1.5;">Bu bilgiler Google sunucularıyla konuşmanızı sağlar. Veritabanında üst düzey kriptografi ile korunmaktadır.</p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="ql_save_global">
                        <?php wp_nonce_field('ql_global_nonce'); ?>
                        
                        <label style="font-weight: 600; display: block; margin-bottom: 6px; font-size: 13px;">Gemini API Anahtarı:</label>
                        <input type="password" name="gemini_api_key" class="ql-input" value="<?php echo esc_attr($gemini_api_key); ?>" placeholder="AIizaSy...">
                        
                        <button type="submit" class="ql-btn">💾 Güvenli Kaydet</button>
                    </form>
                </div>

                <div class="ql-card">
                    <h3>🏪 2. Trendyol Mağaza Entegrasyonu</h3>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom: 25px;">
                        <input type="hidden" name="action" value="ql_save_store">
                        <?php wp_nonce_field('ql_store_nonce'); ?>
                        
                        <input type="text" name="store_name" class="ql-input" required placeholder="Mağaza Adı (Örn: QL Merkez)">
                        <div style="display: flex; gap: 15px;">
                            <input type="text" name="seller_id" class="ql-input" required placeholder="Satıcı ID">
                            <input type="text" name="api_key" class="ql-input" required placeholder="Trendyol API Key">
                        </div>
                       <input type="password" name="api_secret" class="ql-input" required placeholder="Trendyol API Secret (Gizli)">
<label style="font-weight: 600; display: block; margin-bottom: 6px; font-size: 13px;">Mağaza Marka Dili ve Kuralları:</label>
<textarea name="store_prompt" class="ql-textarea" rows="3" placeholder="Örn: 'Merhabalar efendim' diye başla. Aras kargo ile çalışıyoruz. Bizi tercih ettiğiniz için teşekkür ederiz diye bitir." required></textarea>
                        
                        <button type="submit" class="ql-btn ql-btn-secondary" style="width: 100%;">➕ Mağazayı Sisteme Ekle</button>
                    </form>

                   <h4 style="margin: 0 0 10px 0; color: var(--ql-text); font-size: 15px;">Bağlı Mağazalar ve Marka Dilleri</h4>
                    <div style="max-height: 400px; overflow-y: auto; padding-right: 5px;">
                        <?php if(empty($stores)) echo '<p style="font-size:13px; color:var(--ql-text-light);">Henüz mağaza eklenmedi.</p>'; ?>
                        <?php foreach($stores as $id => $s): ?>
                            <div class="ql-list-item" style="flex-direction: column; align-items: stretch; gap: 10px; background: #fff; border-left: 4px solid var(--ql-secondary);">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong style="display: block; font-size: 14px; color: var(--ql-primary);"><?php echo esc_html($s['name']); ?></strong>
                                        <span class="ql-badge" style="margin-top: 4px; display: inline-block; background: #f1f5f9; color: var(--ql-text-light);">Kimlik: <?php echo esc_attr($id); ?></span>
                                    </div>
                                    <button type="button" class="ql-btn ql-btn-secondary btn-test-store" data-id="<?php echo esc_attr($id); ?>" style="padding: 6px 12px; font-size: 11px; border-radius: 6px;">🔌 Test Et</button>
                                </div>
                                
                                <div style="margin-top: 10px; padding-top: 15px; border-top: 1px dashed var(--ql-border); display: flex; justify-content: flex-end;">
                                    <button type="button" class="ql-btn btn-open-modal" data-id="<?php echo esc_attr($id); ?>" data-name="<?php echo esc_attr($s['name']); ?>" data-prompt="<?php echo isset($s['prompt']) ? esc_attr($s['prompt']) : ''; ?>" style="padding: 8px 18px; font-size: 13px; border-radius: 8px; background: var(--ql-secondary);">
                                        <span style="margin-right: 8px; font-size: 16px;">✍️</span> Marka Dilini Düzenle
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="ql-card" style="grid-column: 1 / -1;">
                    <h3>📚 3. Manuel Ürün Bilgisi (RAG Beslemesi)</h3>
                    <p style="color: var(--ql-text-light); font-size: 14px; margin-bottom: 20px;">Özel detay gerektiren ürünler için (Örn: "Bu ürün paraben içermez") yapay zekaya manuel talimat verin. Sistem vektör aramasıyla bulamadığı kısımları buradan çekecektir.</p>
                    
                    <div style="display: flex; gap: 30px; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 300px;">
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <input type="hidden" name="action" value="ql_save_knowledge">
                                <?php wp_nonce_field('ql_knowledge_nonce'); ?>
                                
                                <label style="font-weight: 600; display: block; margin-bottom: 6px; font-size: 13px;">Ürün Model Kodu / Barkod:</label>
                                <input type="text" name="barcode" class="ql-input" required placeholder="Örn: QL-GOZ-001">
                                
                                <label style="font-weight: 600; display: block; margin-bottom: 6px; font-size: 13px;">YZ İçin Özel Talimat:</label>
                                <textarea name="product_info" class="ql-textarea" rows="4" required placeholder="Bu ürün tuz içermez, keratin bakımı sonrasında güvenle kullanılabilir..."></textarea>
                                
                                <button type="submit" class="ql-btn">🧠 Bilgiyi Beyne Yaz</button>
                            </form>
                        </div>
                        
                        <div style="flex: 1; min-width: 300px; background: var(--ql-bg); border-radius: 12px; border: 1px solid var(--ql-border); overflow: hidden;">
                           <table class="wp-list-table widefat fixed striped" style="border: none; box-shadow: none; margin: 0;">
                               <thead>
                                   <tr>
                                       <th style="width: 25%; font-weight: 600; padding: 15px;">Model Kodu</th>
                                       <th style="width: 55%; font-weight: 600; padding: 15px;">Öğrenilmiş Kural</th>
                                       <th style="width: 20%; font-weight: 600; padding: 15px; text-align: right;">İşlem</th>
                                   </tr>
                               </thead>
                                <tbody>
                                    <?php if(empty($products)) echo '<tr><td colspan="3" style="text-align:center; padding: 20px; color: var(--ql-text-light);">Henüz özel ürün bilgisi girilmedi.</td></tr>'; ?>
                                    <?php foreach($products as $p): ?>
                                        <tr>
                                            <td style="padding: 12px 15px;"><strong style="color: var(--ql-primary);"><?php echo esc_html($p->barcode); ?></strong></td>
                                            <td style="padding: 12px 15px; font-size: 13px; color: var(--ql-text);"><?php echo esc_html($p->product_info); ?></td>
                                            <td style="padding: 12px 15px; text-align: right;">
                                                <button type="button" class="ql-btn ql-btn-secondary btn-edit-knowledge" style="padding: 6px 12px; font-size: 11px; border-radius: 6px;" data-id="<?php echo esc_attr($p->id); ?>" data-barcode="<?php echo esc_attr($p->barcode); ?>" data-info="<?php echo esc_attr($p->product_info); ?>">✏️ Düzenle</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
       <div class="ql-modal-overlay" id="qlPromptModal">
                <div class="ql-modal">
                    <button class="ql-modal-close" onclick="closePromptModal()">✖</button>
                    <h2 style="margin: 0 0 5px 0; color: var(--ql-primary); font-size: 24px; font-weight: 800; display:flex; align-items:center; gap:10px;">
                        <span>🧠</span> Mağaza Dilini Eğit
                    </h2>
                    <p style="color: var(--ql-text-light); font-size: 14px; margin-bottom: 25px; line-height: 1.5;">
                        <strong id="modalStoreName" style="color: var(--ql-text); font-size: 15px;"></strong> mağazası için yapay zekanın kullanacağı karşılama, kapanış, kargo ve üslup kurallarını belirleyin.
                    </p>
                    
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="ql_update_store_prompt">
                        <?php wp_nonce_field('ql_store_prompt_nonce'); ?>
                        <input type="hidden" name="store_id" id="modalStoreId" value="">
                        
                        <textarea name="store_prompt" id="modalStorePrompt" class="ql-textarea" placeholder="Örn:&#10;- Merhabalar efendim diye başla.&#10;- Müşteriye daima 'Siz' diye hitap et.&#10;- Sürat Kargo ile gönderim yapıyoruz.&#10;- Bizi tercih ettiğiniz için teşekkür ederiz diyerek bitir."></textarea>
                        
                        <div style="display: flex; justify-content: flex-end; gap: 15px; margin-top: 10px;">
                            <button type="button" class="ql-btn ql-btn-secondary" onclick="closePromptModal()" style="padding: 12px 25px;">İptal Et</button>
                            <button type="submit" class="ql-btn" style="padding: 12px 30px; font-size: 15px;">💾 Yeni Dili Beyne Yaz</button>
                        </div>
            </form>
                </div>
            </div>
            <div class="ql-modal-overlay" id="qlKnowledgeModal">
                <div class="ql-modal" style="max-width: 500px;">
                    <button class="ql-modal-close" onclick="closeKnowledgeModal()">✖</button>
                    <h2 style="margin: 0 0 15px 0; color: var(--ql-primary); font-size: 22px; font-weight: 800; display:flex; align-items:center; gap:10px;">
                        <span>📚</span> Bilgiyi Güncelle
                    </h2>
                    
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="ql_update_knowledge">
                        <?php wp_nonce_field('ql_knowledge_update_nonce'); ?>
                        <input type="hidden" name="k_id" id="modalKId" value="">
                        
                        <label style="font-weight: 600; display: block; margin-bottom: 6px; font-size: 13px;">Model Kodu / Barkod:</label>
                        <input type="text" name="k_barcode" id="modalKBarcode" class="ql-input" required>
                        
                        <label style="font-weight: 600; display: block; margin-bottom: 6px; font-size: 13px;">YZ İçin Özel Talimat:</label>
                        <textarea name="k_info" id="modalKInfo" class="ql-textarea" rows="4" required style="min-height: 120px;"></textarea>
                        
                        <div style="display: flex; justify-content: flex-end; gap: 15px; margin-top: 10px;">
                            <button type="button" class="ql-btn ql-btn-secondary" onclick="closeKnowledgeModal()" style="padding: 10px 20px;">İptal</button>
                            <button type="submit" class="ql-btn" style="padding: 10px 20px;">💾 Güncelle</button>
                        </div>
                    </form>
                </div>
            </div>
            </div>
        
        <script>
        // Popup Kapatma Fonksiyonu
        // Popup Kapatma Fonksiyonu
        function closePromptModal() {
            const overlay = document.getElementById('qlPromptModal');
            overlay.classList.remove('active');
            setTimeout(() => overlay.style.display = 'none', 300);
        }
        function closeKnowledgeModal() {
            const overlay = document.getElementById('qlKnowledgeModal');
            overlay.classList.remove('active');
            setTimeout(() => overlay.style.display = 'none', 300);
        }

        document.addEventListener('DOMContentLoaded', function() {
            
            // Popup Açma Tetikleyicisi
            document.querySelectorAll('.btn-open-modal').forEach(btn => {
                btn.addEventListener('click', function() {
                    // Verileri modalın içine doldur
                    document.getElementById('modalStoreId').value = this.dataset.id;
                    document.getElementById('modalStoreName').innerText = this.dataset.name;
                    document.getElementById('modalStorePrompt').value = this.dataset.prompt;
                    
                    // Modalı ekranda göster ve animasyonu tetikle
                    const overlay = document.getElementById('qlPromptModal');
                    overlay.style.display = 'flex';
                    setTimeout(() => overlay.classList.add('active'), 10);
                });
            });
            // Ürün Bilgisi Düzenleme Popup'ını Aç
            document.querySelectorAll('.btn-edit-knowledge').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.getElementById('modalKId').value = this.dataset.id;
                    document.getElementById('modalKBarcode').value = this.dataset.barcode;
                    document.getElementById('modalKInfo').value = this.dataset.info;
                    
                    const overlay = document.getElementById('qlKnowledgeModal');
                    overlay.style.display = 'flex';
                    setTimeout(() => overlay.classList.add('active'), 10);
                });
            });
            document.querySelectorAll('.btn-test-store').forEach(btn => {
                btn.addEventListener('click', async function() {
                    const id = this.dataset.id;
                    const originalText = this.innerHTML;
                    this.innerHTML = '⏳ Kontrol Ediliyor...'; this.disabled = true;
                    const fd = new FormData();
                    fd.append('action', 'ql_test_store'); fd.append('security', '<?php echo wp_create_nonce("ql_ajax_nonce"); ?>'); fd.append('store_id', id);
                    try {
                        const res = await fetch(ajaxurl, { method: 'POST', body: fd });
                        const data = await res.json();
                        alert(data.data && data.data.message ? data.data.message : 'Bağlantı sağlandı!');
                    } catch (e) { alert('Bağlantı hatası oluştu. Lütfen konsolu kontrol edin.'); }
                    this.innerHTML = originalText; this.disabled = false;
                });
            });
        });
        </script>
        <?php
    }

    // --- SAYFA 2: BEKLEYEN SORULAR (BİRLEŞİK PANEL) ---
    public function page_questions() {
        $stores = get_option('ql_trendyol_stores', []);
        
        // Varsayılan olarak 'all' (Tüm Mağazalar) seçili gelsin
        $selected_seller_id = isset($_GET['store_id']) ? sanitize_text_field($_GET['store_id']) : 'all';

        $all_questions = [];
        
        // Mağazaları döngüye al ve soruları topla
        foreach($stores as $sid => $s) {
            // Eğer bir mağaza filtresi varsa ve o anki mağaza o değilse atla
            if ($selected_seller_id !== 'all' && $selected_seller_id !== $sid) {
                continue;
            }
            
            $qs = QualityLife_API_Services::get_trendyol_questions($sid, $s['key'], $s['secret']);
            
            if (!empty($qs) && is_array($qs)) {
                foreach($qs as $q) {
                    // Her soruya ait olduğu mağaza bilgisini enjekte et
                    $q['ql_store_name'] = $s['name'];
                    $q['ql_store_id'] = $sid;
                    $all_questions[] = $q;
                }
            }
        }
        ?>
        <div class="wrap">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h1>📥 Bekleyen Trendyol Soruları</h1>
                <form method="get" action="">
                    <input type="hidden" name="page" value="ql-ai-questions">
                    <select name="store_id" onchange="this.form.submit()" style="padding: 5px; font-size: 14px; border-radius: 4px;">
                        <option value="all" <?php selected($selected_seller_id, 'all'); ?>>🌐 Tüm Mağazalar</option>
                        <?php foreach($stores as $id => $store): ?>
                            <option value="<?php echo esc_attr($id); ?>" <?php selected($selected_seller_id, $id); ?>>🏪 <?php echo esc_html($store['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <hr>

            <?php if(empty($all_questions)): ?>
                <div class="notice notice-info"><p>Şu an yanıtlanmayı bekleyen soru bulunmuyor.</p></div>
            <?php else: ?>
                
                <div style="margin: 20px 0; padding: 20px; background: #fff; border-left: 4px solid #6a1b9a; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04); display: flex; align-items: center; gap: 20px;">
                    <div>
                        <h3 style="margin: 0 0 5px 0;">🤖 Otomatik YZ Asistanı</h3>
                        <p style="margin: 0; color: #666;">Listedeki <strong><?php echo count($all_questions); ?></strong> sorunun tamamı için taslak cevapları hazırlatın.</p>
                    </div>
                    <button type="button" class="button button-primary button-large" id="btn-ask-all" style="background: #6a1b9a; border-color: #4a148c;">✨ Tümüne YZ Cevabı Üret</button>
                    <span id="ask-all-status" style="font-weight: bold; color: #6a1b9a;"></span>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr)); gap: 20px; margin-top: 20px;">
                    <?php foreach($all_questions as $q): 
                        $q_id = esc_attr($q['id']);
                        $text = esc_html($q['text']);
                        $product_name = esc_html($q['productName']);
                        $barcode = isset($q['productMainId']) ? esc_attr($q['productMainId']) : '';
                        $store_name = esc_html($q['ql_store_name']);
                        $store_id = esc_attr($q['ql_store_id']);
                    ?>
                    <div class="ql-card" style="background: #fff; padding: 15px; border: 1px solid #ccd0d4; border-radius: 6px; position: relative;">
                        <div style="position: absolute; top: 10px; right: 10px;">
                            <span style="background: #f0f6fc; color: #2271b1; padding: 3px 10px; border-radius: 15px; font-size: 10px; font-weight: bold; border: 1px solid #2271b1;">
                                🏪 <?php echo $store_name; ?>
                            </span>
                        </div>

                        <h4 style="margin: 0 0 5px 0; padding-right: 100px;"><?php echo $product_name; ?></h4>
                        <span style="background: #eee; padding: 2px 6px; border-radius: 3px; font-size: 11px;">Model: <?php echo $barcode; ?></span>
                        
                        <div style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 10px; margin: 10px 0;">
                            <strong>Soru:</strong> <?php echo $text; ?>
                        </div>

                        <textarea id="ans_<?php echo $q_id; ?>" rows="5" style="width: 100%; margin-bottom: 10px;" placeholder="Yapay zeka cevabı..."></textarea>
                        
                        <div style="display: flex; justify-content: space-between;">
                            <button type="button" class="button btn-ask" data-id="<?php echo $q_id; ?>" data-barcode="<?php echo $barcode; ?>" data-q="<?php echo esc_attr($text); ?>">✨ YZ Hazırla</button>
                            <button type="button" class="button button-primary btn-send" data-id="<?php echo $q_id; ?>" data-store="<?php echo $store_id; ?>">🚀 Gönder</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const nonce = '<?php echo wp_create_nonce("ql_ajax_nonce"); ?>';
            
            // TOPLU İŞLEM BOTU JS
            const btnAskAll = document.getElementById('btn-ask-all');
            if (btnAskAll) {
                btnAskAll.addEventListener('click', async function() {
                    const buttons = document.querySelectorAll('.btn-ask');
                    const statusLabel = document.getElementById('ask-all-status');
                    if(!confirm(buttons.length + ' soru için YZ cevabı üretilecek. Devam edilsin mi?')) return;
                    
                    this.disabled = true;
                    for(let i = 0; i < buttons.length; i++) {
                        const btn = buttons[i];
                        statusLabel.innerHTML = `⏳ ${i + 1} / ${buttons.length} hazırlanıyor...`;
                        btn.closest('.ql-card').scrollIntoView({behavior: "smooth", block: "center"});
                        
                        const id = btn.dataset.id;
                        const box = document.getElementById('ans_' + id);
                        if (box.value.trim() !== '') continue;

                        btn.disabled = true; btn.innerHTML = '⏳...';
                        const fd = new FormData();
                        fd.append('action', 'ql_ask_ai'); fd.append('security', nonce); fd.append('question', btn.dataset.q); fd.append('barcode', btn.dataset.barcode);
                        try {
                            const res = await fetch(ajaxurl, { method: 'POST', body: fd });
                            const data = await res.json();
                            box.value = data.success ? data.data.answer : 'Hata.';
                        } catch (e) { box.value = 'Hata.'; }
                        btn.disabled = false; btn.innerHTML = '✨ Hazırla';
                        if (i < buttons.length - 1) await new Promise(r => setTimeout(r, 4000));
                    }
                    statusLabel.innerHTML = '✅ Tüm taslaklar hazır.';
                    this.disabled = false;
                });
            }

            // TEKİL İŞLEMLER VE GÖNDERME LOGİC'İ (Aynı kalıyor)
            document.querySelectorAll('.btn-ask').forEach(btn => {
                btn.addEventListener('click', async function() {
                    const id = this.dataset.id; const box = document.getElementById('ans_' + id);
                    this.disabled = true; this.innerHTML = '⏳...';
                    const fd = new FormData();
                    fd.append('action', 'ql_ask_ai'); fd.append('security', nonce); fd.append('question', this.dataset.q); fd.append('barcode', this.dataset.barcode);
                    try {
                        const res = await fetch(ajaxurl, { method: 'POST', body: fd });
                        const data = await res.json();
                        box.value = data.success ? data.data.answer : 'Hata.';
                    } catch (e) { box.value = 'Hata.'; }
                    this.disabled = false; this.innerHTML = '✨ Yeniden Hazırla';
                });
            });

            document.querySelectorAll('.btn-send').forEach(btn => {
                btn.addEventListener('click', async function() {
                    const id = this.dataset.id; const store = this.dataset.store;
                    const answer = document.getElementById('ans_' + id).value.trim();
                    if(!answer) return alert('Cevap boş olamaz!');
                    this.disabled = true; this.innerHTML = '🚀...';
                    const fd = new FormData();
                    fd.append('action', 'ql_send_answer'); fd.append('security', nonce); fd.append('q_id', id); fd.append('answer', answer); fd.append('store_id', store);
                    try {
                        const res = await fetch(ajaxurl, { method: 'POST', body: fd });
                        const data = await res.json();
                        if(data.success) { this.closest('.ql-card').style.opacity = '0.3'; this.innerHTML = '✅ Gönderildi'; }
                        else alert('Hata oluştu.');
                    } catch (e) { alert('Bağlantı hatası.'); }
                });
            });
        });
        </script>
        <?php
    }

   // --- YENİ SAYFA: ÜRÜN EĞİTİM MERKEZİ (RAG) ---
    public function page_product_training() {
        $stores = get_option('ql_trendyol_stores', []);
        ?>
        <div class="wrap ql-ai-wrapper">
           <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
                <h1 style="margin:0;">🧠 Ürün Eğitim Merkezi (RAG)</h1>
                <button type="button" id="btn-sync-products" class="button button-primary" style="background: #2271b1;">🔄 Trendyol'dan Ürünleri Güncelle</button>
            </div>

            <div style="background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 20px; display: flex; gap: 10px;">
                <input type="text" id="ql-product-search" placeholder="Barkod veya Ürün Adı ile ara..." style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px;">
            </div>

            <div style="background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                <div style="display: grid; grid-template-columns: 1fr 2fr 1.5fr 1fr; background: #f8f9fa; padding: 15px; font-weight: bold; border-bottom: 1px solid #eee;">
                    <div>Model / Barkod</div>
                    <div>Ürün Adı</div>
                    <div>Öğrenilmiş Kural (RAG)</div>
                    <div style="text-align: right;">İşlem</div>
                </div>
                <div id="ql-product-items">
                    <div style="padding: 40px; text-align: center; color: #888;">Yükleniyor...</div>
                </div>
            </div>
        </div>

        <div id="ql-edit-modal" style="display:none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center;">
            <div style="background: #fff; width: 90%; max-width: 600px; padding: 25px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
                <h2 id="modal-title" style="margin-top:0;">Ürün Bilgisini Düzenle</h2>
                <p id="modal-subtitle" style="color: #666; font-size: 13px;"></p>
                <textarea id="modal-info-text" rows="8" style="width: 100%; padding: 10px; border-radius: 5px; border: 1px solid #ddd; margin: 15px 0;" placeholder="Bu ürün hakkında yapay zekanın bilmesi gereken kuralları, içerikleri veya kullanım şeklini yazın..."></textarea>
                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="button" onclick="document.getElementById('ql-edit-modal').style.display='none'">İptal</button>
                    <button type="button" id="btn-save-product-info" class="button button-primary">Kaydet ve Eğit</button>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('ql-product-items');
            const searchInput = document.getElementById('ql-product-search');
            const nonce = '<?php echo wp_create_nonce("ql_ajax_nonce"); ?>';

            async function fetchProducts(search = '') {
                container.innerHTML = '<div style="padding:40px; text-align:center;">Yükleniyor...</div>';
                const fd = new FormData();
                fd.append('action', 'ql_fetch_training_products');
                fd.append('security', nonce);
                fd.append('search', search);

                try {
                    const res = await fetch(ajaxurl, { method: 'POST', body: fd });
                    const data = await res.json();
                    container.innerHTML = data.data.html;

                    document.querySelectorAll('.btn-edit-product').forEach(btn => {
                        btn.addEventListener('click', function() {
                            document.getElementById('modal-title').innerText = this.dataset.model;
                            document.getElementById('modal-subtitle').innerText = this.dataset.name;
                            document.getElementById('modal-info-text').value = this.dataset.info || '';
                            document.getElementById('btn-save-product-info').dataset.barcode = this.dataset.model;
                            document.getElementById('ql-edit-modal').style.display = 'flex';
                        });
                    });
                } catch(e) { container.innerHTML = 'Hata oluştu.'; }
            }

            let timeout = null;
            searchInput.addEventListener('keyup', () => {
                clearTimeout(timeout);
                timeout = setTimeout(() => fetchProducts(searchInput.value), 500);
            });

            document.getElementById('btn-save-product-info').addEventListener('click', async function() {
                const btn = this;
                const barcode = btn.dataset.barcode;
                const info = document.getElementById('modal-info-text').value;

                btn.disabled = true; btn.innerText = 'Kaydediliyor...';
                
                const fd = new FormData();
                fd.append('action', 'ql_save_product_rule');
                fd.append('security', nonce);
                fd.append('barcode', barcode);
                fd.append('info', info);

                await fetch(ajaxurl, { method: 'POST', body: fd });
                
                btn.disabled = false; btn.innerText = 'Kaydet ve Eğit';
                document.getElementById('ql-edit-modal').style.display = 'none';
                fetchProducts(searchInput.value); 
            });

           fetchProducts(); // Sayfa açılınca ilk yükleme

            // YENİ: TRENDYOL SENKRONİZASYON BUTONU İŞLEVİ
            const btnSync = document.getElementById('btn-sync-products');
            if(btnSync) {
                btnSync.addEventListener('click', async function() {
                    if(!confirm('Tüm mağazalardaki ürünleriniz çekilecek. Bu işlem ürün sayınıza göre biraz sürebilir. Başlayalım mı?')) return;
                    
                    this.disabled = true;
                    this.innerText = '🔄 Senkronize Ediliyor...';
                    
                    const fd = new FormData();
                    fd.append('action', 'ql_sync_trendyol_products');
                    fd.append('security', nonce);

                    try {
                        const res = await fetch(ajaxurl, { method: 'POST', body: fd });
                       const data = await res.json();
                        if (data.success) {
                            alert(data.data);
                        } else {
                            alert('Hata Yakalandı:\n' + data.data);
                        }
                        fetchProducts(searchInput.value); // İşlem bitince listeyi arka planda yenile
                    } catch (e) { alert('Hata oluştu.'); }
                    
                    this.disabled = false;
                    this.innerText = '🔄 Trendyol\'dan Ürünleri Güncelle';
                });
            }
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
        <style>
            :root { --ql-primary: #4f46e5; --ql-secondary: #0ea5e9; --ql-bg: #f8fafc; --ql-card: #ffffff; --ql-text: #1e293b; --ql-text-light: #64748b; --ql-border: #e2e8f0; }
            .ql-wrap { margin: 20px 20px 0 0; font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: var(--ql-text); }
            .ql-header { display: flex; justify-content: space-between; align-items: center; background: linear-gradient(135deg, #6a1b9a, #4a148c); padding: 25px 35px; border-radius: 16px; color: white; box-shadow: 0 10px 25px -5px rgba(106, 27, 154, 0.3); margin-bottom: 30px; }
            .ql-header h1 { color: white; margin: 0; font-size: 26px; font-weight: 700; }
            .ql-card { background: var(--ql-card); border-radius: 16px; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); border: 1px solid var(--ql-border); transition: all 0.3s; }
            .ql-input, .ql-select { padding: 10px 15px; border: 1px solid var(--ql-border); border-radius: 8px; font-size: 14px; background: #fff; color: var(--ql-text); outline: none; }
            .ql-input:focus { border-color: #6a1b9a; }
            .ql-btn { background: var(--ql-primary); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 13px; transition: all 0.2s; }
            .ql-btn:hover { filter: brightness(1.1); transform: translateY(-1px); }
            .ql-btn-bot { background: #fff; color: #4a148c; border: 1px solid #4a148c; }
            .ql-btn-bot:hover { background: #f3e8fd; }
            .ql-btn-vector { background: linear-gradient(135deg, #6a1b9a, #8e24aa); color: white; border: none; font-size: 15px; padding: 12px 25px; box-shadow: 0 4px 15px rgba(106,27,154,0.3); }
        </style>

        <div class="ql-wrap">
            <div class="ql-header">
                <div>
                    <h1>🧠 Soru Arşivi ve Veri Madenciliği</h1>
                    <div style="font-size: 14px; opacity: 0.9; margin-top: 5px;">Geçmiş soruları çekin ve yapay zekanın beynine kazıyın.</div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 24px; font-weight: 800;"><?php echo number_format($total_items); ?></div>
                    <div style="font-size: 12px; opacity: 0.8; text-transform: uppercase; letter-spacing: 1px;">Toplam Soru</div>
                </div>
            </div>
            
            <div style="display: flex; gap: 25px; margin-bottom: 30px; flex-wrap: wrap;">
                <div class="ql-card" style="flex: 2; border-left: 4px solid #0ea5e9;">
                    <h3 style="margin-top:0; font-size: 16px; display: flex; align-items: center; gap: 8px;">🤖 1. Veri Çekme Botu (Trendyol'dan Arşive)</h3>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-bottom: 10px;">
                        <select id="fetch_store" class="ql-select">
                            <?php foreach($stores as $id => $st) echo "<option value='".esc_attr($id)."'>".esc_html($st['name'])."</option>"; ?>
                        </select>
                        <button class="ql-btn ql-btn-bot" onclick="startFetch(1)">Son 1 Gün</button>
                        <button class="ql-btn ql-btn-bot" onclick="startFetch(7)">Son 1 Hafta</button>
                        <button class="ql-btn ql-btn-bot" onclick="startFetch(30)">Son 1 Ay</button>
                        <button class="ql-btn ql-btn-bot" style="background: #0ea5e9; color: white; border:none;" onclick="startFetch('all')">Tüm Zamanlar</button>
                    </div>
                    <div id="fetch_status" style="font-weight: 600; color: #0ea5e9; font-size: 14px; padding-top: 10px; border-top: 1px dashed var(--ql-border);">Bekliyor...</div>
                </div>

                <div class="ql-card" style="flex: 1; border-left: 4px solid #6a1b9a; background: #faf5ff;">
                    <h3 style="margin-top:0; font-size: 16px; color: #4a148c; display: flex; align-items: center; gap: 8px;">⚡ 2. YZ Vektör Eğitimi</h3>
                    <button class="ql-btn ql-btn-vector" onclick="startVectorization()" style="width: 100%; margin-bottom: 15px;">🧠 Arşivi İndeksle (Turbo)</button>
                    <div id="vector_status" style="font-weight: 600; color: #6a1b9a; font-size: 14px; text-align: center;">
                        <?php 
                        $remaining_vectors = $wpdb->get_var("SELECT COUNT(id) FROM $table WHERE vector_data IS NULL");
                        if($remaining_vectors > 0) echo "İndeks bekleyen <strong style='font-size:18px;'>$remaining_vectors</strong> soru var.";
                        else echo "✅ Tüm arşiv beynine yazıldı.";
                        ?>
                    </div>
                </div>
            </div>

            <div class="ql-card" style="margin-bottom: 25px; padding: 15px 25px;">
                <form method="get" style="display: flex; gap: 15px; align-items: center;">
                    <input type="hidden" name="page" value="ql-ai-past-questions">
                    <span style="font-size: 20px;">🔍</span>
                    <input type="text" name="s_term" class="ql-input" value="<?php echo esc_attr($s_term); ?>" placeholder="Ürün adı, model, soru veya cevap içinde ara..." style="flex: 1; border: none; background: #f8fafc; font-size: 15px;">
                    <button type="submit" class="ql-btn" style="background: #1e293b;">Ara</button>
                    <a href="?page=ql-ai-past-questions" class="ql-btn ql-btn-bot" style="text-decoration: none;">Temizle</a>
                </form>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr)); gap: 20px;">
                <?php if($past_questions): foreach($past_questions as $q): ?>
                   <div class="ql-card" style="display: flex; flex-direction: column;">
                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 11px; color: #999; margin-bottom: 10px; border-bottom: 1px solid var(--ql-border); padding-bottom: 8px;">
                            <span>📅 <?php echo date('d.m.Y H:i', strtotime($q->created_date)); ?> | 📦 <?php echo esc_html($q->model_code); ?></span>
                            <?php if(!empty($q->vector_data)): ?>
                                <span style="background: #dcfce7; color: #166534; padding: 4px 8px; border-radius: 6px; font-weight: 600;">🧠 İndekslendi</span>
                            <?php else: ?>
                                <span style="background: #fee2e2; color: #991b1b; padding: 4px 8px; border-radius: 6px; font-weight: 600;">⏳ İndeks Bekliyor</span>
                            <?php endif; ?>
                        </div>
                        <strong style="color: var(--ql-primary); font-size: 15px; margin-bottom: 10px;"><?php echo esc_html($q->product_name); ?></strong>
                        
                        <div style="background: #f8fafc; padding: 12px; border-radius: 8px; margin-bottom: 10px; font-size: 14px; border-left: 3px solid #cbd5e1;">
                            <strong style="color: #475569;">S:</strong> <?php echo esc_html($q->question_text); ?>
                        </div>
                        <div style="background: #f0fdf4; padding: 12px; border-radius: 8px; font-size: 13px; color: #166534; margin-bottom: 15px; flex-grow: 1;">
                            <strong>C:</strong> <?php echo esc_html($q->answer_text); ?>
                        </div>
                        
                       <button type="button" class="ql-btn btn-test-ai" style="width: 100%; background: #fff; color: #4f46e5; border: 1px solid #4f46e5;" data-id="<?php echo esc_attr($q->id); ?>" data-store="<?php echo esc_attr($q->store_id); ?>" data-barcode="<?php echo esc_attr($q->model_code); ?>" data-q="<?php echo esc_attr($q->question_text); ?>">✨ Bu Soruya YZ Ne Derdi? (Test)</button>
                        <div id="test_res_<?php echo esc_attr($q->id); ?>" style="display:none; margin-top:10px; background:#eef2ff; color:#3730a3; padding:12px; border-radius:8px; font-size: 13px; line-height: 1.5; border: 1px solid #c7d2fe;"></div>
                    </div>
                <?php endforeach; else: ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 50px; color: var(--ql-text-light); background: #fff; border-radius: 16px; border: 1px dashed #cbd5e1;">Aradığınız kriterlere uygun soru bulunamadı.</div>
                <?php endif; ?>
            </div>

            <?php if($total_pages > 1): ?>
            <div style="margin-top: 40px; display: flex; justify-content: center; gap: 15px; align-items: center; background: #fff; padding: 15px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                <button type="button" class="ql-btn ql-btn-bot" onclick="changePage(<?php echo max(1, $current_page - 1); ?>)" <?php if($current_page <= 1) echo 'disabled'; ?>>« Önceki</button>
                <span style="font-weight: 600; color: var(--ql-primary); font-size: 15px;">Sayfa <?php echo $current_page; ?> / <?php echo $total_pages; ?></span>
                <button type="button" class="ql-btn ql-btn-bot" onclick="changePage(<?php echo min($total_pages, $current_page + 1); ?>)" <?php if($current_page >= $total_pages) echo 'disabled'; ?>>Sonraki »</button>
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
            status.innerHTML = '🤖 Bot çalışıyor... Lütfen bekleyin.';

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
                    status.innerHTML = `⏳ Tarayıcı: [${chunk.label}] | Sayfa ${currentPage + 1}... (Yeni Bulunan: <strong style="color:#f59e0b;">${totalInserted}</strong>)`;
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
                    } catch(e) { status.innerHTML = '❌ Ağ bağlantısı koptu.'; return; }
                }
                if (days === 'all' && !hasDataInChunk && c > 1) break;
            }
            status.innerHTML = `✅ Operasyon Tamamlandı! Toplam <strong>${totalInserted}</strong> yeni soru çekildi. Sayfa yenileniyor...`;
            setTimeout(() => location.reload(), 2000);
        }

        async function startVectorization() {
            const status = document.getElementById('vector_status');
            status.innerHTML = '🚀 Turbo İndeksleme Başladı... Çıkış yapmayın.';
            
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
                        status.innerHTML = '🎉 Bitti! Tüm arşiv yapay zeka beynine başarıyla yazıldı.';
                        break;
                    }

                    status.innerHTML = `⚙️ Yapay Zeka Düşünüyor... Kalan Soru: <strong style="font-size:18px;">${data.data.remaining}</strong>`;
                    // Turbo Mod: 500ms bekleme
                    await new Promise(r => setTimeout(r, 500));
                } catch(e) {
                    status.innerHTML = '❌ Bağlantı koptu. İndekslemeye tekrar basın.';
                    break;
                }
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.btn-test-ai').forEach(btn => {
                btn.addEventListener('click', async function() {
                    const id = this.dataset.id; const resDiv = document.getElementById('test_res_' + id);
                    this.disabled = true; this.innerHTML = '⏳ Zihin Okunuyor...'; 
                    resDiv.style.display = 'block'; resDiv.innerHTML = 'Kosinüs benzerliği hesaplanıp cevap üretiliyor...';
                    const fd = new FormData();
                    fd.append('action', 'ql_ask_ai'); fd.append('security', '<?php echo wp_create_nonce("ql_ajax_nonce"); ?>');
                    fd.append('question', this.dataset.q); fd.append('barcode', this.dataset.barcode);
                    fd.append('store_id', this.dataset.store); // YENİ: Mağaza kimliğini gönderiyoruz!
                    try {
                        const res = await fetch(ajaxurl, { method: 'POST', body: fd });
                        const data = await res.json();
                        resDiv.innerHTML = '<strong style="color:#3730a3;">🤖 YZ Der ki:</strong><br><br>' + (data.success ? data.data.answer.replace(/\n/g, '<br>') : 'Cevap üretilemedi.');
                    } catch(e) { resDiv.innerHTML = 'Sunucuyla iletişim kurulamadı.'; }
                    this.disabled = false; this.innerHTML = '✨ Testi Tekrarla';
                });
            });
        });
        </script>
        <?php
    }

    // --- FORM İŞLEMCİLERİ ---
    // --- FORM İŞLEMCİLERİ ---
    public function save_global_settings() {
        if(!check_admin_referer('ql_global_nonce')) return;
        
        // Gemini API'yi şifreleyerek kaydediyoruz!
        $encrypted_gemini = QualityLife_API_Services::encrypt_data(sanitize_text_field($_POST['gemini_api_key']));
        update_option('ql_gemini_api_key', $encrypted_gemini);
        
        update_option('ql_gemini_system_prompt', sanitize_textarea_field($_POST['global_prompt']));
        wp_redirect(admin_url('admin.php?page=ql-ai-settings&updated=1')); exit;
    }

    public function save_store_settings() {
        if(!check_admin_referer('ql_store_nonce')) return;
        $stores = get_option('ql_trendyol_stores', []);
        
        // Trendyol API Secret'ı şifreleyerek kaydediyoruz!
        $encrypted_secret = QualityLife_API_Services::encrypt_data(sanitize_text_field($_POST['api_secret']));
        
        $stores[sanitize_text_field($_POST['seller_id'])] = [
            'name' => sanitize_text_field($_POST['store_name']),
            'key' => sanitize_text_field($_POST['api_key']),
            'secret' => $encrypted_secret,
            'prompt' => sanitize_textarea_field($_POST['store_prompt']) // YENİ: Mağazaya özel dil
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
    public function update_store_prompt() {
        if(!check_admin_referer('ql_store_prompt_nonce')) return;
        
        $stores = get_option('ql_trendyol_stores', []);
        $store_id = sanitize_text_field($_POST['store_id']);
        
        // Sadece ilgili mağazanın 'prompt' (Dil) verisini güncelliyoruz, şifrelere dokunmuyoruz!
        if(isset($stores[$store_id])) {
            $stores[$store_id]['prompt'] = sanitize_textarea_field($_POST['store_prompt']);
            update_option('ql_trendyol_stores', $stores);
        }
        
        wp_redirect(admin_url('admin.php?page=ql-ai-settings&updated=1')); exit;
    }
    // --- Manuel Ürün Bilgisi (RAG) Güncelleme Form İşleyicisi ---
    public function update_knowledge() {
        if(!check_admin_referer('ql_knowledge_update_nonce')) return;
        global $wpdb;
        $table_name = $wpdb->prefix . 'ql_product_knowledge';
        
        $id = intval($_POST['k_id']);
        $barcode = sanitize_text_field($_POST['k_barcode']);
        $info = sanitize_textarea_field($_POST['k_info']);
        
        if($id > 0 && !empty($barcode) && !empty($info)) {
            $wpdb->update(
                $table_name,
                ['barcode' => $barcode, 'product_info' => $info],
                ['id' => $id]
            );
        }
        
        wp_redirect(admin_url('admin.php?page=ql-ai-settings&updated=1')); exit;
    }
}