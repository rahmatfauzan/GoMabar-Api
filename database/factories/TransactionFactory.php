<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Transaction;
use App\Models\Booking;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        $status = $this->faker->randomElement(['pending', 'success', 'failed']);
        $gateway = ($status === 'success') ? $this->faker->randomElement(['midtrans', 'cash']) : 'midtrans';
        
        return [
            // 'booking_id' harus diisi dari Seeder (via ->for())
            'amount' => 0, 
            'status' => $status,
            'payment_gateway' => $gateway,
            'gateway_token' => ($gateway === 'midtrans') ? $this->faker->uuid : null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Transaction $transaction) {
            if ($transaction->amount === 0 && $transaction->booking) {
                $transaction->amount = $transaction->booking->price;
            }
        });
    }
}