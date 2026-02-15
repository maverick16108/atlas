<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class ParticipantsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Force Russian locale for names
        $faker = \Faker\Factory::create('ru_RU');

        // Clear existing participants
        User::where('role', 'client')->delete();

        // 110 Accredited Participants (Organizations)
        User::factory()->count(110)->create([
            'is_accredited' => true,
            'role' => 'client',
            'name' => fn() => $faker->company, // Use company name
            'phone' => fn() => $faker->unique()->phoneNumber,
            // Force mobile format: +7 (9XX) XXX-XX-XX
            'auth_phone' => fn() => sprintf('+7 (%s) %s-%s-%s', 
                $faker->numerify('9##'), 
                $faker->numerify('###'), 
                $faker->numerify('##'), 
                $faker->numerify('##')
            ),
            'inn' => $faker->numerify('##########'),
            'kpp' => $faker->numerify('#########'),
        ]);

        // 10 Non-Accredited Participants (Organizations)
        User::factory()->count(10)->create([
            'is_accredited' => false,
            'role' => 'client',
            'name' => fn() => $faker->company, // Use company name
            'phone' => fn() => $faker->unique()->phoneNumber,
            'auth_phone' => null, // Empty for non-accredited
            'inn' => $faker->numerify('##########'),
            'kpp' => $faker->numerify('#########'),
        ]);
    }
}
