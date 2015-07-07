<?php

class PR_themes_page {

  public function __construct() {

    if( !is_admin() ) {
      return;
    }

    add_action( 'admin_footer', array( $this, 'add_custom_scripts' ) );

    add_action( 'admin_menu', array( $this, 'pr_add_admin_menu' ) );
    add_action( 'wp_ajax_pr_flush_themes_cache', array( $this, 'pr_flush_themes_cache' ) );
    add_action( 'wp_ajax_pr_delete_theme', array( $this, 'pr_delete_theme' ) );
    add_action( 'wp_ajax_pr_upload_theme', array( $this, 'pr_upload_theme' ) );
    add_action( 'wp_ajax_pr_get_remote_themes', array( $this, 'get_remote_themes' ) );
  }

  /**
  * Add options page to wordpress menu
  */
  public function pr_add_admin_menu() {

    add_submenu_page('pressroom', __('Themes', 'pressroom-themes'), __('Themes', 'pressroom-themes'), 'manage_options', 'pressroom-themes', array( $this, 'pr_themes_page' ));
  }

  /**
  * Render a single theme
  * @param array $theme
  * @return string
  */
  public function _render_theme( $theme, $installed, $activated, $free ) {

    $options = get_option( 'pr_settings' );
    $item_id = $theme->info->id;
    $item_slug = $theme->info->slug;
    $item_name = $theme->info->title;
    $item_price = $theme->pricing->amount;
    $item_link = $theme->info->link;

    $html = '<div class="theme ' . ( $activated ? 'active' : '' ) . '" data-name="' . $item_id . '" tabindex="0">
    <form method="post" name="' . $item_slug . '">
    <div class="theme-screenshot pr-theme-screenshot">
    <img src="'.$theme->info->thumbnail.'" alt="">
    </div>
    <p class="pr-theme-description">' . $theme->info->content . '</p>
    <p class="pr-theme-description">';

    $html .= '
    <span>' . __("Category", 'pressroom-themes' ) . ' <a href="#" target="_blank">' . $theme->info->category[0]->name . '</a></span>
    </p>';

    if ( $installed && $activated && !$free ) {
      $html .= '<p class="pr-theme-description"><span>' . __("Your license key", 'pressroom-addons' ) . ' <b>' . $options['pr_license_key_' . $item_slug] . '</b></span></p>';
    }
    elseif ( $installed && !$free ) {
      $html .= '<p class="pr-theme-description pr-theme-description-input"><input type="text" id="pr_license_key" name="pr_settings[pr_license_key_' . $item_slug . ']" style="width:100%" placeholder="' . __("Enter your license key", 'pressroom-addons' ) . '"></p>';
    }
    elseif( $free ) {
      $html .= '<p class="pr-theme-description pr-theme-description">'.__( "Free ", 'pressroom-themes' ). '</p>';
    }
    else {
      $html .= '<p class="pr-theme-description pr-theme-description">'.__( "Price ", 'pressroom-themes' ). $item_price .'</p>';
    }

    $html .= '<h3 class="theme-name" id="' . $item_id . '-name">' . $item_name . '</h3>';
    $html .= '<div class="theme-actions">';
    if ( $installed && $activated && !$free ) {
      $html .= '<input type="submit" class="button button-primary pr-theme-deactivate" name="pr_license_key_' . $item_slug . '_deactivate" value="' . __( "Deactivate", 'pressroom-addons' ) . '"/>';
    }
    else if ( $installed && !$free ) {
      $html .= '<input type="submit" class="button button-primary pr-theme-activate" name="pr_license_key_' . $item_slug . '_activate" value="' . __( "Activate", 'pressroom-addons' ) . '"/>';
    }
    elseif( !$installed && $free ) {
      $html .= '<a class="button button-primary pr-theme-deactivate" target="_blank" href="'.$item_link.'">'.__( "Download", 'pressroom-themes' ).'</a>';
    }
    elseif( !$installed  && !$free ) {
      $html .= '<a class="button button-primary pr-theme-deactivate" target="_blank" href="'.$item_link.'">'.__( "Buy", 'pressroom-themes' ).'</a>';
    }

    $html .= '</div>
    <input type="hidden" name="item_' . $item_slug . '_name" value="' . $item_name . '" />
    <input type="hidden" name="item_slug" value="' . $item_slug . '" />
    <input type="hidden" name="return_page" value="pressroom-themes" />
    <input type="hidden" name="type" value="theme" />
    </form>
    </div>';

    return $html;
  }

