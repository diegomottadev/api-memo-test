<?php

namespace Database\Seeders;

use App\Models\MemoTest;
use Illuminate\Database\Seeder;

class MemoTestSeeder extends Seeder
{
    /**
     * Creates the sample memo tests if they do not exist yet.
     *
     * @return void
     */
    public function run()
    {
        MemoTest::firstOrCreate(['name' => 'Test 1']);
        MemoTest::firstOrCreate(['name' => 'Test 2']);
    }
}
