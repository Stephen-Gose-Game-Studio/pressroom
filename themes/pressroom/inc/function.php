<?php

// remove_filter( 'the_content', 'wpautop' );

/**
 		Remove Empty <p>
 */

add_filter('the_content', 'remove_empty_p', 20, 1);

function remove_empty_p($content){
    $content = force_balance_tags($content);
    return preg_replace('#<p>\s*+(<br\s*/*>)?\s*</p>#i', '', $content);
}

/**
 		Wrap images in a <figure> tag
 */

// add_filter('the_content', 'filter_images', 30, 1);

function filter_images($content){
    return preg_replace('/<img (.*) \/>\s*/iU', '<figure><img \1 /></figure>', $content);
}

/**
 		Image with caption clean up
 */

add_filter('img_caption_shortcode', 'clean_caption', 10, 3);

function clean_caption($output, $attr, $content) {
  if (is_feed()) {
    return $output;
  }

  $defaults = array(
    'id'      => '',
    'align'   => 'alignnone',
    'width'   => '',
    'caption' => ''
  );

  $attr = shortcode_atts($defaults, $attr);

  // If the width is less than 1 or there is no caption, return the content wrapped between the [caption] tags
  if ($attr['width'] < 1 || empty($attr['caption'])) {
    return $content;
  }

  // Set up the attributes for the caption <figure>
  $attributes  = (!empty($attr['id']) ? ' id="' . esc_attr($attr['id']) . '"' : '' );
  $attributes .= ' class="thumbnail wp-caption ' . esc_attr($attr['align']) . '"';
  $attributes .= ' style="width: ' . esc_attr($attr['width']) . 'px"';

  $output  = '<figure' . $attributes .'>';
  $output .= do_shortcode($content);
  $output .= '<figcaption class="caption wp-caption-text">' . esc_attr($attr['caption']) . '</figcaption>';
  $output .= '</figure>';

  return $output;
}

/**
 		Another way of filtering image output
 */

add_filter('the_content', 'another_filter_images', 40, 1);

function another_filter_images($content){

    preg_match_all( "#<img.*?class\s*=((\"|')+(.*?)(\"|'))+(.*?)src\s*=((\"|')+(.*?)(\"|'))+(.*?)>#i", $content, $images );
    foreach ($images[0] as $k => $img) {
      $content = str_replace($img, '<figure class="'.$images[3][$k].' image">'.$img.'</figure>', $content);
      //$content = str_replace($img, '<figure class="'.$images[3][$k].'"><a href="'.$images[8][$k].'" class="img-gallery" data-gallery data-share="">'.$img.'</a></figure>', $content);
    }
    return $content;
}

/**
 		Filter applied at image insert
 */

add_filter( 'image_send_to_editor', 'wp_image_wrap_init', 10, 8 );
function wp_image_wrap_init( $html, $id, $caption, $title, $align, $url, $size, $alt ) {
    $meta_w_img = wp_get_attachment_metadata($id);
    $meta_w_img_title = $meta_w_img['image_meta']['title'];
    return '<figure id="'. $id .'" class="'. $align .'"><a href="'. wp_get_attachment_url( $id ) .'" class="img-gallery" data-gallery data-share=""><img class="lazy-load img-thumbnail" data-src="'. wp_get_attachment_url( $id ) .'"src="'.get_stylesheet_directory_uri().'/assets/img/blank.gif" title="'.$meta_w_img_title.'" alt="'.$meta_w_img_title.'"/><span class="icons"></span><noscript><img src="'. wp_get_attachment_url( $id ) .'" /></noscript></a></figure>';
}


