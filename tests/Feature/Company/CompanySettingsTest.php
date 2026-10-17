<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');

    $this->company = Company::factory()->create();
    $this->owner = User::factory()->owner()->for($this->company)->create();
});

test('only owners change company settings', function () {
    $this->actingAs(User::factory()->accountant()->for($this->company)->create())
        ->get(route('company.settings'))->assertForbidden();
});

test('an owner updates the company and uploads a logo', function () {
    $this->actingAs($this->owner);

    Livewire::test('pages::company.settings')
        ->set('name', 'Acme Renamed')
        ->set('payment_terms_days', '30')
        ->set('logo', UploadedFile::fake()->image('logo.png', 200, 60))
        ->call('save')
        ->assertHasNoErrors();

    $company = $this->company->fresh();

    expect($company->name)->toBe('Acme Renamed')
        ->and($company->payment_terms_days)->toBe(30)
        ->and($company->logo_path)->toStartWith('logos/'.$company->id.'/')
        ->and($company->logo_path)->not->toContain('logo.png');
    Storage::disk('public')->assertExists($company->logo_path);
});

test('the logo must be a PNG or JPEG image', function (UploadedFile $file) {
    $this->actingAs($this->owner);

    Livewire::test('pages::company.settings')->set('logo', $file)->call('save')->assertHasErrors('logo');
})->with([
    'svg' => fn () => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'),
    'php' => fn () => UploadedFile::fake()->createWithContent('logo.php', '<?php echo 1;'),
    'too big' => fn () => UploadedFile::fake()->image('logo.png')->size(2048),
]);
