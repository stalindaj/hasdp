<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The profile picture in the dashboard's welcome circle. */
class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_you_can_upload_replace_and_remove_your_picture(): void
    {
        $me = User::factory()->create();

        $this->actingAs($me)->post(route('photo.store'), [
            'photo' => UploadedFile::fake()->image('me.jpg', 400, 400),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $first = $me->fresh()->photo_path;
        $this->assertNotNull($first);
        Storage::assertExists($first);

        // Replacing deletes the old file rather than piling them up.
        $this->actingAs($me)->post(route('photo.store'), [
            'photo' => UploadedFile::fake()->image('me2.png', 300, 300),
        ])->assertSessionHasNoErrors();
        $second = $me->fresh()->photo_path;
        $this->assertNotSame($first, $second);
        Storage::assertMissing($first);

        $this->actingAs($me)->delete(route('photo.destroy'))->assertRedirect();
        $this->assertNull($me->fresh()->photo_path);
        Storage::assertMissing($second);
    }

    public function test_only_pictures_are_accepted(): void
    {
        $me = User::factory()->create();

        $this->actingAs($me)->post(route('photo.store'), [
            'photo' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('photo');

        $this->assertNull($me->fresh()->photo_path);
    }

    public function test_the_picture_is_served_to_signed_in_users_only(): void
    {
        $me = User::factory()->create();
        $this->actingAs($me)->post(route('photo.store'), [
            'photo' => UploadedFile::fake()->image('me.jpg'),
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('photo.show', $me))->assertOk();

        auth()->logout();
        $this->get(route('photo.show', $me))->assertRedirect(route('login'));
    }

    public function test_the_dashboard_gets_the_picture_url(): void
    {
        $me = User::factory()->create();

        $this->actingAs($me)->get(route('profile.edit'))
            ->assertInertia(fn ($page) => $page->where('auth.photoUrl', null));

        $this->actingAs($me)->post(route('photo.store'), [
            'photo' => UploadedFile::fake()->image('me.jpg'),
        ]);

        $this->actingAs($me->fresh())->get(route('profile.edit'))
            ->assertInertia(fn ($page) => $page->where(
                'auth.photoUrl',
                fn ($url) => str_starts_with($url, '/photo/'.$me->id.'?v=')
            ));
    }
}
