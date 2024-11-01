<?php
/**
 * Archivarix Content Loader
 *
 * See README.txt for instructions with NGINX and Apache 2.x web servers
 *
 * PHP version 5.6 or newer
 * Required extensions: PDO_SQLITE
 * Recommended extensions: mbstring
 *
 * LICENSE:
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @package    Archivarix.Loader
 * @version    0.1.221028
 * @author     Archivarix Team <hello@archivarix.com>
 * @telegram   https://t.me/archivarixsupport
 * @messenger  https://m.me/ArchivarixSupport
 * @copyright  2017-2022 Archivarix LLC
 * @license    https://www.gnu.org/licenses/gpl.html GNU GPLv3
 * @link       https://archivarix.com
 */

@ini_set( 'display_errors', 0 );

/**
 *  Do not edit under this comment
 */

const ARCHIVARIX_VERSION = '0.1.221028';
define( 'ARCHIVARIX_HTTP_HOST', isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : '' );

$LOADER = [
  'ARCHIVARIX_LOADER_MODE'           => 0,
  'ARCHIVARIX_PROTOCOL'              => 'any',
  'ARCHIVARIX_FIX_MISSING_IMAGES'    => 1,
  'ARCHIVARIX_FIX_MISSING_CSS'       => 1,
  'ARCHIVARIX_FIX_MISSING_JS'        => 1,
  'ARCHIVARIX_FIX_MISSING_ICO'       => 1,
  'ARCHIVARIX_REDIRECT_MISSING_HTML' => '/',
  'ARCHIVARIX_INCLUDE_CUSTOM'        => [],
  'ARCHIVARIX_CONTENT_PATH'          => '',
  'ARCHIVARIX_CACHE_CONTROL_MAX_AGE' => 31536000,
  'ARCHIVARIX_CUSTOM_DOMAIN'         => '',
  'ARCHIVARIX_SITEMAP_PATH'          => '',
  'ARCHIVARIX_CATCH_MISSING'         => 0,
  'ARCHIVARIX_QUERYLESS'             => 1,
  'ARCHIVARIX_BLOCK_BOTS'            => [],
];

$ARCHIVARIX_SETTINGS = array();

/**
 * @param string $sourcePath
 */
function loadLoaderSettings( $sourcePath )
{
  global $LOADER;
  $filename = $sourcePath . DIRECTORY_SEPARATOR . '.loader.settings.json';
  if ( !file_exists( $filename ) ) return;
  $data = json_decode( file_get_contents( $filename ), true );
  if ( json_last_error() !== JSON_ERROR_NONE ) return;
  if ( !is_array( $data ) ) return;
  $LOADER = array_merge( $LOADER, $data );
}

/**
 * @return string
 * @throws Exception
 */
function getSourceRoot()
{
  global $LOADER;
  if ( $LOADER['ARCHIVARIX_CONTENT_PATH'] ) {
    $path = $LOADER['ARCHIVARIX_CONTENT_PATH'];
  } else {
    $path = '';
    $list = scandir( dirname( __FILE__ ) );
    foreach ( $list as $item ) {
      if ( preg_match( '~^\.content\.[0-9a-zA-Z]+$~', $item )
        && is_dir( __DIR__ . DIRECTORY_SEPARATOR . $item )
      ) {
        $path = $item;
        break;
      }
    }
    if ( !$path ) {
      header( 'X-Error-Description: Directory .content.xxxxxxxx not found' );
      throw new \Exception( 'Directory .content.xxxxxxxx not found' );
    }
  }
  $absolutePath = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . $path;
  if ( !realpath( $absolutePath ) ) {
    header( 'X-Error-Description: Directory does not exist' );
    throw new \Exception( sprintf( 'Directory %s does not exist', $absolutePath ) );
  }

  return $absolutePath;
}

/**
 * @param $dsn
 * @return bool
 * @throws Exception
 */
