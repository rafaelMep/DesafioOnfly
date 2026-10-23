<?php

namespace Database\Seeders;

use App\Enums\TravelStatus;
use App\Models\TravelOrder;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $admin = User::firstOrCreate(['email' => 'admin@onfly.test'], [
            'name' => 'Marina Costa', 'password' => 'Onfly@123',
        ]);
        $admin->forceFill(['is_admin' => true])->save();
        $rafael = User::firstOrCreate(['email' => 'rafael@onfly.test'], [
            'name' => 'Rafael Silva', 'password' => 'Onfly@123',
        ]);
        $ana = User::firstOrCreate(['email' => 'ana@onfly.test'], [
            'name' => 'Ana Oliveira', 'password' => 'Onfly@123',
        ]);

        // Idempotent: running the seeder again never duplicates or resets orders.
        if (TravelOrder::query()->exists()) {
            return;
        }

        $destinations = ['Belo Horizonte, MG', 'São Paulo, SP', 'Rio de Janeiro, RJ', 'Curitiba, PR', 'Recife, PE', 'Florianópolis, SC', 'Brasília, DF', 'Porto Alegre, RS'];
        foreach ([$rafael, $ana, $admin] as $index => $user) {
            foreach ($destinations as $position => $destination) {
                $order = new TravelOrder([
                    'destination' => $destination,
                    'departure_date' => today()->addDays(4 + $position * 3),
                    'return_date' => today()->addDays(7 + $position * 3),
                ]);
                $order->status = match ($position % 4) {
                    1 => TravelStatus::Approved,
                    3 => TravelStatus::Cancelled,
                    default => TravelStatus::Requested,
                };
                $order->created_at = now()->subDays($position + $index);
                $user->travelOrders()->save($order);
            }
        }
    }
}
