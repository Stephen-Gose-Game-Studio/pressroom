<?php
/**
 * PressRoom packager: Adobe DPS package
 */

define( "PR_DPS_PATH", PR_UPLOAD_PATH . 'adps/' );
define( "PR_DPS_MEDIA_DIR", 'Links/' );
define( "PR_DPS_SHARED_ASSETS", "shared" );
define( "PR_DPS_RESOURCES_FILENAME", "HTMLResources.zip" );
define( "PR_DPS_SIDECAR_FILENAME", "sidecar.xml" );

require_once 'adps_metaboxes.php';

final class PR_Packager_ADPS_Package
{
  private $_xmlwriter;
  private $_folio_dir;

  private $_settings = [
    '_pr_adps_download_path',
    '_pr_adps_is_flattened_stack',
    '_pr_adps_is_trusted_content',
    '_pr_adps_smooth_scrolling',
    '_pr_adps_article_access',
    '_pr_adps_hide_from_toc',
    '_pr_adps_title',
    '_pr_adps_byline',
    '_pr_adps_kicker',
    '_pr_adps_description',
    '_pr_adps_section',
  ];

  public function __construct() {

    add_action( 'pr_add_eproject_tab', [ $this, 'pr_add_option' ], 10, 2 );
    add_action( 'pr_add_edition_tab', [ $this, 'pr_add_option' ], 10, 3 );
    add_action( 'wp_ajax_pr_dps_download_folio', array( $this, 'pr_download_folio' ), 10 );

    add_action( 'pr_packager_adps_start', [ $this, 'adps_start' ], 10, 2 );
    add_action( 'pr_packager_adps', [ $this, 'adps_run' ], 10, 4 );
    add_action( 'pr_packager_adps_end', [ $this, 'adps_end' ], 10, 2 );

    if ( !file_exists( PR_DPS_PATH ) ) {
      PR_Utils::make_dir( PR_UPLOAD_PATH, 'adps' );
    }
  }

  /**
   * Initial step of Adobe DPS packager
   * Generate toc
   *
   * @param  object $packager istance of packager class
   * @param  object $editorial_project
   * @void
   */
  public function adps_start( $packager, $editorial_project ) {

    // Load all settings
    $this->_load_settings( $packager->edition_post->ID, $editorial_project->term_id );

    // Create folio directory
    $this->_folio_dir = PR_Utils::make_dir( PR_DPS_PATH, $editorial_project->slug . '_' . $packager->edition_post->post_name );
    if ( !$this->_folio_dir ) {
      PR_Packager::print_line( sprintf( __( 'Failed to create folder: %s', 'packager' ), PR_DPS_PATH . $this->_folio_dir ), 'error' );
      $packager->exit_on_error();
      return;
    }

    // Initialize xml document
    $this->_xmlwriter = new XMLWriter();
    $this->_xmlwriter->openURI( $this->_folio_dir . DS . PR_DPS_SIDECAR_FILENAME );
    $this->_xmlwriter->startDocument( '1.0', 'UTF-8', 'yes' );
    $this->_xmlwriter->setIndent(4);
    $this->_xmlwriter->startElement( 'sidecar' );
  }

  /**
   * Hooked to posts foreach
   * Html post parsing and html files writing
   *
   * @param object $packager istance of packager class
   * @param object $post
   * @param object $editorial_project
   * @param string $parsed_html_post
   * @void
   */
  public function adps_run( $packager, $post, $editorial_project, $parsed_html_post ) {

    $article_filename = PR_Utils::sanitize_string( PR_Utils::trim_str( $post->post_title, 60 ) );
    $article_dir = PR_Utils::make_dir( $this->_folio_dir, $article_filename );
    if ( !$article_dir ) {
      PR_Packager::print_line( sprintf( __( 'Failed to create folder: %s', 'packager' ), $article_filename ), 'error' );
      $packager->exit_on_error();
      return;
    }

    if ( $parsed_html_post ) {
      // Rewrite post url
      $packager->reset_post_attachments();
      $parsed_html_post = $packager->rewrite_url( $parsed_html_post, 'html', PR_DPS_MEDIA_DIR );
      if ( $packager->save_html_file( $parsed_html_post, $article_filename, $article_dir ) ) {
        // Save media attachments
        $media_dir = PR_Utils::make_dir( $article_dir, PR_DPS_MEDIA_DIR, false );
        if ( !$media_dir ) {
          PR_Packager::print_line( sprintf( __( 'Failed to create folder %s ', 'edition' ), $article_dir . DS . PR_DPS_MEDIA_DIR ), 'error' );
          $packager->exit_on_error();
          return;
        }
        $packager->save_posts_attachments( $media_dir );
      }
      else {
        PR_Packager::print_line( sprintf( __( 'Failed to save post file: %s', 'packager' ), $article_filename ), 'error' );
        exit;
      }
    }
    else {
      do_action( 'pr_packager_run_adps_' . $post->post_type, $post, $article_dir );
    }

    $this->_add_xmlwriter_entry( $post );
  }