  /**
 * Render a single theme
 * @param array $theme
 * @return string
 */
 public function _render_theme_installed( $theme ) {

   $html = '<div class="theme ' . ( $theme['active'] ? 'active' : '' ) . '" data-name="' . $theme['uniqueid'] . '" tabindex="0">
   <div class="theme-screenshot pr-theme-screenshot">
   <img src="' . PR_THEME_URI . $theme['path'] . DS . $theme['thumbnail'] . '" alt="">
   </div>
   <p class="pr-theme-description">' . $theme['description'] . '</p>
   <p class="pr-theme-description">
   <span class="pr-theme-version">' . __("Version ", 'pressroom-themes' ) . $theme['version'] . '</span>
   <span>' . __("Made by", 'pressroom-themes' ) . ' <a href="' . $theme['author_site'] . '" target="_blank">' . $theme['author_name'] . '</a></span>
   <span>' . __("Theme url", 'pressroom-themes' ) . ' <a href="' . $theme['website'] . '" target="_blank">' . $theme['website'] . '</a></span>
   </p>';

   if ( $theme['active'] ) {
     $html.= '<h3 class="theme-name" id="' . $theme['uniqueid'] . '-name"><span>Attivo:</span> ' . $theme['name'] . '</h3>
     <div class="theme-actions">
     <a class="button button-primary pr-theme-deactivate" href="' . admin_url('admin.php?page=pressroom-themes&theme_id='. $theme['uniqueid'] .'&theme_status=false') . '">Deactivate</a>
     <a class="button button-secondary pr-theme-delete" href="#">Delete</a>
     </div>';
   }
   else {
     $html.= '<h3 class="theme-name" id="' . $theme['uniqueid'] . '-name">' . $theme['name'] . '</h3>
     <div class="theme-actions pr-theme-actions">
     <a class="button button-primary pr-theme-activate" href="' . admin_url('admin.php?page=pressroom-themes&theme_id='. $theme['uniqueid'] .'&theme_status=true') . '">Activate</a>
     <a class="button button-secondary pr-theme-delete" href="#">Delete</a>
     </div>';
   }

   $html.= '</div>';

   return $html;
 }

  /**
   * Render themes page
   * @echo
   */
  public function pr_themes_page() {

    $this->_update_theme_status();
    $this->_upload_theme();

    $installed_themes = PR_Theme::get_themes();

    echo '<h2 class="nav-tab-wrapper pr-tab-wrapper">';
    echo '<a class="nav-tab nav-tab-active' . '" data-tab="installed" href="#">' . __('Installati', 'pressroom-themes') . '</a>';
    echo '<a class="nav-tab' . '" data-tab="remotes" href="#">' . __('Download', 'pressroom-themes') . '</a>';

    echo '</h2>';

    echo '<div class="wrap" id="themes-container">
    <h2>PressRoom Themes
    <a href="#" class="button button-primary right" id="pr-flush-themes-cache">' . __("Flush themes cache", 'pressroom-themes') . '</a>
    </h2>
    <br>

    <div class="theme-browser rendered" id="themes-installed">
    <div class="themes">';

    if( $installed_themes ) {
      foreach ( $installed_themes as $theme ) {
        echo $this->_render_theme_installed( $theme );
      }
    }
    echo '<div class="theme add-new-theme">
    <form method="post" id="pr-theme-form" enctype="multipart/form-data">
    <a href="#" id="pr-theme-add">
    <div class="theme-screenshot"><span></span></div>
    <h3 class="theme-name">Aggiungi un nuovo tema</h3>
    </a>
    <input type="file" name="pr-theme-upload" id="pr-theme-upload" accept="zip"  />
    </form>
    </div>
    </div>
    <br class="clear">
    </div>

    </div>';
  }

