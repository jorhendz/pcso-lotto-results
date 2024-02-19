<?php
/*
Plugin Name: PCSO Lottery Results
Description: Fetches and displays PCSO lottery results.
Version: 1.0.1
*/

if (!defined('ABSPATH')) {
    exit;
}

// Update check URL
define('PCSO_UPDATE_CHECK_URL', 'https://github.com/jorhendz/pcso-lotto-results/blob/main/update-check.php');

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
        if (version_compare($current_version, $latest_version, '<')) {
            $transient->response[$plugin_slug] = (object) array(
                'id' => '1',
                'slug' => $plugin_slug,
                'new_version' => $latest_version,
                'url' => '',
                'package' => '',
            );
        }
    }

    return $transient;
}
add_filter('pre_set_site_transient_update_plugins', 'check_plugin_updates');

define('PCSO_URL_DEFAULT', 'https://www.pcso.gov.ph/SearchLottoResult.aspx');

// Fetch PCSO URL from options or use default if not set
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