  /**
   * Adobe DPS package closure step
   * Finalizing sidecar.xml, creating HTMLResources.zip
   *
   * @param object $packager istance of packager class
   * @param object $editorial_project
   * @void
   */
  public function adps_end( $packager, $editorial_project ) {

    // Finalize sidecar xml file
    $packager->set_progress( 70, __( 'Finalizing sidecar xml file', 'edition' ) );
    $this->_xmlwriter->endElement();  // sidecar tag
    $this->_xmlwriter->endDocument();
    $this->_xmlwriter->flush();

    // Build HTMLResources file
    $packager->set_progress( 80, __( 'Creating resources file', 'edition' ) );
    $resources_filename = $this->_build_html_resources_file( $packager->edition_post, $packager->edition_dir );
    if ( !$resources_filename ) {
      PR_Packager::print_line( sprintf( __( 'Failed to create %s', 'edition' ), PR_DPS_RESOURCES_FILENAME ), 'error' );
      $packager->exit_on_error();
      return;
    }

    $this->_build_folio_zip();
  }

  /**
   * Create metabox and custom fields
   *
   * @param object &$metaboxes
   * @param int $item_id (it can be editorial project id or edition id);
   * @void
   */
  public function pr_add_option( &$metaboxes, $item_id, $edition = false ) {

    $adps_metabox = new PR_Metabox( 'adps_metabox', __( 'Adobe DPS', 'adps_package' ), 'normal', 'high', $item_id );

    if( $edition ) {
      $adps_metabox->add_field( '_pr_adps_override_eproject', __( 'Override Editorial Project settings', 'editorial_project' ), __( 'If enabled, will be used edition settings below', 'edition' ), 'checkbox', false );
    }

    $adps_metabox->add_field( '_pr_adps_smooth_scrolling', __( 'Smooth scrolling', 'adps_package' ), '', 'select', '', [
      'options' => [
        [ 'value' => 'never', 'text' => __( "Never", 'adps_package' ) ],
        [ 'value' => 'always', 'text' => __( "Both", 'adps_package' ) ],
        [ 'value' => 'portrait', 'text' => __( "Portrait", 'adps_package' ) ],
        [ 'value' => 'landscape', 'text' => __( "Landscape", 'adps_package' ) ],
      ]
    ]);
    $adps_metabox->add_field( '_pr_adps_hide_from_toc', __( 'Hide From TOC', 'adps_settings_metabox' ), __( 'Prevent the article from appearing when users tap the TOC button in the viewer nav bar', 'adps_settings_metabox' ), 'checkbox', false );
    $adps_metabox->add_field( '_pr_adps_is_flattened_stack', __( 'Horizontal Swipe Only', 'adps_settings_metabox' ), __( 'If enabled, users browse through the article by swiping left and right instead of up and down', 'adps_settings_metabox' ), 'checkbox', false );
    $adps_metabox->add_field( '_pr_adps_is_trusted_content', __( 'Trusted content', 'adps_settings_metabox' ), __( 'Allow Access to Entitlement Information', 'adps_settings_metabox' ), 'checkbox', false );
    $adps_metabox->add_field( '_pr_adps_article_access', __( 'Article access', 'adps_package' ), '', 'select', '', [
      'options' => [
        [ 'value' => 'free', 'text' => __( "Free", 'adps_package' ) ],
        [ 'value' => 'metered', 'text' => __( "Metered", 'adps_package' ) ],
        [ 'value' => 'protected', 'text' => __( "Protected", 'adps_package' ) ]
      ]
    ]);
    $adps_metabox->add_field( '_pr_adps_download_path', __( 'Local download path', 'adps_package' ), __( 'Local download path (i.e. "/Users/mike/Downloads")', 'adps_package' ), 'text', false );

    array_push( $metaboxes, $adps_metabox );
  }

