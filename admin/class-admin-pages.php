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
        
        // DÜZELTME: Sınıf içi çağrım olduğu için $this kullanıldı
        add_submenu_page('ql-ai-settings', 'Maliyet Raporu', '💸 Maliyet Raporu', 'manage_options', 'ql-ai-costs', [$this, 'page_costs']);
    }

  // --- SAYFA 1: AYARLAR ---
    public function page_settings() {
        $stores = get_option('ql_trendyol_stores', []); 
        $encrypted_gemini = get_option('ql_gemini_api_key', '');
        $gemini_api_key = QualityLife_API_Services::decrypt_data($encrypted_gemini);
        ?>
        <style>
            /* Modern SaaS UI CSS & Mobil Uyumluluk */
            :root { --ql-primary: #4f46e5; --ql-primary-hover: #4338ca; --ql-bg: #f8fafc; --ql-card: #ffffff; --ql-text: #1e293b; --ql-text-light: #64748b; --ql-border: #e2e8f0; --ql-success: #10b981; }
            .ql-wrap { max-width: 1200px; margin: 20px auto 0; padding: 0 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: var(--ql-text); box-sizing: border-box; }
            .ql-wrap * { box-sizing: border-box; }
            .ql-header { background: linear-gradient(135deg, #0f172a, #1e293b); border-radius: 16px; padding: 30px 40px; color: #fff; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.15); margin-bottom: 30px; flex-wrap: wrap; gap: 15px; }
            .ql-header h1 { color: white; margin: 0; font-size: 24px; font-weight: 700; }
            
            /* Tabs (Sekmeler) */
            .ql-tabs { display: flex; gap: 5px; margin-bottom: 25px; border-bottom: 2px solid var(--ql-border); overflow-x: auto; padding-bottom: 0; scrollbar-width: none; }
            .ql-tabs::-webkit-scrollbar { display: none; }
            .ql-tab-btn { background: transparent; border: none; padding: 15px 25px; font-size: 15px; font-weight: 600; color: var(--ql-text-light); cursor: pointer; border-bottom: 3px solid transparent; margin-bottom: -2px; transition: 0.3s; white-space: nowrap; }
            .ql-tab-btn:hover { color: var(--ql-primary); background: rgba(79,70,229,0.05); border-top-left-radius: 8px; border-top-right-radius: 8px; }
            .ql-tab-btn.active { color: var(--ql-primary); border-bottom-color: var(--ql-primary); }
            .ql-tab-content { display: none; animation: qlFadeIn 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
            .ql-tab-content.active { display: block; }
            @keyframes qlFadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

            /* Cards & Inputs */
            .ql-card { background: var(--ql-card); border-radius: 16px; padding: 30px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); border: 1px solid var(--ql-border); margin-bottom: 25px; }
            .ql-card h3 { margin-top: 0; border-bottom: 1px solid var(--ql-bg); padding-bottom: 15px; color: var(--ql-text); font-weight: 600; font-size: 18px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
            .ql-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
            .ql-input, .ql-textarea { width: 100%; padding: 12px 16px; border: 1px solid var(--ql-border); border-radius: 10px; margin-bottom: 16px; font-size: 14px; background: #fbfbfc; color: var(--ql-text); transition: 0.2s; }
            .ql-input:focus, .ql-textarea:focus { border-color: var(--ql-primary); box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1); outline: none; background: #fff; }
            label { font-weight: 600; display: block; margin-bottom: 8px; font-size: 13px; color: var(--ql-text); }
            
            /* Buttons */
            .ql-btn { background: var(--ql-primary); color: white; border: none; padding: 12px 24px; border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 14px; transition: 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
            .ql-btn:hover { background: var(--ql-primary-hover); transform: translateY(-1px); }
            .ql-btn-secondary { background: var(--ql-bg); color: var(--ql-text); border: 1px solid var(--ql-border); }
            .ql-btn-secondary:hover { background: #e2e8f0; }

            /* Store List */
            .ql-store-item { background: #fff; border: 1px solid var(--ql-border); border-left: 4px solid var(--ql-primary); padding: 20px; border-radius: 12px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; transition: 0.2s; }
            .ql-store-item:hover { box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); border-color: #cbd5e1; }
            .ql-badge { background: #e0e7ff; color: var(--ql-primary); padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; }

            /* Modal */
            .ql-modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.7); z-index: 99999; backdrop-filter: blur(5px); align-items: center; justify-content: center; opacity: 0; transition: 0.3s; }
            .ql-modal-overlay.active { display: flex; opacity: 1; }
            .ql-modal { background: #fff; width: 600px; max-width: 95%; border-radius: 20px; padding: 35px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.3); transform: translateY(20px); transition: 0.3s; position: relative; }
            .ql-modal-overlay.active .ql-modal { transform: translateY(0); }
            .ql-modal-close { position: absolute; top: 20px; right: 25px; background: #f1f5f9; border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; transition: 0.2s; display:flex; align-items:center; justify-content:center; }
            .ql-modal-close:hover { background: #ef4444; color: #fff; transform: rotate(90deg); }
            .ql-modal-close { position: absolute; top: 20px; right: 25px; background: #f1f5f9; border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; transition: 0.2s; display:flex; align-items:center; justify-content:center; }
            .ql-modal-close:hover { background: #ef4444; color: #fff; transform: rotate(90deg); }
            /* Scrollable Store List */
            .ql-store-list-container { max-height: 550px; overflow-y: auto; padding-right: 8px; }
            .ql-store-list-container::-webkit-scrollbar { width: 6px; }
            .ql-store-list-container::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
            .ql-store-list-container::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
            .ql-store-list-container::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
            .ql-star-btn { cursor: pointer; font-size: 20px; transition: 0.2s; color: #cbd5e1; background: none; border: none; }
            .ql-star-btn.active { color: #fbbf24; transform: scale(1.2); }
            .ql-star-btn:hover { transform: scale(1.1); }
            /* Mobile Responsiveness (Sihirli Kısım) */
            @media (max-width: 768px) {
                .ql-header { flex-direction: column; text-align: center; padding: 25px 20px; }
                .ql-grid-2 { grid-template-columns: 1fr; gap: 0; }
                .ql-card { padding: 20px; }
                .ql-store-item { flex-direction: column; align-items: flex-start; }
                .ql-store-item > div { width: 100%; }
                .ql-store-actions { display: flex; width: 100%; gap: 10px; margin-top: 10px; border-top: 1px dashed var(--ql-border); padding-top: 15px; }
                .ql-store-actions button { flex: 1; justify-content: center; padding: 10px; }
                .ql-tabs { padding-bottom: 5px; }
            }
        </style>

        <div class="ql-wrap">
            <div class="ql-header">
                <div>
                    <h1>✨ YZ Asistan Yönetimi</h1>
                    <div style="font-size: 14px; opacity: 0.8; margin-top: 5px;">Modern e-ticaret için akıllı müşteri ilişkileri.</div>
                </div>
                <div style="background: rgba(255,255,255,0.1); padding: 8px 16px; border-radius: 20px; font-weight: 600; border: 1px solid rgba(255,255,255,0.2);">
                    🚀 Sürüm 2.0 Turbo Mod
                </div>
            </div>

            <?php if(isset($_GET['updated'])) echo '<div class="notice notice-success is-dismissible" style="border-radius: 10px; border-left: 4px solid var(--ql-success); padding: 12px 20px; background: #fff; margin-bottom: 25px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);"><p style="margin:0; font-size: 14px;"><strong>Harika!</strong> Ayarlar başarıyla güncellendi ve AES-256 ile şifrelenerek güvene alındı. 🔒</p></div>'; ?>

            <div class="ql-tabs">
                <button class="ql-tab-btn active" onclick="qlOpenTab('tab-api')">🔐 YZ Bağlantısı</button>
                <button class="ql-tab-btn" onclick="qlOpenTab('tab-stores')">🏪 Mağazalar & Marka Dili</button>
            </div>

            <div id="tab-api" class="ql-tab-content active">
                <div class="ql-card">
                    <h3>🔐 Yapay Zeka (Gemini) Bağlantısı</h3>
                    <p style="color: var(--ql-text-light); font-size: 14px; margin-bottom: 20px; line-height: 1.6;">Sistemin zekasını kullanabilmesi için Google Gemini API anahtarınızı buraya girin. Bilgileriniz üst düzey kriptografi ile saklanmaktadır.</p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="ql_save_global">
                        <?php wp_nonce_field('ql_global_nonce'); ?>
                        
                        <label>Gemini API Anahtarı:</label>
                        <input type="password" name="gemini_api_key" class="ql-input" value="<?php echo esc_attr($gemini_api_key); ?>" placeholder="AIizaSy..." style="max-width: 500px;">
                        
                        <div style="margin-top: 10px;">
                            <button type="submit" class="ql-btn">💾 Güvenli Kaydet</button>
                        </div>
                    </form>
                </div>
            </div>

            <div id="tab-stores" class="ql-tab-content">
                <div class="ql-grid-2">
                    
                    <div>
                        <div class="ql-card">
                            <h3>➕ Yeni Mağaza Ekle</h3>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <input type="hidden" name="action" value="ql_save_store">
                                <?php wp_nonce_field('ql_store_nonce'); ?>
                                
                                <label>Mağaza Adı (Örn: QL Merkez):</label>
                                <input type="text" name="store_name" class="ql-input" required>
                                
                                <div class="ql-grid-2" style="gap: 15px;">
                                    <div>
                                        <label>Satıcı ID:</label>
                                        <input type="text" name="seller_id" class="ql-input" required>
                                    </div>
                                    <div>
                                        <label>API Key:</label>
                                        <input type="text" name="api_key" class="ql-input" required>
                                    </div>
                                </div>
                                
                                <label>API Secret (Gizli & Şifrelenir):</label>
                                <input type="password" name="api_secret" class="ql-input" required>
                                
                                <label>Mağazanın Marka Dili ve Kuralları:</label>
                                <textarea name="store_prompt" class="ql-textarea" rows="3" required placeholder="Örn: 'Merhabalar efendim' diye başla..."></textarea>
                                
                                <button type="submit" class="ql-btn" style="width: 100%;">➕ Sisteme Ekle</button>
                            </form>
                        </div>
                    </div>

                    <div>
                        <div class="ql-card" style="background: transparent; border: none; box-shadow: none; padding: 0;">
                            <h3 style="border-bottom: none; margin-bottom: 10px; display: flex; align-items: center;">
                                🏪 Bağlı Mağazalar 
                                <?php if(!empty($stores)): ?>
                                    <span style="font-size: 12px; background: var(--ql-primary); color: #fff; padding: 2px 8px; border-radius: 12px; font-weight: bold; margin-left: 10px;"><?php echo count($stores); ?></span>
                                <?php endif; ?>
                            </h3>
                            
                            <?php if(empty($stores)): ?>
                                <div style="background: #fff; padding: 30px; border-radius: 12px; text-align: center; color: var(--ql-text-light); border: 1px dashed var(--ql-border);">Henüz sisteme mağaza eklenmedi.</div>
                            <?php else: ?>
                                <div class="ql-store-list-container">
                                    <?php foreach($stores as $id => $s): ?>
                                        <div class="ql-store-item">
                                            <div>
                                                <strong style="display: block; font-size: 16px; color: var(--ql-text); margin-bottom: 5px;"><?php echo esc_html($s['name']); ?></strong>
                                                <span class="ql-badge">ID: <?php echo esc_attr($id); ?></span>
                                            </div>
                                            <div class="ql-store-actions">
                                                <button type="button" class="ql-btn ql-btn-secondary btn-test-store" data-id="<?php echo esc_attr($id); ?>" style="padding: 8px 12px; font-size: 13px;">🔌 Test Et</button>
                                                <button type="button" class="ql-btn btn-open-modal" data-id="<?php echo esc_attr($id); ?>" data-name="<?php echo esc_attr($s['name']); ?>" data-prompt="<?php echo isset($s['prompt']) ? esc_attr($s['prompt']) : ''; ?>" style="padding: 8px 12px; font-size: 13px; background: var(--ql-secondary);">✍️ Marka Dili</button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>

            <div class="ql-modal-overlay" id="qlPromptModal">
                <div class="ql-modal">
                    <button class="ql-modal-close" onclick="closePromptModal()">✖</button>
                    <h2 style="margin: 0 0 10px 0; color: var(--ql-text); font-size: 22px; display:flex; align-items:center; gap:10px;">
                        <span>✍️</span> Marka Dilini Eğit
                    </h2>
                    <p style="color: var(--ql-text-light); font-size: 14px; margin-bottom: 20px;">
                        <strong id="modalStoreName" style="color: var(--ql-text);"></strong> mağazası için yapay zekanın kullanacağı üslup kurallarını belirleyin.
                    </p>
                    
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="ql_update_store_prompt">
                        <?php wp_nonce_field('ql_store_prompt_nonce'); ?>
                        <input type="hidden" name="store_id" id="modalStoreId" value="">
                        
                        <textarea name="store_prompt" id="modalStorePrompt" class="ql-textarea" rows="6" placeholder="Örn:&#10;- Merhabalar efendim diye başla.&#10;- Müşteriye daima 'Siz' diye hitap et."></textarea>
                        
                        <div style="display: flex; justify-content: flex-end; gap: 15px; margin-top: 15px;">
                            <button type="button" class="ql-btn ql-btn-secondary" onclick="closePromptModal()">İptal Et</button>
                            <button type="submit" class="ql-btn">💾 Güncelle</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>

        <script>
        // --- 1. SEKMELER (TABS) MANTIĞI ---
        function qlOpenTab(tabId) {
            // Butonları güncelle
            document.querySelectorAll('.ql-tab-btn').forEach(btn => btn.classList.remove('active'));
            event.currentTarget.classList.add('active');
            
            // İçerikleri (Sayfaları) güncelle
            document.querySelectorAll('.ql-tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.getElementById(tabId).classList.add('active');
        }

        // --- 2. MODAL VE TEST BUTONLARI ---
        function closePromptModal() {
            const overlay = document.getElementById('qlPromptModal');
            overlay.classList.remove('active');
            setTimeout(() => overlay.style.display = 'none', 300);
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Modal Açma İşlemi
            document.querySelectorAll('.btn-open-modal').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.getElementById('modalStoreId').value = this.dataset.id;
                    document.getElementById('modalStoreName').innerText = this.dataset.name;
                    document.getElementById('modalStorePrompt').value = this.dataset.prompt;
                    
                    const overlay = document.getElementById('qlPromptModal');
                    overlay.style.display = 'flex';
                    setTimeout(() => overlay.classList.add('active'), 10);
                });
            });

            // API Test İstekleri
            document.querySelectorAll('.btn-test-store').forEach(btn => {
                btn.addEventListener('click', async function() {
                    const id = this.dataset.id;
                    const originalText = this.innerHTML;
                    this.innerHTML = '⏳...'; this.disabled = true;
                    
                    const fd = new FormData();
                    fd.append('action', 'ql_test_store'); 
                    fd.append('security', '<?php echo wp_create_nonce("ql_ajax_nonce"); ?>'); 
                    fd.append('store_id', id);
                    
                    try {
                        const res = await fetch(ajaxurl, { method: 'POST', body: fd });
                        const data = await res.json();
                        alert(data.data && data.data.message ? data.data.message : 'Bağlantı sağlandı!');
                    } catch (e) { 
                        alert('Bağlantı hatası oluştu. İnternet veya sunucu ayarlarınızı kontrol edin.'); 
                    }
                    
                    this.innerHTML = originalText; this.disabled = false;
                });
            });
        });
        </script>
        <?php
    }

    public function page_questions() {
        global $wpdb;
        $stores = get_option('ql_trendyol_stores', []);
        $selected_seller_id = isset($_GET['store_id']) ? sanitize_text_field($_GET['store_id']) : 'all';

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
        ?>
        <style>
            :root {
                --ql-primary: #4f46e5; --ql-primary-dark: #4338ca; --ql-success: #10b981; --ql-warning: #f59e0b;
                --ql-danger: #ef4444; --ql-bg-light: #f8fafc; --ql-border: #e2e8f0; --ql-text-main: #1e293b; --ql-text-muted: #64748b;
            }
            .ql-questions-wrap { max-width: 1400px; margin: 20px auto; padding: 0 15px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: var(--ql-text-main); }
            .ql-header-section { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 30px; flex-wrap: wrap; gap: 20px; }
            .ql-title-group h1 { margin: 0; font-size: 28px; font-weight: 800; display: flex; align-items: center; gap: 12px; }
            
            .ql-top-bar { background: #fff; padding: 15px 25px; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid var(--ql-border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 30px; }
            .ql-action-group { display: flex; gap: 12px; flex-wrap: wrap; }

            /* Grid System */
            .ql-questions-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 25px; }
            @media (max-width: 480px) { .ql-questions-grid { grid-template-columns: 1fr; } }

            /* Card Redesign */
            .ql-question-card { background: #fff; border-radius: 20px; border: 1px solid var(--ql-border); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); overflow: hidden; display: flex; flex-direction: column; transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1); border-left: 6px solid transparent; position: relative; }
            .ql-question-card:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }

            .ql-card-header { padding: 20px 25px; border-bottom: 1px solid var(--ql-bg-light); display: flex; justify-content: space-between; align-items: flex-start; }
            .ql-product-title { margin: 0 0 8px; font-size: 16px; font-weight: 700; line-height: 1.4; color: var(--ql-text-main); padding-right: 60px; }
            .ql-store-badge { background: #e0e7ff; color: var(--ql-primary); font-size: 10px; font-weight: 800; padding: 4px 10px; border-radius: 20px; white-space: nowrap; position: absolute; top: 20px; right: 20px; }

            .ql-card-body { padding: 25px; flex-grow: 1; }
            .ql-q-box { background: var(--ql-bg-light); padding: 15px; border-radius: 14px; font-size: 14px; line-height: 1.6; margin-bottom: 20px; position: relative; }
            .ql-q-label { position: absolute; top: -10px; left: 15px; background: var(--ql-primary); color: #fff; font-size: 9px; font-weight: 900; padding: 2px 8px; border-radius: 6px; text-transform: uppercase; }

            /* Inline RAG */
            .ql-rag-box { background: #fffbeb; border: 1px solid #fef3c7; border-radius: 14px; padding: 15px; margin-bottom: 20px; }
            .ql-rag-input-group { display: flex; gap: 8px; margin-top: 8px; }
            .ql-rag-input { flex: 1; border: 1px solid #fcd34d; border-radius: 10px; padding: 8px 12px; font-size: 13px; background: #fff; }
            .ql-btn-save { background: #f59e0b; color: #fff; border: none; border-radius: 10px; padding: 0 15px; font-weight: 700; cursor: pointer; }

            /* Score Box */
            .ql-score-pill { display: none; margin-bottom: 15px; font-size: 12px; font-weight: 800; padding: 8px; border-radius: 10px; text-align: center; }

            .ql-ans-textarea { width: 100%; border: 1.5px solid var(--ql-border); border-radius: 14px; padding: 15px; font-size: 14px; line-height: 1.6; min-height: 110px; background: #fcfcfc; transition: 0.2s; }
            .ql-ans-textarea:focus { outline: none; border-color: var(--ql-primary); background: #fff; box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.08); }

            .ql-card-footer { padding: 20px 25px; background: var(--ql-bg-light); display: flex; gap: 12px; border-top: 1px solid var(--ql-border); }
            
            /* Buttons */
            .ql-btn-m { cursor: pointer; border-radius: 12px; font-weight: 700; font-size: 13px; padding: 12px 20px; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; border: none; }
            .ql-btn-p { background: var(--ql-primary); color: #fff; }
            .ql-btn-p:hover { background: var(--ql-primary-dark); transform: translateY(-2px); }
            .ql-btn-s { background: #fff; color: var(--ql-text-main); border: 1px solid var(--ql-border); }
            .ql-btn-s:hover { background: var(--ql-bg-light); }
            
            @keyframes ql-spin { 100% { transform: rotate(360deg); } }
            .ql-spin { animation: ql-spin 1s linear infinite; }
        </style>

        <div class="ql-questions-wrap">
            <div class="ql-header-section">
                <div class="ql-title-group">
                    <h1>📥 Bekleyen Sorular</h1>
                    <p>Müşterilerinizden gelen yanıt bekleyen mesajları buradan yönetin.</p>
                </div>
            </div>

            <div class="ql-top-bar">
                <div class="ql-action-group">
                    <button type="button" id="btn-check-new" class="ql-btn-m ql-btn-s" data-current="<?php echo count($all_questions); ?>">
                        <span class="dashicons dashicons-update"></span> Kontrol Et
                    </button>
                    <?php if(!empty($all_questions)): ?>
                        <button type="button" id="btn-ask-all" class="ql-btn-m ql-btn-p" style="background:#6366f1;">
                            <span class="dashicons dashicons-marker"></span> Tümünü YZ Hazırla
                        </button>
                    <?php endif; ?>
                </div>
                
                <form method="get" action="">
                    <input type="hidden" name="page" value="ql-ai-questions">
                    <select name="store_id" onchange="this.form.submit()" class="ql-btn-m ql-btn-s" style="padding: 8px 15px;">
                        <option value="all">🌐 Tüm Mağazalar</option>
                        <?php foreach($stores as $id => $store): ?>
                            <option value="<?php echo esc_attr($id); ?>" <?php selected($selected_seller_id, $id); ?>>🏪 <?php echo esc_html($store['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <?php if(empty($all_questions)): ?>
                <div style="text-align:center; padding: 100px 20px; background:#fff; border-radius:30px; border: 2px dashed var(--ql-border);">
                    <div style="font-size:50px; margin-bottom:20px;">🎉</div>
                    <h2 style="margin:0; color:var(--ql-text-main);">Tebrikler! Bekleyen soru yok.</h2>
                    <p style="color:var(--ql-text-muted);">Yeni sorular geldiğinde burada görünecekler.</p>
                </div>
            <?php else: ?>
                <div id="ask-all-status" style="margin-bottom:15px; font-weight:bold; color:var(--ql-primary);"></div>
                <div class="ql-questions-grid">
                    <?php 
                    $table_knowledge = $wpdb->prefix . 'ql_product_knowledge';
                    foreach($all_questions as $q): 
                        $q_id = esc_attr($q['id']);
                        $text = esc_html($q['text']);
                        $product_name = esc_html($q['productName']);
                        $barcode = isset($q['productMainId']) ? esc_attr($q['productMainId']) : '';
                        $store_id = esc_attr($q['ql_store_id']);
                        $rag_rule = $wpdb->get_var($wpdb->prepare("SELECT product_info FROM {$table_knowledge} WHERE barcode = %s", $barcode));
                    ?>
                    <div class="ql-question-card" id="card_<?php echo $q_id; ?>">
                        <div class="ql-card-header">
                            <div class="ql-product-info">
                                <h4 class="ql-product-title"><?php echo $product_name; ?></h4>
                                <span class="ql-model-badge">Model: <?php echo $barcode; ?></span>
                            </div>
                            <span class="ql-store-badge"><?php echo esc_html($q['ql_store_name']); ?></span>
                        </div>

                        <div class="ql-card-body">
                            <div class="ql-q-box">
                                <span class="ql-q-label">Soru</span>
                                <?php echo $text; ?>
                            </div>

                           <div style="display: flex; gap: 8px; margin-bottom: 12px;">
                                <button type="button" class="ql-btn-m ql-btn-s" onclick="document.getElementById('rag_container_<?php echo $q_id; ?>').style.display = document.getElementById('rag_container_<?php echo $q_id; ?>').style.display === 'none' ? 'block' : 'none'" style="font-size: 11px; padding: 6px 12px;">🧠 Bilgi Düzenle</button>
                                <button type="button" class="ql-btn-m ql-btn-s" onclick="document.getElementById('note_container_<?php echo $q_id; ?>').style.display = document.getElementById('note_container_<?php echo $q_id; ?>').style.display === 'none' ? 'block' : 'none'" style="font-size: 11px; padding: 6px 12px; border-color: #cbd5e1; background: #f1f5f9;">💡 Özel Not Ekle</button>
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
                            <button type="button" class="ql-btn-m ql-btn-s btn-ask" style="flex:1; justify-content:center;" 
                                data-id="<?php echo $q_id; ?>" data-barcode="<?php echo $barcode; ?>" data-q="<?php echo esc_attr($text); ?>" data-store="<?php echo $store_id; ?>">
                                ✨ Hazırla
                            </button>
                            <button type="button" class="ql-btn-m ql-btn-p btn-send" style="flex:1.5; justify-content:center;" 
                                data-id="<?php echo $q_id; ?>" data-store="<?php echo $store_id; ?>" data-barcode="<?php echo $barcode; ?>" data-q="<?php echo esc_attr($text); ?>" data-pname="<?php echo esc_attr($product_name); ?>">
                                🚀 Gönder
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const nonce = '<?php echo wp_create_nonce("ql_ajax_nonce"); ?>';

            // Trafik Lambası
            function updateScoreUI(id, score) {
                const box = document.getElementById('score_box_' + id);
                const card = document.getElementById('card_' + id);
                box.style.display = 'block';
                if(score >= 0.85) {
                    box.style.background = '#dcfce7'; box.style.color = '#166534'; box.innerHTML = '🟢 %' + Math.round(score*100) + ' Uyum (Altın)';
                    card.style.borderColor = '#10b981';
                } else if(score >= 0.60) {
                    box.style.background = '#fef3c7'; box.style.color = '#92400e'; box.innerHTML = '🟡 %' + Math.round(score*100) + ' Orta Derece Uyum';
                    card.style.borderColor = '#f59e0b';
                } else {
                    box.style.background = '#fee2e2'; box.style.color = '#991b1b'; box.innerHTML = '🔴 %' + Math.round(score*100) + ' Düşük Uyum (Kontrol Et)';
                    card.style.borderColor = '#ef4444';
                }
            }

            // Yeni Soru Kontrolü
            const btnCheckNew = document.getElementById('btn-check-new');
            if (btnCheckNew) {
                btnCheckNew.addEventListener('click', async function() {
                    const originalHTML = this.innerHTML;
                    this.innerHTML = '<span class="dashicons dashicons-update ql-spin"></span>';
                    this.disabled = true;
                    try {
                        const fd = new FormData(); fd.append('action', 'ql_check_waiting_questions'); fd.append('security', nonce);
                        const res = await fetch(ajaxurl, { method: 'POST', body: fd });
                        const data = await res.json();
                        if (data.success && data.data.count != this.dataset.current) location.reload();
                        else { this.innerHTML = '✅ Sorular Güncel'; setTimeout(() => { this.innerHTML = originalHTML; this.disabled = false; }, 2000); }
                    } catch (e) { this.innerHTML = originalHTML; this.disabled = false; }
                });
            }

            // Toplu Bot (Trafik Lambası Uyumlu)
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
                        const id = btn.dataset.id; const box = document.getElementById('ans_' + id);
                        if (box.value.trim() !== '') continue;
                        btn.disabled = true;
                        try {
                            const fd = new FormData(); fd.append('action', 'ql_ask_ai'); fd.append('security', nonce);
                            fd.append('question', btn.dataset.q); fd.append('barcode', btn.dataset.barcode); fd.append('store_id', btn.dataset.store);
                            const res = await fetch(ajaxurl, { method: 'POST', body: fd });
                            const data = await res.json();
                            if(data.success) { box.value = data.data.answer; updateScoreUI(id, data.data.score); }
                        } catch (e) {}
                        btn.disabled = false;
                        if (i < buttons.length - 1) await new Promise(r => setTimeout(r, 4000));
                    }
                    statusLabel.innerHTML = '✅ Tüm taslaklar hazır.'; this.disabled = false;
                });
            }

            // Kaydet (RAG)
            document.querySelectorAll('.btn-save-rag').forEach(btn => {
                btn.addEventListener('click', async function() {
                    const id = this.dataset.id; const status = document.getElementById('rag_status_' + id);
                    this.disabled = true;
                    const fd = new FormData(); fd.append('action', 'ql_save_product_rule'); fd.append('security', nonce);
                    fd.append('barcode', this.dataset.barcode); fd.append('info', document.getElementById('rag_' + id).value);
                    await fetch(ajaxurl, { method: 'POST', body: fd });
                    status.innerHTML = 'KAYDEDİLDİ'; this.disabled = false;
                    setTimeout(() => status.innerHTML = '', 2000);
                });
            });

            // Tekil Hazırla
            document.querySelectorAll('.btn-ask').forEach(btn => {
                btn.addEventListener('click', async function() {
                    const id = this.dataset.id; const box = document.getElementById('ans_' + id);
                    const originalText = this.innerHTML; this.disabled = true; this.innerHTML = '⏳...';
                    const fd = new FormData(); fd.append('action', 'ql_ask_ai'); fd.append('security', nonce);
                    fd.append('question', this.dataset.q); fd.append('barcode', this.dataset.barcode); fd.append('store_id', this.dataset.store);
                    fd.append('quick_note', document.getElementById('note_' + id).value);
                    try {
                        const res = await fetch(ajaxurl, { method: 'POST', body: fd });
                        const data = await res.json();
                        if(data.success) { box.value = data.data.answer; updateScoreUI(id, data.data.score); }
                    } catch (e) {}
                    this.disabled = false; this.innerHTML = originalText;
                });
            });

            // Gönder
            document.querySelectorAll('.btn-send').forEach(btn => {
                btn.addEventListener('click', async function() {
                    const id = this.dataset.id; const answer = document.getElementById('ans_' + id).value.trim();
                    if(!answer) return alert('Cevap boş!');
                    this.disabled = true; this.innerHTML = '🚀...';
                    const fd = new FormData(); fd.append('action', 'ql_send_answer'); fd.append('security', nonce);
                    fd.append('q_id', id); fd.append('answer', answer); fd.append('store_id', this.dataset.store);
                    fd.append('q_text', this.dataset.q); fd.append('barcode', this.dataset.barcode); fd.append('p_name', this.dataset.pname);
                    try {
                        const res = await fetch(ajaxurl, { method: 'POST', body: fd });
                        const data = await res.json();
                        if(data.success) { this.closest('.ql-question-card').style.opacity = '0.3'; this.innerHTML = '✅ Gönderildi'; }
                    } catch (e) {}
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
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 2fr 1.5fr 1fr; background: #f8f9fa; padding: 15px; font-weight: bold; border-bottom: 1px solid #eee; gap: 10px;">
                    <div>Mağaza</div>
                    <div>Barkod</div>
                    <div>Model Kodu</div>
                    <div>Ürün Adı</div>
                    <div>Öğrenilmiş Kural</div>
                    <div style="text-align: right;">İşlem</div>
                </div>
                <div id="ql-product-items">
                    <div style="padding: 40px; text-align: center; color: #888;">Yükleniyor...</div>
                </div>
                <div id="ql-product-pagination"></div>
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

            let currentPage = 1;

            async function fetchProducts(search = '', page = 1) {
                container.innerHTML = '<div style="padding:40px; text-align:center;">Yükleniyor...</div>';
                const fd = new FormData();
                fd.append('action', 'ql_fetch_training_products');
                fd.append('security', nonce);
                fd.append('search', search);
                fd.append('page', page);

                try {
                    const res = await fetch(ajaxurl, { method: 'POST', body: fd });
                    const data = await res.json();
                    container.innerHTML = data.data.html;
                    document.getElementById('ql-product-pagination').innerHTML = data.data.pagination || '';

                    // Düzenle Butonları
                    document.querySelectorAll('.btn-edit-product').forEach(btn => {
                        btn.addEventListener('click', function() {
                            document.getElementById('modal-title').innerText = this.dataset.model;
                            document.getElementById('modal-subtitle').innerText = this.dataset.name;
                            document.getElementById('modal-info-text').value = this.dataset.info || '';
                            document.getElementById('btn-save-product-info').dataset.barcode = this.dataset.model;
                            document.getElementById('ql-edit-modal').style.display = 'flex';
                        });
                    });

                    // Sayfalama Butonları
                    document.querySelectorAll('.btn-page').forEach(btn => {
                        btn.addEventListener('click', function() {
                            currentPage = parseInt(this.dataset.page);
                            fetchProducts(searchInput.value, currentPage);
                        });
                    });

                } catch(e) { container.innerHTML = 'Hata oluştu.'; }
            }

            let timeout = null;
            searchInput.addEventListener('keyup', () => {
                clearTimeout(timeout);
                currentPage = 1; // Yeni aramada sayfayı 1'e sıfırla
                timeout = setTimeout(() => fetchProducts(searchInput.value, currentPage), 500);
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

       $s_term   = isset($_GET['s_term']) ? sanitize_text_field($_GET['s_term']) : '';
        $s_store  = isset($_GET['store_id']) ? sanitize_text_field($_GET['store_id']) : '';
        $s_golden = isset($_GET['is_golden']) ? 1 : 0;
        $s_start  = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
        $s_end    = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
        
        $items_per_page = 50;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $items_per_page;

        // DÜZELTME: Sadece gerçek soruları göster, otomatik senkronize edilen sahte kayıtları arşivden gizle.
        $where = "WHERE status != 'SYNCED'"; $params = [];
        
        if ($s_term) {
            $where .= " AND (product_name LIKE %s OR question_text LIKE %s OR model_code LIKE %s)";
            array_push($params, '%'.$s_term.'%', '%'.$s_term.'%', '%'.$s_term.'%');
        }
        if ($s_store) {
            $where .= " AND store_id = %s"; $params[] = $s_store;
        }

        if ($s_golden) {
            $where .= " AND is_golden = 1";
        }
        if ($s_start) {
            $where .= " AND created_date >= %s"; $params[] = $s_start . ' 00:00:00';
        }
        if ($s_end) {
            $where .= " AND created_date <= %s"; $params[] = $s_end . ' 23:59:59';
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
                <form method="get" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                    <input type="hidden" name="page" value="ql-ai-past-questions">
                    
                    <div style="flex: 1; min-width: 250px; display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 20px;">🔍</span>
                        <input type="text" name="s_term" class="ql-input" value="<?php echo esc_attr($s_term); ?>" placeholder="Ürün, model, soru veya cevap ara..." style="width: 100%; border: none; background: #f8fafc; font-size: 14px;">
                    </div>

                    <select name="store_id" class="ql-select" style="min-width: 160px; height: 38px;">
                        <option value="">🏪 Tüm Mağazalar</option>
                        <?php foreach($stores as $id => $st) echo "<option value='".esc_attr($id)."' ".selected($s_store, $id, false).">".esc_html($st['name'])."</option>"; ?>
                    </select>

                    <div style="display: flex; align-items: center; gap: 8px; background: #f8fafc; padding: 5px 10px; border-radius: 8px; border: 1px solid var(--ql-border);">
                        <span style="font-size: 12px; font-weight: 600; color: var(--ql-text-light);">Tarih:</span>
                        <input type="date" name="start_date" class="ql-input" value="<?php echo esc_attr($s_start); ?>" style="border:none; background:transparent; padding:0; width:115px; font-size:12px;">
                        <span style="color: #cbd5e1;">-</span>
                        <input type="date" name="end_date" class="ql-input" value="<?php echo esc_attr($s_end); ?>" style="border:none; background:transparent; padding:0; width:115px; font-size:12px;">
                    </div>

                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; font-weight: 600; margin: 0; padding: 5px 10px; background: #fff9db; border-radius: 8px; color: #856404; border: 1px solid #ffe066;">
                        <input type="checkbox" name="is_golden" value="1" <?php checked($s_golden, 1); ?> style="margin:0;"> ⭐ Sadece Altınlar
                    </label>

                    <div style="display: flex; gap: 8px;">
                        <button type="submit" class="ql-btn" style="background: #1e293b; padding: 8px 20px;">Filtrele</button>
                        <a href="?page=ql-ai-past-questions" class="ql-btn ql-btn-bot" style="text-decoration: none; padding: 8px 15px;">Temizle</a>
                    </div>
                </form>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr)); gap: 20px;">
                <?php if($past_questions): foreach($past_questions as $q): ?>
                   <div class="ql-card" style="display: flex; flex-direction: column;">
                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 11px; color: #999; margin-bottom: 10px; border-bottom: 1px solid var(--ql-border); padding-bottom: 8px;">
                            <span>📅 <?php echo date('d.m.Y H:i', strtotime($q->created_date)); ?> | 📦 <?php echo esc_html($q->model_code); ?></span>
                           <div style="display: flex; align-items: center; gap: 10px;">
                                <button type="button" class="ql-star-btn <?php echo $q->is_golden ? 'active' : ''; ?>" data-id="<?php echo $q->id; ?>" title="Altın Soru Yap/Kaldır">
                                    <?php echo $q->is_golden ? '★' : '☆'; ?>
                                </button>
                                <?php if(!empty($q->vector_data)): ?>
                                    <span style="background: #dcfce7; color: #166534; padding: 4px 8px; border-radius: 6px; font-size:10px; font-weight: 600;">🧠 İndekslendi</span>
                                <?php else: ?>
                                    <span style="background: #fee2e2; color: #991b1b; padding: 4px 8px; border-radius: 6px; font-size:10px; font-weight: 600;">⏳ İndeks Bekliyor</span>
                                <?php endif; ?>
                            </div>
                        </div>
                       <strong style="color: var(--ql-primary); font-size: 15px; margin-bottom: 10px;"><?php echo esc_html($q->product_name); ?></strong>
                        
                        <?php if ($q->status === 'SYNCED' || $q->question_text === 'OTOMATIK SENKRONIZASYON'): ?>
                            <div style="background: #fff8e1; padding: 15px; border-radius: 8px; margin-bottom: 15px; font-size: 13px; border-left: 4px solid #ffc107; flex-grow: 1; color: #856404; display: flex; flex-direction: column; justify-content: center;">
                                <div style="font-weight: 600; margin-bottom: 5px; font-size: 14px;">🔄 Sistem Kaydı</div>
                                Bu ürün veritabanına otomatik senkronize edilmiştir. Ürüne ait gerçek bir müşteri sorusu bulunmuyor.
                            </div>
                        <?php else: ?>
                            <div style="background: #f8fafc; padding: 12px; border-radius: 8px; margin-bottom: 10px; font-size: 14px; border-left: 3px solid #cbd5e1;">
                                <strong style="color: #475569;">S:</strong> <?php echo esc_html($q->question_text); ?>
                            </div>
                            <div style="background: #f0fdf4; padding: 12px; border-radius: 8px; font-size: 13px; color: #166534; margin-bottom: 15px; flex-grow: 1;">
                                <strong>C:</strong> <?php echo esc_html($q->answer_text); ?>
                            </div>
                            
                            <button type="button" class="ql-btn btn-test-ai" style="width: 100%; background: #fff; color: #4f46e5; border: 1px solid #4f46e5;" data-id="<?php echo esc_attr($q->id); ?>" data-store="<?php echo esc_attr($q->store_id); ?>" data-barcode="<?php echo esc_attr($q->model_code); ?>" data-q="<?php echo esc_attr($q->question_text); ?>">✨ Bu Soruya YZ Ne Derdi? (Test)</button>
                            <div id="test_res_<?php echo esc_attr($q->id); ?>" style="display:none; margin-top:10px; background:#eef2ff; color:#3730a3; padding:12px; border-radius:8px; font-size: 13px; line-height: 1.5; border: 1px solid #c7d2fe;"></div>
                        <?php endif; ?>
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

        document.querySelectorAll('.ql-star-btn').forEach(btn => {
            btn.addEventListener('click', async function() {
                const id = this.dataset.id;
                const fd = new FormData();
                fd.append('action', 'ql_toggle_golden');
                fd.append('security', '<?php echo wp_create_nonce("ql_ajax_nonce"); ?>');
                fd.append('item_id', id);
                
                const res = await fetch(ajaxurl, { method: 'POST', body: fd });
                const data = await res.json();
                if(data.success) {
                    this.classList.toggle('active');
                    this.innerHTML = this.classList.contains('active') ? '★' : '☆';
                }
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
    // --- YENİ: MALİYET RAPORU SAYFASI ---
   public function page_costs() {
        global $wpdb;
        $table_logs = $wpdb->prefix . 'ql_api_logs';
        
        // Tablo kurulu mu kontrolü (ilk sefer için)
        if($wpdb->get_var("SHOW TABLES LIKE '$table_logs'") != $table_logs) {
            echo '<div class="wrap"><h2>Tablo Güncelleniyor... Lütfen sayfayı yenileyin.</h2></div>';
            return;
        }

        // 🛠️ OTOMATİK VERİTABANI TAMİRCİSİ: Yanlış kurulan sütun adını anında düzeltir
        $has_col = $wpdb->get_results("SHOW COLUMNS FROM $table_logs LIKE 'created_at'");
        if(!empty($has_col)) {
            $wpdb->query("ALTER TABLE $table_logs CHANGE created_at created_date datetime DEFAULT '0000-00-00 00:00:00'");
        }

        // Özet İstatistikleri Çekelim (Günlük, Haftalık, Aylık, Toplam)
        $today = current_time('Y-m-d');
        $cost_today = $wpdb->get_var("SELECT SUM(cost_usd) FROM $table_logs WHERE DATE(created_date) = '$today'") ?: 0;
        $cost_week = $wpdb->get_var("SELECT SUM(cost_usd) FROM $table_logs WHERE YEARWEEK(created_date, 1) = YEARWEEK('$today', 1)") ?: 0;
        $cost_month = $wpdb->get_var("SELECT SUM(cost_usd) FROM $table_logs WHERE MONTH(created_date) = MONTH('$today') AND YEAR(created_date) = YEAR('$today')") ?: 0;
        $cost_total = $wpdb->get_var("SELECT SUM(cost_usd) FROM $table_logs") ?: 0;

        // Sayfalama Listesi
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $limit = 25;
        $offset = ($page - 1) * $limit;
        
        $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_logs");
        $total_pages = ceil($total_items / $limit);
        $logs = $wpdb->get_results("SELECT * FROM $table_logs ORDER BY created_date DESC LIMIT $limit OFFSET $offset");
        ?>
        <div class="wrap" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
            <h1 style="margin-bottom: 25px; font-weight: 700; display:flex; align-items:center; gap:10px;">
                <span style="font-size:28px;">💸</span> API Maliyet Raporu
            </h1>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <div style="background:#fff; padding:20px; border-radius:12px; border:1px solid #e2e8f0; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05); text-align:center;">
                    <div style="font-size:13px; color:#64748b; font-weight:600; text-transform:uppercase;">Bugün</div>
                    <div style="font-size:28px; font-weight:800; color:#10b981; margin-top:5px;">$<?php echo number_format($cost_today, 4); ?></div>
                </div>
                <div style="background:#fff; padding:20px; border-radius:12px; border:1px solid #e2e8f0; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05); text-align:center;">
                    <div style="font-size:13px; color:#64748b; font-weight:600; text-transform:uppercase;">Bu Hafta</div>
                    <div style="font-size:28px; font-weight:800; color:#3b82f6; margin-top:5px;">$<?php echo number_format($cost_week, 4); ?></div>
                </div>
                <div style="background:#fff; padding:20px; border-radius:12px; border:1px solid #e2e8f0; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05); text-align:center;">
                    <div style="font-size:13px; color:#64748b; font-weight:600; text-transform:uppercase;">Bu Ay</div>
                    <div style="font-size:28px; font-weight:800; color:#8b5cf6; margin-top:5px;">$<?php echo number_format($cost_month, 4); ?></div>
                </div>
                <div style="background:linear-gradient(135deg, #0f172a, #1e293b); padding:20px; border-radius:12px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.2); text-align:center; color:#fff;">
                    <div style="font-size:13px; color:#94a3b8; font-weight:600; text-transform:uppercase;">Genel Toplam</div>
                    <div style="font-size:28px; font-weight:800; margin-top:5px;">$<?php echo number_format($cost_total, 4); ?></div>
                </div>
            </div>

            <div style="background:#fff; border-radius:12px; border:1px solid #e2e8f0; overflow:hidden; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);">
                <div style="padding:15px 20px; background:#f8fafc; border-bottom:1px solid #e2e8f0; font-weight:700; color:#1e293b;">📊 Detaylı İşlem Geçmişi</div>
                <table class="wp-list-table widefat fixed striped" style="border:none; margin:0;">
                    <thead>
                        <tr>
                            <th style="font-weight:600;">Tarih</th>
                            <th style="font-weight:600;">İşlem Türü</th>
                            <th style="font-weight:600;">Okunan Token</th>
                            <th style="font-weight:600;">Yazılan Token</th>
                            <th style="font-weight:600;">Maliyet ($)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($logs)): ?>
                            <tr><td colspan="5" style="text-align:center; padding:20px; color:#64748b;">Henüz bir harcama kaydı bulunmuyor.</td></tr>
                        <?php else: ?>
                            <?php foreach($logs as $l): ?>
                            <tr>
                                <td><?php echo date('d.m.Y H:i:s', strtotime($l->created_date)); ?></td>
                                <td><span style="background:#eef2ff; color:#4f46e5; padding:4px 8px; border-radius:6px; font-size:12px; font-weight:600;"><?php echo esc_html($l->action_type); ?></span></td>
                                <td><?php echo number_format($l->tokens_in); ?></td>
                                <td><?php echo number_format($l->tokens_out); ?></td>
                                <td style="color:#ef4444; font-weight:600;">$<?php echo number_format($l->cost_usd, 6); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <?php if($total_pages > 1): ?>
                <div style="padding:15px; border-top:1px solid #e2e8f0; text-align:right;">
                    <?php
                    $page_links = paginate_links([
                        'base' => add_query_arg('paged', '%#%'), 'format' => '', 'prev_text' => '&laquo;', 'next_text' => '&raquo;', 'total' => $total_pages, 'current' => $page
                    ]);
                    if ($page_links) echo "<div class='tablenav-pages' style='margin:0;'>{$page_links}</div>";
                    ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}