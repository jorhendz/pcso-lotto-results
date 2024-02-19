<?php
/*
Plugin Name: PCSO Lottery Results
Description: Fetches and displays PCSO lottery results.
Version: 1.1.1
*/

if (!defined('ABSPATH')) {
    exit;
}

// Update check URL
// Update check URL
define('PCSO_UPDATE_CHECK_URL', 'https://raw.githubusercontent.com/jorhendz/pcso-lotto-results/main/version.txt');

// Check for plugin updates
function check_plugin_updates($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }

    $update_check_url = PCSO_UPDATE_CHECK_URL;
    $plugin_slug = plugin_basename(__FILE__);
    $current_version = $transient->checked[$plugin_slug];

    $response = wp_remote_get($update_check_url);
    if (!is_wp_error($response) && $response['response']['code'] == 200) {
        $latest_version = trim($response['body']);
        // Debugging
        echo "Current Version: $current_version<br>";
        echo "Latest Version: $latest_version<br>";
        if (version_compare($current_version, $latest_version, '<')) {
            $transient->response[$plugin_slug] = (object) array(
                'id' => '1',
                'slug' => $plugin_slug,
                'new_version' => $latest_version,
                'url' => '',
                'package' => '',
            );
        }
    } else {
        // Debugging
        echo "Failed to fetch version from $update_check_url<br>";
    }

    return $transient;
}



// Display update notice
function display_update_notice($transient) {
    $current_version = get_plugin_data(__FILE__)['Version'];
    
    // Check if the transient contains update information for this plugin
    if (isset($transient->response[plugin_basename(__FILE__)])) {
        $latest_version = $transient->response[plugin_basename(__FILE__)]->new_version;
        
        // Compare current version with the latest version
        if (version_compare($current_version, $latest_version, '<')) {
            $update_url = admin_url('update.php?action=upgrade-plugin&plugin=' . urlencode(plugin_basename(__FILE__)));
            $message = sprintf(__('There is a new version (%s) of PCSO Lottery Results available. <a href="%s">Update now</a>.'), $latest_version, $update_url);
            echo '<div class="notice notice-warning"><p>' . $message . '</p></div>';
        }
    }
}

// Call the function and pass $transient as a parameter
add_action('in_plugin_update_message-' . plugin_basename(__FILE__), 'display_update_notice');

add_action('admin_notices', 'display_update_notice');

define('PCSO_URL_DEFAULT', 'https://www.pcso.gov.ph/SearchLottoResult.aspx');

// Fetch PCSO URL from options or use default if not set - new updates
function get_pcso_url() {
    return get_option('pcso_url', PCSO_URL_DEFAULT);
}

// Fetch and display PCSO lottery results
function fetch_pcso_lottery_results() {
    // Fetch PCSO URL
    $url = get_pcso_url();

    // Initialize cURL
    $curl = curl_init($url);
    // Set cURL options
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/97.0.4692.71 Safari/537.36');
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($curl);
    curl_close($curl);

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($response);
    libxml_clear_errors();
    libxml_use_internal_errors(false);

    $table = $dom->getElementById('cphContainer_cpContent_GridView1');

    $output = '';

    if ($table) {
        $output .= '<table class="lottery-results-table">';
        $output .= '<thead>';
        $output .= '<tr>';
        $output .= '<th style="background-color: #EEEEEE;"><font color="black">Draw Name</font></th>';
        $output .= '<th style="background-color: #EEEEEE;"><font color="black">Winning Numbers</font></th>';
        $output .= '<th style="background-color: #EEEEEE;"><font color="black">Draw Date</font></th>';
        $output .= '<th style="background-color: #EEEEEE;"><font color="black">Jackpot</font></th>';
        $output .= '</tr>';
        $output .= '</thead>';
        $output .= '<tbody>';

        foreach ($table->getElementsByTagName('tr') as $row) {
            $cells = $row->getElementsByTagName('td');
            if ($cells->length > 0) {
                $drawName = $cells->item(0)->textContent ?? '';
                $winningNumbers = $cells->item(1)->textContent ?? '';
                $drawDate = $cells->item(2)->textContent ?? '';
                $jackpot = $cells->item(3)->textContent ?? '';
                $output .= '<tr>';
                $output .= '<td style="font-weight: bold;"><font color="#068FFF">' . $drawName . '</font></td>';
                $output .= '<td class="winning-numbers"><font color="#4E4FEB">' . $winningNumbers . '</font></td>';
                $output .= '<td class="draw-date"><font color="#068FFF">' . $drawDate . '</font></td>';
                $output .= '<td class="jackpot"><font color="#4E4FEB">' . $jackpot . '</font></td>';
                $output .= '</tr>';
            }
        }

        $output .= '</tbody>';
        $output .= '</table>';
    }

    return $output;
}
add_shortcode('pcso_lottery_results', 'fetch_pcso_lottery_results');

// Enqueue stylesheet
function pcso_lottery_results_enqueue_styles() {
    wp_enqueue_style('pcso-lottery-results-style', plugins_url('css/style.css', __FILE__));
}
add_action('wp_enqueue_scripts', 'pcso_lottery_results_enqueue_styles');
