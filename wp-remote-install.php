<?php

// Global Configuration
set_time_limit( 0 );
error_reporting( E_ALL );

// GitHub Information
define( 'GITHUB_USERNAME' , 'lucanos' );
define( 'GITHUB_PROJECT'  , 'WordPress-Remote-Installer' );

// Version Information
define( 'WPRI_VERSION'    , '0.5.2' );

// Suggested Plugins and Themes
$suggestions = array(

  # Can be an Array of URLs for each Plugin, or a string URL for a text file with URLs for each Plugin on a new line
  'plugins' => 'https://' . GITHUB_USERNAME . '.github.io/' . GITHUB_PROJECT .'/list-plugin.txt' ,

 # Can be an Array of URLs for each Theme, or a string URL for a text file with URLs for each Theme on a new line
  'themes'  => 'https://' . GITHUB_USERNAME . '.github.io/' . GITHUB_PROJECT .'/list-theme.txt'

);

// Delete Directory and Contents
function deleteAll( $dir ){
  echo '<pre>';
  echo "\$dir\n=====\n{$dir}\n\n";
  $directory_contents = scandir( $dir );
  echo "\$directory_contents\n=====\n";
  print_r( $directory_contents );
  echo "\n\n";
  foreach( array_diff( array( '.' , '..' ) , scandir( $dir ) ) as $k => $item ){
    echo "$k = $item - ";
    if( is_dir( $item ) ){
      echo "directory\n";
      deleteAll( $item );
    }else{
      echo "file\n";
      unlink( $item );
    }
  }
  rmdir( $dir );
  echo '</pre>';
}

die();

// Function for Extraction
function extractSubFolder( $zipFile , $target = null , $subFolder = null ){
  if( is_null( $target ) )
    $target = dirname( __FILE__ );
  $zip = new ZipArchive;
  $res = $zip->open( $zipFile );
  if( $res === TRUE ){
    if( is_null( $subFolder ) ){
      $zip->extractTo( $target );
    }else{
      for( $i = 0 , $c = $zip->numFiles ; $i < $c ; $i++ ){
        $entry = $zip->getNameIndex( $i );
        //Use strpos() to check if the entry name contains the directory we want to extract
        if( $entry!=$subFolder.'/' && strpos( $entry , $subFolder.'/' )===0 ){
          $stripped = substr( $entry , 9 );
          if( substr( $entry , -1 )=='/' ){
           // Subdirectory
            $subdir = $target.'/'.substr( $stripped , 0 , -1 );
            if( !is_dir( $subdir ) )
              mkdir( $subdir );
          }else{
            $stream = $zip->getStream( $entry );
            $write = fopen( $target.'/'.$stripped , 'w' );
            while( $data = fread( $stream , 1024 ) ){
              fwrite( $write , $data );
            }
            fclose( $write );
            fclose( $stream );
          }
        }
      }
    }
    $zip->close();
    return true;
  }
  die( 'Unable to open '.$zipFile );
  return false;
}

