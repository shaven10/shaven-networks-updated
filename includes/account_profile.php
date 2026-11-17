<?php
function shaven_profile_photo_path($photo) {
    $photo = str_replace('\\', '/', (string)$photo);
    if (!preg_match('#^uploads/profiles/[A-Za-z0-9._-]+$#', $photo)) {
        return '';
    }
    $dir = realpath(SHAVEN_ROOT . '/uploads/profiles');
    $file = realpath(SHAVEN_ROOT . '/' . $photo);
    if (!$dir || !$file || strpos($file, $dir) !== 0 || !is_file($file)) {
        return '';
    }
    return $file;
}

function shaven_store_profile_photo($userId, array $file) {
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'path' => null, 'error' => ''];
    }
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        return ['ok' => false, 'path' => '', 'error' => 'Use a picture smaller than 2 MB.'];
    }
    if ($error !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'path' => '', 'error' => 'The picture was not received. Choose it again.'];
    }
    if ((int)($file['size'] ?? 0) > 2 * 1024 * 1024) {
        return ['ok' => false, 'path' => '', 'error' => 'Use a picture smaller than 2 MB.'];
    }
    $info = @getimagesize($file['tmp_name']);
    $makers = [
        IMAGETYPE_JPEG => 'imagecreatefromjpeg',
        IMAGETYPE_PNG => 'imagecreatefrompng',
        IMAGETYPE_GIF => 'imagecreatefromgif',
    ];
    if (defined('IMAGETYPE_WEBP')) {
        $makers[IMAGETYPE_WEBP] = 'imagecreatefromwebp';
    }
    $maker = $makers[$info[2] ?? 0] ?? '';
    if (!$info || $maker === '' || !function_exists($maker) || !function_exists('imagecreatetruecolor')) {
        return ['ok' => false, 'path' => '', 'error' => 'Use a JPG, PNG, or GIF picture.'];
    }
    $src = @$maker($file['tmp_name']);
    if (!$src) {
        return ['ok' => false, 'path' => '', 'error' => 'That picture could not be read.'];
    }
    $width = imagesx($src);
    $height = imagesy($src);
    $edge = max($width, $height);
    $scale = $edge > 480 ? 480 / $edge : 1;
    $newW = max(1, (int)round($width * $scale));
    $newH = max(1, (int)round($height * $scale));
    $canvas = imagecreatetruecolor($newW, $newH);
    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagefilledrectangle($canvas, 0, 0, $newW, $newH, $white);
    imagecopyresampled($canvas, $src, 0, 0, 0, 0, $newW, $newH, $width, $height);
    imagedestroy($src);
    $dir = SHAVEN_ROOT . '/uploads/profiles';
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        imagedestroy($canvas);
        return ['ok' => false, 'path' => '', 'error' => 'The picture folder is not available.'];
    }
    $name = 'user-' . (int)$userId . '-' . bin2hex(random_bytes(4)) . '.jpg';
    $saved = imagejpeg($canvas, $dir . '/' . $name, 86);
    imagedestroy($canvas);
    if (!$saved) {
        return ['ok' => false, 'path' => '', 'error' => 'The picture was not saved.'];
    }
    return ['ok' => true, 'path' => 'uploads/profiles/' . $name, 'error' => ''];
}

function shaven_save_account_profile($conn, $userId, array $input, array $file) {
    $userId = (int)$userId;
    $rows = shaven_rows(
        $conn,
        "SELECT id, username, password, photo, subscriber_id, sys_level, status FROM tbl_sys_users WHERE id=? LIMIT 1",
        'i',
        [$userId]
    );
    if (!$rows || (int)$rows[0]['status'] !== 1) {
        return ['ok' => false, 'error' => 'This account is not active.'];
    }
    $user = $rows[0];
    $name = trim((string)($input['fullname'] ?? ''));
    $username = trim((string)($input['username'] ?? ''));
    $current = (string)($input['current_password'] ?? '');
    $password = (string)($input['password'] ?? '');
    $confirm = (string)($input['password_confirm'] ?? '');
    $address = trim((string)($input['s_address'] ?? ''));
    if ($name === '' || strlen($name) > 255) {
        return ['ok' => false, 'error' => 'Enter the name for this account.'];
    }
    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,31}$/', $username)) {
        return ['ok' => false, 'error' => 'Use a username of 3 to 32 letters, numbers, dots, or dashes.'];
    }
    $taken = shaven_rows($conn, "SELECT id FROM tbl_sys_users WHERE username=? AND id<>? LIMIT 1", 'si', [$username, $userId]);
    if ($taken) {
        return ['ok' => false, 'error' => 'That username is already in use.'];
    }
    if ($password !== '' || $confirm !== '') {
        if ($password !== $confirm) {
            return ['ok' => false, 'error' => 'The new password and the confirmation do not match.'];
        }
        if (strlen($password) < 4) {
            return ['ok' => false, 'error' => 'Use a password of at least 4 characters.'];
        }
        if (!hash_equals((string)$user['password'], $current)) {
            return ['ok' => false, 'error' => 'The current password does not match.'];
        }
    }
    $subscriberId = (int)$user['subscriber_id'];
    $service = null;
    if ((int)$user['sys_level'] === 4 && $subscriberId > 0) {
        $serviceRows = shaven_rows($conn, "SELECT id, s_address FROM tbl_subs_profile WHERE id=? LIMIT 1", 'i', [$subscriberId]);
        if (!$serviceRows) {
            return ['ok' => false, 'error' => 'The subscriber record for this login was not found.'];
        }
        if (strlen($address) > 255) {
            return ['ok' => false, 'error' => 'Shorten the address to 255 characters.'];
        }
        $service = $serviceRows[0];
    }
    $photo = shaven_store_profile_photo($userId, $file);
    if (!$photo['ok']) {
        return ['ok' => false, 'error' => $photo['error']];
    }
    $remove = !empty($input['remove_photo']) && $photo['path'] === null;
    $nextPhoto = (string)($user['photo'] ?? '');
    if ($photo['path']) {
        $nextPhoto = $photo['path'];
    } elseif ($remove) {
        $nextPhoto = '';
    }
    mysqli_begin_transaction($conn);
    $saved = shaven_exec(
        $conn,
        "UPDATE tbl_sys_users SET sys_user_fullname=?, username=?, photo=? WHERE id=?",
        'sssi',
        [$name, $username, $nextPhoto, $userId]
    );
    if ($saved['ok'] && $password !== '') {
        $saved = shaven_exec($conn, "UPDATE tbl_sys_users SET password=? WHERE id=?", 'si', [$password, $userId]);
    }
    if ($saved['ok'] && $service) {
        $saved = shaven_exec(
            $conn,
            "UPDATE tbl_subs_profile SET s_name=?, s_address=? WHERE id=?",
            'ssi',
            [$name, $address, $subscriberId]
        );
    }
    if (!$saved['ok']) {
        mysqli_rollback($conn);
        $newFile = shaven_profile_photo_path((string)$photo['path']);
        if ($newFile !== '') {
            unlink($newFile);
        }
        return ['ok' => false, 'error' => 'The account profile was not saved.'];
    }
    mysqli_commit($conn);
    if ($photo['path'] || $remove) {
        $oldFile = shaven_profile_photo_path($user['photo'] ?? '');
        $newFile = shaven_profile_photo_path($nextPhoto);
        if ($oldFile !== '' && $oldFile !== $newFile) {
            unlink($oldFile);
        }
    }
    $_SESSION['fullname'] = $name;
    $_SESSION['photo'] = $nextPhoto;
    return ['ok' => true, 'error' => ''];
}
