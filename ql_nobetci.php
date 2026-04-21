<?php
// UZMAN DOKUNUŞU: WordPress çekirdeğini ÖNCE başlat, token kontrolü için DB erişimine ihtiyacımız var.
$wp_root = dirname(dirname(dirname(dirname(__FILE__))));
if (file_exists($wp_root . '/wp-load.php')) {
    define('SHORTINIT', false);
    require_once($wp_root . '/wp-load.php');
} else {
    die('HATA: wp-load.php bulunamadi. Nöbetci calisamiyor.');
}

// DÜZELTME: Güvenlik Kalkanı - Token artık veritabanından okunuyor (admin panelinden değiştirilebilir)
$cron_token = get_option('ql_cron_token', 'gizli_cron_sifreniz_99x');
if (php_sapi_name() !== 'cli' && (!isset($_GET['token']) || $_GET['token'] !== $cron_token)) {
    die('Erişim Engellendi.');
}

// Güvenlik ve Şifre Çözücü Sınıfını (API Services) Dahil Et
require_once dirname(__FILE__) . '/includes/class-api-services.php';

global $wpdb;

// 1. Mağaza Ayarlarını Al
$stores = get_option('ql_trendyol_stores', []);
if (empty($stores)) exit('Mağaza bulunamadı.');

$new_questions_count = 0;
$store_names_with_new = [];

// SPAM ENGELLEYİCİ: Daha önce bildirim atılan soruların listesini çek
$notified_questions = get_option('ql_notified_questions', []);
if (!is_array($notified_questions)) $notified_questions = [];

foreach ($stores as $store_id => $store_data) {
    $api_key = trim($store_data['key']);
    $encrypted_secret = $store_data['secret'];

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
                    $new_questions_count++;
                    $store_names_with_new[$store_data['name']] = true;
                    // Soru ID'sini hafızaya ekle ki bir dahaki dakikaya tekrar bildirim gitmesin
                    $notified_questions[] = $q_id;
                }
            }
        }
    } else {
        QualityLife_API_Services::log_error('NOBETCI_API_ERROR', 'Nöbetçi Bot Trendyol ile iletişim kuramadı.', "Mağaza: {$store_data['name']} - HTTP: {$http_status} - Response: {$response}");
    }
}

// 2. Yeni sorular varsa OneSignal üzerinden BİLDİRİM (Push) gönder
if ($new_questions_count > 0) {

    // Hafızayı güncelle (Veritabanı şişmesin diye sadece son 100 soruyu tut)
    if (count($notified_questions) > 100) {
        $notified_questions = array_slice($notified_questions, -100);
    }
    update_option('ql_notified_questions', $notified_questions);

    // Şifreleri doğrudan WordPress veritabanından çek
    $saved_app_id = get_option('ql_onesignal_app_id', '');
    $saved_rest_key = get_option('ql_onesignal_rest_key', '');

    if (empty($saved_app_id) || empty($saved_rest_key)) {
        QualityLife_API_Services::log_error('ONESIGNAL_ERROR', 'Yeni soru bulundu ama OneSignal API bilgileri eksik olduğu için bildirim atılamadı.');
        exit("HATA: OneSignal ayarlari panele girilmemis.");
    }

    $target_url = get_site_url() . "/wp-admin/admin.php?page=ql-ai-questions";
    
    $stores_str = implode(', ', array_keys($store_names_with_new));
    $content = array("en" => "Müşterilerden bekleyen toplam {$new_questions_count} yeni sorunuz var! Hemen yanıtlayın.");
    
    $fields = array(
        'app_id'             => $saved_app_id,
        'included_segments'  => array('All'),
        'contents'           => $content,
        'headings'           => array("en" => "📩 Yeni Sorular: {$stores_str}"),
        'ios_sound'          => 'default',
        'url'                => $target_url
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8', 'Authorization: Basic ' . $saved_rest_key));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    $os_res = curl_exec($ch);
    $os_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($os_http !== 200) {
        QualityLife_API_Services::log_error('ONESIGNAL_API_ERROR', 'OneSignal API Bildirim Gönderme Başarısız.', "HTTP: {$os_http} - Response: {$os_res}");
    }

    echo "Toplam {$new_questions_count} soru için bildirim gönderildi.";
} else {
    echo "Yeni soru yok. Beklemede...";
}