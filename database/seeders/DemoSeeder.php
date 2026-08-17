<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Content\CreateContentBlock;
use App\Actions\Pages\PublishPage;
use App\Enums\ContentBlockStatus;
use App\Enums\ContentBlockType;
use App\Enums\ListingStatus;
use App\Enums\PageStatus;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PlanSeeder::class,
            SystemPageSeeder::class,
        ]);

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@demo.listinghub.bg'],
            ['name' => 'Демо Админ', 'password' => bcrypt('password')],
        );
        if (! $admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }

        $member = User::query()->firstOrCreate(
            ['email' => 'ivan@demo.listinghub.bg'],
            ['name' => 'Иван Петров', 'password' => bcrypt('password')],
        );
        if (! $member->hasRole('member')) {
            $member->assignRole('member');
        }

        $this->seedCategories();
        $this->seedHomepageBlocks($admin);
        $this->seedAboutPage($admin);
        $this->seedMenus();
        $this->seedListings($admin, $member);
    }

    private function seedCategories(): void
    {
        $categories = [
            ['name' => 'Ресторанти и заведения', 'slug' => 'restaurants',  'icon' => '🍽️', 'sort_order' => 100],
            ['name' => 'Хотели и настаняване',   'slug' => 'hotels',       'icon' => '🏨', 'sort_order' => 200],
            ['name' => 'Автосервизи',             'slug' => 'auto-service', 'icon' => '🔧', 'sort_order' => 300],
            ['name' => 'Здраве и красота',        'slug' => 'health',       'icon' => '💆', 'sort_order' => 400],
            ['name' => 'Образование',             'slug' => 'education',    'icon' => '📚', 'sort_order' => 500],
            ['name' => 'Строителство и ремонти',  'slug' => 'construction', 'icon' => '🏗️', 'sort_order' => 600],
            ['name' => 'Правни услуги',           'slug' => 'legal',        'icon' => '⚖️', 'sort_order' => 700],
            ['name' => 'IT и технологии',         'slug' => 'it',           'icon' => '💻', 'sort_order' => 800],
        ];

        foreach ($categories as $cat) {
            Category::query()->firstOrCreate(
                ['slug' => $cat['slug']],
                [...$cat, 'is_active' => true],
            );
        }
    }

    private function seedHomepageBlocks(User $admin): void
    {
        $home = Page::query()->where('slug', 'home')->first();
        if (! $home || $home->contentBlocks()->exists()) {
            return;
        }

        $creator = app(CreateContentBlock::class);

        $hero = $creator->handle(
            ContentBlockType::Hero,
            [
                'title' => 'Намерете най-добрите бизнеси в България',
                'subtitle' => 'Каталог с проверени фирми от всички области — ресторанти, хотели, сервизи и още.',
                'cta_text' => 'Разгледай обявите',
                'cta_url' => '/listings',
            ],
            $home,
            actor: $admin,
            sortOrder: 100,
        );
        $hero->update(['status' => ContentBlockStatus::Published, 'published_at' => now()]);

        $faq = $creator->handle(
            ContentBlockType::Faq,
            [
                'title' => 'Често задавани въпроси',
                'items' => [
                    ['question' => 'Безплатно ли е?', 'answer' => 'Да, основният план е напълно безплатен. Може да публикувате обява за минути.'],
                    ['question' => 'Как да добавя обява?', 'answer' => 'Регистрирайте се, попълнете формата и натиснете „Публикувай". Обявата ще бъде прегледана от екипа.'],
                    ['question' => 'Мога ли да промотирам обявата си?', 'answer' => 'Да, Pro и Business плановете включват промотиране на начална страница и в резултатите.'],
                ],
            ],
            $home,
            actor: $admin,
            sortOrder: 300,
        );
        $faq->update(['status' => ContentBlockStatus::Published, 'published_at' => now()]);

        $home->update(['status' => PageStatus::Published, 'published_at' => now()]);
    }

    private function seedAboutPage(User $admin): void
    {
        $about = Page::query()->where('slug', 'about')->first();
        if (! $about || $about->contentBlocks()->exists()) {
            return;
        }

        $creator = app(CreateContentBlock::class);

        $block = $creator->handle(
            ContentBlockType::RichText,
            [
                'tiptap' => [
                    'type' => 'doc',
                    'content' => [
                        ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Нашата мисия']]],
                        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'ListingHub е националният каталог за бизнеси в България. Нашата цел е да свържем потребители с проверени фирми от всички области на страната.']]],
                        ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Какво предлагаме']]],
                        ['type' => 'bulletList', 'content' => [
                            ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Безплатна регистрация на фирми и обяви']]]]],
                            ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Търсене по категория, област и населено място']]]]],
                            ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Отзиви и оценки от реални потребители']]]]],
                            ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Pro и Business планове за допълнително промотиране']]]]],
                        ]],
                        ['type' => 'paragraph', 'content' => [
                            ['type' => 'text', 'text' => 'Свържете се с нас на '],
                            ['type' => 'text', 'marks' => [['type' => 'link', 'attrs' => ['href' => '/pages/contact', 'target' => null, 'rel' => 'noopener noreferrer nofollow']]], 'text' => 'страницата за контакти'],
                            ['type' => 'text', 'text' => '.'],
                        ]],
                    ],
                ],
            ],
            $about,
            actor: $admin,
            sortOrder: 100,
        );
        $block->update(['status' => ContentBlockStatus::Published, 'published_at' => now()]);

        app(PublishPage::class)->handle($about);
    }

    private function seedMenus(): void
    {
        $header = Menu::query()->where('handle', 'header')->first();
        if ($header && $header->items()->doesntExist()) {
            $items = [
                ['label' => 'Обяви',      'url' => '/listings',       'sort_order' => 100],
                ['label' => 'Категории',  'url' => '/categories',     'sort_order' => 200],
                ['label' => 'За нас',     'url' => '/pages/about',    'sort_order' => 300],
                ['label' => 'Контакти',   'url' => '/pages/contact',  'sort_order' => 400],
            ];
            foreach ($items as $item) {
                MenuItem::query()->create([...$item, 'menu_id' => $header->id]);
            }
        }

        $footer = Menu::query()->where('handle', 'footer')->first();
        if ($footer && $footer->items()->doesntExist()) {
            $items = [
                ['label' => 'Начало',    'url' => '/',              'sort_order' => 100],
                ['label' => 'Обяви',     'url' => '/listings',      'sort_order' => 200],
                ['label' => 'За нас',    'url' => '/pages/about',   'sort_order' => 300],
                ['label' => 'Контакти',  'url' => '/pages/contact', 'sort_order' => 400],
            ];
            foreach ($items as $item) {
                MenuItem::query()->create([...$item, 'menu_id' => $footer->id]);
            }
        }

        $legal = Menu::query()->where('handle', 'footer-legal')->first();
        if ($legal && $legal->items()->doesntExist()) {
            $items = [
                ['label' => 'Условия за ползване', 'url' => '/pages/terms',          'sort_order' => 100],
                ['label' => 'Поверителност',       'url' => '/pages/privacy',         'sort_order' => 200],
                ['label' => 'Политика за връщане', 'url' => '/pages/refund-policy',   'sort_order' => 300],
            ];
            foreach ($items as $item) {
                MenuItem::query()->create([...$item, 'menu_id' => $legal->id]);
            }
        }
    }

    private function seedListings(User $admin, User $member): void
    {
        $settlement = Settlement::query()->first();
        $categories = Category::query()->where('is_active', true)->get();

        if ($categories->isEmpty() || ! $settlement) {
            return;
        }

        $listings = [
            ['title' => 'Тракия Гриб & Бар',        'category' => 'restaurants',  'user' => $member, 'featured' => true,  'description' => 'Модерно заведение в центъра на Пловдив с авторска кухня, крафт бири и панорамна тераса.'],
            ['title' => 'Хотел Стара Планина',       'category' => 'hotels',       'user' => $member, 'featured' => true,  'description' => 'Четиризвезден планински хотел с СПА център, ресторант и конферентна зала.'],
            ['title' => 'АвтоМайстор ЕООД',         'category' => 'auto-service', 'user' => $member, 'featured' => false, 'description' => 'Пълно обслужване на леки и товарни автомобили — диагностика, ремонт, годишен преглед.'],
            ['title' => 'Дентал Клиник д-р Иванова', 'category' => 'health',       'user' => $admin,  'featured' => true,  'description' => 'Съвременна дентална клиника с пълен обхват на зъболечение, ортодонтия и имплантология.'],
            ['title' => 'Академия Знание',           'category' => 'education',    'user' => $admin,  'featured' => false, 'description' => 'Частно училище с английски език на обучение, подготовка за Cambridge и IELTS.'],
            ['title' => 'СтройПро ООД',              'category' => 'construction', 'user' => $member, 'featured' => false, 'description' => 'Строителство, ремонти и довършителни дейности — качество и коректни срокове.'],
            ['title' => 'Адвокат Георгиева',         'category' => 'legal',        'user' => $admin,  'featured' => false, 'description' => 'Правни консултации по търговско, трудово и семейно право.'],
            ['title' => 'CodeCraft Solutions',        'category' => 'it',           'user' => $admin,  'featured' => true,  'description' => 'Уеб разработка, мобилни приложения и IT консултиране за малък и среден бизнес.'],
        ];

        foreach ($listings as $data) {
            $category = $categories->firstWhere('slug', $data['category']);
            if (! $category) {
                continue;
            }

            $slug = Str::slug($data['title']);
            if (Listing::query()->where('slug', $slug)->exists()) {
                continue;
            }

            Listing::query()->create([
                'user_id' => $data['user']->id,
                'category_id' => $category->id,
                'settlement_id' => $settlement->id,
                'title' => $data['title'],
                'slug' => $slug,
                'description' => $data['description'],
                'status' => ListingStatus::Published,
                'published_at' => now(),
                'is_featured' => $data['featured'],
            ]);
        }
    }
}
