<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

ob_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit();
}

function respond($success, $message = '', $data = null) {
    ob_end_clean();
    $out = array('success' => $success, 'message' => $message);
    if ($data !== null) $out['data'] = $data;
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

function read_json($file) {
    if (!file_exists($file)) return array();
    $content = file_get_contents($file);
    if ($content === false) return array();
    $data = json_decode($content, true);
    return is_array($data) ? $data : array();
}

function write_json($file, $data) {
    $dir = dirname($file);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            respond(false, 'لا يمكن إنشاء مجلد البيانات - تحقق من صلاحيات الخادم');
        }
    }
    if (!is_writable($dir)) {
        respond(false, 'لا توجد صلاحية كتابة على مجلد data/ - يرجى منح صلاحية 755 أو 777');
    }
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $result = file_put_contents($file, $json, LOCK_EX);
    if ($result === false) {
        respond(false, 'فشل حفظ البيانات - تحقق من صلاحيات الملف');
    }
    return true;
}

$action = isset($_GET['action']) ? trim($_GET['action']) : (isset($_POST['action']) ? trim($_POST['action']) : '');

// ===================== العقارات =====================

if ($action === 'save_property') {
    $data_file = __DIR__ . '/data/properties.json';
    $properties = read_json($data_file);

    $id      = (isset($_POST['id']) && $_POST['id'] !== '') ? intval($_POST['id']) : null;
    $title   = isset($_POST['title'])   ? trim($_POST['title'])   : '';
    $type    = isset($_POST['type'])    ? trim($_POST['type'])    : '';
    $city    = isset($_POST['city'])    ? trim($_POST['city'])    : '';
    $price   = isset($_POST['price'])   ? floatval($_POST['price'])  : 0;
    $area    = isset($_POST['area'])    ? floatval($_POST['area'])   : 0;
    $details = isset($_POST['details']) ? trim($_POST['details']) : '';
    $date    = isset($_POST['date'])    ? trim($_POST['date'])    : date('Y-m-d');
    $featured = (isset($_POST['featured']) && $_POST['featured'] === '1');
    $sold    = (isset($_POST['sold']) && $_POST['sold'] === '1');
    $owner_name  = isset($_POST['owner_name'])  ? trim($_POST['owner_name'])  : '';
    $owner_phone = isset($_POST['owner_phone']) ? trim($_POST['owner_phone']) : '';
    $location_url = isset($_POST['location_url']) ? trim($_POST['location_url']) : '';

    if (empty($title)) {
        respond(false, 'عنوان العقار مطلوب');
    }

    $existing_images = array();
    if (isset($_POST['existing_images']) && $_POST['existing_images'] !== '') {
        $decoded = json_decode($_POST['existing_images'], true);
        if (is_array($decoded)) $existing_images = $decoded;
    }

    $uploaded_images = array();
    // يدعم الاسمين: images أو images[]
    $files_key = isset($_FILES['images']) ? 'images' : null;
    if ($files_key && !empty($_FILES[$files_key]['name'])) {
        $upload_dir = __DIR__ . '/uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $allowed = array('jpg', 'jpeg', 'png', 'gif', 'webp');
        $files = $_FILES[$files_key];

        // دعم ملف واحد أو متعدد
        $names   = is_array($files['name'])     ? $files['name']     : array($files['name']);
        $tmps    = is_array($files['tmp_name']) ? $files['tmp_name'] : array($files['tmp_name']);
        $errors  = is_array($files['error'])    ? $files['error']    : array($files['error']);
        $count   = count($names);

        for ($i = 0; $i < $count; $i++) {
            if (empty($names[$i])) continue;
            if ($errors[$i] !== UPLOAD_ERR_OK) continue;
            $ext = strtolower(pathinfo($names[$i], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) continue;
            $filename = 'prop_' . time() . '_' . $i . '_' . uniqid() . '.' . $ext;
            $dest = $upload_dir . $filename;
            if (move_uploaded_file($tmps[$i], $dest)) {
                $uploaded_images[] = 'uploads/' . $filename;
            }
        }
    }

    $all_images = array_merge($existing_images, $uploaded_images);

    if ($id) {
        $found = false;
        foreach ($properties as $key => $prop) {
            if (intval($prop['id']) === $id) {
                $properties[$key]['title']    = $title;
                $properties[$key]['type']     = $type;
                $properties[$key]['city']     = $city;
                $properties[$key]['price']    = $price;
                $properties[$key]['area']     = $area;
                $properties[$key]['details']  = $details;
                $properties[$key]['featured'] = $featured;
                $properties[$key]['sold']     = $sold;
                $properties[$key]['owner_name']  = $owner_name;
                $properties[$key]['owner_phone'] = $owner_phone;
                $properties[$key]['location_url'] = $location_url;
                $properties[$key]['images']   = $all_images;
                if (!isset($properties[$key]['date']) || empty($properties[$key]['date'])) {
                    $properties[$key]['date'] = $date;
                }
                $found = true;
                break;
            }
        }
        if (!$found) {
            respond(false, 'العقار غير موجود (ID: ' . $id . ')');
        }
    } else {
        $max_id = 0;
        foreach ($properties as $prop) {
            if (intval($prop['id']) > $max_id) $max_id = intval($prop['id']);
        }
        $new_prop = array(
            'id'       => $max_id + 1,
            'title'    => $title,
            'type'     => $type,
            'city'     => $city,
            'price'    => $price,
            'area'     => $area,
            'details'  => $details,
            'date'     => $date,
            'featured' => $featured,
            'sold'     => $sold,
            'owner_name'  => $owner_name,
            'owner_phone' => $owner_phone,
            'location_url' => $location_url,
            'images'   => $all_images
        );
        array_unshift($properties, $new_prop);
    }

    write_json($data_file, $properties);
    respond(true, 'تم الحفظ بنجاح');
}

if ($action === 'delete_property') {
    $data_file = __DIR__ . '/data/properties.json';
    $id = intval(isset($_POST['id']) ? $_POST['id'] : (isset($_GET['id']) ? $_GET['id'] : 0));
    if (!$id) respond(false, 'معرّف العقار مطلوب');
    $properties = read_json($data_file);
    $new_props = array();
    foreach ($properties as $p) {
        if (intval($p['id']) !== $id) $new_props[] = $p;
    }
    write_json($data_file, $new_props);
    respond(true, 'تم الحذف بنجاح');
}

if ($action === 'get_properties') {
    $properties = read_json(__DIR__ . '/data/properties.json');
    respond(true, '', $properties);
}

if ($action === 'get_property') {
    $id = intval(isset($_GET['id']) ? $_GET['id'] : 0);
    $properties = read_json(__DIR__ . '/data/properties.json');
    foreach ($properties as $prop) {
        if (intval($prop['id']) === $id) respond(true, '', $prop);
    }
    respond(false, 'العقار غير موجود');
}

if ($action === 'delete_image') {
    $img_path = isset($_POST['path'])    ? trim($_POST['path'])      : '';
    $prop_id  = isset($_POST['prop_id']) ? intval($_POST['prop_id']) : 0;
    $data_file = __DIR__ . '/data/properties.json';
    $properties = read_json($data_file);
    foreach ($properties as $key => $prop) {
        if (intval($prop['id']) === $prop_id) {
            $new_images = array();
            foreach ($prop['images'] as $img) {
                if ($img !== $img_path) $new_images[] = $img;
            }
            $properties[$key]['images'] = $new_images;
            break;
        }
    }
    write_json($data_file, $properties);
    if (!empty($img_path)) {
        $full_path = __DIR__ . '/' . $img_path;
        if (file_exists($full_path)) @unlink($full_path);
    }
    respond(true, 'تم حذف الصورة');
}

// ===================== الطلبات =====================

if ($action === 'save_request') {
    $data_file = __DIR__ . '/data/requests.json';
    $requests = read_json($data_file);

    $id      = (isset($_POST['id']) && $_POST['id'] !== '') ? intval($_POST['id']) : null;
    $title   = isset($_POST['title'])   ? trim($_POST['title'])   : '';
    $type    = isset($_POST['type'])    ? trim($_POST['type'])    : '';
    $city    = isset($_POST['city'])    ? trim($_POST['city'])    : '';
    $budget  = isset($_POST['budget'])  ? floatval($_POST['budget']) : 0;
    $details = isset($_POST['details']) ? trim($_POST['details']) : '';
    $owner_name = isset($_POST['owner_name']) ? trim($_POST['owner_name']) : '';
    $owner_phone = isset($_POST['owner_phone']) ? trim($_POST['owner_phone']) : '';
    $date    = date('Y-m-d');

    if (empty($title)) respond(false, 'عنوان الطلب مطلوب');

    if ($id) {
        foreach ($requests as $key => $req) {
            if (intval($req['id']) === $id) {
                $requests[$key]['title']   = $title;
                $requests[$key]['type']    = $type;
                $requests[$key]['city']    = $city;
                $requests[$key]['budget']  = $budget;
                $requests[$key]['details'] = $details;
                $requests[$key]['owner_name'] = $owner_name;
                $requests[$key]['owner_phone'] = $owner_phone;
                break;
            }
        }
    } else {
        $max_id = 0;
        foreach ($requests as $req) {
            if (intval($req['id']) > $max_id) $max_id = intval($req['id']);
        }
        array_unshift($requests, array(
            'id'      => $max_id + 1,
            'title'   => $title,
            'type'    => $type,
            'city'    => $city,
            'budget'  => $budget,
            'details' => $details,
            'owner_name' => $owner_name,
            'owner_phone' => $owner_phone,
            'date'    => $date
        ));
    }

    write_json($data_file, $requests);
    respond(true, 'تم الحفظ بنجاح');
}

if ($action === 'delete_request') {
    $data_file = __DIR__ . '/data/requests.json';
    $id = intval(isset($_POST['id']) ? $_POST['id'] : (isset($_GET['id']) ? $_GET['id'] : 0));
    if (!$id) respond(false, 'معرّف الطلب مطلوب');
    $requests = read_json($data_file);
    $new_reqs = array();
    foreach ($requests as $r) {
        if (intval($r['id']) !== $id) $new_reqs[] = $r;
    }
    write_json($data_file, $new_reqs);
    respond(true, 'تم الحذف بنجاح');
}

if ($action === 'get_requests') {
    $requests = read_json(__DIR__ . '/data/requests.json');
    respond(true, '', $requests);
}

if ($action === 'get_request') {
    $id = intval(isset($_GET['id']) ? $_GET['id'] : 0);
    $requests = read_json(__DIR__ . '/data/requests.json');
    foreach ($requests as $req) {
        if (intval($req['id']) === $id) respond(true, '', $req);
    }
    respond(false, 'الطلب غير موجود');
}

respond(false, 'إجراء غير معروف: ' . $action);
