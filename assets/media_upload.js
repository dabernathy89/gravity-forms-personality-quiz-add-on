jQuery(document).ready(function($) {
    var file_frame, image_html;

    function create_file_frame() {
        file_frame = wp.media.frames.file_frame = wp.media({
            title: jQuery( this ).data( 'uploader_title' ),
            button: {
                text: 'Select Image',
            },
            multiple: false  // Set to true to allow multiple files to be selected
        });
    }

    $('.pq-label-media-upload').on('click', function(event) {
        event.preventDefault();
        if ( !file_frame ) {
            create_file_frame();
        }

        file_frame.open();

        file_frame.on( 'select', function(event) {
            attachment = file_frame.state().get('selection').first().toJSON();
            image_html = "<img alt='field label' src='" + attachment.url + "' />";
            $('#field_personality_quiz_replace_label').val(image_html).trigger('blur');
            file_frame.off( 'select' );
        });
    });

    $('#field_choices').on('click', '.pq-choice-media-upload', function(event) {
        var self = $(this);

        event.preventDefault();
        if ( !file_frame ) {
            create_file_frame();
        }

        file_frame.open();

        file_frame.on( 'select', function(event) {
            attachment = file_frame.state().get('selection').first().toJSON();
            image_html = "<img alt='field label' src='" + attachment.url + "' />";
            self.siblings('.field-choice-text').val(image_html).trigger('focus');
            SetFieldChoices();
            file_frame.off( 'select' );
        });
    });

});