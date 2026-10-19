<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 100);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->boolean('is_deleted')->default(false);
            });
        }

        if (!Schema::hasTable('stores')) {
            Schema::create('stores', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 100);
                $table->integer('prefecture_id');
                $table->string('postal_code', 10);
                $table->string('address_detail', 255);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->boolean('is_deleted')->default(false);
            });
        }

        if (!Schema::hasTable('approval_statuses_master')) {
            Schema::create('approval_statuses_master', function (Blueprint $table) {
                $table->integer('id')->primary();
                $table->string('name', 50);
                $table->text('memo')->nullable()->default(null);
            });
        }

        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('department_id')->nullable();
                $table->unsignedBigInteger('store_id')->nullable();
                $table->string('name', 100);
                $table->string('email', 255)->unique();
                $table->string('password_hash', 255);
                $table->string('position', 100)->nullable()->default(null);
                $table->string('authority', 50)->default('applicant');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->boolean('is_deleted')->default(false);

                $table->foreign('department_id')->references('id')->on('departments');
                $table->foreign('store_id')->references('id')->on('stores');
            });
        }

        if (!Schema::hasTable('approvals')) {
            Schema::create('approvals', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('applicant_user_id');
                $table->unsignedBigInteger('assignee_user_id')->default(0);
                $table->string('assignee_name', 100)->nullable()->default(null);
                $table->integer('status_id')->default(3);
                $table->string('subject', 255);
                $table->bigInteger('amount')->default(0);
                $table->text('detail');
                $table->text('reason');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->boolean('is_deleted')->default(false);

                $table->foreign('applicant_user_id')->references('id')->on('users');
                $table->foreign('assignee_user_id')->references('id')->on('users');
                $table->foreign('status_id')->references('id')->on('approval_statuses_master');
            });
        }

        if (!Schema::hasTable('approval_histories')) {
            Schema::create('approval_histories', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('approval_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->text('operation_detail');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->boolean('is_deleted')->default(false);

                $table->foreign('approval_id')->references('id')->on('approvals');
                $table->foreign('user_id')->references('id')->on('users');
            });
        }

        if (!Schema::hasTable('approval_files')) {
            Schema::create('approval_files', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('approval_id');
                $table->string('file_name', 255);
                $table->string('file_url', 1024);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->boolean('is_deleted')->default(false);

                $table->foreign('approval_id')->references('id')->on('approvals');
            });
        }

        if (!Schema::hasTable('approval_comments')) {
            Schema::create('approval_comments', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('approval_id');
                $table->unsignedBigInteger('user_id');
                $table->text('content');
                $table->integer('status')->default(0);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->boolean('is_deleted')->default(false);

                $table->foreign('approval_id')->references('id')->on('approvals');
                $table->foreign('user_id')->references('id')->on('users');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_comments');
        Schema::dropIfExists('approval_files');
        Schema::dropIfExists('approval_histories');
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('users');
        Schema::dropIfExists('approval_statuses_master');
        Schema::dropIfExists('stores');
        Schema::dropIfExists('departments');
    }
};

