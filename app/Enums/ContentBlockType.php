<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Closed set of presentation-safe blocks. New block types require a schema,
 * validator and renderer; administrators never submit executable templates.
 */
enum ContentBlockType: string
{
    case RichText = 'rich_text';
    case Hero = 'hero';
    case CallToAction = 'cta';
    case CategoryGrid = 'category_grid';
    case FeaturedListings = 'featured_listings';
    case Faq = 'faq';
    case ImageText = 'image_text';
    case Announcement = 'announcement';
    case Carousel = 'carousel';
    case Gallery = 'gallery';
    case Video = 'video';
    case LogoStrip = 'logo_strip';
    case Testimonials = 'testimonials';
}
