<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\ContentCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminContentCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_categories_and_assign_one_to_content(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.content-categories.store'), [
            'name' => 'التسويق',
            'slug' => 'marketing',
            'description' => 'دروس تساعد على فهم التسويق.',
            'icon' => 'megaphone',
            'color' => '#2575ff',
            'is_active' => '1',
            'sort_order' => 10,
        ])->assertRedirect(route('admin.content-categories.index'));

        $category = ContentCategory::query()->sole();

        $this->actingAs($admin)
            ->get(route('admin.content.create'))
            ->assertOk()
            ->assertSee('name="category_id"', false)
            ->assertSee('التسويق');

        $this->actingAs($admin)->post(route('admin.content.store'), [
            'type' => Content::TYPE_LESSON,
            'category_id' => $category->id,
            'title' => 'درس مصنف',
            'slug' => 'categorized-lesson',
            'status' => Content::STATUS_DRAFT,
        ])->assertRedirect();

        $this->assertDatabaseHas('contents', [
            'slug' => 'categorized-lesson',
            'category_id' => $category->id,
        ]);
    }

    public function test_admin_content_table_can_filter_by_category(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $marketing = ContentCategory::query()->create(['name' => 'التسويق', 'slug' => 'marketing']);
        $sales = ContentCategory::query()->create(['name' => 'المبيعات', 'slug' => 'sales']);
        Content::query()->create(['title' => 'مادة التسويق', 'slug' => 'marketing-item', 'category_id' => $marketing->id]);
        Content::query()->create(['title' => 'مادة المبيعات', 'slug' => 'sales-item', 'category_id' => $sales->id]);

        $this->actingAs($admin)
            ->get(route('admin.content.index', ['category_id' => $marketing->id]))
            ->assertOk()
            ->assertSee('مادة التسويق')
            ->assertDontSee('مادة المبيعات');
    }

    public function test_category_validation_and_deletion_protect_assigned_content(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = ContentCategory::query()->create(['name' => 'التسويق', 'slug' => 'marketing']);
        Content::query()->create(['title' => 'مادة مرتبطة', 'slug' => 'linked-item', 'category_id' => $category->id]);

        $this->actingAs($admin)->post(route('admin.content-categories.store'), [
            'name' => 'قسم غير صالح',
            'slug' => 'bad slug',
            'icon' => 'not-supported',
            'color' => 'blue',
        ])->assertSessionHasErrors(['slug', 'icon', 'color']);

        $this->actingAs($admin)
            ->delete(route('admin.content-categories.destroy', $category))
            ->assertRedirect(route('admin.content-categories.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('content_categories', ['id' => $category->id]);
    }

    public function test_non_admin_cannot_open_category_management(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.content-categories.index'))
            ->assertNotFound();
    }
}
