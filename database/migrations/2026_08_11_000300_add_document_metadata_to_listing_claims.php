<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * document_path alone is not a trustworthy contract for a verified upload:
 * without the MIME the download endpoint would have to guess a Content-Type,
 * without the size a listing of claims cannot show one, and without a hash
 * there is no way to prove the served bytes are the ones that were reviewed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listing_claims', function (Blueprint $table) {
            $table->string('document_disk', 64)->nullable()->after('document_path');
            $table->string('document_mime', 128)->nullable()->after('document_disk');
            $table->unsignedBigInteger('document_size')->nullable()->after('document_mime');
            $table->char('document_sha256', 64)->nullable()->after('document_size');
            $table->string('document_original_name')->nullable()->after('document_sha256');
        });
    }

    public function down(): void
    {
        Schema::table('listing_claims', function (Blueprint $table) {
            $table->dropColumn([
                'document_disk', 'document_mime', 'document_size',
                'document_sha256', 'document_original_name',
            ]);
        });
    }
};
