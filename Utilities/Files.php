<?php declare(strict_types=1);
namespace Utilities;
use Utilities\Strings;
use Utilities\RateLimit;
class Files
{
    /**
     * Fail closed on incomplete, oversized or non-HTTP-uploaded files.
     * No remote content or $_FILES['type'] is trusted here.
     */
    private function validate_uploaded_file(?array $file, int $maxBytes): ?string
    {
        if (!is_array($file) || !isset($file['error'], $file['tmp_name'], $file['size'])
            || (int) $file['error'] !== UPLOAD_ERR_OK) {
            return 'Upload failed';
        }
        if (!is_string($file['tmp_name']) || !is_numeric($file['size'])
            || (int) $file['size'] <= 0 || (int) $file['size'] > $maxBytes) {
            return 'File size is invalid or too big';
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            return 'Invalid upload';
        }
        return null;
    }

    public function upload_image(string $token, ?array $file, bool $json_result = true): array
    {
        $result = ['error' => null, 'url' => null];
        $error = $this->validate_uploaded_file($file, 10 * 1024 * 1024);
        if ($error !== null) {
            $result['error'] = $error;
        } else {
            $info = @getimagesize($file['tmp_name']);
            if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF], true)
                || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 40000000) {
                $result['error'] = 'Unsupported image type or dimensions';
            } else {
                $extension = image_type_to_extension($info[2], true);
                $source = $_SERVER['DOCUMENT_ROOT'].'/Web/public/uploads/'.(new Strings())->random_string(128).$extension;
                $targetName = 'zhabbler_'.(new Strings())->random_string(128).'.jpeg';
                $target = $_SERVER['DOCUMENT_ROOT'].'/Web/public/uploads/'.$targetName;
                if (!move_uploaded_file($file['tmp_name'], $source)) {
                    $result['error'] = 'Could not save uploaded image';
                } else {
                    $this->convertImage($source, $target, 50);
                    if (!is_file($target) || filesize($target) === 0) {
                        $result['error'] = 'Could not convert uploaded image';
                    } else {
                        (new RateLimit())->increase_rate_limit($token);
                        $result['url'] = '/uploads/'.$targetName;
                    }
                }
            }
        }
        if ($json_result) {
            header('Content-Type: application/json');
            die(json_encode($result));
        }
        return $result;
    }

	public function compress_image(string $path, int $new_width): void
	{
		header("Content-Type: image/jpeg");
		$directory_is = "uploads";
		if(str_starts_with($path, '/uploads/')){
			$path = str_replace('/uploads/', "", $path);
		}else if(str_starts_with($path, BASE_URL.'uploads/')){
			$path = str_replace(BASE_URL.'uploads/', "", $path);
		}else if(str_starts_with($path, '/static/')){
			$directory_is = "static";
			$path = str_replace('/static/', "", $path);
		}else if(str_starts_with($path, BASE_URL.'static/')){
			$directory_is = "static";
			$path = str_replace(BASE_URL.'static/', "", $path);
		}

		$rate = 100;

		if (getimagesize($_SERVER['DOCUMENT_ROOT'].'/Web/public/'.$directory_is.'/'.$path)['mime'] == 'image/jpeg') 
			$mask = imagecreatefromjpeg($_SERVER['DOCUMENT_ROOT'].'/Web/public/'.$directory_is.'/'.$path);
		elseif (getimagesize($_SERVER['DOCUMENT_ROOT'].'/Web/public/'.$directory_is.'/'.$path)['mime'] == 'image/gif') 
			$mask = imagecreatefromgif($_SERVER['DOCUMENT_ROOT'].'/Web/public/'.$directory_is.'/'.$path);
		elseif (getimagesize($_SERVER['DOCUMENT_ROOT'].'/Web/public/'.$directory_is.'/'.$path)['mime'] == 'image/png') 
			$mask = imagecreatefrompng($_SERVER['DOCUMENT_ROOT'].'/Web/public/'.$directory_is.'/'.$path);
		else
			die;

		list($width, $height) = getimagesize($_SERVER['DOCUMENT_ROOT'].'/Web/public/'.$directory_is.'/'.$path);
		$ratio = $width/$height;
		$new_height = (int)round($new_width/$ratio);
		$new_file = imagecreatetruecolor($new_width, $new_height);
		imagecopyresampled($new_file, $mask, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
		imagejpeg($new_file,NULL,$rate);
	}

    public function upload_gif(string $token, array $file): void
    {
        $result = ['error' => null, 'url' => null];
        $error = $this->validate_uploaded_file($file, 4 * 1024 * 1024);
        if ($error !== null) {
            $result['error'] = $error;
        } else {
            $info = @getimagesize($file['tmp_name']);
            if ($info === false || $info[2] !== IMAGETYPE_GIF
                || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 40000000) {
                $result['error'] = 'File is not a valid GIF';
            } else {
                $name = 'zhabbler_animated_'.(new Strings())->random_string(128).'.gif';
                if (!move_uploaded_file($file['tmp_name'], $_SERVER['DOCUMENT_ROOT'].'/Web/public/uploads/'.$name)) {
                    $result['error'] = 'Could not save uploaded GIF';
                } else {
                    (new RateLimit())->increase_rate_limit($token);
                    $result['url'] = '/uploads/'.$name;
                }
            }
        }
        header('Content-Type: application/json');
        die(json_encode($result));
    }

	public function thumbnail_avatar_crop(string $path, string $output): void
	{
		list($width, $height) = getimagesize($path);
		$myImage = imagecreatefromjpeg($path);

		if($width > $height){
			$y = 0;
			$x = ($width - $height) / 2;
			$smallestSide = $height;
		}else{
			$x = 0;
			$y = ($height - $width) / 2;
			$smallestSide = $width;
		}

		$thumbSize = 250;
		$thumb = imagecreatetruecolor($thumbSize, $thumbSize);
		imagecopyresampled($thumb, $myImage, 0, 0, (int)$x, (int)$y, $thumbSize, $thumbSize, $smallestSide, $smallestSide);

		imagejpeg($thumb, $output);
		unlink($path);
	}

    public function upload_image_by_url(string $token, string $url, bool $json_result = true): array
    {
        // Security Core 0.1.0: refuse untrusted URL imports until a downloader
        // with IP pinning, DNS rebinding protection, redirects disabled,
        // bounded streaming and content validation is implemented.
        // This intentionally does not perform any network request.
        $result = ['error' => 'Remote image URL imports are temporarily disabled', 'url' => null];
        if ($json_result) {
            header('Content-Type: application/json');
            die(json_encode($result));
        }
        return $result;
    }

    public function upload_audio(string $token, ?array $file, bool $json_result = true): array
    {
        $result = ['error' => null, 'url' => null];
        $error = $this->validate_uploaded_file($file, 20 * 1024 * 1024);
        if ($error !== null) {
            $result['error'] = $error;
        } else {
            $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            if ($extension !== 'mp3' || !in_array($mime, ['audio/mpeg', 'audio/mp3', 'audio/x-mpeg'], true)) {
                $result['error'] = 'Only MP3 audio is allowed';
            } else {
                $name = 'zhabbler_audio_'.(new Strings())->random_string(128).'.mp3';
                if (!move_uploaded_file($file['tmp_name'], $_SERVER['DOCUMENT_ROOT'].'/Web/public/uploads/'.$name)) {
                    $result['error'] = 'Could not save uploaded audio';
                } else {
                    (new RateLimit())->increase_rate_limit($token);
                    $result['url'] = '/uploads/'.$name;
                }
            }
        }
        if ($json_result) {
            header('Content-Type: application/json');
            die(json_encode($result));
        }
        return $result;
    }

    public function upload_video(string $token, ?array $file, bool $json_result = true): array
    {
        $result = ['error' => null, 'url' => null];
        $error = $this->validate_uploaded_file($file, 50 * 1024 * 1024);
        if ($error !== null) {
            $result['error'] = $error;
        } else {
            $allowed = ['mp4', 'flv', 'webm', 'mkv', 'vob', 'ogv', 'ogg', 'avi', 'wmv', 'mov', 'mpeg', 'mpg', '3gp'];
            $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            if (!in_array($extension, $allowed, true)
                || !is_string($mime)
                || !(str_starts_with($mime, 'video/') || $mime === 'application/ogg'
                    || $mime === 'application/octet-stream')) {
                $result['error'] = 'File is not a supported video';
            } else {
                $source = $_SERVER['DOCUMENT_ROOT'].'/Web/public/uploads/'.(new Strings())->random_string(128).'.'.$extension;
                $targetName = 'zhabbler_'.(new Strings())->random_string(128).'.mp4';
                $target = $_SERVER['DOCUMENT_ROOT'].'/Web/public/uploads/'.$targetName;
                if (!move_uploaded_file($file['tmp_name'], $source)) {
                    $result['error'] = 'Could not save uploaded video';
                } elseif ($this->convertVideo($source, $target) !== '' || !is_file($target)
                          || filesize($target) === 0) {
                    $result['error'] = 'Could not convert uploaded video';
                    @unlink($target);
                } else {
                    (new RateLimit())->increase_rate_limit($token);
                    $result['url'] = '/uploads/'.$targetName;
                }
            }
        }
        if ($json_result) {
            header('Content-Type: application/json');
            die(json_encode($result));
        }
        return $result;
    }

    public function convertVideo(string $tempFilePath, string $finalFilePath): string
    {
        // Paths are safely quoted even when a server directory contains spaces.
        $cmd = 'ffmpeg -nostdin -y -i '.escapeshellarg($tempFilePath).' '.escapeshellarg($finalFilePath).' 2>&1';
        $outputLog = [];
        exec($cmd, $outputLog, $returnCode);
        @unlink($tempFilePath);
        return $returnCode !== 0 ? 'error' : '';
    }

	public function convertImage(string $originalImage, string $outputImage, int $quality): void 
	{
	    $exploded = explode('.',$originalImage);
	    $ext = $exploded[count($exploded) - 1]; 

	    if(preg_match('/jpg|jpeg/i',$ext)){
			$imageTmp = @imagecreatefromjpeg($originalImage);
		}else if(preg_match('/png/i',$ext)){
			$imageTmp = @imagecreatefrompng($originalImage);
		}else if(preg_match('/gif/i',$ext)){
			$imageTmp = @imagecreatefromgif($originalImage);
		}else if(preg_match('/bmp/i',$ext)){
			$imageTmp = @imagecreatefrombmp($originalImage);
		}else{
			die;
		}

	    imagejpeg($imageTmp, $outputImage, $quality);
	    imagedestroy($imageTmp);

		unlink($originalImage);
	}
}
