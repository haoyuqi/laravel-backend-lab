<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    /**
     * Test that application locale is configured to zh_CN.
     */
    public function test_application_locale_is_zh_cn(): void
    {
        $this->assertEquals('zh_CN', config('app.locale'));
        $this->assertEquals('zh_CN', App::getLocale());
    }

    /**
     * Test that form validator returns error messages in Simplified Chinese.
     */
    public function test_validator_returns_chinese_error_messages(): void
    {
        $validator = Validator::make([], [
            'email' => 'required|email',
        ]);

        $this->assertTrue($validator->fails());

        $errors = $validator->errors();
        $emailError = $errors->first('email');

        $this->assertNotEmpty($emailError);
        $this->assertStringContainsString('邮箱', $emailError);
        $this->assertStringContainsString('不能为空', $emailError);
    }

    /**
     * Test that core translation lines resolve to Simplified Chinese.
     */
    public function test_core_translations_resolve_in_chinese(): void
    {
        $this->assertNotEquals('auth.failed', __('auth.failed'));
        $this->assertNotEmpty(__('auth.failed'));

        $this->assertNotEquals('pagination.previous', __('pagination.previous'));
    }
}
