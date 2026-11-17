<?php

namespace Database\Seeders;

use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\BusinessHour;
use App\Models\Reservation;
use App\Models\Trainer;
use App\Models\TrainerSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // This seeder contains development accounts and intentionally never runs in production.
        if (app()->environment('production')) {
            $this->command?->warn('デモデータは本番環境には登録しません。');
            return;
        }
        User::firstOrCreate(['email' => 'admin@example.com'], ['name' => 'FORM 管理者', 'password' => 'Password123!', 'role' => UserRole::Admin]);
        User::firstOrCreate(['email' => 'client@example.com'], ['name' => '山田 花子', 'password' => 'Password123!', 'role' => UserRole::Client]);

        foreach (range(0, 6) as $day) {
            BusinessHour::firstOrCreate(['day_of_week' => $day], ['open_time' => '09:00', 'close_time' => '19:00', 'is_open' => $day !== 1]);
        }
        $profiles = [
            ['name' => 'YUNA', 'specialty' => 'ボディメイク・姿勢改善', 'bio' => '小さな変化を、一緒に楽しみましょう。はじめての方も、無理なく続けられるトレーニングをご提案します。', 'color' => '#D9C5B2'],
            ['name' => 'MIKU', 'specialty' => 'ピラティス・コンディショニング', 'bio' => 'しなやかで、動きやすい身体へ。呼吸と姿勢から整え、あなたらしいペースを大切にサポートします。', 'color' => '#BFCBBE'],
            ['name' => 'ARISA', 'specialty' => '筋力アップ・ダイエット', 'bio' => '目標に向かう一歩を、丁寧に。正しいフォームと食習慣で、日常が変わる身体づくりをお手伝いします。', 'color' => '#D7B7A7'],
            ['name' => 'REN', 'specialty' => '機能改善・パフォーマンス', 'bio' => '身体の使い方を知ることから。スポーツにも日々の暮らしにもつながる、機能的なトレーニングを。', 'color' => '#B9C5CB'],
        ];
        foreach ($profiles as $index => $profile) {
            $trainer = Trainer::firstOrCreate(['name' => $profile['name']], [...$profile, 'is_active' => true]);
            foreach (range(0, 6) as $day) {
                TrainerSchedule::firstOrCreate(['trainer_id' => $trainer->id, 'day_of_week' => $day], [
                    'start_time' => $index === 1 ? '10:00' : '09:00',
                    'end_time' => $index === 2 ? '18:00' : '19:00',
                    'is_working' => $day !== 1 && ! ($index === 3 && $day === 3),
                ]);
            }
        }

        // A few labelled demo bookings make availability visible without sending emails.
        $demo = User::firstOrCreate(['email' => 'demo-guest@example.com'], ['name' => 'デモ利用者', 'password' => 'Password123!', 'role' => UserRole::Client]);
        $today = CarbonImmutable::today(config('booking.timezone'));
        foreach (Trainer::orderBy('id')->get() as $index => $trainer) {
            foreach ([1, 3, 5, 8] as $offset) {
                $day = $today->addDays($offset);
                $schedule = $trainer->schedules->firstWhere('day_of_week', $day->dayOfWeek);
                if (! $schedule?->is_working || ! BusinessHour::where('day_of_week', $day->dayOfWeek)->value('is_open')) {
                    continue;
                }
                $start = $day->setTime(10 + $index * 2, 0)->utc();
                // The reference makes repeated seeding idempotent.
                Reservation::firstOrCreate(['reference' => 'DEMO-'.$trainer->id.'-'.$day->format('Ymd')], [
                    'user_id' => $demo->id, 'trainer_id' => $trainer->id,
                    'starts_at' => $start, 'ends_at' => $start->addMinutes(config('booking.duration')),
                    'status' => ReservationStatus::Confirmed, 'duration' => config('booking.duration'),
                    'price' => config('booking.price'), 'notes' => 'デモ用の予約データです。',
                ]);
            }
        }
    }
}
