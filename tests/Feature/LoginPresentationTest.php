<?php

namespace Tests\Feature;

use Tests\TestCase;

class LoginPresentationTest extends TestCase
{
    public function test_login_page_defaults_to_arabic_when_no_locale_was_chosen(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('مرحبًا بعودتك')
            ->assertSee('dir="rtl"', false);
    }

    public function test_login_page_renders_the_english_split_brand_experience(): void
    {
        $this->withSession(['locale' => 'en'])
            ->get('/admin/login')
            ->assertOk()
            ->assertSee('madar-login-background.png')
            ->assertSee('Welcome back')
            ->assertSee('Every real-estate decision, in one clear place.')
            ->assertSee('dir="ltr"', false);
    }

    public function test_login_page_uses_the_guest_selected_arabic_locale_and_direction(): void
    {
        $this->withSession(['locale' => 'ar'])
            ->get('/admin/login')
            ->assertOk()
            ->assertSee('مرحبًا بعودتك')
            ->assertSee('كل قرار عقاري، في مكان واحد واضح.')
            ->assertSee('dir="rtl"', false);
    }
}
