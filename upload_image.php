<?php
/** رفع صورة للمحرر: التحقق من النوع الحقيقي، حدود الحجم والأبعاد، إعادة ترميز كاملة، اسم عشوائي، وحد أقصى للرفع. */
require __DIR__ . '/includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
$fail = function ($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
};
$user = current_user();
if ($user === null || !is_post()) {
    $fail('غير مسموح.', 403);
}
$sent = isset($_POST['csrf']) ? $_POST['csrf'] : '';
if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
    $fail('انتهت الجلسة، أعد تحميل الصفحة.', 403);
}
if (mute_message() !== null) {
    $fail('لا يمكنك رفع الصور الآن.', 403);
}
$limit = is_admin() ? 100 : 20;
if ((int) db_val('SELECT COUNT(*) FROM user_uploads WHERE user_id = ? AND created_at > (NOW() - INTERVAL 1 HOUR)', 'i', [$user['id']]) >= $limit) {
    $fail('تجاوزت الحد المسموح من الصور في الساعة.', 429);
}
$file = isset($_FILES['image']) ? $_FILES['image'] : null;
if (!is_array($file) || is_array($file['error']) || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
    $fail('تعذر رفع الصورة.');
}
if ((int) $file['size'] < 1 || (int) $file['size'] > 2 * 1024 * 1024) {
    $fail('الحد الأقصى لحجم الصورة 2 ميغابايت.');
}
$info = @getimagesize($file['tmp_name']);
$mime = class_exists('finfo') ? (string) (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) : '';
$types = ['image/png' => IMAGETYPE_PNG, 'image/jpeg' => IMAGETYPE_JPEG, 'image/webp' => IMAGETYPE_WEBP];
if (!$info || !isset($types[$mime]) || $info[2] !== $types[$mime] || !function_exists('imagecreatefromstring')) {
    $fail('الصيغ المسموحة: PNG أو JPG أو WEBP.');
}
if ($info[0] < 16 || $info[1] < 16 || $info[0] > 4000 || $info[1] > 4000) {
    $fail('مقاس الصورة غير مناسب (من 16 إلى 4000 بكسل).');
}
$img = @imagecreatefromstring((string) file_get_contents($file['tmp_name']));
if (!$img) {
    $fail('تعذرت معالجة الصورة.');
}
$w = imagesx($img);
$h = imagesy($img);
$nw = min($w, 1200);
$nh = (int) round($h * $nw / $w);
$out = imagecreatetruecolor($nw, $nh);
$isPng = $mime === 'image/png';
if ($isPng) {
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
} else {
    imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
}
imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
$dir = __DIR__ . '/assets/uploads/img';
if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
    $fail('تعذر إنشاء مجلد الصور.', 500);
}
if (!is_file($dir . '/.htaccess')) {
    @copy(__DIR__ . '/assets/images/.htaccess', $dir . '/.htaccess');
}
$name = bin2hex(random_bytes(12)) . ($isPng ? '.png' : '.jpg');
$ok = $isPng ? imagepng($out, $dir . '/' . $name, 6) : imagejpeg($out, $dir . '/' . $name, 84);
if (!$ok) {
    $fail('فشل حفظ الصورة.', 500);
}
db_exec('INSERT INTO user_uploads (user_id, name) VALUES (?, ?)', 'is', [$user['id'], $name]);
echo json_encode(['url' => url('assets/uploads/img/' . $name)]);
