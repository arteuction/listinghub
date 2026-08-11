<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// withoutVite() stops the public layout's @vite directive from demanding a
// built manifest. Asset compilation is proven by the frontend build job, not
// by every feature test.
uses(TestCase::class, RefreshDatabase::class)
    ->beforeEach(fn () => $this->withoutVite())
    ->in('Feature');

uses(TestCase::class)->in('Unit');
