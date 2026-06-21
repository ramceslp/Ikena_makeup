<?php

namespace Tests\Feature\Posts;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PostMigrationTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // posts table
    // =========================================================================

    public function test_posts_table_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('posts'), 'posts table must exist');

        $columns = [
            'id', 'author_id', 'title', 'slug', 'excerpt',
            'cover_image_path', 'body', 'type', 'is_featured',
            'cta_label', 'cta_url', 'is_published', 'published_at',
            'created_at', 'updated_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('posts', $column),
                "posts table must have column [{$column}]"
            );
        }
    }

    public function test_posts_table_slug_is_unique(): void
    {
        $this->assertTrue(Schema::hasTable('posts'));

        // Insert a row and try to insert a duplicate slug — should fail
        \DB::table('posts')->insert([
            'title'        => 'Post One',
            'slug'         => 'unique-slug',
            'body'         => '<p>content</p>',
            'type'         => 'noticia',
            'is_featured'  => false,
            'is_published' => false,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        \DB::table('posts')->insert([
            'title'        => 'Post Two',
            'slug'         => 'unique-slug',
            'body'         => '<p>content 2</p>',
            'type'         => 'noticia',
            'is_featured'  => false,
            'is_published' => false,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    // =========================================================================
    // post_images table
    // =========================================================================

    public function test_post_images_table_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('post_images'), 'post_images table must exist');

        $columns = ['id', 'post_id', 'path', 'sort_order', 'created_at', 'updated_at'];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('post_images', $column),
                "post_images table must have column [{$column}]"
            );
        }
    }

    public function test_post_images_cascade_on_post_delete(): void
    {
        // Create a post and an associated image row directly via DB
        $postId = \DB::table('posts')->insertGetId([
            'title'        => 'Test Post',
            'slug'         => 'test-post-cascade',
            'body'         => '<p>body</p>',
            'type'         => 'noticia',
            'is_featured'  => false,
            'is_published' => false,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        \DB::table('post_images')->insert([
            'post_id'    => $postId,
            'path'       => 'posts/images/test.jpg',
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseCount('post_images', 1);

        \DB::table('posts')->where('id', $postId)->delete();

        $this->assertDatabaseCount('post_images', 0);
    }
}
