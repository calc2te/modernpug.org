<?php

namespace Tests\Feature\Web;

use App\Blog;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BlogTest extends TestCase
{
    use DatabaseTransactions;

    const NOT_AVAILABLE_FEED = 'http://test.com';
    const AVAILABLE_FEED = 'https://blog.jetbrains.com/feed/';

    protected function setUp(): void
    {
        parent::setUp();

        \Toastr::clear();
    }

    public function testIndex()
    {
        $this->get(route('blogs.index'))->assertOk();
    }

    public function testRedirectIfExecutedByUnauthorizedUserOrNotVerifiedUser()
    {
        $this->get(route('blogs.create'))
            ->assertRedirect('/login');
        $this->post(route('blogs.store'))
            ->assertRedirect('/login');

        /**
         * @var User
         */
        $user = factory(User::class)->create(['email_verified_at' => null]);

        $this->actingAs($user)->get(route('blogs.create'))->assertRedirect('/email/verify');
        $this->actingAs($user)->post(route('blogs.store'))->assertRedirect('/email/verify');
    }

    public function testCreateBlogWithEmptyRequestByAuthorizedUser()
    {

        /**
         * @var User
         */
        $user = factory(User::class)->create();

        $this->actingAs($user)->get(route('blogs.create'))->assertOk();

        $this->actingAs($user)->post(route('blogs.store'), [])
            ->assertSessionHasErrors('feed_url')
            ->assertRedirect(route('blogs.create'));
    }

    public function testCreateCantAccessibleBlogByAuthorizedUser()
    {

        /**
         * @var User
         */
        $user = factory(User::class)->create();

        $this->actingAs($user)->get(route('blogs.create'))->assertOk();

        /**
         * @var Blog $blog
         */
        $blog = factory(Blog::class)->make(['owner_id' => null]);

        $this->actingAs($user)->post(route('blogs.store'), $blog->toArray())
            ->assertToastrHasError()
            ->assertRedirect(route('blogs.create'));

        $this->get(route('blogs.index'))->assertOk()->assertDontSee($blog->title);
    }

    public function testCreateCanAccessibleBlogByAuthorizedUser()
    {

        /**
         * @var User
         */
        $user = factory(User::class)->create();

        $this->actingAs($user)->get(route('blogs.create'))->assertOk();

        /**
         * @var Blog $blog
         */
        $blog = factory(Blog::class)->make(['feed_url' => self::AVAILABLE_FEED]);

        $this->actingAs($user)->post(route('blogs.store'), $blog->toArray())
            ->assertToastrHasSuccess()
            ->assertRedirect(route('blogs.create'));
    }

    public function testSeeBlogRedirectToOriginUrl()
    {

        /**
         * @var Blog $blog
         */
        $blog = factory(Blog::class)->create(['owner_id' => null]);

        $this->get(route('blogs.show', [$blog->id]))
            ->assertRedirect($blog->site_url);
    }

    public function testCantUpdateBlogByNonOwner()
    {

        /**
         * @var User
         */
        $nonOwner = factory(User::class)->create();

        /**
         * @var Blog $blog
         */
        $blog = factory(Blog::class)->create();

        $this->get(route('blogs.index'))->assertOk()->assertSee($blog->title);

        $this->actingAs($nonOwner)->get(route('blogs.edit', [$blog->id]))
            ->assertToastrHasError()
            ->assertRedirect(route('blogs.index'));

        $this->get(route('blogs.index'))->assertOk()->assertSee($blog->title);

        $this->actingAs($nonOwner)->put(route('blogs.update',
            [$blog->id, 'feed_url' => self::NOT_AVAILABLE_FEED]))
            ->assertToastrHasError()
            ->assertRedirect(route('blogs.index'));

        $this->get(route('blogs.index'))->assertOk()->assertSee($blog->title);

        $this->actingAs($nonOwner)->put(route('blogs.update', [$blog->id, 'feed_url' => self::AVAILABLE_FEED]))
            ->assertToastrHasError()
            ->assertRedirect(route('blogs.index'));

        $this->get(route('blogs.index'))->assertOk()->assertSee($blog->title);
    }

    public function testUpdateBlogByOwner()
    {

        /**
         * @var User
         */
        $owner = factory(User::class)->create();

        /**
         * @var Blog $blog
         */
        $blog = factory(Blog::class)->create(['owner_id' => $owner]);

        $this->get(route('blogs.index'))->assertOk()->assertSee($blog->title);

        $this->actingAs($owner)->get(route('blogs.edit', [$blog->id]))
            ->assertOk();

        $this->actingAs($owner)->put(route('blogs.update', [$blog->id, 'feed_url' => self::NOT_AVAILABLE_FEED]))
            ->assertToastrHasError()
            ->assertRedirect(route('blogs.edit', [$blog->id]));

        \Toastr::clear();

        $this->actingAs($owner)->put(route('blogs.update', [$blog->id, 'feed_url' => self::AVAILABLE_FEED]))
            ->assertToastrHasSuccess()
            ->assertRedirect(route('blogs.edit', [$blog->id]));
    }

    public function testCantDeleteBlogByNonOwner()
    {

        /**
         * @var User
         */
        $nonOwner = factory(User::class)->create();

        /**
         * @var Blog $blog
         */
        $blog = factory(Blog::class)->create();

        $this->get(route('blogs.index'))->assertOk()->assertSee($blog->title);

        $this->actingAs($nonOwner)->delete(route('blogs.destroy', [$blog->id]))
            ->assertToastrHasError()
            ->assertRedirect(route('blogs.index'));

        $this->get(route('blogs.index'))->assertOk()->assertSee($blog->title);
    }

    public function testCanDeleteBlogByOwner()
    {

        /**
         * @var User
         */
        $owner = factory(User::class)->create();

        /**
         * @var Blog $blog
         */
        $blog = factory(Blog::class)->create(['owner_id' => $owner]);

        $this->get(route('blogs.index'))->assertOk()->assertSee($blog->title);

        $this->actingAs($owner)->delete(route('blogs.destroy', [$blog->id]))
            ->assertToastrHasSuccess()
            ->assertRedirect(route('blogs.index'));

        $this->get(route('blogs.index'))->assertOk()->assertDontSee($blog->title);
    }

    public function testCanDeleteBlogByNonOwnerWithPermission()
    {

        /**
         * @var User
         */
        $nonOwnerWithPermission = factory(User::class)->create();
        $nonOwnerWithPermission->givePermissionTo('blog-delete');

        /**
         * @var Blog $blog
         */
        $blog = factory(Blog::class)->create();

        $this->get(route('blogs.index'))->assertOk()->assertSee($blog->title);

        $this->actingAs($nonOwnerWithPermission)->delete(route('blogs.destroy', [$blog->id]))
            ->assertToastrHasSuccess()
            ->assertRedirect(route('blogs.index'));

        $this->get(route('blogs.index'))->assertOk()->assertDontSee($blog->title);
    }

    public function testCantRestoreBlogByNonOwner()
    {

        /**
         * @var User
         */
        $nonOwner = factory(User::class)->create();

        /**
         * @var Blog $blog
         */
        $blog = factory(Blog::class)->create();
        $blog->delete();

        $this->get(route('blogs.index'))->assertOk()->assertDontSee($blog->title);

        $this->actingAs($nonOwner)->patch(route('blogs.restore', [$blog->id]))
            ->assertToastrHasError()
            ->assertRedirect(route('blogs.index'));

        $this->get(route('blogs.index'))->assertOk()->assertDontSee($blog->title);
    }

    public function testCanRestoreBlogByOwner()
    {

        /**
         * @var User
         */
        $owner = factory(User::class)->create();

        /**
         * @var Blog $blog
         */
        $blog = factory(Blog::class)->create(['owner_id' => $owner]);
        $blog->delete();

        $this->get(route('blogs.index'))->assertOk()->assertDontSee($blog->title);

        $this->actingAs($owner)->patch(route('blogs.restore', [$blog->id]))
            ->assertToastrHasSuccess()
            ->assertRedirect(route('blogs.index'));

        $this->get(route('blogs.index'))->assertOk()->assertSee($blog->title);
    }

    public function testCanRestoreBlogByNonOwnerWithPermission()
    {

        /**
         * @var User
         */
        $nonOwnerWithPermission = factory(User::class)->create();
        $nonOwnerWithPermission->givePermissionTo('blog-restore');

        /**
         * @var Blog $blog
         */
        $blog = factory(Blog::class)->create();
        $blog->delete();

        $this->get(route('blogs.index'))->assertOk()->assertDontSee($blog->title);

        $this->actingAs($nonOwnerWithPermission)->patch(route('blogs.restore', [$blog->id]))
            ->assertToastrHasSuccess()
            ->assertRedirect(route('blogs.index'));

        $this->get(route('blogs.index'))->assertOk()->assertSee($blog->title);
    }
}
