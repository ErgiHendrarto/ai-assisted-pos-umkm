<?php

namespace Tests\Feature;

use App\Models\AiSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AiSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_view_and_save_encrypted_ai_api_settings(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('AI API Configuration')
            ->assertSee('AI API Key');

        $secretKey = 'AIzaSyTestSecretKey123456789';

        $response = $this->actingAs($admin)->put(route('settings.ai.update'), [
            'provider' => 'gemini',
            'api_key' => $secretKey,
            'model_name' => 'gemini-2.5-flash',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'timeout_seconds' => 45,
            'voice_enabled' => '1',
            'ocr_enabled' => '1',
            'matching_enabled' => '1',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('settings.index'))
            ->assertSessionHas('status');

        $setting = AiSetting::current();
        $this->assertSame('gemini', $setting->provider);
        $this->assertSame($secretKey, $setting->api_key);
        $this->assertSame('gemini-2.5-flash', $setting->model_name);
        $this->assertSame(45, $setting->timeout_seconds);
        $this->assertTrue($setting->is_active);

        // Verify raw database value is encrypted and not stored in plaintext
        $rawApiKey = DB::table('ai_settings')->where('id', $setting->id)->value('api_key');
        $this->assertNotSame($secretKey, $rawApiKey);

        // Leaving api_key blank on subsequent update keeps the existing encrypted key
        $this->actingAs($admin)->put(route('settings.ai.update'), [
            'provider' => 'openai',
            'api_key' => '',
            'model_name' => 'gpt-4o-mini',
            'timeout_seconds' => 30,
            'is_active' => '1',
        ])->assertRedirect(route('settings.index'));

        $setting->refresh();
        $this->assertSame('openai', $setting->provider);
        $this->assertSame($secretKey, $setting->api_key);

        // Custom model option also resolves cleanly
        $this->actingAs($admin)->put(route('settings.ai.update'), [
            'provider' => 'custom',
            'api_key' => '',
            'model_name' => '__custom__',
            'custom_model_name' => 'my-local-llm-v1',
            'timeout_seconds' => 30,
            'is_active' => '1',
        ])->assertRedirect(route('settings.index'));

        $this->assertSame('my-local-llm-v1', $setting->fresh()->model_name);

        // Masked key is shown in UI, never raw key
        $this->actingAs($admin)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('AIza')
            ->assertDontSee($secretKey);
    }

    public function test_cashier_cannot_update_ai_api_settings(): void
    {
        $cashier = User::factory()->cashier()->create();

        $this->actingAs($cashier)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('managed by the store Administrator')
            ->assertDontSee('Save AI Configuration');

        $this->actingAs($cashier)
            ->put(route('settings.ai.update'), [
                'provider' => 'gemini',
                'api_key' => 'AIzaSyUnauthorizedKey',
                'model_name' => 'gemini-2.5-flash',
                'timeout_seconds' => 30,
            ])
            ->assertForbidden();
    }
}