  /**
   * Force folio download
   * @void
   */
  public function pr_download_folio() {

    if( isset( $_GET['filename'] ) ) {
      $filename = $_GET['filename'];
      // http headers for zip downloads
      header( "Pragma: public" );
      header( "Expires: 0" );
      header( "Cache-Control: must-revalidate, post-check=0, pre-check=0" );
      header( "Cache-Control: public" );
      header( "Content-Description: File Transfer" );
      header( "Content-type: application/zip" );
      header( "Content-Disposition: attachment; filename=\"" . $filename . "\"" );
      header( "Content-Length: ". filesize( PR_DPS_PATH . DS . $filename ) );
      readfile( PR_DPS_PATH . DS . $filename );
      exit;
    }
  }

  /**
	 * Download assets and build HTMLResources.zip
	 *
	 * @param object $edition_post
	 * @param string $edition_dir
	 * @return string or boolean false
	 */
	protected function _build_html_resources_file( $edition_post, $edition_dir ) {

    // Get associated theme
		$theme_dir = PR_Theme::get_theme_path( $edition_post->ID );
		if ( !$theme_dir ) {
			PR_Packager::print_line( __( 'Failed to load edition theme', 'edition' ), 'error' );
			$this->exit_on_error();
			return;
		}

    // Download all shared assets
    $shared_assets_dir = $theme_dir . PR_DPS_SHARED_ASSETS;
		if ( !is_dir( $shared_assets_dir ) ) {
			PR_Packager::print_line( sprintf( __( 'Can\'t read shared assets folder %s', 'edition' ), $shared_assets_dir ), 'warning' );
		} else {
      // Recursive copy shared assets into edition assets dir
      $copied_files = PR_Utils::recursive_copy( $shared_assets_dir, $edition_dir . DS . PR_THEME_ASSETS_DIR );
	    if ( is_array( $copied_files ) ) {
        foreach ( $copied_files as $file ) {
  				PR_Packager::print_line( sprintf( __( 'Error: Can\'t copy file %s ', 'edition' ), $file ), 'error' );
  			}
      } else {
        PR_Packager::print_line( sprintf( __( 'Copy shared assets folder with %s files ', 'edition' ), $copied_files ), 'success' );
  		}
    }

    // Create HTMLResources zip
    $resources_filename = $this->_folio_dir . DS . PR_DPS_RESOURCES_FILENAME;
    if ( PR_Utils::create_zip_file( $edition_dir . DS . PR_THEME_ASSETS_DIR, $resources_filename, '' ) ) {
      return $resources_filename;
    }
    return false;
  }