// Function to Cleanse Webroot
function rrmdir( $dir ){
  if( is_dir( $dir ) ){
    $objects = scandir( $dir );
    foreach( $objects as $object ){
      if( $object!='.' && $object!='..' ){
        if( filetype( $dir.'/'.$object )=='dir' )
          rrmdir( $dir.'/'.$object );
        else
          unlink( $dir.'/'.$object );
      }
    }
    reset( $objects );
    rmdir( $dir );
  }else{
    unlink( $dir );
  }
}
function cleanseFolder( $exceptFiles = null ){
  if( $exceptFiles == null )
    $exceptFiles[] = basename( __FILE__ );
  $contents = glob('*');
  foreach( $contents as $c ){
    if( !in_array( $c , $exceptFiles ) )
      rrmdir( $c );
  }
}
function downloadFromURL( $url = null , $local = null ){
  $result = null;
  if( is_null( $local ) )
    $local = basename( $url );
  if( $content = @file_get_contents( $url ) ){
    $result = @file_put_contents( $local , $content );
  }elseif( function_exists( 'curl_init' ) ){
    $fp = fopen( dirname(__FILE__) . '/' . $local , 'w+' );
    $ch = curl_init( str_replace( ' ' , '%20' , $url ) );
    curl_setopt($ch , CURLOPT_TIMEOUT        , 50 );
    curl_setopt($ch , CURLOPT_FILE           , $fp );
    curl_setopt($ch , CURLOPT_FOLLOWLOCATION , true );
    $result = curl_exec( $ch );
    curl_close( $ch );
    fclose( $fp );
  }else{
    $result = false;
  }
  return $result;
}
function getGithubVersion(){
  $versionURL = 'https://' . GITHUB_USERNAME . '.github.io/' . GITHUB_PROJECT .'/version.txt';
  $remoteVersion = null;
  if( !( $remoteVersion = @file_get_contents( $versionURL ) )
      && function_exists( 'curl_init' ) ){
    $ch = curl_init( str_replace( ' ' , '%20' , $versionURL ) );
    curl_setopt($ch , CURLOPT_TIMEOUT        , 50 );
	curl_setopt($ch , CURLOPT_RETURNTRANSFER , true );
	curl_setopt($ch , CURLOPT_HEADER         , false );
    curl_setopt($ch , CURLOPT_FOLLOWLOCATION , true );
    $remoteVersion = curl_exec( $ch );
    curl_close( $ch );
  }
  return $remoteVersion;
}

// Declare Parameters
$step = 0;
if( isset( $_POST['step'] ) )
  $step = (int) $_POST['step'];

?><!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en-US" lang="en-US">
<head>
<meta name="viewport" content="width=device-width">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title>WordPress &gt; Remote Installer</title>
<link rel="stylesheet" id="combined-css" href="//<?php echo GITHUB_USERNAME; ?>.github.io/<?php echo GITHUB_PROJECT; ?>/stylesheets/combined.css" type="text/css" media="all">
</head>
<body class="wp-core-ui">
<h1 id="logo"><a href="http://wordpress.org/">WordPress Remote Installer</a></h1>

<?php

