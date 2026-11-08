<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssetManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_assets_index_renders(): void
    {
        Asset::factory()->create(['name' => 'لوگوی شرکت']);
        $viewer = User::factory()->create();

        $this->actingAs($viewer)
            ->get('/admin/assets')
            ->assertOk()
            ->assertSee('لوگوی شرکت');
    }

    public function test_image_can_be_uploaded(): void
    {
        $manager = User::factory()->manager()->create();
        $file = UploadedFile::fake()->image('logo.png', 24, 24);

        $this->actingAs($manager)
            ->post(route('admin.assets.store'), [
                'file' => $file,
                'type' => 'image',
                'name' => 'لوگوی اصلی',
                'description' => 'لوگوی سایت',
            ])
            ->assertRedirect();

        $asset = Asset::query()->latest('id')->first();

        $this->assertNotNull($asset);
        $this->assertSame(AssetType::Image, $asset->type);
        $this->assertSame('لوگوی اصلی', $asset->name);
        $this->assertMatchesRegularExpression('/^[a-z0-9][a-z0-9\-]*$/', $asset->handle);
        $this->assertSame('png', $asset->extension);
        Storage::disk('local')->assertExists($asset->path);
        $this->assertSame('asset://'.$asset->handle, $asset->referenceToken());
    }

    public function test_css_file_can_be_uploaded(): void
    {
        $manager = User::factory()->manager()->create();
        $file = UploadedFile::fake()->createWithContent('print.css', 'body { color: #111; }');

        $this->actingAs($manager)
            ->post(route('admin.assets.store'), ['file' => $file, 'type' => 'css'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $asset = Asset::query()->latest('id')->first();
        $this->assertNotNull($asset);
        $this->assertSame(AssetType::Css, $asset->type);
    }

    public function test_php_upload_is_rejected(): void
    {
        $manager = User::factory()->manager()->create();
        $file = UploadedFile::fake()->createWithContent('shell.php', '<?php echo "evil";');

        $this->actingAs($manager)
            ->post(route('admin.assets.store'), ['file' => $file, 'type' => 'image'])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('assets', 0);
    }

    public function test_svg_upload_is_rejected(): void
    {
        $manager = User::factory()->manager()->create();
        $file = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

        $this->actingAs($manager)
            ->post(route('admin.assets.store'), ['file' => $file, 'type' => 'image'])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('assets', 0);
    }

    public function test_mime_type_must_match_selected_asset_type(): void
    {
        $manager = User::factory()->manager()->create();
        $file = UploadedFile::fake()->image('picture.png', 10, 10);

        $this->actingAs($manager)
            ->post(route('admin.assets.store'), ['file' => $file, 'type' => 'font'])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('assets', 0);
    }

    public function test_viewer_cannot_upload_assets(): void
    {
        $viewer = User::factory()->create();
        $file = UploadedFile::fake()->image('logo.png', 10, 10);

        $this->actingAs($viewer)
            ->post(route('admin.assets.store'), ['file' => $file, 'type' => 'image'])
            ->assertForbidden();
    }

    public function test_asset_file_and_download_routes_are_authenticated(): void
    {
        $path = 'assets/images/test-image.png';
        Storage::disk('local')->put($path, (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true,
        ));

        $asset = Asset::factory()->create([
            'type' => AssetType::Image,
            'mime_type' => 'image/png',
            'extension' => 'png',
            'path' => $path,
            'size' => 68,
        ]);

        $viewer = User::factory()->create();

        $this->actingAs($viewer)
            ->get(route('admin.assets.file', $asset))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $this->actingAs($viewer)
            ->get(route('admin.assets.download', $asset))
            ->assertOk();
    }

    public function test_guest_cannot_fetch_asset_files(): void
    {
        $asset = Asset::factory()->create();

        $this->get(route('admin.assets.file', $asset))->assertRedirect('/admin/login');
        $this->get(route('admin.assets.download', $asset))->assertRedirect('/admin/login');
    }

    public function test_manager_can_delete_asset_and_file(): void
    {
        $path = 'assets/images/delete-me.png';
        Storage::disk('local')->put($path, 'binary');

        $asset = Asset::factory()->create([
            'type' => AssetType::Image,
            'path' => $path,
            'extension' => 'png',
        ]);

        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->delete(route('admin.assets.destroy', $asset))
            ->assertRedirect();

        $this->assertSoftDeleted('assets', ['id' => $asset->id]);
        Storage::disk('local')->assertMissing($path);
    }
}
