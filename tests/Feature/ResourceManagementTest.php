<?php

use App\Models\Resource;
use App\Models\School;
use App\Models\User;
use Database\Seeders\ResourceSeeder;

function adminUser(): User
{
    return User::factory()->admin()->create(['email_verified_at' => now()]);
}

function studentUser(): User
{
    return User::factory()->create(['email_verified_at' => now(), 'user_type' => 'user']);
}

it('forbids non-admins from the resource admin area', function () {
    $student = studentUser();

    $this->actingAs($student)->get(route('admin.resources.index'))->assertForbidden();
    $this->actingAs($student)->post(route('admin.resources.store'), [])->assertForbidden();
});

it('lets an admin view the resource center', function () {
    $admin = adminUser();

    Resource::factory()->create(['title' => 'UPCAT (University of the Philippines)']);

    $this->actingAs($admin)->get(route('admin.resources.index'))
        ->assertOk()
        ->assertSee('Resource Center')
        ->assertSee('UPCAT');
});

it('lets an admin create a scholarship with dates and schools', function () {
    $admin = adminUser();
    $school = School::factory()->create();

    $response = $this->actingAs($admin)->post(route('admin.resources.store'), [
        'type' => Resource::TYPE_SCHOLARSHIP,
        'title' => 'DOST-SEI Undergraduate S&T Scholarship',
        'grantor' => 'DOST',
        'coverage' => 'Full tuition + stipend',
        'criteria' => 'Top 5% of class',
        'opens_at' => '2026-01-01',
        'closes_at' => '2026-03-31',
        'is_published' => '1',
        'sort_order' => 1,
        'school_ids' => [$school->id],
    ]);

    $response->assertRedirect(route('admin.resources.index', ['type' => Resource::TYPE_SCHOLARSHIP]));

    $resource = Resource::query()->where('title', 'DOST-SEI Undergraduate S&T Scholarship')->first();

    expect($resource)->not->toBeNull()
        ->and($resource->type)->toBe(Resource::TYPE_SCHOLARSHIP)
        ->and($resource->is_published)->toBeTrue()
        ->and($resource->opens_at->format('Y-m-d'))->toBe('2026-01-01')
        ->and($resource->closes_at->format('Y-m-d'))->toBe('2026-03-31')
        ->and($resource->schools->pluck('id')->all())->toBe([$school->id]);
});

it('publishes a newly created university by default', function () {
    $admin = adminUser();

    $this->actingAs($admin)->post(route('admin.resources.store'), [
        'type' => Resource::TYPE_UNIVERSITY,
        'title' => 'Example University',
        'short_name' => 'EU',
    ])->assertRedirect(route('admin.resources.index', ['type' => Resource::TYPE_UNIVERSITY]));

    $resource = Resource::query()->where('title', 'Example University')->firstOrFail();

    expect($resource->is_published)->toBeTrue();

    $this->get(route('resources.index'))
        ->assertOk()
        ->assertSee('Example University');
});

it('accepts standard checkbox boolean values for publication status', function () {
    $admin = adminUser();

    $this->actingAs($admin)->post(route('admin.resources.store'), [
        'type' => Resource::TYPE_UNIVERSITY,
        'title' => 'Checkbox Value University',
        'is_published' => 'true',
    ])->assertRedirect(route('admin.resources.index', ['type' => Resource::TYPE_UNIVERSITY]));

    expect(Resource::query()->where('title', 'Checkbox Value University')->firstOrFail()->is_published)
        ->toBeTrue();
});

it('submits one explicit publication value from the admin resource form', function () {
    $admin = adminUser();

    $page = $this->actingAs($admin)
        ->get(route('admin.resources.index', ['type' => Resource::TYPE_UNIVERSITY]))
        ->assertOk()
        ->getContent();

    expect($page)
        ->toContain('name="is_published" x-bind:value="form.is_published ? \'1\' : \'0\'"')
        ->not->toContain('id="is_published" name="is_published"');
});

