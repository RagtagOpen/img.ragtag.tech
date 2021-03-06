<?php
  // if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== 'images.dev.dryan.net'):
  //   error_reporting(0);
  // endif;

  require_once 'vendor/autoload.php';

  try {
    $dotenv = new Dotenv\Dotenv(__DIR__);
    $dotenv->load();
  } catch (\Exception $e) {
    // if this fails, we're in production
  }

  if(get($_ENV['SENTRY_DSN'])):
    $sentry_opts = array();
    $sentry_opts['dsn'] = $_ENV['SENTRY_DSN'];
    if(get($_ENV['HEROKU_SLUG_COMMIT'])):
      $sentry_opts['release'] = get($_ENV['HEROKU_SLUG_COMMIT']);
    endif;
    if(get($_ENV['SENTRY_ENVIRONMENT'])):
      $sentry_opts['environment'] = get($_ENV['SENTRY_ENVIRONMENT']);
    endif;
    Sentry\init($sentry_opts);
  endif;

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
    'gif' => 'image/gif'
  );

  $formats = array(
    'JPEG' => IMAGETYPE_JPEG,
    'JPG' => IMAGETYPE_JPEG,
    'PNG' => IMAGETYPE_PNG,
    'GIF' => IMAGETYPE_GIF,
    'jpeg' => IMAGETYPE_JPEG,
    'jpg' => IMAGETYPE_JPEG,
    'png' => IMAGETYPE_PNG,
    'gif' => IMAGETYPE_GIF
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
    header('Expires: Thu, 01 Jan 1970 00:00:01 GMT');
    header('Cache-Control: private, no-cache');
    if($request_ip_whitelisted):
      header('X-Request-IP: ' . $request_ip);
      header('X-Imagick-Supported: ' . implode(',', \Imagick::queryformats()));
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

  function autoRotateImage($image) {
    // from http://php.net/manual/en/imagick.getimageorientation.php#111448
      $image = new Imagick($image);
      $orientation = $image->getImageOrientation();
      $rotated = 0;

      switch($orientation) {
          case imagick::ORIENTATION_BOTTOMRIGHT:
              $image->rotateimage("#000", 180); // rotate 180 degrees
              $rotated = 180;
          break;

          case imagick::ORIENTATION_RIGHTTOP:
              $image->rotateimage("#000", 90); // rotate 90 degrees CW
              $rotated = 90;
          break;

          case imagick::ORIENTATION_LEFTBOTTOM:
              $image->rotateimage("#000", -90); // rotate 90 degrees CCW
              $rotated = -90;
          break;
      }

      // Now that it's auto-rotated, make sure the EXIF data is correct in case the EXIF gets saved with the image!
      $image->setImageOrientation(imagick::ORIENTATION_TOPLEFT);

      return $image;
  }

  $original_extension = pathinfo($image_path, PATHINFO_EXTENSION);

  if(in_array($original_extension, array_keys($formats))):

    $image = ImageResize::createFromString($image);

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
      $image->save(
        $temp_image,
        $formats[$options->fm]
      );
      $content_type = get($formats[$options->fm], $image->source_type);
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

    $image = autoRotateImage($temp_image);
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
