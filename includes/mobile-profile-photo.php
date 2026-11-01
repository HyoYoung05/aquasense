<?php
declare(strict_types=1);

final class MobileProfilePhotoException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}

function mobile_profile_photo_validate(array $file, int $maximumBytes = 5_242_880): array
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        throw new MobileProfilePhotoException('Choose a profile photo to upload.');
    }
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        throw new MobileProfilePhotoException('The profile photo must be 5 MB or smaller.');
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new MobileProfilePhotoException('The profile photo could not be uploaded.');
    }
    $temporary = (string) ($file['tmp_name'] ?? '');
    $size = (int) ($file['size'] ?? 0);
    if ($temporary === '' || !is_uploaded_file($temporary) || $size < 1) {
        throw new MobileProfilePhotoException('Choose a valid profile photo.');
    }
    if ($size > $maximumBytes) {
        throw new MobileProfilePhotoException('The profile photo must be 5 MB or smaller.');
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporary) ?: '';
    $extension = $allowed[$mime] ?? null;
    $dimensions = @getimagesize($temporary);
    if ($extension === null || $dimensions === false || ($dimensions['mime'] ?? '') !== $mime) {
        throw new MobileProfilePhotoException('Use a valid JPEG, PNG, or WEBP profile photo.');
    }
    if ((int) $dimensions[0] < 64 || (int) $dimensions[1] < 64) {
        throw new MobileProfilePhotoException('The profile photo must be at least 64 by 64 pixels.');
    }
    return [
        'temporary_path' => $temporary,
        'mime_type' => $mime,
        'extension' => $extension,
    ];
}

function mobile_profile_photo_store(PDO $pdo, int $ownerId, array $file): array
{
    $photo = mobile_profile_photo_validate($file);
    $relativeDirectory = 'profile-photos';
    $directory = storage_path($relativeDirectory);
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not prepare profile-photo storage.');
    }
    $storedFilename = 'owner-' . $ownerId . '-' . bin2hex(random_bytes(20)) . '.' . $photo['extension'];
    $relativePath = $relativeDirectory . '/' . $storedFilename;
    $absolutePath = storage_path($relativePath);
    $oldQuery = $pdo->prepare('SELECT profile_photo_path FROM users WHERE id=?');
    $oldQuery->execute([$ownerId]);
    $oldRelativePath = $oldQuery->fetchColumn();
    if (!move_uploaded_file($photo['temporary_path'], $absolutePath)) {
        throw new RuntimeException('The profile photo could not be stored.');
    }
    @chmod($absolutePath, 0640);
    try {
        $query = $pdo->prepare('UPDATE users SET profile_photo_path=?,profile_photo_mime=?,
            profile_photo_updated_at=UTC_TIMESTAMP() WHERE id=?');
        $query->execute([$relativePath, $photo['mime_type'], $ownerId]);
    } catch (Throwable $error) {
        @unlink($absolutePath);
        throw $error;
    }
    if (is_string($oldRelativePath) && $oldRelativePath !== '') {
        $oldPath = storage_path($oldRelativePath);
        if ($oldPath !== $absolutePath && is_file($oldPath)) {
            @unlink($oldPath);
        }
    }
    return mobile_owner();
}

function mobile_profile_photo_stream(array $owner): never
{
    $query = db()->prepare('SELECT profile_photo_path,profile_photo_mime FROM users WHERE id=?');
    $query->execute([$owner['id']]);
    $photo = $query->fetch();
    if (!$photo || !is_string($photo['profile_photo_path']) || $photo['profile_photo_path'] === '') {
        api_fail('Profile photo was not found.', 404);
    }
    $path = storage_path($photo['profile_photo_path']);
    $mime = (string) ($photo['profile_photo_mime'] ?? '');
    if (!is_file($path) || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        api_fail('Profile photo was not found.', 404);
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($path));
    header('Content-Disposition: inline; filename="profile-photo.' . pathinfo($path, PATHINFO_EXTENSION) . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}
