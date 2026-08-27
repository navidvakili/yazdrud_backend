<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('smart_pages', function (Blueprint $table) {
            // صفحهٔ والد — صفحات زیرمجموعه (زیرصفحه) با parent_id به والد خود اشاره می‌کنند
            $table->unsignedBigInteger('parent_id')->nullable()->after('slug');
            // ترتیب نمایش زیرصفحه‌ها در فهرست والد
            $table->integer('sort_order')->default(0)->after('parent_id');

            $table->index('parent_id');
            $table->foreign('parent_id')->references('id')->on('smart_pages')
                  ->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('smart_pages', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['parent_id']);
            $table->dropColumn(['parent_id', 'sort_order']);
        });
    }
};
