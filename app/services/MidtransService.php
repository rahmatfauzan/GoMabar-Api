<?php

namespace App\Services;

use Midtrans\Config;
use Midtrans\Snap;

class MidtransService
{
    public function __construct()
    {
        // Set Midtrans configuration
        Config::$serverKey = 'SB-Mid-server-DCGznSE43UkVSSZtN462g0Zv';
        Config::$isProduction = false;
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    /**
     * Create Snap Token for payment
     */
    public function createSnapToken($booking, $customerDetails)
    {
        $params = [
            'transaction_details' => [
                'order_id' => $booking->invoice_number,
                'gross_amount' => $booking->price,
            ],
            'customer_details' => $customerDetails,
            'item_details' => [
                [
                    'id' => $booking->field_id,
                    'price' => $booking->price,
                    'quantity' => 1,
                    'name' => 'Booking ' . $booking->field->name . ' - ' . $booking->booking_date->format('d M Y'),
                ]
            ],
            'enabled_payments' => [
                'credit_card',
                'gopay',
                'shopeepay',
                'bca_va',
                'bni_va',
                'bri_va',
                'permata_va',
                'other_va',
                'qris',
            ],
            'expiry' => [
                'start_time' => date('Y-m-d H:i:s O'),
                'unit' => 'minute',
                'duration' => 15
            ],
            'callbacks' => [
                'finish' => config('app.frontend_url') . '/booking/success',
                'error' => config('app.frontend_url') . '/booking/error',
                'pending' => config('app.frontend_url') . '/booking/pending',
            ],
        ];

        try {
            $snapToken = Snap::getSnapToken($params);
            return $snapToken;
        } catch (\Exception $e) {
            throw new \Exception('Failed to create snap token: ' . $e->getMessage());
        }
    }

    /**
     * Get transaction status from Midtrans
     */
    public function getTransactionStatus($orderId)
    {
        try {
            return \Midtrans\Transaction::status($orderId);
        } catch (\Exception $e) {
            throw new \Exception('Failed to get transaction status: ' . $e->getMessage());
        }
    }
}
