<?php

namespace Database\Seeders;

use App\Models\MemoTest;
use App\Models\MemoTestImage;
use Illuminate\Database\Seeder;

class MemoTestImageSeeder extends Seeder
{
    /**
     * Images of each sample memo test, by memo test name. Each image makes one
     * pair of cards in the game.
     *
     * The two Wikimedia Commons images replace links that stopped working
     * (they returned 404).
     */
    private const IMAGES = [
        'Test 1' => [
            'https://www.cinconoticias.com/wp-content/uploads/tradiciones-argentinas.jpg',
            'https://www.infobae.com/new-resizer/fpRP2jo0qfWqZQMcIUCKSlsZ5mU=/1200x1600/filters:format(webp):quality(85)/cloudfront-us-east-1.images.arcpublishing.com/infobae/MXD2UAV6PBEHHF6XPQNUL3QQVY.jpg',
            'https://play-lh.googleusercontent.com/rX_nOuUDijsV_NnWZP9JgYTsFpxn5y7qCqDxFIpZ-BqiJu8un7UbdSgVTZSrJuzAlQ',
            'https://hablemosdeculturas.com/wp-content/uploads/2017/11/tradiciones-argentinas-1.jpg',
        ],
        'Test 2' => [
            'https://agroverdad.com.ar/wp-content/uploads/2021/05/locro-25demayo-campo-ingredientes-650x404.jpg',
            'https://www.welcomeargentina.com/blog/wp-content/uploads/2015/05/churros-2.jpg',
            'https://thumb.wikimedia.org/wikipedia/commons/thumb/1/1a/Flag_of_Argentina.svg/500px-Flag_of_Argentina.svg.png',
            'https://thumb.wikimedia.org/wikipedia/commons/thumb/4/49/Alfajor-P1060387.JPG/500px-Alfajor-P1060387.JPG',
        ],
    ];

    /**
     * Adds the sample images. It can run many times without duplicates; it
     * also removes the two old broken links from databases seeded before.
     *
     * @return void
     */
    public function run()
    {
        MemoTestImage::whereIn('image_url', [
            'https://www.biodiversidadvirtual.org/etno/data/media/627/Bandera-argentina-Rosario-Argentina-39555.jpg',
            'https://viajesdeunchapin.com/wp-content/uploads/2022/01/Alfajores.jpg',
        ])->delete();

        foreach (self::IMAGES as $memoTestName => $urls) {
            $memoTest = MemoTest::where('name', $memoTestName)->first();
            if (! $memoTest) {
                continue;
            }
            foreach ($urls as $url) {
                MemoTestImage::firstOrCreate([
                    'memo_test_id' => $memoTest->id,
                    'image_url' => $url,
                ]);
            }
        }
    }
}
