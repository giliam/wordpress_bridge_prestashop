<?php
session_start();
require_once "../secret_key.php";
require_once "bridge_parameters.php";
assert(is_dir(PRESTASHOP_RELATIVE_PATH), "Problem of directory...");
require PRESTASHOP_RELATIVE_PATH . 'vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;

if (isset($_SESSION["user_mail"])) {
    $currentRequest = Request::createFromGlobals();

    //create new HttpFundation\Request
    //add id_shop in $_GET
    //copy $_SERVER from currentRequest
    $cleanRequest = Request::create('', 'GET', array('id_shop' => 1), array(), array(), $currentRequest->server->all());
    $cleanRequest->overrideGlobals();

    //init prestashop
    include(PRESTASHOP_RELATIVE_PATH . 'config/config.inc.php');

    function generateRandomString($length = 10)
    {
        // from https://stackoverflow.com/a/4356295
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    function psLogin($email, $firstname, $lastname)
    {
        $customer = new Customer();
        $authentication = $customer->getByEmail(
            $email
        );

        if (!$authentication || !$customer->id) {
            $customer->firstname = empty(trim($firstname)) ? "Inconnu" : trim($firstname);
            $customer->lastname = empty(trim($lastname)) ? "Inconnu" : trim($lastname);
            $customer->passwd = md5(pSQL(_COOKIE_KEY_ . generateRandomString(15)));
            $customer->email = strtolower(trim($email));
            $customer->is_guest = 0;
            $customer->active = 1;
            $add_action = $customer->add();
            $authentication = $customer->getByEmail(
                $email
            );
        }

        if (isset($authentication->active) && !$authentication->active) {
            return false;
        } elseif ($customer->is_guest) {
            return false;
        } else {
            $ctx = \Context::getContext();

            $ctx->customer = $customer;
            $ctx->cookie->id_customer = (int) $customer->id;
            $ctx->cookie->customer_lastname = $customer->lastname;
            $ctx->cookie->customer_firstname = $customer->firstname;
            $ctx->cookie->passwd = $customer->passwd;
            $ctx->cookie->logged = 1;
            $customer->logged = 1;
            $ctx->cookie->email = $customer->email;
            $ctx->cookie->is_guest = $customer->isGuest();

            $idCart = (int) \Cart::lastNoneOrderedCart($ctx->customer->id);
            if ($idCart) {
                $ctx->cart = new \Cart($idCart);
            } else {
                $ctx->cart = new \Cart();
                $ctx->cart->id_currency = \Currency::getDefaultCurrency()->id; //mandatory field
            }
            if (isset($idCarrier) && $idCarrier) {
                $deliveryOption = [$ctx->cart->id_address_delivery => $idCarrier . ','];
                $ctx->cart->setDeliveryOption($deliveryOption);
            }

            $ctx->cart->save();
            $ctx->cookie->id_cart = (int) $ctx->cart->id;
            $ctx->cookie->write();
            $ctx->cart->autosetProductAddress();

            $ctx->cookie->registerSession(new CustomerSession());
            return true;
        }
        return false;
    }

    function decipher_text($ciphertext)
    {
        // From https://www.php.net/manual/fr/function.openssl-encrypt.php
        $c = base64_decode($ciphertext);
        $ivlen = openssl_cipher_iv_length($cipher = "AES-128-CBC");
        $iv = substr($c, 0, $ivlen);
        $hmac = substr($c, $ivlen, $sha2len = 32);
        $ciphertext_raw = substr($c, $ivlen + $sha2len);
        $original_plaintext = openssl_decrypt($ciphertext_raw, $cipher, SECRET_KEY, $options = OPENSSL_RAW_DATA, $iv);
        $calcmac = hash_hmac('sha256', $ciphertext_raw, SECRET_KEY, $as_binary = true);
        if (hash_equals($hmac, $calcmac)) {
            return $original_plaintext;
        } else {
            return false;
        }
    }

    $email = decipher_text($_SESSION["user_mail"]);
    $firstname = decipher_text($_SESSION["user_firstname"]);
    $lastname = decipher_text($_SESSION["user_lastname"]);

    $connect = psLogin($email, $firstname, $lastname);

    if ($connect) {
        unset($_SESSION["user_mail"]);
        unset($_SESSION["user_firstname"]);
        unset($_SESSION["user_lastname"]);
        header("Location: " . PRESTASHOP_RELATIVE_PATH);
    } else {
        header("Location: /");
    }
} else {
    header("Location: /");
}
