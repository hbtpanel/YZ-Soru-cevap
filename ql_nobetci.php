<?php
// UZMAN DOKUNUŞU: Güvenlik Kalkanı - Dışarıdan izinsiz tetiklemeleri engelle!
// Sadece komut satırından (Plesk) veya doğru şifreyle çalışsın.
if (php_sapi_name() !== 'cli' && (!isset($_GET['token']) || $_GET['token'] !== 'gizli_cron_sifreniz_99x')) {
    die('Erişim Engellendi.');
}

// UZMAN DOKUNUŞU: WordPress çekirdeğini başlat
$wp_root = dirname(dirname(dirname(dirname(__FILE__)))); 
if (file_exists($wp_root . '/wp-load.php')) {
    define('SHORTINIT', false);
    require_once($wp_root . '/wp-load.php');
} else {
    die('HATA: wp-load.php bulunamadi. Nöbetci calisamiyor.');
}

// Güvenlik ve Şifre Çözücü Sınıfını (API Services) Dahil Et
require_once dirname(__FILE__) . '/includes/class-api-services.php';

global $wpdb;

// 1. Mağaza Ayarlarını Al
$stores = get_option('ql_trendyol_stores', []);
if (empty($stores)) exit('Mağaza bulunamadı.');

$new_question_found = false;
$store_name_for_notification = 'Mağazanızda';

// SPAM ENGELLEYİCİ: Daha önce bildirim atılan soruların listesini çek
$notified_questions = get_option('ql_notified_questions', []); 
if (!is_array($notified_questions)) $notified_questions = [];

foreach ($stores as $store_id => $store_data) {
    $api_key = trim($store_data['key']); // 'api_key' değil, senin sisteminde adı 'key' olarak kayıtlı!
    $encrypted_secret = $store_data['secret']; // 'api_secret' değil, 'secret' olarak kayıtlı!
    
    // ŞİFRE ÇÖZÜCÜ DEVREDE
    $api_secret = QualityLife_API_Services::decrypt_data($encrypted_secret);

    // DOĞRU TRENDYOL API LİNKİ
    $url = "https://apigw.trendyol.com/integration/qna/sellers/{$store_id}/questions/filter?status=WAITING_FOR_ANSWER&size=10";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . base64_encode($api_key . ':' . $api_secret),
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, $store_id . ' - QL Nöbetçi Bot');
    $response = curl_exec($ch);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_status === 200) {
        $data = json_decode($response, true);
        if (isset($data['content']) && !empty($data['content'])) {
            foreach ($data['content'] as $q) {
                $q_id = strval($q['id']);
                
                // Eğer bu soruya daha önce bildirim ATMADIYSAK
                if (!in_array($q_id, $notified_questions)) {
                    $new_question_found = true;
                    $store_name_for_notification = $store_data['name'];
                    
                    // Soru ID'sini hafızaya ekle ki bir dahaki dakikaya tekrar bildirim gitmesin
                    $notified_questions[] = $q_id;
                    break 2; // İç içe döngülerden çık
                }
            }
        }
    }
}

// 2. Yeni soru varsa OneSignal üzerinden BİLDİRİM (Push) gönder
if ($new_question_found) {
    
    // Hafızayı güncelle (Veritabanı şişmesin diye sadece son 100 soruyu tut)
    if (count($notified_questions) > 100) {
        $notified_questions = array_slice($notified_questions, -100);
    }
    update_option('ql_notified_questions', $notified_questions);

    // Şifreleri doğrudan WordPress veritabanından çek
    $saved_app_id = get_option('ql_onesignal_app_id', '');
    $saved_rest_key = get_option('ql_onesignal_rest_key', '');

    if (empty($saved_app_id) || empty($saved_rest_key)) {
        exit("HATA: OneSignal ayarlari panele girilmemis.");
    }

 $target_url = get_site_url() . "/wp-admin/admin.php?page=ql-ai-questions";
    
    $content = array("en" => "Bekleyen yeni bir müşteri sorusu var! Hemen yanıtlayın.");
    $fields = array(
        'app_id' => $saved_app_id,
        'included_segments' => array('All'),
        'contents' => $content,
        'headings' => array("en" => "📩 {$store_name_for_notification} Yeni Soru!"),
        'ios_sound' => 'default',
        'url' => $target_url
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8', 'Authorization: Basic ' . $saved_rest_key));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_exec($ch);
    curl_close($ch);
    
    echo "Bildirim Gonderildi.";
} else {
    echo "Yeni soru yok. Beklemede...";
}