switch( $step ){

  default :
  case 0 :

?>
<!-- STEP 0 //-->
<h1>WordPress Remote Installer</h1>
<p>The WordPress Remote Installer is a script designed to streamline the installation of the WordPress Content Management System. Some users have limited experience using FTP, some webhosts do not allow files to be decompressed after being uploaded, and some people want to make their WordPress installs faster and simpler.</p>
<p>Using the WordPress Remote Installer is simple - upload a single PHP file to your server, access it via a web-browser and simply follow the prompts through 7 easy steps, at the end of which, the Wordpress Installer will commence.</p>
<?php
    if( version_compare( WPRI_VERSION , $githubVersion = getGithubVersion() , '<' ) ){
?>
<p class="version_alert">You are using Version <?php echo WPRI_VERSION; ?>. Version <?php echo $githubVersion; ?> is available through <a href="https://github.com/<?php echo GITHUB_USERNAME; ?>/<?php echo GITHUB_PROJECT; ?>">Github</a>.</p>
<?php
    }
?>
<form method="post">
  <input type="hidden" name="step" value="1" />
  <input type="submit" name="submit" value="Let's Get Started!" class="button button-large" />
</form>
<?php

    break;

  case 1 :

    if( isset( $_POST['action'] ) && $_POST['action']=='cleanse' )
      cleanseFolder();

    $tests = array(
      array(
        'result' => ini_get( 'allow_url_fopen' ) ,
        'pass' => '<strong>allow_url_open</strong> is Enabled' ,
        'fail' => '<strong>allow_url_open</strong> is Disabled'
      ) ,
      array(
        'result' => !count( array_diff( glob( '*' ) , array( basename( __FILE__ ) , 'version.txt' ) ) ) ,
        'pass' => 'The server is empty (apart from this file)' ,
        'fail' => 'The server is not empty.'
      )
    );
?>
<!-- STEP 1 //-->
<h1>Step 1/7: Pre-Install Checks</h1>
<?php
    if( isset( $_POST['action'] ) && $_POST['action']=='cleanse' ){
?>
<p>All Files Deleted from the Directory as requested.</p>
<?php
    }
?>
<ul>
<?php

    $proceed = true;
    foreach( $tests as $t ){
      if( !$t['result'] )
        $proceed = false;
?>
  <li class="<?php echo ( $t['result'] ? 'pass' : 'fail' ); ?>"><?php echo $t[( $t['result'] ? 'pass' : 'fail' )]; ?></li>
<?php
    }
?>
</ul>
<?php
    if( !$proceed ){
?>
<p>NOTE: We are unable to proceed until the above issue(s) are resolved.</p>
<form method="post">
  <input type="hidden" name="step" value="1" />
  <input type="hidden" name="action" value="cleanse" />
  <input type="submit" name="submit" value="Delete All Files from Directory to Proceed" class="button button-large confirm" data-msg="Are you sure? All files, Wordpress-related or not, will be removed. Delete files are unrecoverable." />
</form>
<?php
    }else{
?>
<form method="post">
  <input type="hidden" name="step" value="2" />
  <input type="submit" name="submit" value="Commence Install of WordPress" class="button button-large" />
</form>
<?php
    }

    break;

  case 2 :

?>
<!-- STEP 2 //-->
<h1>Step 2/7: Installing Wordpress</h1>
<ul>
<?php
    $proceed = true;

    if( downloadFromURL( 'https://wordpress.org/latest.zip' , 'wordpress.zip' ) ){
?>
  <li class="pass">Downloading Latest WordPress from Wordpress.org - OK</li>
<?php
    }else{
      $proceed = false;
?>
  <li class="fail">Downloading Latest WordPress from Wordpress.org - FAILED</li>
<?php
    }

    if( !$proceed ){
?>
  <li class="skip">Extract WordPress - SKIPPED</li>
<?php
    }elseif( extractSubFolder( 'wordpress.zip' , null , 'wordpress' ) ){
?>
  <li class="pass">Extract WordPress - OK</li>
<?php
    }else{
      $proceed = false;
?>
  <li class="fail">Extract WordPress - FAILED</li>
<?php
    }

    if( !$proceed ){
?>
  <li class="skip">Delete WordPress ZIP - SKIPPED</li>
<?php
    }elseif( unlink( 'wordpress.zip' ) ){
?>
  <li class="pass">Delete WordPress ZIP - OK</li>
<?php
    }else{
      $proceed = false;
?>
  <li class="fail">Delete WordPress ZIP - FAILED</li>
<?php
    }
?>
</ul>
<?php

    if( !$proceed ){
?>
<p>NOTE: We are unable to proceed until the above issue(s) are resolved.</p>
<?php
    }else{
?>
<form method="post">
  <input type="hidden" name="step" value="3" />
  <input type="submit" name="submit" value="Next Step - Plugins" class="button button-large" />
</form>
<?php
    }

    break;

  case 3 :

    $suggest = '';
    if( is_array( $suggestions['plugins'] ) ){
      $suggest = implode( "\n" , $suggestions['plugins'] );
    }elseif( is_string( $suggestions['plugins'] ) ){
      if( !( $suggest = @file_get_contents( $suggestions['plugins'] ) ) )
        $suggest = '';
    }

?>
<!-- STEP 3 //-->
<h1>Step 3/7: Installing Plugins</h1>
<p>List the Download URLs, or the slugs (e.g "classic-editor"), for all WordPress Plugins, one per line</p>
<form method="post">
  <textarea name="plugins"><?php echo $suggest; ?></textarea>
  <input type="hidden" name="step" value="4" />
  <input type="submit" name="submit" value="Install Plugins" class="button button-large" />
</form>
<?php

    break;

  case 4 :

?>
<!-- STEP 4 //-->
<h1>Step 4/7: Installing Plugins</h1>
<ul>
<?php
    $plugin_result = ( !file_exists( @unlink( dirname( __FILE__ ).'/wp-content/plugins/hello.php' ) || dirname( __FILE__ ).'/wp-content/plugins/hello.php' ) );
?>
  <li class="<?php echo ( $plugin_result ? 'pass' : 'fail' ); ?>">Delete Unneeded "Hello Dolly" Plugin - <?php echo ( $plugin_result ? 'OK' : 'FAILED' ); ?></li>
<?php
    $plugin_result = ( !is_dir( @deleteAll( dirname( __FILE__ ).'/wp-content/plugins/akismet' ) || dirname( __FILE__ ).'/wp-content/plugins/akismet' ) );
?>
  <li class="<?php echo ( $plugin_result ? 'pass' : 'fail' ); ?>">Delete Unneeded "Akismet Anti-Spam" Plugin - <?php echo ( $plugin_result ? 'OK' : 'FAILED' ); ?></li>
<?php
    if( isset( $_POST['plugins'] ) ){
      $plugins = array_filter( explode( "\n" , $_POST['plugins'] ) );
      foreach( $plugins as $url ){
        $plugin_result = false;
        $plugin_message = 'UNKNOWN';
        $url = trim( $url );
        if( !$url ) continue;
        $title = $url;
        if( preg_match( '/\/plugins\/([^\/]+)\//' , $url , $bits ) )
          $url = $bits[1];
        if( strpos( $url , 'http' )!==0 )
          $url = 'https://downloads.wordpress.org/plugin/'.$url.'.zip';
        if( preg_match( '/(?:([^\.\/]+)\.)(?:((?:\d+\.)*\d*)\.)?zip$/' , $url , $bits ) )
          $title = $bits[1].' ('.( isset( $bits[2] ) ? 'v'.$bits[2] : '<em>latest version</em>' ).')';
        $get = @file_get_contents( $url );
        if( !$get ){
          $plugin_message = 'FAILED TO DOWNLOAD';
        }else{
          file_put_contents( 'temp_plugin.zip' , $get );
          if( !extractSubFolder( 'temp_plugin.zip' , dirname( __FILE__ ).'/wp-content/plugins' ) ){
            $plugin_message = 'FAILED TO EXTRACT';
          }else{
            $plugin_result = true;
            $plugin_message = 'OK';
          }
          @unlink( 'temp_plugin.zip' );
        }
?>
  <li class="<?php echo ( $plugin_result ? 'pass' : 'fail' ); ?>">Installing <strong><?php echo $title; ?></strong> - <?php echo $plugin_message; ?></li>
<?php
      }
    }
?>
</ul>
<form method="post">
  <input type="hidden" name="step" value="5" />
  <input type="submit" name="submit" value="Next Step - Themes" class="button button-large" />
</form>
<?php

    break;

  case 5 :

    $suggest = '';
    if( is_array( $suggestions['themes'] ) ){
      $suggest = implode( "\n" , $suggestions['themes'] );
    }elseif( is_string( $suggestions['themes'] ) ){
      if( !( $suggest = @file_get_contents( $suggestions['themes'] ) ) )
        $suggest = '';
    }

?>
<!-- STEP 5 //-->
<h1>Step 5/7: Installing Themes</h1>
<p>List the Download URLs for all WordPress Themes, one per line</p>
<form method="post">
  <textarea name="themes"><?php echo $suggest; ?></textarea>
  <input type="hidden" name="step" value="6" />
  <input type="submit" name="submit" value="Install Themes" class="button button-large" />
</form>
<?php

    break;

  case 6 :

?>
<!-- STEP 6 //-->
<h1>Step 6/7: Installing Themes</h1>
<ul>
<?php

    if( isset( $_POST['themes'] ) ){
      $themes = array_filter( explode( "\n" , $_POST['themes'] ) );
      foreach( $themes as $url ){
        $theme_result = false;
        $theme_message = 'UNKNOWN';
        $url = trim( $url );
        if( !$url ) continue;
        $title = $url;
        if( preg_match( '/\/themes\/([^\/]+)\//' , $url , $bits ) )
          $url = $bits[1];
        if( strpos( $url , 'http' )!==0 )
          $url = 'https://downloads.wordpress.org/theme/'.$url.'.zip';
        if( preg_match( '/(?:([^\.\/]+)\.)(?:((?:\d+\.)*\d*)\.)?zip$/' , $url , $bits ) )
          $title = $bits[1].' ('.( isset( $bits[2] ) ? 'v'.$bits[2] : '<em>latest version</em>' ).')';
        $get = @file_get_contents( $url );
        if( !$get ){
          $theme_message = 'FAILED TO DOWNLOAD';
        }else{
          file_put_contents( 'temp_theme.zip' , $get );
          if( !extractSubFolder( 'temp_theme.zip' , dirname( __FILE__ ).'/wp-content/themes' ) ){
            $theme_message = 'FAILED TO EXTRACT';
          }else{
            $theme_result = true;
            $theme_message = 'OK';
          }
?>
  <li class="<?php echo ( $theme_result ? 'pass' : 'fail' ); ?>">Installing <strong><?php echo $title; ?></strong> - <?php echo $theme_message; ?></li>
<?php
          @unlink( 'temp_theme.zip' );
        }
        echo '</li>';
      }
    }

?>
</ul>
<form method="post">
  <input type="hidden" name="step" value="7" />
  <input type="submit" name="submit" value="Next Step - Clean Up" class="button button-large" />
</form>
<?php

    break;

  case 7 :

?>
<!-- STEP 7 //-->
<h1>Step 7/7: Cleaning Up</h1>
<ul>
<?php

    $tests = array(
      array(
        'result' => ( !file_exists( 'wordpress.zip' ) || @unlink( 'wordpress.zip' ) ) ,
        'pass' => 'Remove WordPress Installer - OK' ,
        'fail' => 'Remove WordPress Installer - FAILED'
      ) ,
      array(
        'result' => ( !file_exists( 'temp_plugin.zip' ) || @unlink( 'temp_plugin.zip' ) ) ,
        'pass' => 'Remove Temporary Plugin File - OK' ,
        'fail' => 'Remove Temporary Plugin File - FAILED'
      ) ,
      array(
        'result' => ( !file_exists( 'temp_theme.zip' ) || @unlink( 'temp_theme.zip' ) ) ,
        'pass' => 'Remove Temporary Theme File - OK' ,
        'fail' => 'Remove Temporary Theme File - FAILED'
      ) ,
      array(
        'result' => ( !file_exists( __FILE__ ) || @unlink( __FILE__ ) ) ,
        'pass' => 'Remove WordPress Remote Installer - OK' ,
        'fail' => 'Remove WordPress Remote Installer - FAILED'
      ) ,
    );

    foreach( $tests as $t ){
?>
  <li class="<?php echo ( $t['result'] ? 'pass' : 'fail' ); ?>"><?php echo $t[( $t['result'] ? 'pass' : 'fail' )]; ?></li>
<?php
    }
?>
</ul>
<form method="post" action="./wp-admin/setup-config.php">
  <input type="submit" name="submit" value="Launch WordPress Installer" class="button button-large" />
</form>
<?php

    break;
}

?>

<div id="footer">
  <a href="https://github.com/<?php echo GITHUB_USERNAME; ?>/<?php echo GITHUB_PROJECT; ?>" class="github">View on GitHub</a>
  Created by <a href="http://lucanos.com">Luke Stevenson</a><br/>
  <div class="legal">
    <strong>NOTE:</strong> This script is not an official WordPress product.<br/>
    The WordPress logo is the property of the WordPress Foundation.
  </div>
</div>

<script src="//code.jquery.com/jquery.min.js"></script>
<script src="//<?php echo GITHUB_USERNAME; ?>.github.io/<?php echo GITHUB_PROJECT; ?>/javascripts/installer.js"></script>
<script>
(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
})(window,document,'script','//www.google-analytics.com/analytics.js','ga');
ga('create', 'UA-238524-33');
ga('send', 'pageview', 'step<?php echo $step; ?>');
ga('send', 'event', 'step', '<?php echo $step; ?>');
</script>
</body>
</html>
