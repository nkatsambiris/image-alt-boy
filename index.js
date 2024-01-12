function processImageWithOpenAI() {
    var button = jQuery('.image-alt-boy-process-button');
    var buttonText = button.find('.button-text');
    var spinner = button.find('.alt-boy-btn-spinner');

    // Show spinner, disable button, and change text
    spinner.show();
    buttonText.text('Generating Description');
    button.prop('disabled', true);

    var imageUrl = jQuery('#attachment-details-copy-link, .attachment-details-copy-link').val(); // Retrieve the image URL from the input field
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'process_image_with_openai',
            image_url: imageUrl,
        },
        success: function(response) {
            if(response.success) {
                
                // console.log('Alt text updated successfully: ', response.data);

                // Update the alt text field with the received response
                jQuery('#attachment-details-alt-text, #attachment-details-two-column-alt-text').val(response.data);
            } else {
                console.error('Error: ', response.data);
            }
        },
        error: function(xhr, status, error) {

            // console.error('AJAX Error:', xhr.responseText, status, error);
            
            alert('An error occurred generating the image alt description. Please try again.');
        },
        complete: function() {
            // Hide spinner, enable button, and revert text
            spinner.hide();
            buttonText.text('Generate Alt Description');
            button.prop('disabled', false);
        }
    });
}