  public function get_remote_themes() {

    $themes = PR_Theme::get_remote_themes();
    $available_themes = get_option('pressroom_themes');
    echo '<div class="theme-browser rendered" id="themes-remote" style="display:none">
    <div class="themes">';
    if( $themes ) {
      foreach ( $themes as $theme ) {
        $pr_license = new PR_EDD_License( __FILE__, $theme->info->slug, '1.0', 'thePrintLabs' );

        $is_installed = false;

        if( isset( $available_themes[$theme->info->slug] ) ) {
          $filepath = isset( $available_themes[$theme->info->slug]['path'] ) ? PR_THEMES_PATH . $available_themes[$theme->info->slug]['path'] : false;
          // check if file exist and is out of pressroom plugin dir ( embedded web exporter )
          if ( file_exists( $filepath ) ) {
            $is_installed = true;
          }
        }

        $is_activated = PR_EDD_License::check_license( $theme );
        $is_free = $theme->pricing->amount == 0 ? true : false;

        echo $this->_render_theme( $theme, $is_installed, $is_activated, $is_free  );

      }
    }
    echo'
    </div>
    <br class="clear">
    </div>';

    die();
  }

  /**
   * Add custom scripts to footer
   *
   * @void
   */
  public function add_custom_scripts() {

    global $pagenow;
    if( $pagenow == 'admin.php' && $_GET['page'] == 'pressroom-themes' ) {
      wp_register_script( "pr_themes_page", PR_ASSETS_URI . "/js/pr.themes_page.js", array( 'jquery' ), '1.0', true );
      wp_enqueue_script( "pr_themes_page" );
      wp_localize_script( "pr_themes_page", "pr", $this->_get_i18n_strings() );
    }
  }

  /**
   * Ajax function for delete theme
   * @return json string
   */
  public function pr_delete_theme() {

    if ( isset( $_POST['theme_id'] ) && strlen( $_POST['theme_id'] ) ) {
      if ( PR_Theme::delete_theme( $_POST['theme_id'] ) ) {
        delete_option( 'pressroom_themes' );
        wp_send_json_success();
      }
    }
    wp_send_json_error();
  }

  /**
   * Ajax function for upload theme
   * @return json string
   */
  public function pr_upload_theme() {

    if ( $this->_upload_theme() ) {
      wp_send_json_success();
    }
    wp_send_json_error();
  }

  /**
   * Ajax function for flush themes cache
   * @return json string
   */
  public function pr_flush_themes_cache() {

    if ( delete_option( 'pressroom_themes' ) ) {
      wp_send_json_success();
    }
    wp_send_json_error();
  }

  /**
   * Script i18n
   * @return array
   */
  protected function _get_i18n_strings() {

    return array(
      'delete_confirm'      => __( "Are you sure you want to delete this theme?", 'pressroom-themes' ),
      'delete_failed'       => __( "An error occurred during deletion", 'pressroom-themes' ),
      'theme_upload_error'  => __( "An error occurred during theme upload", 'pressroom-themes' ),
      'flush_failed'        => __( "An error occurred during cache flush", 'pressroom-themes' ),
      'flush_redirect_url'  => admin_url('admin.php?page=pressroom-themes&refresh_cache=true&pmtype=updated&pmcode=themes_cache_flushed'),
    );
  }

  /**
   * Upload a new theme
   * @return boolean
   */
  protected function _upload_theme() {

    if ( !empty( $_FILES['pr-theme-upload'] ) ) {
      if ( !function_exists( 'wp_handle_upload' ) ) {
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
      }

      $file_types = wp_check_filetype( basename( $_FILES['pr-theme-upload']['name'] ) );
      $uploaded_type = $file_types['type'];
      // Check if the type is supported. If not, throw an error.
      if ( $uploaded_type == 'application/zip' ) {
        $uploaded = wp_handle_upload( $_FILES['pr-theme-upload'], array( 'test_form' => false ) );
        $zip = new ZipArchive;
        if ( $zip->open( $uploaded['file'] ) ) {
          if ( $zip->extractTo( PR_THEMES_PATH ) ) {
            delete_option( 'pressroom_themes' );
            return true;
          }
        }
        $zip->close();
        unlink( $uploaded['file'] );
      }
      return false;
    }
  }

  /**
  * Active / Deactive theme
  * @void
  */
  protected function _update_theme_status() {

    if ( isset( $_GET['theme_status'], $_GET['theme_id'] ) && strlen( $_GET['theme_id'] ) ) {
      PR_Theme::set_theme_status( $_GET['theme_id'], $_GET['theme_status'] == 'true' );
    }
  }
}

$pr_themes_page = new PR_themes_page();
