<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the sample memo tests and their images.
     *
     * Both seeders can run many times without creating duplicates, so the
     * Docker entrypoint runs `php artisan db:seed` on every start.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            MemoTestSeeder::class,
            MemoTestImageSeeder::class,
        ]);
    }
}
