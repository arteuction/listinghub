<?php

declare(strict_types=1);

namespace App\Http\Requests\Member;

use App\Support\ImageLimits;
use Illuminate\Foundation\Http\FormRequest;

class ListingMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ownership is checked against the concrete listing in the controller.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'max:'.ImageLimits::MAX_PER_REQUEST],
            'images.*' => [
                'required',
                'file',
                // `mimetypes` reads the type finfo derives from the CONTENT,
                // not the Content-Type the client sent. It is a cheap first
                // gate; ImageProcessor re-decodes and re-encodes regardless.
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:'.ImageLimits::maxKilobytes(),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'images.max' => 'Може да качите най-много '.ImageLimits::MAX_PER_REQUEST.' изображения наведнъж.',
            'images.*.mimetypes' => 'Поддържат се само JPEG, PNG и WebP.',
            'images.*.max' => 'Всяко изображение трябва да е под 5 MB.',
        ];
    }
}