function loadSettings( $dsn )
{
  global $ARCHIVARIX_SETTINGS;
  $pdo = new PDO( $dsn );
  $res = $pdo->query( 'SELECT param, value FROM settings' );
  if ( $res ) {
    $ARCHIVARIX_SETTINGS = $res->fetchAll( PDO::FETCH_KEY_PAIR );
  } else {
    $error = $pdo->errorInfo();
    switch ( $error[1] ) :
      case 1 :
      case 11:
        header( 'X-Error-Description: Database is corrupted or data missing.' );
        throw new \Exception( 'Database is corrupted or tables are missing' );
        break;
      case 14 :
        header( 'X-Error-Description: Write permission problem.' );
        throw new \Exception( 'Write permission problem. Make sure your files are under a correct user/group and avoid using PHP in a module mode.' );
        break;
    endswitch;
  }
}

function blockBots()
{
  global $LOADER;
  if ( empty( $LOADER['ARCHIVARIX_BLOCK_BOTS'] ) || !is_array( $LOADER['ARCHIVARIX_BLOCK_BOTS'] ) ) return;
  if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) return;
  $cleanRegex = implode( '|',
    ['Safari.[\d\.]*', 'Firefox.[\d\.]*', ' Chrome.[\d\.]*', 'Chromium.[\d\.]*', 'MSIE.[\d\.]', 'Opera\/[\d\.]*', 'Mozilla.[\d\.]*', 'AppleWebKit.[\d\.]*', 'Trident.[\d\.]*', 'Windows NT.[\d\.]*', 'Android [\d\.]*', 'Macintosh.', 'Ubuntu', 'Linux', '[ ]Intel', 'Mac OS X [\d_]*', '(like )?Gecko(.[\d\.]*)?', 'KHTML,', 'CriOS.[\d\.]*', 'CPU iPhone OS ([0-9_])* like Mac OS X', 'CPU OS ([0-9_])* like Mac OS X', 'iPod', 'compatible', 'x86_..', 'i686', 'x64', 'X11', 'rv:[\d\.]*', 'Version.[\d\.]*', 'WOW64', 'Win64', 'Dalvik.[\d\.]*', ' \.NET CLR [\d\.]*', 'Presto.[\d\.]*', 'Opera Mini\/\d{1,2}\.\d{1,2}\.[\d\.]*\/\d{1,2}\.', ' \.NET[\d\.]*'] );
  $botRegex   = implode( '|', $LOADER['ARCHIVARIX_BLOCK_BOTS'] );
  $agent      = trim( preg_replace( "~{$cleanRegex}~i", '', $_SERVER['HTTP_USER_AGENT'] ) );
  if ( preg_match( "~{$botRegex}~i", $agent ) ) {
    render404();
    exit( 0 );
  }
}

/**
 * @param string $dsn
 * @param string $url
 * @return array|false
 */
