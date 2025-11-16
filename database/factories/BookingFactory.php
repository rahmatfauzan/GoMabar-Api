<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Field;
use App\Models\User;
use App\Models\Role;
use App\Models\Booking;
use Carbon\Carbon;

class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $field = Field::inRandomOrder()->first();
        if (!$field) {
            $field = Field::factory()->create();
        }

        $user = User::whereHas('roles', fn($q) => $q->where('name', 'user'))->inRandomOrder()->first();
        if (!$user) {
             $user = User::factory()->create();
             $user->roles()->attach(Role::firstOrCreate(['name' => 'user']));
        }

        $bookingDate = Carbon::today()->subDays(rand(0, 30));
        $totalSlots = rand(1, 2);
        $startHour = rand(8, 20);
        $bookedSlots = [];
        $totalPrice = 0;
        $pricePerSlot = $bookingDate->isWeekend() ? $field->price_weekend : $field->price_weekday;

        for ($i = 0; $i < $totalSlots; $i++) {
            $hour = $startHour + $i;
            $bookedSlots[] = str_pad($hour, 2, '0', STR_PAD_LEFT) . ':00';
            $totalPrice += $pricePerSlot;
        }

        return [
            'user_id' => $user->id,
            'name_orders' => null,
            'phone_orders' => null,
            'invoice_number' => 'INV-BOOK-' . strtoupper(uniqid()),
            'status' => $this->faker->randomElement(['active', 'failed', 'cancelled', 'waiting_payment']),
            'field_id' => $field->id,
            'booking_date' => $bookingDate->toDateString(),
            'booked_slots' => $bookedSlots,
            'price' => $totalPrice,
        ];
    }
}