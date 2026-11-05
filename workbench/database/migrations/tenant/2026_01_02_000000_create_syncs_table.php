<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syncs', function (Blueprint $table) {
            $table->id();
            $table->boolean('succeeded');
            $table->string('message');
            $table->timestamp('synced_at');
        });
    }
};
