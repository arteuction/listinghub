<?php

declare(strict_types=1);

use App\Enums\CustomFieldType;
use App\Exceptions\CustomFieldConflict;
use App\Support\CustomFieldValueNormalizer;

beforeEach(function () {
    $this->n = new CustomFieldValueNormalizer;
});

it('routes each type to its typed column', function () {
    expect($this->n->normalize(CustomFieldType::Text, 'hello'))->toBe(['column' => 'value_text', 'value' => 'hello']);
    expect($this->n->normalize(CustomFieldType::Number, '12.5'))->toBe(['column' => 'value_decimal', 'value' => '12.5']);
    expect($this->n->normalize(CustomFieldType::Checkbox, '1'))->toBe(['column' => 'value_boolean', 'value' => true]);
    expect($this->n->normalize(CustomFieldType::Url, 'https://example.com'))->toBe(['column' => 'value_string', 'value' => 'https://example.com']);
    expect($this->n->normalize(CustomFieldType::Email, 'a@b.com'))->toBe(['column' => 'value_string', 'value' => 'a@b.com']);
});

it('treats null/empty as empty (delete), but keeps checkbox false meaningful', function () {
    expect($this->n->normalize(CustomFieldType::Text, ''))->toBeNull();
    expect($this->n->normalize(CustomFieldType::Text, null))->toBeNull();
    expect($this->n->normalize(CustomFieldType::Number, '  '))->toBeNull();
    // checkbox "0"/false is a real value
    expect($this->n->normalize(CustomFieldType::Checkbox, '0'))->toBe(['column' => 'value_boolean', 'value' => false]);
});

it('handles decimals as strings within 16+4 digits', function () {
    expect($this->n->normalize(CustomFieldType::Number, '1234567890123456.1234')['value'])->toBe('1234567890123456.1234');
});

it('rejects excess integer or fractional precision without truncation', function () {
    expect(fn () => $this->n->normalize(CustomFieldType::Number, '12345678901234567')) // 17 int digits
        ->toThrow(CustomFieldConflict::class);
    expect(fn () => $this->n->normalize(CustomFieldType::Number, '1.23456')) // 5 fraction digits
        ->toThrow(CustomFieldConflict::class);
    expect(fn () => $this->n->normalize(CustomFieldType::Number, '1,5'))
        ->toThrow(CustomFieldConflict::class);
});

it('rejects strings longer than 255 chars', function () {
    expect(fn () => $this->n->normalize(CustomFieldType::Url, 'http://x.com/'.str_repeat('a', 260)))
        ->toThrow(CustomFieldConflict::class);
});

it('enforces select membership and url/email validity', function () {
    $options = [['value' => 'red', 'label' => 'Red'], ['value' => 'blue', 'label' => 'Blue']];
    expect($this->n->normalize(CustomFieldType::Select, 'red', $options)['value'])->toBe('red');
    expect(fn () => $this->n->normalize(CustomFieldType::Select, 'green', $options))->toThrow(CustomFieldConflict::class);
    expect(fn () => $this->n->normalize(CustomFieldType::Url, 'not-a-url'))->toThrow(CustomFieldConflict::class);
    expect(fn () => $this->n->normalize(CustomFieldType::Email, 'not-an-email'))->toThrow(CustomFieldConflict::class);
});

it('rejects a non-scalar text value (P0-7)', function () {
    expect(fn () => (new CustomFieldValueNormalizer)->normalize(CustomFieldType::Text, ['a', 'b']))
        ->toThrow(CustomFieldConflict::class);
});

it('accepts http/https URLs but rejects other schemes (P0-7)', function () {
    $n = new CustomFieldValueNormalizer;
    expect($n->normalize(CustomFieldType::Url, 'https://ok.example')['value'])->toBe('https://ok.example');
    expect(fn () => $n->normalize(CustomFieldType::Url, 'ftp://x.example'))
        ->toThrow(CustomFieldConflict::class);
    expect(fn () => $n->normalize(CustomFieldType::Url, 'javascript:alert(1)'))
        ->toThrow(CustomFieldConflict::class);
});
