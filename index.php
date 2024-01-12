<?php
/**
* Plugin Name: Image Alt Boy
* Description: Auto-generates alt descriptions for media library attachments
* Version: 1.0.0
* Plugin URI:  https://www.katsambiris.com
* Author: Nicholas Katsambiris
* Update URI: image-alt-boy
* License: GPL v3
* Tested up to: 6.3
* Requires at least: 6.2
* Requires PHP: 7.4.2
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
* You should have received a copy of the GNU General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

defined( 'ABSPATH' ) || exit;

// Create Admin Submenu under Tools
add_action('admin_menu', 'alt_boy_admin_menu');
function alt_boy_admin_menu() {
    add_submenu_page(
        'tools.php', // Parent slug: the slug for the Tools menu
        'Image Alt Boy', // Page title
        'Image Alt Boy', // Menu title
        'manage_options', // Capability
        'image-alt-boy-settings', // Menu slug
        'image_alt_boy_settings_page', // Function that displays the page content
        null // Position (null means at the end of the Tools menu)
    );
}

// Enqueue Scripts
add_action('admin_enqueue_scripts', 'my_custom_media_library_scripts');
function my_custom_media_library_scripts() {
    wp_enqueue_script('my-custom-media-library', plugin_dir_url(__FILE__) . 'index.js', array('jquery'), null, true);
    wp_enqueue_style('my-plugin-style', plugin_dir_url(__FILE__) . 'style.css?v=12483291', );
}


// Admin page
function image_alt_boy_settings_page() {
    // Check if the form has been submitted and update the option
    if (isset($_POST['image_alt_boy_open_api_key'])) {
        update_option('image_alt_boy_open_api_key', sanitize_text_field($_POST['image_alt_boy_open_api_key']));
    }

    if (isset($_POST['image_alt_boy_custom_prompt'])) {
        update_option('image_alt_boy_custom_prompt', sanitize_textarea_field($_POST['image_alt_boy_custom_prompt']));
    }

    // Retrieve the current API key
    $openai_api_key = get_option('image_alt_boy_open_api_key', '');
    $custom_prompt = get_option('image_alt_boy_custom_prompt', 'Generate an SEO optimised Alt Description for this image, less than 125 characters.');

    ?>
    <div class="wrap">
        <h1>Image Alt Boy</h1>
        <form method="post" action="">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">OpenAI API Key:</th>
                    <td>
                        <input type="password" name="image_alt_boy_open_api_key" value="<?php echo esc_attr($openai_api_key); ?>" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Custom Prompt:</th>
                    <td>
                        <textarea name="image_alt_boy_custom_prompt" rows="4" cols="50"><?php echo esc_textarea($custom_prompt); ?></textarea>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}


function get_alt_text_from_openai($image_url) {
    $api_key = get_option('image_alt_boy_open_api_key', ''); 
    $custom_prompt = get_option('image_alt_boy_custom_prompt', 'Generate an SEO optimised Alt Description for this image, less than 125 characters.');
    $api_endpoint = 'https://api.openai.com/v1/chat/completions';

    $request_body = json_encode([
        'model' => 'gpt-4-vision-preview',
        'messages' => [
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $custom_prompt . ' Do not wrap the text in quotation marks. Always end with a full stop.',
                    ],
                    [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => $image_url
                        ]
                    ]
                ]
            ]
        ],
        'max_tokens' => 300
    ]);

    error_log('Request Body: ' . $request_body);

    $response = wp_remote_post($api_endpoint, [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ],
        'body' => $request_body,
        'data_format' => 'body',
        'timeout' => 30 
    ]);

    if (is_wp_error($response)) {
        return 'Error: ' . $response->get_error_message();
    }

    $response_body = wp_remote_retrieve_body($response);
    error_log('Response Body: ' . $response_body);

    if (wp_remote_retrieve_response_code($response) == 200) {
        $body = json_decode($response_body, true);
        if (isset($body['choices'][0]['message']['content'])) {
            return $body['choices'][0]['message']['content'];
        } else {
            return 'Error: Alt text not found in response.';
        }
    } else {
        return 'Error: Unexpected API response status ' . wp_remote_retrieve_response_code($response);
    }
}


add_action('wp_ajax_process_image_with_openai', 'process_image_with_openai');
function process_image_with_openai() {
    $image_url = isset($_POST['image_url']) ? sanitize_text_field($_POST['image_url']) : '';

    if ($image_url) {

        $alt_text = get_alt_text_from_openai($image_url);

        if ($alt_text) {
            // Here, you can either update an attachment's meta or just return the alt text
            wp_send_json_success($alt_text);
        } else {
            wp_send_json_error('Failed to retrieve alt text from OpenAI.');
        }

        if (is_wp_error($response)) {
            error_log('WP_Error: ' . $response->get_error_message());
            wp_send_json_error('Error: ' . $response->get_error_message());
        }

    } else {
        wp_send_json_error('Image URL not provided.');
    }

    wp_die();
}


function add_custom_fields_to_attachment_fields_to_edit($form_fields, $post) {
    // Define the new field
    $html = '<button class="button image-alt-boy-process-button" onclick="processImageWithOpenAI('.$post->ID.');">';
    $html .= '<span class="button-text">Generate Alt Description</span>';
    $html .= '<span class="alt-boy-btn-spinner" style="display: none;"></span>';
    $html .= '</button>';
    $form_fields['openai_process_button'] = array(
        'label' => '',
        'input' => 'html',
        'html' => $html
    );
    $new_field = array(
        'openai_process_button' => array(
            'label' => '',
            'input' => 'html',
            'html' => $html
        )
    );

    // Merge the new field at the beginning of the form_fields array
    $form_fields = array_merge($new_field, $form_fields);

    return $form_fields;
}
add_filter('attachment_fields_to_edit', 'add_custom_fields_to_attachment_fields_to_edit', 10, 2);


// Add a filter to your specific plugin
add_filter('plugin_action_links_image-alt-boy/index.php', 'image_alt_boy_add_action_links');

function image_alt_boy_add_action_links($links) {
    // The URL to the plugin information
    // Adjust the URL to point to your plugin's readme.txt file or equivalent
    $info_url = 'plugin-install.php?tab=plugin-information&plugin=image-alt-boy&TB_iframe=true&width=600&height=550';

    // Create the thickbox link
    $info_link = '<a href="' . esc_url($info_url) . '" class="thickbox open-plugin-details-modal">View Details</a>';

    // Add the link to the beginning of the $links array
    array_unshift($links, $info_link);

    return $links;
}




// Updater
class My_Plugin_Updater {

    private $current_version;
    private $api_url;

    public function __construct($current_version, $api_url) {
        $this->current_version = $current_version;
        $this->api_url = $api_url;
    }

    public function check_for_update() {
        $response = wp_remote_get($this->api_url);
        if (is_wp_error($response)) {
            return false;
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($data['version'] && version_compare($data['version'], $this->current_version, '>')) {
            return $data;
        }
        return false;
    }
}

function image_alt_boy_check_for_update($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }

    $updater = new My_Plugin_Updater('1.0.0', 'https://raw.githubusercontent.com/nkatsambiris/image-alt-boy/main/updates.json');
    $update_data = $updater->check_for_update();

    if ($update_data) {
        $transient->response['image-alt-boy/index.php'] = (object) array(
            'new_version' => $update_data['version'],
            'package'     => $update_data['download_url'],
            'slug'        => 'image-alt-boy',
            'plugin'      => 'image-alt-boy/index.php',  // This line ensures WordPress knows which plugin is being updated
        );
    }

    return $transient;
}
add_filter('pre_set_site_transient_update_plugins', 'image_alt_boy_check_for_update');

// Displayed in the pligin info window
function image_alt_boy_plugin_info($false, $action, $args) {
    if (isset($args->slug) && $args->slug === 'image-alt-boy') {
        $response = wp_remote_get('https://raw.githubusercontent.com/nkatsambiris/image-alt-boy/main/plugin-info.json');
        if (!is_wp_error($response)) {
            $plugin_info = json_decode(wp_remote_retrieve_body($response));
            if ($plugin_info) {
                return (object) array(
                    'slug' => $args->slug, 
                    'name' => $plugin_info->name,
                    'version' => $plugin_info->version,
                    'author' => $plugin_info->author,
                    'requires' => $plugin_info->requires,
                    'tested' => $plugin_info->tested,
                    'last_updated' => $plugin_info->last_updated,
                    'sections' => array(
                        'description' => $plugin_info->sections->description,
                        'changelog' => $plugin_info->sections->changelog
                    ),
                    'download_link' => $plugin_info->download_link,
                    'banners' => array(
                        'low' => plugin_dir_url(__FILE__) . 'banner-772x250.jpg',
                        'high' => plugin_dir_url(__FILE__) . 'banner-1544x500.jpg'
                    ),
                );
            }
        }
    }
    return $false;
}
add_filter('plugins_api', 'image_alt_boy_plugin_info', 10, 3);

// Used to rename the zip folder on plugin update success
function image_alt_boy_upgrader_package_options($options) {
    if (isset($options['hook_extra']['plugin']) && $options['hook_extra']['plugin'] === 'image-alt-boy/index.php') {
        $options['destination'] = WP_PLUGIN_DIR . '/image-alt-boy';
        $options['clear_destination'] = true; // Overwrite the files
    }
    return $options;
}
add_filter('upgrader_package_options', 'image_alt_boy_upgrader_package_options');