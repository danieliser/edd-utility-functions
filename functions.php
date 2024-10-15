<?php
/**
 * EDD Helper Functions
 *
 * @category   Custom
 * @package    HookedFiltered
 * @author     Daniel Iser <daniel@hookedfiltered.com>
 * @license    GPL-3.0-or-later https://opensource.org/licenses/GPL-3.0
 * @version    GIT: <git_id>
 * @link       https://hookedfiltered.com
 * @copyright: Hooked & Filtered 2024
 */

// phpcs:disable PEAR.NamingConventions.ValidFunctionName, Generic.Files.LineLength.TooLong

namespace HookedFiltered\EDD;

/**
 * Check if a user owns a download.
 *
 * @param int $download_id The ID of the download.
 * @param int $user_id     The ID of the user.
 * 
 * @return bool
 * 
 * @version 1.0.2
 */
function user_owns_download( $download_id = null, $user_id = null )
{
    static $cache = [];

    if (! is_user_logged_in() ) {
        return false;
    }

    $download_id = $download_id ? $download_id : get_the_ID();
    $user_id = $user_id ? $user_id : get_current_user_id();

    // Generate a unique cache key
    $cache_key = $download_id . '_' . $user_id;

    // Check if the result is already cached for this request
    if (isset($cache[$cache_key]) ) {
        return $cache[$cache_key];
    }

    // If not cached, check if the user has purchased the download.
    $owns_download = edd_has_user_purchased($user_id, [ $download_id ]);

    // Cache the result for this request
    $cache[$cache_key] = $owns_download;

    return $owns_download;
}

/**
 * Get the download URL for a licensed user for a given download ID.
 *
 * @param int $download_id The ID of the download.
 * @param int $user_id     The ID of the user.
 * 
 * @return string The download URL if available, empty string otherwise.
 * 
 * @version 1.0.3
 */
function get_licensed_download_url( $download_id = null, $user_id = null )
{
    if (! is_user_logged_in() ) {
        return '';
    }

    $download_id = $download_id ? $download_id : get_the_ID();
    $user_id     = $user_id ? $user_id : get_current_user_id();

    // Generate a unique cache key
    $cache_key = 'edd_licensed_url_' . $download_id . '_' . $user_id;

    // Try to get the cached value
    $url = wp_cache_get($cache_key, 'edd_licensed_urls');

    if (false === $url ) {
        // Cache miss, generate the URL
        if (!edd_has_user_purchased($user_id, [$download_id])) {
            $url = '';
        } else {
            $payments = edd_get_payments(
                [
                'user' => $user_id,
                'download' => $download_id,
                'status' => 'complete',
                'number' => 1
                ]
            );

            if (empty($payments)) {
                $url = '';
            } else {
                $payment = $payments[0];
                $payment_key = edd_get_payment_key($payment->ID);
                $email = edd_get_payment_user_email($payment->ID);

                $file_key = 0; // Assuming you want the first file, adjust if needed
                $price_id = null; // Adjust if using variable pricing

                $url = edd_get_download_file_url($payment_key, $email, $file_key, $download_id, $price_id);
            }
        }

        // Cache the result
        wp_cache_set($cache_key, $url, 'edd_licensed_urls', HOUR_IN_SECONDS);
    }

    return $url;
}

/**
 * Clear the user owns download cache for a specific user and download.
 *
 * @param int $download_id The ID of the download.
 * @param int $user_id     The ID of the user.
 * 
 * @return void
 * 
 * @version 1.0.0
 */
function clear_user_owns_download_cache( $download_id, $user_id )
{
    $cache_key = 'edd_user_owns_download_' . $download_id . '_' . $user_id;
    wp_cache_delete($cache_key, 'edd_user_purchases');
}


/**
 * Clear the user owns download cache for a specific user and download.
 *
 * @param int          $payment_id   The ID of the payment.
 * @param array        $payment_data The payment data.
 * @param EDD_Customer $customer     The customer object.
 * 
 * @return void
 * 
 * @version 1.0.0
 */
function clear_cache_on_purchase( $payment_id, $payment_data, $customer )
{
    $downloads = edd_get_payment_meta_downloads($payment_id);
    foreach ( $downloads as $download ) {
        clear_user_owns_download_cache($download['id'], $customer->user_id);
    }
}

add_action('edd_complete_purchase', 'clear_cache_on_purchase', 10, 3);

/**
 * Clear the user owns download cache for a specific user and download.
 *
 * @param int    $payment_id The ID of the payment.
 * @param string $new_status The new status of the payment.
 * @param string $old_status The old status of the payment.
 * 
 * @return void
 * 
 * @version 1.0.0
 */
function clear_cache_on_payment_status_change( $payment_id, $new_status, $old_status )
{
    if ($new_status !== $old_status ) {
        $customer_id = edd_get_payment_customer_id($payment_id);
        $downloads = edd_get_payment_meta_downloads($payment_id);
        foreach ( $downloads as $download ) {
            clear_user_owns_download_cache($download['id'], $customer_id);
        }
    }
}

add_action('edd_update_payment_status', 'clear_cache_on_payment_status_change', 10, 3);
