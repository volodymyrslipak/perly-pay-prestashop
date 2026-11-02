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

declare(strict_types=1);

namespace PerlyPay\PrestaShop\Shop;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Private messages on an order: the "Messages" card of the order page in
 * the back office, marked private, never shown to the customer. They live
 * in the order's customer thread, as PrestaShop keeps every order message.
 */
final class Messages
{
    public static function add(\Order $order, string $text): void
    {
        $text = trim($text);
        if ($text === '') {
            return;
        }
        $customer = new \Customer((int) $order->id_customer);
        $email = \Validate::isEmail($customer->email) ? (string) $customer->email : '';
        $threadId = $email === '' ? 0 : (int) \CustomerThread::getIdCustomerThreadByEmailAndIdOrder($email, (int) $order->id);
        if ($threadId === 0) {
            $thread = new \CustomerThread();
            $thread->id_contact = 0;
            $thread->id_customer = (int) $order->id_customer;
            $thread->id_shop = (int) $order->id_shop;
            $thread->id_order = (int) $order->id;
            $thread->id_lang = (int) $order->id_lang;
            $thread->email = $email;
            // Closed: a note for the staff, not a question from the customer waiting in Customer Service.
            $thread->status = 'closed';
            $thread->token = \Tools::passwdGen(12);
            $thread->add();
            $threadId = (int) $thread->id;
        }
        $message = new \CustomerMessage();
        $message->id_customer_thread = $threadId;
        $message->id_employee = 0;
        $message->message = mb_substr($text, 0, 65000);
        $message->private = true;
        $message->add();
    }
}
