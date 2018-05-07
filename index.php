<?php
  // if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== 'images.dev.dryan.net'):
  //   error_reporting(0);
  // endif;

  require_once 'vendor/autoload.php';

  use Gumlet\ImageResize;
  use Spatie\ImageOptimizer\OptimizerChainFactory;

  function get(&$var, $default=null) {
    return isset($var) ? $var : $default;
  }

  $request_ip = get($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REMOTE_ADDR']);
  $request_ip = explode(',', $request_ip);
  $request_ip = array_shift($request_ip);
  $request_ip = trim($request_ip);

  $request_ip_whitelisted = in_array($request_ip, explode(',', get($_ENV['WHITELISTED_IPS'], '')));

  $imagick_webp_support = in_array('WEBP', \Imagick::queryformats());

  if(isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN']):
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
  endif;
  if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS'):
    die();
  endif;

  $mimetypes = array(
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp'
  );

  $formats = array(
    'JPEG' => IMAGETYPE_JPEG,
    'JPG' => IMAGETYPE_JPEG,
    'PNG' => IMAGETYPE_PNG,
    'GIF' => IMAGETYPE_GIF,
    'WEBP' => IMAGETYPE_WEBP,
    'jpeg' => IMAGETYPE_JPEG,
    'jpg' => IMAGETYPE_JPEG,
    'png' => IMAGETYPE_PNG,
    'gif' => IMAGETYPE_GIF,
    'webp' => IMAGETYPE_WEBP
  );

  $partners = json_decode(get($_ENV['PARTNERS'], '{}'), TRUE);

  $default_partner_slug = get($_ENV['DEFAULT_PARTNER_SLUG'], NULL);
  $parts = array_values(array_filter(explode("/", $_SERVER['REQUEST_URI'])));
  $partner_slug = array_shift($parts);

  header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');

  if($partner_slug === 'health-check'):
    header('Content-Type: application/json');
    echo json_encode(
      array(
        'status' => 'OK',
        'php' => phpversion()
      )
    );
    die();
  elseif ($partner_slug === 'info'):
    header('X-Request-IP: ' . $request_ip);
    header('Cache-Control: private, no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:01 GMT');
    header('X-Imagick-Supported: ' . implode(',', \Imagick::queryformats()));
    if($request_ip_whitelisted):
      phpinfo();
    else:
      header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
      echo file_get_contents('404.html');
    endif;
    die();
  elseif ($partner_slug === 'dpr'):
    $url = implode("/", $parts);
    $url = explode("?", $url, 2);
    if(count($url) > 1):
      $params = $url[1];
    else:
      $params = '';
    endif;
    parse_str($params, $options);
    $dpr = isset($options['dpr']) ? floatval($options['dpr']) : 1;
    $dpr = max($dpr, 1.0);
    setcookie(
      'dpr',
      (string) $dpr,
      time() + (60*60*24*365),
      '/',
      $_SERVER['HTTP_HOST'],
      FALSE
    );
    header('Cache-Control: public, max-age=31536000');
    if(isset($options['f']) && $options['f'] === 'gif'):
      header('Content-Type: image/gif');
      echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    else:
      header('Content-Type: application/javascript');
      echo '';
    endif;
    die();
  endif;

  if($default_partner_slug):
    array_unshift($parts, $partner_slug);
    $partner_slug = $default_partner_slug;
  endif;

  if(!isset($partners[$partner_slug])):
    header('X-404: $partner_slug "' . $partner_slug . '" not found');
    header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
    echo file_get_contents('404.html');
    die();
  endif;

  if(count($parts) < 1):
    header('X-404: count($parts) < 1 ' . json_encode($parts));
    header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
    echo file_get_contents('404.html');
    die();
  endif;

  $partner_url = $partners[$partner_slug];

  $image_path = implode("/", $parts);
  $image_path = implode("/", array($partner_url, $image_path));
  $image_path = explode("?", $image_path, 2);
  if(count($image_path) > 1):
    $params = $image_path[1];
  else:
    $params = '';
  endif;
  $image_path = $image_path[0];
  parse_str($params, $options);
  $options = (object) $options;

  if(isset($_COOKIE['dpr'])):
    try {
      $options->dpr = ceil(floatval($_COOKIE['dpr']));
    } catch (Exception $e) {
    }
  endif;

  if(!isset($options->dpr)):
    $options->dpr = 1;
  endif;
  if(!isset($options->e)):
    $options->e = TRUE;
  else:
    $options->e = filter_var($options->e, FILTER_VALIDATE_BOOLEAN);
  endif;
  if(!isset($options->webp)):
    $options->webp = FALSE;
  else:
    $options->webp = filter_var($options->webp, FILTER_VALIDATE_BOOLEAN);
  endif;
  if(!isset($options->w)):
    $options->w = null;
  else:
    try {
      $options->w = intval($options->w);
    } catch (Exception $e) {
      $options->w = null;
    }
  endif;
  if(!isset($options->h)):
    $options->h = null;
  else:
    try {
      $options->h = intval($options->h);
    } catch (Exception $e) {
      $options->h = null;
    }
  endif;
  if(!isset($options->mw)):
    $options->mw = null;
  else:
    try {
      $options->mw = intval($options->mw);
    } catch (Exception $e) {
      $options->mw = null;
    }
  endif;
  if(!isset($options->mh)):
    $options->mh = null;
  else:
    try {
      $options->mh = intval($options->mh);
    } catch (Exception $e) {
      $options->mh = null;
    }
  endif;
  if(!isset($options->fit)):
    $options->fit = 'crop';
  endif;
  if(!isset($options->fm)):
    $options->fm = null;
  endif;

  header('X-Image-Options: ' . json_encode($options));

  $image = file_get_contents($image_path);
  $temp_image = tempnam('/tmp', 'IMAGE');

  if($image === FALSE):
    header('X-404: source image not found');
    header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
    echo file_get_contents('404.html');
    die();
  endif;

  $original_extension = pathinfo($image_path, PATHINFO_EXTENSION);

  if(in_array($original_extension, array_keys($formats))):

    $image = ImageResize::createFromString($image);

    // if max-width or max-height are set, override the width and height options
    if($options->mw && $image->getSourceWidth() > $options->mw):
      $options->w = $options->mw;
    endif;
    if($options->mh && $image->getSourceHeight() > $options->mh):
      $options->h = $options->mh;
    endif;

    if ($options->w && $options->h):
      if($options->fit === 'crop'):
        $image->crop(
          $options->w ? $options->w * $options->dpr : $options->w,
          $options->h ? $options->h * $options->dpr : $options->h,
          $allow_enlarge=$options->e
        );
      else:
        $image->resizeToBestFit(
          $options->w ? $options->w * $options->dpr : $options->w,
          $options->h ? $options->h * $options->dpr : $options->h,
          $allow_enlarge=$options->e
        );
      endif;
    elseif ($options->w):
      $image->resizeToWidth($options->w * $options->dpr, $allow_enlarge=$options->e);
    elseif ($options->h):
      $image->resizeToHeight($options->h * $options->dpr, $allow_enlarge=$options->e);
    else:
      // neither width nor height was specified, but we should resize to respect dpr
      $image->resizeToBestFit(
        $image->getSourceWidth() * $options->dpr,
        $image->getSourceHeight() * $options->dpr,
        $allow_enlarge=$options->e
      );
    endif;

    header('X-Image-Original-Width: ' . round($image->getSourceWidth()));
    header('X-Image-Original-Height: ' . round($image->getSourceHeight()));
    header('X-Image-Width: ' . round($image->getDestWidth()));
    header('X-Image-Height: ' . round($image->getDestHeight()));
    $content_type = $image->source_type;

    if(get($formats[$options->fm])):
      if(get($formats[$options->fm]) === IMAGETYPE_WEBP && $imagick_webp_support):
        if(!in_array($image->source_type, array(IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP))):
          throw Exception('Unsupported source format for conversion to webp');
        endif;
        $image->save(
          $temp_image
        );
        $magick = new Imagick($temp_image);
        $magick->setImageFormat('WEBP');
        $magick->setOption('webp:method', '6');
        $magick->setOption('webp:low-memory', 'true');
        $magick->setImageCompressionQuality(75);
        if($image->source_type === IMAGETYPE_PNG):
          $magick->setOption('webp:lossless', 'true');
          $magick->setImageAlphaChannel(imagick::ALPHACHANNEL_ACTIVATE);
          $magick->setBackgroundColor(new ImagickPixel('transparent'));
        endif;
        $magick->writeImage($temp_image);
        $content_type = IMAGETYPE_WEBP;
      else:
        $image->save(
          $temp_image,
          $formats[$options->fm]
        );
        if(get($formats[$options->fm]) === IMAGETYPE_WEBP):
          if (filesize($temp_image) % 2 == 1):
            file_put_contents($temp_image, "\0", FILE_APPEND);
            header('X-WebP-Padded: true');
          endif;
        endif;
        $content_type = get($formats[$options->fm], $image->source_type);
      endif;
    else:
      $image->save(
        $temp_image
      );
    endif;

    $acceptable = get($_SERVER['HTTP_ACCEPT'], '');
    $acceptable = explode(',', $acceptable);
    array_walk(
      $acceptable,
      function($item) {
        return trim($item);
      }
    );

    if(in_array('image/webp', $acceptable) && $imagick_webp_support && in_array($image->source_type, array(IMAGETYPE_PNG, IMAGETYPE_JPEG)) && $options->webp):
      $magick = new Imagick($temp_image);
      $magick->setImageFormat('WEBP');
      $magick->setOption('webp:method', '6');
      $magick->setOption('webp:low-memory', 'true');
      $magick->setImageCompressionQuality(50);
      if($magick->getFormat() === IMAGETYPE_PNG):
        $magick->setOption('webp:lossless', 'true');
        $magick->setImageAlphaChannel(imagick::ALPHACHANNEL_ACTIVATE);
        $magick->setBackgroundColor(new ImagickPixel('transparent'));
      endif;
      $magick->writeImage($temp_image);
      $content_type = IMAGETYPE_WEBP;
    endif;

    header('Content-Type: ' . image_type_to_mime_type($content_type));

  elseif ($original_extension === 'svg'):
    header('Content-Type: image/svg+xml');
    file_put_contents($temp_image, $image);
  endif;

  if($request_ip_whitelisted && isset($options->no_cache) && $options->no_cache):
    header('Cache-Control: private, no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:01 GMT');
  else:
    header('Cache-Control: public, max-age=31536000');
  endif;

  $optimizerChain = OptimizerChainFactory::create();
  $optimizerChain->optimize($temp_image);

  header('Content-Length: ' . filesize($temp_image));

  echo file_get_contents($temp_image);

  unlink($temp_image);

  die();
