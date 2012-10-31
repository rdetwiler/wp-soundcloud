jQuery(function () {
	jQuery('#add_tag').click(function (e) {
		var value = jQuery('input[name=tag]').val();

		if (value) {
			jQuery('#tags').append(
			jQuery('<li></li>', {
			text: value
			}));
			
		jQuery('input[name=tag]').val('');
		}
	});

	jQuery('#tags').delegate('li', 'click', function () {
		jQuery(this).remove();
	});	

	jQuery('#add_track').submit(function (e) {
	alert('hi');
		var $tags = jQuery('#tags li'),
		i = 0,
		s = $tags.length,
		tags = [];

		if ($tags.length > 0) {
			for (; i < s; i += 1) {
				tags.push($tags.eq(i).text());
			}
			jQuery(this).append($('<input />', {
				name: 'tags',
				value: tags.join(' '),
				type: 'hidden'
			}));
		}
	});								
});		