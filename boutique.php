<?php
require_once "../secret_key.php";

function get_current_user_wordpress()
{
    require 'wp-load.php';
    return wp_get_current_user();
}

function get_current_user_email_wordpress($current_user)
{
    $email = (string) $current_user->user_email;
    if ($email == "") {
        return false;
    } else {
        return $email;
    }
}

function cipher_text($email)
{
    // From https://www.php.net/manual/fr/function.openssl-encrypt.php
    $ivlen = openssl_cipher_iv_length($cipher = "AES-128-CBC");
    $iv = openssl_random_pseudo_bytes($ivlen);
    $ciphertext_raw = openssl_encrypt($email, $cipher, SECRET_KEY, $options = OPENSSL_RAW_DATA, $iv);
    $hmac = hash_hmac('sha256', $ciphertext_raw, SECRET_KEY, $as_binary = true);
    $ciphertext = base64_encode($iv . $hmac . $ciphertext_raw);

    return $ciphertext;
}
$current_user = get_current_user_wordpress();
$email = get_current_user_email_wordpress($current_user);
if (!$email) {
    header("Location: /");
} else {
    session_start();
    $firstname = (string) $current_user->user_firstname;
    $lastname = (string) $current_user->user_lastname;
    $_SESSION["user_mail"] = cipher_text($email);
    $_SESSION["user_firstname"] = cipher_text($firstname);
    $_SESSION["user_lastname"] = cipher_text($lastname);
    header("Location: boutique_prestashop.php");
}
