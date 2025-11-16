<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MidtransWebhookController extends Controller
{
    /**
     * Handle Midtrans notification webhook
     */
    public function handleNotification(Request $request)
    {
        Log::info('🔔 Midtrans Notification Received', $request->all());

        // Get notification data
        $serverKey = 'SB-Mid-server-DCGznSE43UkVSSZtN462g0Zv';

        $orderId = $request->order_id;
        $statusCode = $request->status_code;
        $grossAmount = $request->gross_amount;
        $signatureKey = $request->signature_key;

        // Verify signature key
        $hashed = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        if ($hashed !== $signatureKey) {
            Log::error('❌ Invalid signature key', [
                'expected' => $hashed,
                'received' => $signatureKey
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid signature'
            ], 403);
        }

        // Find booking
        $booking = Booking::where('invoice_number', $orderId)->first();

        if (!$booking) {
            Log::error('❌ Booking not found', ['order_id' => $orderId]);

            return response()->json([
                'success' => false,
                'message' => 'Booking not found'
            ], 404);
        }

        $transaction = $booking->transaction;

        // Log notification
        Log::info('📝 Processing notification', [
            'order_id' => $orderId,
            'transaction_status' => $request->transaction_status,
            'payment_type' => $request->payment_type,
            'fraud_status' => $request->fraud_status ?? null,
        ]);

        // Update transaction status based on Midtrans status
        $transactionStatus = $request->transaction_status;
        $fraudStatus = $request->fraud_status ?? null;

        if ($transactionStatus == 'capture') {
            if ($fraudStatus == 'accept') {
                // Payment success
                $this->updateToSuccess($booking, $transaction);
            }
        } elseif ($transactionStatus == 'settlement') {
            // Payment success
            $this->updateToSuccess($booking, $transaction);
        } elseif ($transactionStatus == 'pending') {
            // Payment pending
            $transaction->update(['status' => 'pending']);
            Log::info('⏳ Payment pending', ['order_id' => $orderId]);
        } elseif (in_array($transactionStatus, ['deny', 'expire', 'cancel'])) {
            // Payment failed
            $this->updateToFailed($booking, $transaction);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification processed'
        ]);
    }

    private function updateToSuccess($booking, $transaction)
    {
        $booking->update(['status' => 'active']);
        $transaction->update(['status' => 'success']);

        Log::info('✅ Payment success', [
            'booking_id' => $booking->id,
            'invoice_number' => $booking->invoice_number
        ]);
    }

    private function updateToFailed($booking, $transaction)
    {
        $booking->update(['status' => 'failed']);
        $transaction->update(['status' => 'failed']);

        Log::info('❌ Payment failed', [
            'booking_id' => $booking->id,
            'invoice_number' => $booking->invoice_number
        ]);
    }
}
