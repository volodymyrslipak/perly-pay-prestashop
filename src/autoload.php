<?php

/**
 * Perly Pay for PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * that is bundled with this package in the file LICENSE.
 *
 * @author    Perly
 * @copyright 2026 Perly
 * @license   https://opensource.org/licenses/MIT MIT License
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

/*
 * Class loading without Composer on the shop's server: the module's own
 * classes under PerlyPay\PrestaShop\ (this folder), and Perly Pay's PHP
 * SDK, copied in at build time under its own prefix
 * (PerlyPay\PrestaShop\Vendor\Perly\Pay\, in vendor/perly-pay-sdk) so it
 * can never clash with another module that ships the SDK too.
 */
spl_autoload_register(
    static function ($className) {
        $roots = [
            'PerlyPay\\PrestaShop\\Vendor\\Perly\\Pay\\' => dirname(__DIR__) . '/vendor/perly-pay-sdk/src/',
            'PerlyPay\\PrestaShop\\' => __DIR__ . '/',
        ];
        foreach ($roots as $prefix => $dir) {
            if (strncmp($className, $prefix, strlen($prefix)) !== 0) {
                continue;
            }
            $file = $dir . str_replace('\\', '/', substr($className, strlen($prefix))) . '.php';
            if (is_readable($file)) {
                require_once $file;
            }

            return;
        }
    }
);
