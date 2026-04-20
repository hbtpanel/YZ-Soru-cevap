<?php
// UZMAN DOKUNUŞU: Eklenti içinden kök dizindeki wp-load.php'yi bulma navigasyonu
$wp_root = dirname(dirname(dirname(dirname(__FILE__)))); 
if (file_exists($wp_root . '/wp-load.php')) {
    define('SHORTINIT', false);
    require_once($wp_root . '/wp-load.php');
} else {
    die('HATA: wp-load.php bulunamadi. Nöbetci calisamiyor.');
}

global $wpdb;

// 1. Mağaza Ayarlarını Al
$stores = get_option('ql_trendyol_stores', []);
if (empty($stores)) exit('Mağaza bulunamadı.');

$new_question_found = false;

foreach ($stores as $store_id => $store_data) {
    $api_key = $store_data['api_key'];
    $api_secret = $store_data['api_secret'];
    $seller_id = $store_data['seller_id'];

    // Trendyol'dan son 10 soruyu çek
    $url = "https://api.trendyol.com/sapigw/sellers/$seller_id/questions?status=WAITING_FOR_ANSWER&size=10";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . base64_encode($api_key . ':' . $api_secret)]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    if (isset($data['content']) && !empty($data['content'])) {
        foreach ($data['content'] as $q) {
            $q_id = $q['id'];
            // Veritabanında bu soru var mı bak
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}ql_all_questions WHERE trendyol_id = %s", $q_id));
            
            if (!$exists) {
                // Yeni soru bulundu!
                $new_question_found = true;
                break 2; // İç içe döngülerden çık
            }
        }
    }
}

// 2. Yeni soru varsa OneSignal üzerinden BİLDİRİM (Push) gönder
if ($new_question_found) {
    // Şifreleri doğrudan WordPress veritabanından çek (Güvenli Yöntem)
    $saved_app_id = get_option('ql_onesignal_app_id', '');
    $saved_rest_key = get_option('ql_onesignal_rest_key', '');

    if (empty($saved_app_id) || empty($saved_rest_key)) {
        exit("HATA: OneSignal ayarlari panele girilmemis. Bildirim atildi sayilamaz.");
    }

    $content = array("en" => "Trendyol Mağazanızda Yeni Bir Soru Var! Hemen cevaplayın.");
    $fields = array(
        'app_id' => $saved_app_id,
        'included_segments' => array('All'),
        'contents' => $content,
        'headings' => array("en" => "📩 Yeni Soru Geldi!"),
        'ios_sound' => 'notification_sound.wav' 
    );

    $fields = json_encode($fields);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8', 'Authorization: Basic ' . $saved_rest_key));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_exec($ch);
    curl_close($ch);
    echo "Bildirim Gonderildi.";
} else {
    echo "Yeni soru yok. Beklemede...";
}