function getFileMetadata( $dsn, $url )
{
  global $LOADER;
  global $ARCHIVARIX_SETTINGS;
  if ( $LOADER['ARCHIVARIX_CUSTOM_DOMAIN'] ) {
    if ( !empty( $ARCHIVARIX_SETTINGS['www'] )
      && ARCHIVARIX_HTTP_HOST == $LOADER['ARCHIVARIX_CUSTOM_DOMAIN']
    ) {
      $url = preg_replace( '~' . preg_quote( $LOADER['ARCHIVARIX_CUSTOM_DOMAIN'], '~' ) . '~', 'www.' . $ARCHIVARIX_SETTINGS['domain'], $url, 1 );
    } else {
      $url = preg_replace( '~' . preg_quote( $LOADER['ARCHIVARIX_CUSTOM_DOMAIN'], '~' ) . '~', $ARCHIVARIX_SETTINGS['domain'], $url, 1 );
    }
  } elseif ( !preg_match( '~^([-a-z0-9.]+\.)?' . preg_quote( $ARCHIVARIX_SETTINGS['domain'], '~' ) . '$~i', ARCHIVARIX_HTTP_HOST ) ) {
    if ( !empty( $ARCHIVARIX_SETTINGS['www'] ) ) {
      $url = preg_replace( '~' . preg_quote( ARCHIVARIX_HTTP_HOST, '~' ) . '~', 'www.' . $ARCHIVARIX_SETTINGS['domain'], $url, 1 );
    } else {
      $url = preg_replace( '~' . preg_quote( ARCHIVARIX_HTTP_HOST, '~' ) . '~', $ARCHIVARIX_SETTINGS['domain'], $url, 1 );
    }
  }

  $urls = [$url];
  if ( preg_match( '~[?]+$~', $url ) ) {
    $urls[] = preg_replace( '~[?]+$~', '', $url );
  }
  if ( preg_match( '~[/]+$~', $url ) ) {
    $urls[] = preg_replace( '~[/]+$~', '', $url );
  }
  if ( preg_match( '~[^:][/]{2,}~', $url ) ) {
    $urls[] = preg_replace( '~([^:])[/]{2,}~', '$1/', $url );
  }
  if ( !parse_url( $url, PHP_URL_QUERY ) && preg_match( '~[^/]$~', $url ) ) {
    $urls[] = $url . '/';
  }

  if ( $LOADER['ARCHIVARIX_QUERYLESS'] && parse_url( $url, PHP_URL_QUERY ) ) {
    $urls[] = preg_replace( '/\?.*/', '', $url );
  }

  global $ARCHIVARIX_ORIGINAL_URL;
  $ARCHIVARIX_ORIGINAL_URL = $url;

  $sqlWhere = implode( ' OR ', array_map( function ( $v ) {
    return "url = ? COLLATE NOCASE";
  }, array_keys( $urls ) ) );
  $sqlOrder = implode( ' ', array_map( function ( $v ) {
    return "WHEN ? THEN {$v}";
  }, array_keys( $urls ) ) );
  $sqlUrls  = array_merge( $urls, $urls );

  $pdo = new PDO( $dsn );

  $sth = $pdo->prepare( "
    SELECT rowid, *
    FROM structure
    WHERE ({$sqlWhere})
      AND enabled = 1
    ORDER BY CASE url
                 {$sqlOrder}
                 END,
             filetime
        DESC
    LIMIT 1
    " );
  $sth->execute( $sqlUrls );
  $metadata = $sth->fetch( PDO::FETCH_ASSOC );

  return $metadata;
}

/**
 * @return string
 */
function getProtocol()
{
  if (
    (
      !empty( $_SERVER['HTTPS'] )
      && $_SERVER['HTTPS'] !== 'off'
    )
    || (
      !empty( $_SERVER['SERVER_PORT'] )
      && $_SERVER['SERVER_PORT'] == 443
    )
    || (
      !empty( $_SERVER['HTTP_X_FORWARDED_PROTO'] )
      && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https'
    )
    || (
      !empty( $_SERVER['HTTP_CF_VISITOR'] )
      && ( $HTTP_CF_VISITOR = json_decode( $_SERVER['HTTP_CF_VISITOR'], true ) )
      && !empty( $HTTP_CF_VISITOR['scheme'] )
      && $HTTP_CF_VISITOR['scheme'] == 'https'
    )
  ) {
    return 'https';
  }
  return 'http';
}

/**
 * @param array $metaData
 * @param string $sourcePath
 * @param string $url
 */
function render( array $metaData, $sourcePath, $url = '' )
{
  global $LOADER;
  if ( isset( $metaData['redirect'] ) && $metaData['redirect'] ) {
    header( 'Location: ' . $metaData['redirect'] );
    http_response_code( 301 );
    exit( 0 );
  }
  $sourceFile = $sourcePath . DIRECTORY_SEPARATOR . $metaData['folder'] . DIRECTORY_SEPARATOR . $metaData['filename'];
  if ( !file_exists( $sourceFile ) ) {
    handle404( $sourcePath, $url );
    exit( 0 );
  }

  if ( preg_match( '~(^text)|(javascript$)~', $metaData['mimetype'] ) ) {
    header( 'Content-Type:' . $metaData['mimetype'] . ( $metaData['charset'] ? '; charset=' . $metaData['charset'] : '' ), true );
  } else header( 'Content-Type:' . $metaData['mimetype'] );

  if ( preg_match( '~(^(image|video|audio|application))|((css|javascript)$)~', $metaData['mimetype'] ) ) {
    $etag = md5_file( $sourceFile );
    header( "Etag: \"{$etag}\"" );
    if ( $LOADER['ARCHIVARIX_CACHE_CONTROL_MAX_AGE'] ) {
      header( 'Cache-Control: public, max-age=' . $LOADER['ARCHIVARIX_CACHE_CONTROL_MAX_AGE'] );
    }
    if ( isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag ) {
      http_response_code( 304 );
      exit( 0 );
    }
  }

  if ( !empty( $metaData['filetime'] ) ) header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s GMT', strtotime( $metaData['filetime'] ) ) );

  if ( 0 === strpos( $metaData['mimetype'], 'text/html' ) ) {
    echo prepareContent( $sourceFile, $sourcePath, $metaData );
  } else {
    $fp = fopen( $sourceFile, 'rb' );
    fpassthru( $fp );
    fclose( $fp );
  }
}

function render404()
{
  http_response_code( 404 );
  echo <<<EOF
<html>
<head><title>404 Not Found</title></head>
<body>
<center><h1>404 Not Found</h1></center>
<hr><center>nginx</center>
</body>
</html>
<!-- a padding to disable MSIE and Chrome friendly error page -->
<!-- a padding to disable MSIE and Chrome friendly error page -->
<!-- a padding to disable MSIE and Chrome friendly error page -->
<!-- a padding to disable MSIE and Chrome friendly error page -->
<!-- a padding to disable MSIE and Chrome friendly error page -->
<!-- a padding to disable MSIE and Chrome friendly error page -->
EOF;
}

/**
 * @param $file
 * @param $sourcePath
 * @param $metaData
 */
function prepareContent( $file, $sourcePath, $metaData )
{
  global $LOADER;
  $content = file_get_contents( $file );

  foreach ( $LOADER['ARCHIVARIX_INCLUDE_CUSTOM'] as $includeCustom ) {
    if ( $includeCustom['FILE'] ) {
      global $includeRule;
      $includeRule = $includeCustom;
      $customFile  = $sourcePath . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . $includeCustom['FILE'];
      if ( isset( $includeCustom['URL_MATCH'] )
        && strlen( $includeCustom['URL_MATCH'] )
        && !preg_match( '~' . str_replace( '~', '\~', $includeCustom['URL_MATCH'] ) . '~is', rawurldecode( $metaData['request_uri'] ) )
      ) continue;
      if ( !empty( $includeCustom['URL_DEPTH'] ) && isset( $metaData['depth'] ) && preg_match( '~^(>|<|=|>=|<=|==|!=)([\d]+)$~', $includeCustom['URL_DEPTH'], $depthMatch ) ) {
        if ( $depthMatch ) switch ( $depthMatch[1] ) {
          case '<' :
            if ( $metaData['depth'] >= $depthMatch[2] ) continue 2;
            break;
          case '<=' :
            if ( $metaData['depth'] > $depthMatch[2] ) continue 2;
            break;
          case '>' :
            if ( $metaData['depth'] <= $depthMatch[2] ) continue 2;
            break;
          case '>=' :
            if ( $metaData['depth'] < $depthMatch[2] ) continue 2;
            break;
          case '=':
          case '==':
            if ( $metaData['depth'] != $depthMatch[2] ) continue 2;
            break;
          case '!=':
            if ( $metaData['depth'] == $depthMatch[2] ) continue 2;
            break;
        }
      }
      if ( $includeCustom['REGEX'] ) {
        $includeCustom['KEYPHRASE'] = str_replace( '~', '\~', $includeCustom['KEYPHRASE'] );
      } else {
        $includeCustom['KEYPHRASE'] = preg_quote( $includeCustom['KEYPHRASE'], '~' );
      }
      if ( !preg_match( '~' . $includeCustom['KEYPHRASE'] . '~is', $content ) ) continue;
      $content = preg_replace( '~' . $includeCustom['KEYPHRASE'] . '~is', includeCustom( $customFile, $includeCustom['POSITION'] ), $content, $includeCustom['LIMIT'] );
    }
  }
  return $content;
}

/**
 * @param $customFile
 * @param $position
 */
function includeCustom( $customFile, $position )
{
  ob_start();
  include $customFile;
  $includedContent = preg_replace( '~\$(\d)?~', '\\\$$1', ob_get_clean() );
  switch ( $position ) {
    case -1 :
      $includedContent = $includedContent . '${0}';
      break;
    case 1 :
      $includedContent = '${0}' . $includedContent;
      break;
  }
  return $includedContent;
}

/**
 * @param string $sourcePath
 * @param string $url
 */
function handle404( $sourcePath, $url )
{
  global $LOADER;
  if ( $LOADER['ARCHIVARIX_CATCH_MISSING'] ) {
    global $dsn;
    global $ARCHIVARIX_ORIGINAL_URL;
    $url = $ARCHIVARIX_ORIGINAL_URL;

    $pdo = new PDO( $dsn );
    $pdo->exec( 'CREATE TABLE IF NOT EXISTS missing (url TEXT PRIMARY KEY, status INTEGER DEFAULT 0, ignore INTEGER DEFAULT 0)' );

    $stmt = $pdo->prepare( 'INSERT OR IGNORE INTO missing (url) VALUES(:url)' );
    $stmt->bindParam( ':url', $url, PDO::PARAM_STR );
    $stmt->execute();

    $stmt = $pdo->prepare( 'UPDATE missing SET status = status + 1 WHERE url = :url' );
    $stmt->bindParam( ':url', $url, PDO::PARAM_STR );
    $stmt->execute();
  }

  $fileType = strtolower( pathinfo( parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
  switch ( true ) {
    case ( in_array( $fileType, ['jpg', 'jpeg', 'gif', 'png', 'bmp'] ) && $LOADER['ARCHIVARIX_FIX_MISSING_IMAGES'] ):
      $fileName = $sourcePath . DIRECTORY_SEPARATOR . '1px.png';
      $size     = filesize( $fileName );
      render( ['folder' => '', 'filename' => '1px.png', 'mimetype' => 'image/png', 'charset' => '', 'filesize' => $size], $sourcePath );
      break;
    case ( $fileType === 'ico' && $LOADER['ARCHIVARIX_FIX_MISSING_ICO'] ):
      $fileName = $sourcePath . DIRECTORY_SEPARATOR . 'empty.ico';
      $size     = filesize( $fileName );
      render( ['folder' => '', 'filename' => 'empty.ico', 'mimetype' => 'image/x-icon', 'charset' => '', 'filesize' => $size], $sourcePath );
      break;
    case( $fileType === 'css' && $LOADER['ARCHIVARIX_FIX_MISSING_CSS'] ):
      $fileName = $sourcePath . DIRECTORY_SEPARATOR . 'empty.css';
      $size     = filesize( $fileName );
      render( ['folder' => '', 'filename' => 'empty.css', 'mimetype' => 'text/css', 'charset' => 'utf-8', 'filesize' => $size], $sourcePath );
      break;
    case ( $fileType === 'js' && $LOADER['ARCHIVARIX_FIX_MISSING_JS'] ):
      $fileName = $sourcePath . DIRECTORY_SEPARATOR . 'empty.js';
      $size     = filesize( $fileName );
      render( ['folder' => '', 'filename' => 'empty.js', 'mimetype' => 'application/javascript', 'charset' => 'utf-8', 'filesize' => $size], $sourcePath );
      break;
    case ( $LOADER['ARCHIVARIX_REDIRECT_MISSING_HTML'] && $LOADER['ARCHIVARIX_REDIRECT_MISSING_HTML'] !== $_SERVER['REQUEST_URI'] ):
      header( 'Location: ' . $LOADER['ARCHIVARIX_REDIRECT_MISSING_HTML'] );
      http_response_code( 301 );
      exit( 0 );
      break;
    default:
      http_response_code( 404 );
  }
}

/**
 * @return bool
 */
function checkRedirects()
{
  global $LOADER;
  global $ARCHIVARIX_SETTINGS;
  $protocol = getProtocol();

  if ( in_array( strtolower( $LOADER['ARCHIVARIX_PROTOCOL'] ), ['http', 'https'] ) && strtolower( $LOADER['ARCHIVARIX_PROTOCOL'] ) != $protocol ) {
    $location = $LOADER['ARCHIVARIX_PROTOCOL'] . '://' . ARCHIVARIX_HTTP_HOST . $_SERVER['REQUEST_URI'];
    header( 'Location: ' . $location );
    http_response_code( 301 );
    exit( 0 );
  }

  if ( !empty( $ARCHIVARIX_SETTINGS['non-www'] ) && 0 === strpos( ARCHIVARIX_HTTP_HOST, 'www.' ) ) {
    $host     = preg_replace( '~^www\.~', '', ARCHIVARIX_HTTP_HOST );
    $location = $protocol . '://' . $host . $_SERVER['REQUEST_URI'];
    header( 'Location: ' . $location );
    http_response_code( 301 );
    exit( 0 );
  }

  if ( !empty( $ARCHIVARIX_SETTINGS['www'] ) && ARCHIVARIX_HTTP_HOST == $ARCHIVARIX_SETTINGS['domain'] ) {
    $location = $protocol . '://www.' . $ARCHIVARIX_SETTINGS['domain'] . $_SERVER['REQUEST_URI'];
    header( 'Location: ' . $location );
    http_response_code( 301 );
    exit( 0 );
  }
}

/**
 * @param string $dsn
 */
function renderSitemapXML( $dsn )
{
  global $LOADER;
  global $ARCHIVARIX_SETTINGS;
  $pagesLimit   = 50000;
  $pageProtocol = !empty( $ARCHIVARIX_SETTINGS['https'] ) ? 'https' : getProtocol();

  if ( $LOADER['ARCHIVARIX_CUSTOM_DOMAIN'] ) {
    $domain = preg_replace( '~' . preg_quote( $LOADER['ARCHIVARIX_CUSTOM_DOMAIN'], '~' ) . '$~', '', ARCHIVARIX_HTTP_HOST ) . $ARCHIVARIX_SETTINGS['domain'];
    if ( !empty( $ARCHIVARIX_SETTINGS['www'] ) && $domain == $ARCHIVARIX_SETTINGS['domain'] ) {
      $domain = 'www.' . $domain;
    }
  } elseif ( preg_match( '~^([-a-z0-9.]+\.)?' . preg_quote( $ARCHIVARIX_SETTINGS['domain'], '~' ) . '$~i', ARCHIVARIX_HTTP_HOST ) ) {
    $domain = ARCHIVARIX_HTTP_HOST;
  } else {
    $domain = $ARCHIVARIX_SETTINGS['domain'];
    if ( !empty( $ARCHIVARIX_SETTINGS['www'] ) && $domain == $ARCHIVARIX_SETTINGS['domain'] ) {
      $domain = 'www.' . $domain;
    }
  }

  $pdo = new PDO( $dsn );
  $res = $pdo->prepare( 'SELECT count(*) FROM structure WHERE hostname = :domain AND mimetype = "text/html" AND enabled = 1 AND redirect = ""' );
  $res->execute( ['domain' => $domain] );
  $pagesCount = $res->fetchColumn();

  if ( !$pagesCount ) {
    exit( 0 );
  }

  if ( $pagesCount > $pagesLimit && empty( $_GET['id'] ) ) {
    header( 'Content-type: text/xml; charset=utf-8' );
    echo '<?xml version="1.0" encoding="UTF-8"?' . '><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    for ( $pageNum = 1; $pageNum <= ceil( $pagesCount / $pagesLimit ); $pageNum++ ) {
      echo '<sitemap><loc>' . htmlspecialchars( $pageProtocol . '://' . ARCHIVARIX_HTTP_HOST . $LOADER['ARCHIVARIX_SITEMAP_PATH'] . '?id=' . $pageNum, ENT_XML1, 'UTF-8' ) . '</loc></sitemap>';
    }
    echo '</sitemapindex>';
    exit( 0 );
  }

  if ( !empty( $_GET['id'] ) && !ctype_digit( $_GET['id'] ) ) {
    render404();
    exit( 0 );
  }

  if ( !empty( $_GET['id'] ) ) {
    $pageId = $_GET['id'];
    if ( $pageId < 1 || $pageId > ceil( $pagesCount / $pagesLimit ) ) {
      render404();
      exit( 0 );
    }
    $pagesOffset = ( $pageId - 1 ) * $pagesLimit;
    $res         = $pdo->prepare( '
      SELECT *
      FROM structure
      WHERE hostname = :domain
        AND mimetype = "text/html"
        AND enabled = 1
        AND redirect = ""
      ORDER BY request_uri
      LIMIT :limit OFFSET :offset
    ' );
    $res->execute( ['domain' => $domain, 'limit' => $pagesLimit, 'offset' => $pagesOffset] );
    $pages = $res->fetchAll( PDO::FETCH_ASSOC );
  }

  if ( empty( $_GET['id'] ) ) {
    $res = $pdo->prepare( '
      SELECT *
      FROM structure
      WHERE hostname = :domain
        AND mimetype = "text/html"
        AND enabled = 1
        AND redirect = ""
      ORDER BY request_uri
    ' );
    $res->execute( ['domain' => $domain] );
    $pages = $res->fetchAll( PDO::FETCH_ASSOC );
  }

  header( 'Content-type: text/xml; charset=utf-8' );
  echo '<?xml version="1.0" encoding="UTF-8"?' . '><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
  foreach ( $pages as $page ) {
    if ( $LOADER['ARCHIVARIX_LOADER_MODE'] == -1
      && $LOADER['ARCHIVARIX_REDIRECT_MISSING_HTML'] != '/'
      && $page['request_uri'] == $LOADER['ARCHIVARIX_REDIRECT_MISSING_HTML']
    ) continue;
    echo '<url><loc>' . htmlspecialchars( $pageProtocol . '://' . ARCHIVARIX_HTTP_HOST . $page['request_uri'], ENT_XML1, 'UTF-8' ) . '</loc></url>';
  }
  echo '</urlset>';
}

try {
  if ( !in_array( 'sqlite', PDO::getAvailableDrivers() ) ) {
    header( 'X-Error-Description: PDO_SQLITE driver is not enabled' );
    throw new \Exception( 'PDO_SQLITE driver is not enabled.' );
  }
  if ( 'cli' === php_sapi_name() ) {
    echo "OK" . PHP_EOL;
    exit( 0 );
  }

  $sourcePath = getSourceRoot();
  loadLoaderSettings( $sourcePath );
  blockBots();

  if ( $LOADER['ARCHIVARIX_LOADER_MODE'] == 2 && $_SERVER['REQUEST_URI'] == '/' ) {
    include __DIR__ . DIRECTORY_SEPARATOR . 'index.php';
    exit( 0 );
  }

  $dbm = new PDO( 'sqlite::memory:' );
  if ( version_compare( $dbm->query( 'SELECT sqlite_version()' )->fetch()[0], '3.7.0' ) >= 0 ) {
    $dsn = sprintf( 'sqlite:%s%s%s', $sourcePath, DIRECTORY_SEPARATOR, 'structure.db' );
  } else {
    $dsn = sprintf( 'sqlite:%s%s%s', $sourcePath, DIRECTORY_SEPARATOR, 'structure.legacy.db' );
  }
  $dbm = null;

  loadSettings( $dsn );
  checkRedirects();

  $url = ( !empty( $ARCHIVARIX_SETTINGS['https'] ) ? 'https' : 'http' ) . '://' . ARCHIVARIX_HTTP_HOST . $_SERVER['REQUEST_URI'];

  if ( $LOADER['ARCHIVARIX_SITEMAP_PATH'] && $LOADER['ARCHIVARIX_SITEMAP_PATH'] === parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) ) {
    renderSitemapXML( $dsn );
    exit( 0 );
  }

  $metaData = getFileMetadata( $dsn, $url );
  if ( $metaData ) {
    render( $metaData, $sourcePath, $url );
  } else {
    if ( $LOADER['ARCHIVARIX_LOADER_MODE'] == -1 ) {
      render404();
      exit( 0 );
    }
    if ( $LOADER['ARCHIVARIX_LOADER_MODE'] == 0 ) {
      handle404( $sourcePath, $url );
    }
    if ( $LOADER['ARCHIVARIX_LOADER_MODE'] > 0 ) {
      include __DIR__ . DIRECTORY_SEPARATOR . 'index.php';
      exit( 0 );
    }
  }
} catch ( \Exception $e ) {
  http_response_code( 503 );
  error_log( $e );
}