  /**
   * Add an xml entry on sidecar file
   * @param object $post
   * @void
   */
  private function _add_xmlwriter_entry( $post ) {

    if ( empty( $this->_settings ) ) {
      return false;
    }

    // Overwrite settings with those of the post
    $override_meta = get_post_meta( $post->ID, '_pr_adps_override', true );
    if ( $override_meta ) {
      foreach ( $this->_settings as $setting => $value ) {
        $post_meta = get_post_meta( $post->ID, $setting, true );
        $this->_settings[$setting] = $post_meta;
      }
    }

    // Format fields
    $filename = PR_Utils::sanitize_string( PR_Utils::trim_str( $post->post_title, 60 ) );
    $title = PR_Utils::trim_str( strlen( $this->_settings['_pr_adps_title'] ) ? $this->_settings['_pr_adps_title'] : $post->post_title, 60 );
    $author = PR_Utils::trim_str( strlen( $this->_settings['_pr_adps_byline'] ) ? $this->_settings['_pr_adps_byline'] : get_the_author_meta( 'display_name', $post->post_author ), 40 );
    $description = PR_Utils::trim_str( strlen( $this->_settings['_pr_adps_description'] ) ? $this->_settings['_pr_adps_description'] : $post->post_excerpt, 120 );
    $section = strlen( $this->_settings['_pr_adps_section'] ) ? PR_Utils::trim_str( $this->_settings['_pr_adps_section'], 60 ) : '';
    $kicker = strlen( $this->_settings['_pr_adps_kicker'] ) ? PR_Utils::trim_str( $this->_settings['_pr_adps_kicker'], 35 ) : '';
    $tags = wp_get_post_tags( $post->ID, [ 'fields' => 'names' ] );

    $resources_path = $this->_folio_dir;
    if ( strlen( $this->_settings['_pr_adps_download_path'] ) ) {
      $resources_path = $this->_settings['_pr_adps_download_path'] . ( substr( $this->_settings['_pr_adps_download_path'], -1) == DS ? '' : DS );
      $resources_path = str_replace( PR_DPS_PATH , $resources_path, $this->_folio_dir );
    }

    // XML
    $this->_xmlwriter->startElement( 'entry' );

    $this->_xmlwriter->startElement( 'contentSource' );
    $this->_xmlwriter->writeElement( 'articleName', $filename );
    $this->_xmlwriter->writeElement( 'sourceFormat', 'html' );
    $this->_xmlwriter->writeElement( 'sourceFolder', $resources_path . DS . $filename );
    $this->_xmlwriter->endElement(); // contentSource tag

    $this->_xmlwriter->writeElement( 'articleTitle', $title );
    $this->_xmlwriter->writeElement( 'byline', $author );
    $this->_xmlwriter->writeElement( 'description', $description );
    $this->_xmlwriter->writeElement( 'section', $section );
    $this->_xmlwriter->writeElement( 'kicker', $kicker );
    $this->_xmlwriter->writeElement( 'isAd', $post->post_type === 'pr_ad_bundle' ? 'true' : 'false' );

    if ( $tags ) {
      $this->_xmlwriter->writeElement( 'tags', implode(' ', $tags) );
    }

    $this->_xmlwriter->writeElement( 'smoothScrolling', $this->_settings['_pr_adps_smooth_scrolling'] );
    $this->_xmlwriter->writeElement( 'isFlattenedStack', $this->_settings['_pr_adps_is_flattened_stack'] === 'on' ? 'true' : 'false' );
    $this->_xmlwriter->writeElement( 'isTrustedContent', $this->_settings['_pr_adps_is_trusted_content'] === 'on' ? 'true' : 'false' );
    $this->_xmlwriter->writeElement( 'hideFromTOC', $this->_settings['_pr_adps_hide_from_toc'] === 'on' ? 'true' : 'false' );
    $this->_xmlwriter->writeElement( 'articleAccess', $this->_settings['_pr_adps_article_access'] );

    $this->_xmlwriter->endElement(); // entry tag
  }

  /**
   * Check edition post settings else check for editorial project settings
   *
   * @param  int $edition_id
   * @param  int $eproject_id
   * @void
   */
  private function _load_settings( $edition_id, $eproject_id ) {

    // check whether using edition settings or editorial project settings
    $override = get_post_meta( $edition_id, '_pr_adps_override_eproject', true );

    $settings = array_flip( $this->_settings );
    foreach( $settings as $setting => $value ) {
      $option = false;
      if( $override ) {
        $option = get_post_meta( $edition_id, $setting, true );
      } else if( $eproject_id ) {
        $option = PR_Editorial_Project::get_config( $eproject_id, $setting);
      }
      $settings[$setting] = $option;
    }
    $this->_settings = $settings;
  }

  /**
   * Make and download folio zip
   * @return boolean
   */
  private function _build_folio_zip() {

    $filename = str_replace( PR_DPS_PATH, '', $this->_folio_dir ) . '.zip';
    $filepath = dirname( $this->_folio_dir );
    if ( PR_Utils::create_zip_file( $this->_folio_dir, $filepath . DS . $filename ) ) {
      $download_link = admin_url( 'admin-ajax.php?action=pr_dps_download_folio&filename=' . $filename );
      PR_Packager::print_line( sprintf( __( '<a href="%s" download><b>Download folio</b></a>', 'edition' ), $download_link ), 'success' );
      PR_Packager::print_line( '<script type="text/javascript">downloadFile("' . $download_link . '");</script>' );

      PR_Utils::remove_dir( $this->_folio_dir );
      return true;
    } else {
      PR_Packager::print_line( sprintf( __( 'Error: Can\'t create file %s ', 'edition' ), $filename ), 'error' );
      PR_Utils::remove_dir( $this->_folio_dir );
      return false;
    }
  }
}

$pr_packager_adps_package = new PR_Packager_ADPS_Package;
