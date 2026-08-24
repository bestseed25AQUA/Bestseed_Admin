<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\FarmImage;
use App\Models\Farmer;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * The app stores farm photos as a JSON array of absolute URLs in
 * farm_images.images. An admin upload has to land in exactly that shape, or
 * the photo saves without ever appearing in the app.
 */
class AdminFarmImageUploadTest extends TestCase
{
    public function test_uploading_photos_stores_urls_the_app_can_read(): void
    {
        $admin  = User::first();
        $farmer = Farmer::first();

        $farm = Farm::create([
            'farm_name' => 'Image Test Farm',
            'farmer_id' => $farmer->id,
            'status'    => 1,
        ]);

        $written = [];

        try {
            $this->actingAs($admin)
                ->put("/admin/farm-management/farms/{$farm->id}", [
                    'farm_name' => 'Image Test Farm',
                    'farmer_id' => $farmer->id,
                    'status'    => 1,
                    'images'    => [
                        UploadedFile::fake()->image('one.jpg', 40, 30),
                        UploadedFile::fake()->image('two.jpg', 40, 30),
                    ],
                ])->assertRedirect();

            $row = FarmImage::where('farm_id', $farm->id)->firstOrFail();
            $urls = json_decode($row->images, true);

            $this->assertIsArray($urls);
            $this->assertCount(2, $urls);

            foreach ($urls as $url) {
                $this->assertStringStartsWith(
                    rtrim(config('app.url'), '/') . '/uploads/images/farms/',
                    $url,
                    'The app resolves media by this path; a different one shows a broken image.'
                );

                $path = public_path('uploads/images/farms/' . basename($url));
                $this->assertFileExists($path);
                $written[] = $path;
            }
        } finally {
            foreach ($written as $path) {
                @unlink($path);
            }
            FarmImage::where('farm_id', $farm->id)->delete();
            $farm->forceDelete();
        }
    }
}
