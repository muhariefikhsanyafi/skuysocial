<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Hashtag;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

class ApplicationLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_b5_translation_catalogs_have_matching_keys(): void
    {
        foreach ([
            'activity',
            'home',
            'navigation',
            'profile',
            'saved',
            'search',
            'settings',
            'threads',
        ] as $catalog) {
            $indonesian = Arr::dot(require lang_path("id/{$catalog}.php"));
            $english = Arr::dot(require lang_path("en/{$catalog}.php"));

            $this->assertSame(
                array_keys($indonesian),
                array_keys($english),
                "The id/en keys for {$catalog}.php must stay aligned.",
            );
            $this->assertNotContains('', array_values($indonesian));
            $this->assertNotContains('', array_values($english));
        }
    }

    public function test_public_home_follows_the_guest_session_locale(): void
    {
        $cases = [
            'id' => [
                'expected' => [
                    'Masuk',
                    'Buat akun',
                    'Mulai ngobrol dengan caramu sendiri.',
                    'Buat utas, temukan topik yang ingin kamu ikuti, dan ikut percakapan dengan caramu sendiri.',
                    'Mulai dari satu utas.',
                    'Punya sesuatu untuk dibicarakan?',
                    'SkuySocial',
                ],
                'unexpected' => ['Sign in', 'Create account', 'Start a conversation in your own voice.', 'STAY'],
            ],
            'en' => [
                'expected' => [
                    'Sign in',
                    'Create account',
                    'Start a conversation in your own voice.',
                    'Start a thread, find topics you want to follow, and join conversations in your own way.',
                    'Start with one thread.',
                    'Have something to say?',
                    'SkuySocial',
                ],
                'unexpected' => ['Masuk', 'Buat akun', 'Mulai ngobrol dengan caramu sendiri.', 'TETAP'],
            ],
        ];

        foreach ($cases as $locale => $copy) {
            $response = $this->withSession(['locale' => $locale])->get('/');

            $response->assertOk();
            $this->assertRootLanguage($response, $locale);

            foreach ($copy['expected'] as $expected) {
                $response->assertSeeText($expected);
            }

            foreach ($copy['unexpected'] as $unexpected) {
                $response->assertDontSeeText($unexpected);
            }
        }
    }

    public function test_dashboard_navigation_and_feed_follow_the_authenticated_account_locale(): void
    {
        $cases = [
            'id' => [
                'dashboard' => [
                    'Dasbor',
                    'Halo, Locale id User',
                    'Gunakan pintasan di bawah untuk kembali ke bagian SkuySocial yang kamu butuhkan.',
                    'Buat utas',
                    'Akunmu',
                    'Mau ke mana sekarang?',
                ],
                'feed' => ['Beranda', 'Cari', 'Aktivitas', 'Profil', 'Topik Utas', 'Mulai utas baru...', 'Posting', 'Sedang Hangat'],
            ],
            'en' => [
                'dashboard' => [
                    'Dashboard',
                    'Hello, Locale en User',
                    'Use the shortcuts below to return to the part of SkuySocial you need.',
                    'Create Thread',
                    'Your account',
                    'Where do you want to go?',
                ],
                'feed' => ['Home', 'Search', 'Activity', 'Profile', 'Thread topic', 'Start a new Thread...', 'Post', 'Trending'],
            ],
        ];

        foreach ($cases as $locale => $copy) {
            $user = User::factory()->create([
                'locale' => $locale,
                'name' => "Locale {$locale} User",
            ]);

            $dashboard = $this->localizedResponse($user, $locale, route('dashboard'));
            foreach ($copy['dashboard'] as $expected) {
                $dashboard->assertSeeText($expected);
            }

            $feed = $this->localizedResponse($user, $locale, route('feed'));
            foreach ($copy['feed'] as $expected) {
                $feed->assertSee($expected);
            }
            $oppositePlaceholders = $locale === 'id'
                ? ['Thread topic', 'Start a new Thread...']
                : ['Topik Utas', 'Mulai utas baru...'];

            foreach ($oppositePlaceholders as $placeholder) {
                $feed->assertDontSee('placeholder="'.$placeholder.'"', false);
            }
        }
    }

    public function test_thread_profile_follow_and_edit_surfaces_are_bilingual(): void
    {
        $cases = [
            'id' => [
                'detail' => ['Utas', 'Balasan', 'Kirim'],
                'profile' => ['Edit Profil', 'Pengikut', 'Mengikuti', 'Utas', 'Suka'],
                'followers' => ['Pengikut', 'Belum ada pengikut'],
                'edit' => ['Edit Utas', 'Batal', 'Simpan Perubahan'],
            ],
            'en' => [
                'detail' => ['Thread', 'Replies', 'Send'],
                'profile' => ['Edit Profile', 'Followers', 'Following', 'Threads', 'Likes'],
                'followers' => ['Followers', 'No followers yet'],
                'edit' => ['Edit Thread', 'Cancel', 'Save Changes'],
            ],
        ];

        foreach ($cases as $locale => $copy) {
            $user = User::factory()->create([
                'locale' => $locale,
                'username' => "thread_{$locale}_owner",
            ]);
            $post = Post::create([
                'user_id' => $user->id,
                'content' => "Thread localization fixture {$locale}",
            ]);
            Comment::create([
                'user_id' => $user->id,
                'post_id' => $post->id,
                'content' => "Reply localization fixture {$locale}",
            ]);

            $detail = $this->localizedResponse($user, $locale, route('posts.show', $post));
            foreach ($copy['detail'] as $expected) {
                $detail->assertSeeText($expected);
            }

            $profile = $this->localizedResponse($user, $locale, route('user.profile', $user));
            foreach ($copy['profile'] as $expected) {
                $profile->assertSeeText($expected);
            }

            $followers = $this->localizedResponse($user, $locale, route('user.followers', $user));
            foreach ($copy['followers'] as $expected) {
                $followers->assertSeeText($expected);
            }

            $edit = $this->localizedResponse($user, $locale, route('posts.edit', $post));
            foreach ($copy['edit'] as $expected) {
                $edit->assertSeeText($expected);
            }
        }
    }

    public function test_both_live_settings_surfaces_are_bilingual(): void
    {
        $cases = [
            'id' => [
                'profile' => ['Pengaturan Akun', 'Email Akun', 'Perbarui Kata Sandi', 'Autentikasi dua faktor', 'Kelola autentikasi dua faktor'],
                'user' => ['Pengaturan Profil', 'Nama Tampilan', 'Nama pengguna (tidak dapat diubah)', 'Simpan Perubahan'],
            ],
            'en' => [
                'profile' => ['Account Settings', 'Account Email', 'Update Password', 'Two-factor authentication', 'Manage two-factor authentication'],
                'user' => ['Profile Settings', 'Display Name', 'Username (cannot be changed)', 'Save Changes'],
            ],
        ];

        foreach ($cases as $locale => $copy) {
            $user = User::factory()->create(['locale' => $locale]);

            $profileSettings = $this->localizedResponse($user, $locale, route('profile.edit', $user->username));
            foreach ($copy['profile'] as $expected) {
                $profileSettings->assertSeeText($expected);
            }

            $userSettings = $this->localizedResponse($user, $locale, route('user.edit', $user->username));
            foreach ($copy['user'] as $expected) {
                $userSettings->assertSeeText($expected);
            }
        }
    }

    public function test_saved_and_search_surfaces_are_bilingual(): void
    {
        $cases = [
            'id' => [
                'saved' => ['Tersimpan', 'Utas yang kamu simpan', 'Belum ada utas tersimpan'],
                'search' => ['Cari Sesuatu?', 'Cari nama, nama pengguna, atau topik...', 'Tidak ada akun yang cocok.', 'Tidak ada utas yang cocok dengan kata kunci ini.'],
            ],
            'en' => [
                'saved' => ['Saved', 'Threads you have saved', 'No saved Threads yet'],
                'search' => ['Looking for Something?', 'Search names, usernames, or topics...', 'No matching accounts.', 'No Threads match this search.'],
            ],
        ];

        foreach ($cases as $locale => $copy) {
            $user = User::factory()->create(['locale' => $locale]);

            $saved = $this->localizedResponse($user, $locale, route('bookmarks.index'));
            foreach ($copy['saved'] as $expected) {
                $saved->assertSeeText($expected);
            }

            $query = "NO_MATCH_{$locale}_94827";
            $search = $this->localizedResponse($user, $locale, route('search', ['q' => $query]));
            foreach ($copy['search'] as $expected) {
                $search->assertSee($expected);
            }
            $search->assertSee('value="'.$query.'"', false);
        }
    }

    public function test_hashtag_topic_and_activity_shells_are_bilingual(): void
    {
        $cases = [
            'id' => [
                'hashtag' => ['Menjelajahi Tagar', 'Belum ada utas ditemukan.'],
                'topic' => ['Menelusuri Topik', 'Belum ada utas ditemukan.'],
                'activity' => ['Aktivitas Saya', 'Belum ada aktivitas', 'Notifikasi akan muncul di sini saat seseorang menyukai, membalas, atau mengikuti Anda.'],
            ],
            'en' => [
                'hashtag' => ['Exploring a Hashtag', 'No Threads found.'],
                'topic' => ['Browsing a Topic', 'No Threads found.'],
                'activity' => ['My Activity', 'No activity yet', 'Notifications will appear here when someone likes, replies to, or follows you.'],
            ],
        ];

        foreach ($cases as $locale => $copy) {
            $user = User::factory()->create(['locale' => $locale]);
            $hashtag = Hashtag::create(['name' => "exact-{$locale}-tag"]);
            $topic = "Exact-{$locale}-Topic";

            $hashtagResponse = $this->localizedResponse($user, $locale, route('tags.show', $hashtag->name));
            foreach ($copy['hashtag'] as $expected) {
                $hashtagResponse->assertSeeText($expected);
            }
            $hashtagResponse->assertSeeText('#'.$hashtag->name);

            $topicResponse = $this->localizedResponse($user, $locale, route('topik.show', $topic));
            foreach ($copy['topic'] as $expected) {
                $topicResponse->assertSeeText($expected);
            }
            $topicResponse->assertSeeText('#'.$topic);

            $activity = $this->localizedResponse($user, $locale, route('activity.index'));
            foreach ($copy['activity'] as $expected) {
                $activity->assertSeeText($expected);
            }
        }
    }

    public function test_user_generated_content_is_unchanged_in_both_locales(): void
    {
        $name = 'UNCHANGED_DISPLAY_NAME_4821';
        $username = 'unchanged_user_4821';
        $bio = 'UNCHANGED_BIO_CONTENT_4821';
        $thread = 'UNCHANGED_THREAD_CONTENT_4821';
        $commentText = 'UNCHANGED_COMMENT_CONTENT_4821';
        $topic = 'UNCHANGED_TOPIC_4821';
        $hashtagName = 'unchanged_tag_4821';

        $user = User::factory()->create([
            'locale' => 'id',
            'name' => $name,
            'username' => $username,
            'bio' => $bio,
        ]);
        $post = Post::create([
            'user_id' => $user->id,
            'content' => $thread,
            'topic' => $topic,
        ]);
        Comment::create([
            'user_id' => $user->id,
            'post_id' => $post->id,
            'content' => $commentText,
        ]);
        $hashtag = Hashtag::create(['name' => $hashtagName]);
        $post->hashtags()->attach($hashtag->id);

        foreach (['id', 'en'] as $locale) {
            $user->forceFill(['locale' => $locale])->save();
            $localizedUser = $user->fresh();

            $profile = $this->localizedResponse($localizedUser, $locale, route('user.profile', $localizedUser));
            foreach ([$name, $username, $bio, $thread] as $content) {
                $profile->assertSeeText($content);
            }

            $detail = $this->localizedResponse($localizedUser, $locale, route('posts.show', $post));
            foreach ([$username, $thread, $commentText] as $content) {
                $detail->assertSeeText($content);
            }

            $topicResponse = $this->localizedResponse($localizedUser, $locale, route('topik.show', $topic));
            $topicResponse
                ->assertSeeText('#'.$topic)
                ->assertSeeText($thread);

            $hashtagResponse = $this->localizedResponse($localizedUser, $locale, route('tags.show', $hashtagName));
            $hashtagResponse
                ->assertSeeText('#'.$hashtagName)
                ->assertSeeText($thread);

            $search = $this->localizedResponse($localizedUser, $locale, route('search', ['q' => $thread]));
            $search
                ->assertSee('value="'.$thread.'"', false)
                ->assertSeeText($thread);
        }
    }

    private function localizedResponse(User $user, string $locale, string $url)
    {
        $conflictingLocale = $locale === 'id' ? 'en' : 'id';

        $response = $this
            ->actingAs($user)
            ->withSession(['locale' => $conflictingLocale])
            ->get($url);

        $response->assertOk();
        $this->assertRootLanguage($response, $locale);

        return $response;
    }

    private function assertRootLanguage($response, string $locale): void
    {
        $this->assertMatchesRegularExpression(
            '/\A<!DOCTYPE html>\s*<html\s+lang="'.preg_quote($locale, '/').'"(?:\s|>)/',
            $response->getContent(),
        );
    }
}
