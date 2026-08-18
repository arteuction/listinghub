<?php

declare(strict_types=1);

namespace App\Support\Content;

use App\Enums\ContentBlockType;
use App\Support\Content\Schemas\AnnouncementSchema;
use App\Support\Content\Schemas\CallToActionSchema;
use App\Support\Content\Schemas\CarouselSchema;
use App\Support\Content\Schemas\CategoryGridSchema;
use App\Support\Content\Schemas\FaqSchema;
use App\Support\Content\Schemas\FeaturedListingsSchema;
use App\Support\Content\Schemas\GallerySchema;
use App\Support\Content\Schemas\HeroSchema;
use App\Support\Content\Schemas\ImageTextSchema;
use App\Support\Content\Schemas\LogoStripSchema;
use App\Support\Content\Schemas\RichTextSchema;
use App\Support\Content\Schemas\TestimonialsSchema;
use App\Support\Content\Schemas\VideoSchema;

interface BlockSchemaContract
{
    /** @return array<string, mixed> Laravel validation rules keyed as content.* */
    public function rules(): array;

    /** @return list<string> Top-level keys allowed in the content JSON */
    public function allowedKeys(): array;

    public function maxContentSize(): int;
}

final class BlockSchema
{
    /** @var array<string, class-string<BlockSchemaContract>> */
    private const MAP = [
        'rich_text' => RichTextSchema::class,
        'hero' => HeroSchema::class,
        'cta' => CallToActionSchema::class,
        'category_grid' => CategoryGridSchema::class,
        'featured_listings' => FeaturedListingsSchema::class,
        'faq' => FaqSchema::class,
        'image_text' => ImageTextSchema::class,
        'announcement' => AnnouncementSchema::class,
        'carousel' => CarouselSchema::class,
        'gallery' => GallerySchema::class,
        'video' => VideoSchema::class,
        'logo_strip' => LogoStripSchema::class,
        'testimonials' => TestimonialsSchema::class,
    ];

    public static function for(ContentBlockType $type): BlockSchemaContract
    {
        $class = self::MAP[$type->value];

        return new $class;
    }

    /**
     * Reject unknown top-level keys in the content array.
     *
     * @param array<string, mixed> $content
     * @return list<string> Unknown keys (empty = valid)
     */
    public static function unknownKeys(ContentBlockType $type, array $content): array
    {
        $allowed = self::for($type)->allowedKeys();

        return array_values(array_diff(array_keys($content), $allowed));
    }

    /**
     * Full validation rules for the content field, prefixed with 'content.'.
     *
     * @return array<string, mixed>
     */
    public static function validationRules(ContentBlockType $type): array
    {
        return self::for($type)->rules();
    }
}
