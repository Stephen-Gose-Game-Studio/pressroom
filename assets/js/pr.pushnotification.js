jQuery(function(){
  jQuery('.tab').click(function(e){
    var $this = jQuery(this);
    jQuery('.tab').removeClass('activeTab');
    jQuery('.prp-panel').removeClass('active');
    $this.addClass('activeTab');
    jQuery('.prp-panel:eq('+$this.index()+')').addClass('active');
  });
  jQuery('#pr-btn-continue').click(function(){
    jQuery('.tab:eq(1)').click();
  });
  jQuery('.prp-type').change(function(){
    var $this = jQuery(this);
    if ( $this.is(':checked') && $this.val() == 'download' ) {
      jQuery('#pr-edition-d').slideDown();
    }
    else {
      jQuery('#pr-edition-d').slideUp();
    }
  });
  jQuery('.prp-time').change(function(){
    var $this = jQuery(this);
    if ( $this.is(':checked') && $this.val() == 'later' ) {
      jQuery('#pr-edition-t').slideDown();
    }
    else {
      jQuery('#pr-edition-t').slideUp();
    }
  });

  jQuery('#prp-edition-0').click(function(){jQuery('#prp-edition-s').prop('disabled', 'disabled');});
  jQuery('#prp-edition-1').click(function(){jQuery('#prp-edition-s').prop('disabled', false);});
  jQuery('input[name="pr_push[editorial_project]"]').change(function(){
    var $this = jQuery(this);
    if ( $this.is(':checked')) {
      jQuery('#prp-edition-s').load(ajaxurl, {'action' : 'pr_push_get_editions_list', 'eproject_slug' : jQuery(this).val() });
    }
    jQuery('#prp-edition-0').click();
  }).change();
  jQuery('#pr-push-form').submit(function(e){
    e.preventDefault();
    var $this = jQuery(this);
    $clog = jQuery('#pr-push-console');
    jQuery('.tab:eq(2)').click();
    jQuery.ajax({
      url: ajaxurl,
      dataType: 'json',
      data: $this.serialize() + '&action=pr_send_push_notification',
      method: 'post',
      beforeSend: function() {
        $clog.empty().append('Sending...<br>');
      }
    }).done(function(data) {
      if ( data.success ) {
        $clog.append( '<span class="cs-success">Sending success.</span><br>' + data.data );
      }
      else {
        $clog.append( '<span class="cs-error">Sending failed.<br>' + data.data + '</span>' );
      }
    });
  });

  var d = new Date();
  jQuery('#prp-rp-time').datetimepicker({
    format: 'Y-m-d H:i:s',
    lang: 'en',
    minDate: 0,
    minTime: new Date(d.setHours(d.getHours()+1)).dateFormat('H:i'),
    maxDate: new Date(d.setDate(d.getDate()+14)).dateFormat('Y/m/d'),
    inline: true,
    yearStart: d.dateFormat('Y'),
    yearEnd: new Date(d.setDate(d.getDate()+14)).dateFormat('Y'),
    onSelectDate: function(c) {
      var t = new Date(d.setHours(d.getHours()+1)).dateFormat('H:i');
      this.setOptions({
        minTime: ( c.dateFormat('d/m/Y') != d.dateFormat('d/m/Y') ? false : t )
      });
    }
  });
});
