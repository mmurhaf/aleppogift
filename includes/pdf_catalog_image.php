<?php
/**
 * Build a small JPEG data URL for catalog PDF export.
 * Returns null when the file is missing, not an image, or too large.
 */
function catalog_pdf_data_url($path, $maxEdge = 260, $quality = 60)
{
    if (!is_string($path) || !is_file($path)) {
        return null;
    }

    $data = @file_get_contents($path);
    if ($data === false || strlen($data) === 0 || strlen($data) >= 5000000) {
        return null;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $path) : '';
    if ($finfo) {
        finfo_close($finfo);
    }
    if (strpos((string) $mime, 'image/') !== 0) {
        return null;
    }

    if (function_exists('imagecreatefromstring')) {
        $src = @imagecreatefromstring($data);
        if ($src !== false) {
            $w = imagesx($src);
            $h = imagesy($src);
            if ($w > 0 && $h > 0) {
                $scale = min($maxEdge / $w, $maxEdge / $h, 1);
                $nw = max(1, (int) round($w * $scale));
                $nh = max(1, (int) round($h * $scale));
                $dst = imagecreatetruecolor($nw, $nh);
                $white = imagecolorallocate($dst, 255, 255, 255);
                imagefill($dst, 0, 0, $white);
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
                imagedestroy($src);
                ob_start();
                imagejpeg($dst, null, $quality);
                imagedestroy($dst);
                $jpeg = ob_get_clean();
                if (is_string($jpeg) && strlen($jpeg) > 0) {
                    return 'data:image/jpeg;base64,' . base64_encode($jpeg);
                }
            } else {
                imagedestroy($src);
            }
        }
    }

    $encoded = base64_encode($data);
    if ($encoded === false || $encoded === '') {
        return null;
    }

    return 'data:' . $mime . ';base64,' . $encoded;
}

function catalog_pdf_data_url_from_candidates(array $paths, $maxEdge = 260, $quality = 60)
{
    foreach ($paths as $path) {
        $url = catalog_pdf_data_url($path, $maxEdge, $quality);
        if ($url) {
            return $url;
        }
    }

    return null;
}
