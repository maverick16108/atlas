<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ModeratorsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $firstNames = [
            'Александр', 'Дмитрий', 'Максим', 'Сергей', 'Андрей', 'Алексей', 'Артём', 'Илья',
            'Кирилл', 'Михаил', 'Никита', 'Матвей', 'Роман', 'Егор', 'Арсений', 'Иван',
            'Денис', 'Евгений', 'Тимофей', 'Владислав', 'Игорь', 'Владимир', 'Павел', 'Руслан',
            'Мария', 'Анна', 'Елена', 'Ольга', 'Наталья', 'Екатерина', 'Анастасия', 'Татьяна',
            'Ирина', 'Светлана', 'Юлия', 'Дарья', 'Алина', 'Виктория', 'Полина', 'Ксения',
            'Софья', 'Вероника', 'Кристина', 'Валерия', 'Диана', 'Марина', 'Людмила', 'Галина'
        ];

        $lastNames = [
            'Иванов', 'Смирнов', 'Кузнецов', 'Попов', 'Васильев', 'Петров', 'Соколов', 'Михайлов',
            'Новиков', 'Федоров', 'Морозов', 'Волков', 'Алексеев', 'Лебедев', 'Семенов', 'Егоров',
            'Павлов', 'Козлов', 'Степанов', 'Николаев', 'Орлов', 'Андреев', 'Макаров', 'Никитин',
            'Захаров', 'Зайцев', 'Соловьев', 'Борисов', 'Яковлев', 'Григорьев', 'Романов', 'Воробьев',
            'Сергеев', 'Кузьмин', 'Фролов', 'Александров', 'Дмитриев', 'Королев', 'Гусев', 'Киселев',
            'Ильин', 'Максимов', 'Поляков', 'Сорокин', 'Виноградов', 'Ковалев', 'Белов', 'Медведев'
        ];

        $domains = ['mail.ru', 'yandex.ru', 'gmail.com', 'rambler.ru', 'inbox.ru'];

        for ($i = 1; $i <= 100; $i++) {
            $firstName = $firstNames[array_rand($firstNames)];
            $lastName = $lastNames[array_rand($lastNames)];
            $domain = $domains[array_rand($domains)];
            
            // Transliterate for email
            $emailName = $this->transliterate(mb_strtolower($firstName)) . '.' . $this->transliterate(mb_strtolower($lastName)) . $i;
            
            User::create([
                'name' => $firstName . ' ' . $lastName,
                'email' => $emailName . '@' . $domain,
                'password' => Hash::make('Password123!'),
                'role' => 'moderator',
                'phone' => 'mod_' . uniqid(),
                'is_accredited' => true,
                'last_login_at' => now()->subDays(rand(0, 30))->subHours(rand(0, 23))->subMinutes(rand(0, 59)),
            ]);
        }
    }

    private function transliterate(string $string): string
    {
        $converter = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
            'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
            'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
            'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'sch', 'ь' => '', 'ы' => 'y', 'ъ' => '',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        ];

        return strtr($string, $converter);
    }
}