it('shows validation errors when a university cannot be created', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->from(route('admin.resources.index', ['type' => Resource::TYPE_UNIVERSITY]))
        ->post(route('admin.resources.store'), [
            'type' => Resource::TYPE_UNIVERSITY,
            'title' => 'Example University',
            'website' => 'not a valid URL',
            'is_published' => '1',
        ])
        ->assertRedirect(route('admin.resources.index', ['type' => Resource::TYPE_UNIVERSITY]))
        ->assertSessionHasErrors('website');

    $this->get(route('admin.resources.index', ['type' => Resource::TYPE_UNIVERSITY]))
        ->assertOk()
        ->assertSee('Resource was not saved')
        ->assertSee('The website field must be a valid URL.');
});

it('preserves seeded resource publication changes and allows republication', function () {
    $admin = adminUser();
    $this->seed(ResourceSeeder::class);

    $resource = Resource::query()
        ->where('type', Resource::TYPE_UNIVERSITY)
        ->where('title', 'University of the Philippines')
        ->firstOrFail();

    $resource->update(['is_published' => false]);
    $this->seed(ResourceSeeder::class);

    expect($resource->refresh()->is_published)->toBeFalse();

    $this->actingAs($admin)->put(route('admin.resources.update', $resource), [
        'type' => Resource::TYPE_UNIVERSITY,
        'title' => $resource->title,
        'is_published' => '1',
    ])->assertRedirect(route('admin.resources.index', ['type' => Resource::TYPE_UNIVERSITY]));

    expect($resource->refresh()->is_published)->toBeTrue();
});

it('validates required fields when creating a resource', function () {
    $admin = adminUser();

    $this->actingAs($admin)->post(route('admin.resources.store'), [
        'type' => Resource::TYPE_CET,
        'title' => '',
    ])->assertSessionHasErrors('title');
});

it('lets an admin update a resource and its schools', function () {
    $admin = adminUser();
    $resource = Resource::factory()->scholarship()->create(['title' => 'Old Title']);
    $school = School::factory()->create();

    $this->actingAs($admin)->put(route('admin.resources.update', $resource), [
        'type' => Resource::TYPE_SCHOLARSHIP,
        'title' => 'New Title',
        'grantor' => 'CHED',
        'is_published' => '0',
        'school_ids' => [$school->id],
    ])->assertRedirect(route('admin.resources.index', ['type' => Resource::TYPE_SCHOLARSHIP]));

    $resource->refresh();

    expect($resource->title)->toBe('New Title')
        ->and($resource->is_published)->toBeFalse()
        ->and($resource->schools->pluck('id')->all())->toBe([$school->id]);
});

it('lets an admin delete a resource', function () {
    $admin = adminUser();
    $resource = Resource::factory()->create();

    $this->actingAs($admin)->delete(route('admin.resources.destroy', $resource))
        ->assertRedirect();

    expect(Resource::query()->find($resource->id))->toBeNull();
});

it('shows only published resources on the public resource center', function () {
    Resource::factory()->create(['title' => 'Published CET', 'is_published' => true]);
    Resource::factory()->create(['title' => 'Draft CET', 'is_published' => false]);

    $this->get(route('resources.index'))
        ->assertOk()
        ->assertSee('Published CET')
        ->assertDontSee('Draft CET');
});

it('renders resource pages with a valid alpine payload (no uncompiled blade braces)', function () {
    $admin = adminUser();
    Resource::factory()->scholarship()->create(['title' => 'Payload Scholarship']);

    $adminPage = $this->actingAs($admin)->get(route('admin.resources.index'))->assertOk()->getContent();
    expect($adminPage)->toContain('resourceManager(')
        ->and($adminPage)->not->toContain('{{');

    $publicPage = $this->get(route('resources.index'))->assertOk()->getContent();
    expect($publicPage)->toContain('resource-detail')
        ->and($publicPage)->not->toContain('{{');
});
