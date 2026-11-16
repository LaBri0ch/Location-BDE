<?php
declare(strict_types=1);

/* Photos des objets : dossier uploads/items/, accessible depuis le web. */

const IMAGE_DIR = ROOT_DIR . '/uploads/items';
const IMAGE_NAME_RE = '/^[a-f0-9]{24}\.(jpg|png|webp)$/';
const IMAGE_MAX_SIDE = 1200;
const IMAGE_MAX_PIXELS = 40000000;

function item_image_url(?string $file): string
{
    return ($file !== null && preg_match(IMAGE_NAME_RE, $file)) ? url('uploads/items/' . $file) : '';
}

function delete_item_image(?string $file): void
{
    if ($file !== null && preg_match(IMAGE_NAME_RE, $file)) {
        @unlink(IMAGE_DIR . '/' . $file);
    }
}

function human_bytes(string $ini): string
{
    return $ini === '' ? '2 Mo' : str_replace(['M', 'K', 'G'], [' Mo', ' Ko', ' Go'], $ini);
}

/**
 * Traite le fichier envoyé par le formulaire.
 * Retourne ['file' => nom enregistré ou null si aucun envoi, 'error' => message ou null].
 * Avec l'extension GD, l'image est recompressée en JPEG et réduite : cela retire aussi
 * toute donnée cachée dans le fichier. Sans GD, seuls les vrais JPEG/PNG/WebP légers sont gardés.
 */
function save_item_image(?array $f): array
{
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['file' => null, 'error' => null];
    }
    switch ($f['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['file' => null, 'error' => 'La photo est trop lourde pour le serveur (limite : ' . human_bytes((string)ini_get('upload_max_filesize')) . '). Réduis-la ou choisis-en une plus légère.'];
        default:
            return ['file' => null, 'error' => 'L\'envoi de la photo a échoué. Réessaie avec une autre image.'];
    }
    $tmp = (string)$f['tmp_name'];
    if (!is_uploaded_file($tmp)) {
        return ['file' => null, 'error' => 'L\'envoi de la photo a échoué. Réessaie.'];
    }

    $info = @getimagesize($tmp);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($types[$info[2]])) {
        return ['file' => null, 'error' => 'Ce fichier n\'est pas une image JPG, PNG ou WebP.'];
    }
    [$w, $h] = [(int)$info[0], (int)$info[1]];
    if ($w < 1 || $h < 1 || $w * $h > IMAGE_MAX_PIXELS) {
        return ['file' => null, 'error' => 'Cette image est trop grande (plus de 40 mégapixels). Réduis-la avant de l\'envoyer.'];
    }

    if (!is_dir(IMAGE_DIR) && !@mkdir(IMAGE_DIR, 0755, true)) {
        return ['file' => null, 'error' => 'Impossible de créer le dossier « uploads/items ». Crée-le à la main et rends-le accessible en écriture.'];
    }
    $base = bin2hex(random_bytes(12));

    $canResize = function_exists('imagecreatetruecolor') && function_exists('imagejpeg');
    $loader = [IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_WEBP => 'imagecreatefromwebp'][$info[2]];
    if ($canResize && function_exists($loader)) {
        $src = @$loader($tmp);
        if ($src !== false) {
            if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
                $exif = @exif_read_data($tmp);
                $rot = [3 => 180, 6 => -90, 8 => 90][(int)($exif['Orientation'] ?? 1)] ?? 0;
                if ($rot !== 0 && function_exists('imagerotate')) {
                    $r = @imagerotate($src, $rot, 0);
                    if ($r !== false) {
                        $src = $r;
                        [$w, $h] = [imagesx($src), imagesy($src)];
                    }
                }
            }
            $scale = min(1.0, IMAGE_MAX_SIDE / max($w, $h));
            $nw = max(1, (int)round($w * $scale));
            $nh = max(1, (int)round($h * $scale));
            $dst = imagecreatetruecolor($nw, $nh);
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));   // fond blanc pour les PNG transparents
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            $name = $base . '.jpg';
            $ok = @imagejpeg($dst, IMAGE_DIR . '/' . $name, 82);
            if (!$ok) {
                return ['file' => null, 'error' => 'Impossible d\'enregistrer la photo. Vérifie que le dossier « uploads/items » est accessible en écriture.'];
            }
            return ['file' => $name, 'error' => null];
        }
    }

    // Sans GD : on garde le fichier tel quel, mais seulement s'il est léger.
    if ((int)$f['size'] > 1500000) {
        return ['file' => null, 'error' => 'La photo dépasse 1,5 Mo. Réduis-la avant de l\'envoyer.'];
    }
    $name = $base . '.' . $types[$info[2]];
    if (!@move_uploaded_file($tmp, IMAGE_DIR . '/' . $name)) {
        return ['file' => null, 'error' => 'Impossible d\'enregistrer la photo. Vérifie que le dossier « uploads/items » est accessible en écriture.'];
    }
    return ['file' => $name, 'error' => null];
}
