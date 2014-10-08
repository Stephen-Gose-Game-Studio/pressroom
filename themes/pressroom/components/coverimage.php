<?php 

$image_id =  get_post_thumbnail_id();
if($image_id):
    $attached_image = wp_get_attachment_metadata($image_id);
    $image = wp_get_attachment_image_src($image_id, 'full');
    $coverClass = 'cover__overlay--'.get_field('cover_overlay').' check';
    $titleClass = 'cover__title cover__title--resize check'; 
else:
    $titleClass = 'article__title'; 
endif;